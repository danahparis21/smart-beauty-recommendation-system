<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Access denied. Admin privileges required.']));
}

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Get filter parameters
    $dateFrom = isset($_GET['dateFrom']) ? trim($_GET['dateFrom']) : '';
    $dateTo = isset($_GET['dateTo']) ? trim($_GET['dateTo']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Debug logging
    error_log("=== Announcement History Filter ===");
    error_log("Date From: " . $dateFrom);
    error_log("Date To: " . $dateTo);
    error_log("Status: " . $status);
    error_log("Search: " . $search);

    // Build WHERE clause for filters
    $whereConditions = ["u.Role = 'admin'"];
    $params = [];
    $types = "";

    // Date range filter
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(n.CreatedAt) >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }

    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(n.CreatedAt) <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }

    // Status filter - using subquery in HAVING clause
    $havingClause = "";
    if ($status !== 'all') {
        if ($status === 'Active') {
            $havingClause = "HAVING status = 'Active'";
        } elseif ($status === 'Expired') {
            $havingClause = "HAVING status = 'Expired'";
        }
    }

    // Search filter (title)
    if (!empty($search)) {
        $whereConditions[] = "n.Title LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }

    // Build final WHERE clause
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);

    error_log("WHERE Clause: " . $whereClause);
    error_log("HAVING Clause: " . $havingClause);
    error_log("Params: " . json_encode($params));

    // Main query with filters and correct column names
    $sql = "SELECT 
                n.Title, 
                n.Message, 
                n.CreatedAt, 
                MAX(n.ExpirationDate) as ExpirationDate,
                COUNT(n.UserID) as RecipientCount,
                CASE 
                    WHEN MAX(n.ExpirationDate) IS NULL OR MAX(n.ExpirationDate) > NOW() THEN 'Active'
                    ELSE 'Expired'
                END as status
            FROM notifications n
            INNER JOIN users u ON n.UserID = u.UserID
            $whereClause
            GROUP BY n.CreatedAt, n.Title, n.Message 
            $havingClause
            ORDER BY n.CreatedAt DESC";

    error_log("SQL Query: " . $sql);

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters dynamically
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
    }

    $history = [];

    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'title' => $row['Title'],
            'message' => $row['Message'],
            'date' => date('M d, Y h:i A', strtotime($row['CreatedAt'])),
            'raw_date' => $row['CreatedAt'],
            'recipients' => $row['RecipientCount'],
            'status' => $row['status']
        ];
    }

    error_log("Found " . count($history) . " announcements");

    echo json_encode([
        'success' => true, 
        'data' => $history,
        'filters_applied' => [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'search' => $search
        ]
    ]);

    if (!empty($params)) {
        $stmt->close();
    }

} catch (Exception $e) {
    error_log("Error in get-announcement-history.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>