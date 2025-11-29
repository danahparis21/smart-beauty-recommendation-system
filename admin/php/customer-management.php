<?php
session_start();

// Prevent caching for admin pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Admin privileges required.']));
}

$adminId = $_SESSION['user_id'];

header('Content-Type: application/json');
error_reporting(E_ALL); 
ini_set('display_errors', 0);

// Configuration & Connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set admin ID for triggers
$conn->query("SET @admin_user_id = $adminId");

// Get parameters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Debug output
error_log("=== NEW REQUEST ===");
error_log("Filter received: " . $filter);
error_log("Search received: " . $search);
error_log("Page received: " . $page);

// Validate filter value
$validFilters = ['all', 'new', 'feedback', 'inactive'];
if (!in_array($filter, $validFilters)) {
    error_log("Invalid filter '$filter', defaulting to 'all'");
    $filter = 'all';
}

if ($page < 1) {
    $page = 1;
}

try {
    // Stats - these are always the same regardless of filter
    $totalCustomers = $conn->query("SELECT COUNT(*) as total FROM users WHERE Role = 'customer'")->fetch_assoc()['total'];
    $newThisMonth = $conn->query("SELECT COUNT(*) as total FROM users WHERE Role='customer' AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetch_assoc()['total'];
    $activeCustomers = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetch_assoc()['total'];
    $feedbackReceived = $conn->query("SELECT COUNT(*) as total FROM store_ratings")->fetch_assoc()['total'];

    // Base query condition
    $where = ["u.Role = 'customer'"];

    // Apply filters based on the filter parameter
    if ($filter === 'new') {
        $where[] = "u.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        error_log("Applied NEW filter: customers from last month");
        
    } elseif ($filter === 'feedback') {
        // Only customers who have given feedback
        $where[] = "EXISTS (SELECT 1 FROM store_ratings sr2 WHERE sr2.user_id = u.UserID)";
        error_log("Applied FEEDBACK filter: customers with ratings");
        
    } elseif ($filter === 'inactive') {
        // Customers with no orders OR last order more than 3 months ago
        $where[] = "(
            NOT EXISTS (SELECT 1 FROM orders o2 WHERE o2.user_id = u.UserID)
            OR 
            NOT EXISTS (
                SELECT 1 FROM orders o3 
                WHERE o3.user_id = u.UserID 
                AND o3.order_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            )
        )";
        error_log("Applied INACTIVE filter: no orders or no orders in 3 months");
        
    } else {
        error_log("Applied ALL filter: showing all customers");
    }

    // Apply search filter if provided
    if ($search !== '') {
        $search = $conn->real_escape_string($search);
        $where[] = "(
            u.username LIKE '%$search%' OR 
            u.first_name LIKE '%$search%' OR 
            u.last_name LIKE '%$search%' OR 
            u.Email LIKE '%$search%'
        )";
        error_log("Applied SEARCH filter: " . $search);
    }

    // Build WHERE clause
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    error_log("Final WHERE clause: " . $whereClause);

    // Count total results for pagination
    $countSql = "
        SELECT COUNT(DISTINCT u.UserID) as total
        FROM users u
        $whereClause
    ";
    
    error_log("Count SQL: " . $countSql);
    
    $countResult = $conn->query($countSql);
    if (!$countResult) {
        error_log("Count query failed: " . $conn->error);
        throw new Exception("Count query failed: " . $conn->error);
    }
    
    $totalCustomersFiltered = $countResult->fetch_assoc()['total'];
    error_log("Total customers matching filter: " . $totalCustomersFiltered);
    
    // Calculate pagination
    $limit = 12;
    $offset = ($page - 1) * $limit;
    $totalPages = ceil($totalCustomersFiltered / $limit);

    // Main query with all customer details
    $sql = "
        SELECT 
            u.UserID, 
            u.username, 
            u.first_name, 
            u.last_name, 
            u.Email, 
            u.CreatedAt, 
            u.profile_photo,
            COUNT(DISTINCT o.order_id) as total_orders,
            COALESCE(SUM(o.total_price), 0) as total_spent,
            MAX(o.order_date) as last_order_date,
            COALESCE(AVG(sr.rating), 0) as avg_rating,
            COUNT(DISTINCT sr.store_rating_id) as rating_count
        FROM users u
        LEFT JOIN orders o ON u.UserID = o.user_id
        LEFT JOIN store_ratings sr ON u.UserID = sr.user_id
        $whereClause
        GROUP BY u.UserID, u.username, u.first_name, u.last_name, u.Email, u.CreatedAt, u.profile_photo
        ORDER BY u.CreatedAt DESC
        LIMIT $limit OFFSET $offset
    ";

    error_log("Main SQL query prepared");
    error_log("Query: " . $sql);

    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Main query failed: " . $conn->error);
        throw new Exception("Database query failed: " . $conn->error);
    }

    error_log("Query executed successfully. Rows returned: " . $result->num_rows);

    $customers = [];

    while ($row = $result->fetch_assoc()) {
        // Determine customer status based on last order
        $status = 'inactive';
        if ($row['last_order_date']) {
            $last = new DateTime($row['last_order_date']);
            $days = (new DateTime())->diff($last)->days;
            if ($days <= 30) {
                $status = 'active';
            } elseif ($days <= 90) {
                $status = 'moderate';
            }
        }

        // Check if this is a new customer (joined within last month)
        $joinedDate = new DateTime($row['CreatedAt']);
        $daysSinceJoined = (new DateTime())->diff($joinedDate)->days;
        if ($daysSinceJoined <= 30) {
            $status = 'new';
        }

        $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
        
        $customers[] = [
            'user_id' => $row['UserID'],
            'username' => $row['username'],
            'display_name' => $fullName ?: $row['username'],
            'email' => $row['Email'],
            'profile_photo' => $row['profile_photo'],
            'join_date' => (new DateTime($row['CreatedAt']))->format('M d, Y'),
            'status' => $status,
            'total_orders' => $row['total_orders'],
            'total_spent' => number_format($row['total_spent'], 2),
            'avg_rating' => floatval($row['avg_rating']),
            'has_feedback' => $row['rating_count'] > 0,
            'rating_count' => $row['rating_count']
        ];
    }

    error_log("Processed " . count($customers) . " customer records");

    // Return response
    $response = [
        'success' => true,
        'stats' => [
            'total_customers' => $totalCustomers,
            'new_this_month' => $newThisMonth,
            'active_customers' => $activeCustomers,
            'feedback_received' => $feedbackReceived
        ],
        'customers' => $customers,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_customers' => $totalCustomersFiltered,
        'per_page' => $limit,
        'filter_applied' => $filter,
        'search_applied' => $search
    ];

    error_log("Sending response with " . count($customers) . " customers");
    echo json_encode($response);

} catch (Exception $e) {
    error_log("ERROR in customer-management.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'filter' => $filter,
        'search' => $search
    ]);
}

$conn->close();
?>