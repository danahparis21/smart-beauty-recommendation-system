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
ini_set('display_errors', 0); // Don't display errors to output, keep JSON clean

// Configuration & Connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set admin ID for triggers (if this file does any database modifications)
$conn->query("SET @admin_user_id = $adminId");


$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

try {
    // Stats
    $totalCustomers = $conn->query("SELECT COUNT(*) as total FROM users WHERE Role = 'customer'")->fetch_assoc()['total'];
    $newThisMonth = $conn->query("SELECT COUNT(*) as total FROM users WHERE Role='customer' AND CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetch_assoc()['total'];
    $activeCustomers = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM orders WHERE order_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)")->fetch_assoc()['total'];
    $feedbackReceived = $conn->query("SELECT COUNT(*) as total FROM store_ratings")->fetch_assoc()['total'];

    // Base query
    $where = ["u.Role = 'customer'"];
    if ($filter === 'new') $where[] = "u.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    if ($filter === 'feedback') $where[] = "sr.store_rating_id IS NOT NULL";
    if ($filter === 'inactive') $where[] = "(o.order_id IS NULL OR o.order_date < DATE_SUB(NOW(), INTERVAL 3 MONTH))";

    if ($search !== '') {
        $search = $conn->real_escape_string($search);
        $where[] = "(u.username LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.Email LIKE '%$search%')";
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $sql = "
        SELECT 
            u.UserID, u.username, u.first_name, u.last_name, u.Email, u.CreatedAt,u.profile_photo,
            COUNT(DISTINCT o.order_id) as total_orders,
            COALESCE(SUM(o.total_price),0) as total_spent,
            MAX(o.order_date) as last_order_date,
            COALESCE(AVG(sr.rating), 0) as avg_rating,
            COUNT(sr.store_rating_id) as rating_count
        FROM users u
        LEFT JOIN orders o ON u.UserID = o.user_id
        LEFT JOIN store_ratings sr ON u.UserID = sr.user_id
        $whereClause
        GROUP BY u.UserID
        ORDER BY u.CreatedAt DESC
        LIMIT 100
    ";

    $result = $conn->query($sql);
    $customers = [];

    while ($row = $result->fetch_assoc()) {
        $status = 'inactive';
        if ($row['last_order_date']) {
            $last = new DateTime($row['last_order_date']);
            $days = (new DateTime())->diff($last)->days;
            if ($days <= 30) $status = 'active';
            elseif ($days <= 90) $status = 'moderate';
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

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_customers' => $totalCustomers,
            'new_this_month' => $newThisMonth,
            'active_customers' => $activeCustomers,
            'feedback_received' => $feedbackReceived
        ],
        'customers' => $customers
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>