<?php
header('Content-Type: application/json');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}
// Get filter and search parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // === 1. Summary Stats ===
    $totalOrders = $conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()['total'];
    
    // Fix status values to match your database
    $pendingOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'Pending' OR status = 'pending'")->fetch_assoc()['total'];
    $completedOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'Completed' OR status = 'completed'")->fetch_assoc()['total'];
    $cancelledOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'Cancelled' OR status = 'cancelled'")->fetch_assoc()['total'];

    // === 2. Base query ===
    $whereConditions = ["1=1"];

    // Apply filter - match your actual status values
    switch (strtolower($filter)) {
        case 'pending':
            $whereConditions[] = "(o.status = 'Pending' OR o.status = 'pending')";
            break;
        case 'completed':
            $whereConditions[] = "(o.status = 'Completed' OR o.status = 'completed')";
            break;
        case 'cancelled':
            $whereConditions[] = "(o.status = 'Cancelled' OR o.status = 'cancelled')";
            break;
        case 'this_week':
            $whereConditions[] = "YEARWEEK(o.order_date, 1) = YEARWEEK(NOW(), 1)";
            break;
    }

    // Apply search
    if (!empty($search)) {
        $searchEscaped = $conn->real_escape_string($search);
        $whereConditions[] = "(
            o.order_id LIKE '%$searchEscaped%' 
            OR u.username LIKE '%$searchEscaped%' 
            OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE '%$searchEscaped%' 
            OR u.Email LIKE '%$searchEscaped%'
            OR o.order_id IN (SELECT order_id FROM orderitems oi 
                             JOIN products p ON oi.product_id = p.ProductID 
                             WHERE p.Name LIKE '%$searchEscaped%')
        )";
    }

    $whereClause = "WHERE " . implode(" AND ", $whereConditions);

    // === 3. Main query - only use existing columns ===
    $query = "
        SELECT 
            o.order_id,
            o.user_id,
            u.username,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS full_name,
            u.Email,
            o.total_price,
            o.status as order_status,
            o.order_date,
            o.qr_code
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.UserID
        $whereClause
        ORDER BY o.order_date DESC
        LIMIT 100
    ";

    error_log("SQL Query: " . $query); // Debug logging

    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'order_id' => $row['order_id'],
            'customer_name' => $row['full_name'] ?: $row['username'] ?: 'Unknown Customer',
            'email' => $row['Email'],
            'total_price' => number_format($row['total_price'], 2),
            'order_status' => $row['order_status'],
            'order_date' => date('M d, Y h:i A', strtotime($row['order_date'])),
            'qr_code' => $row['qr_code']
        ];
    }

    // === 4. Response ===
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_orders' => (int)$totalOrders,
            'pending_orders' => (int)$pendingOrders,
            'completed_orders' => (int)$completedOrders,
            'cancelled_orders' => (int)$cancelledOrders
        ],
        'orders' => $orders,
        'filter' => $filter,
        'search' => $search
    ]);

} catch (Exception $e) {
    error_log("Error in order-transaction.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load orders: ' . $e->getMessage(),
        'stats' => [
            'total_orders' => 0,
            'pending_orders' => 0,
            'completed_orders' => 0,
            'cancelled_orders' => 0
        ],
        'orders' => []
    ]);
}

if ($conn) {
    $conn->close();
}
?>