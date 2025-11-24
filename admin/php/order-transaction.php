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
ini_set('display_errors', 0);  // Don't display errors to output, keep JSON clean

// Configuration & Connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set admin ID for triggers (if this file does any database modifications)
$conn->query("SET @admin_user_id = $adminId");

// Add global error handler to catch any unexpected errors
function handleException($e)
{
    error_log('Uncaught exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit;
}

set_exception_handler('handleException');

// Your existing code continues...
$action = $_GET['action'] ?? '';

// Handle different actions - REMOVED update_status
if ($action === 'get_order_details') {
    getOrderDetails($conn);
} else {
    getOrdersList($conn);
}

function getOrdersList($conn)
{
    // Get filter and search parameters
    $filter = strtolower($_GET['filter'] ?? 'all');
    $search = trim($_GET['search'] ?? '');

    try {
        // === 1. Summary Stats ===
        $totalOrders = $conn->query('SELECT COUNT(*) AS total FROM orders')->fetch_assoc()['total'];
        $pendingOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE LOWER(status) = 'pending'")->fetch_assoc()['total'];
        $completedOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE LOWER(status) = 'completed'")->fetch_assoc()['total'];
        $cancelledOrders = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE LOWER(status) = 'cancelled'")->fetch_assoc()['total'];

        // === 2. Base query ===
        $whereConditions = [];

        // Apply filter
        switch ($filter) {
            case 'pending':
                $whereConditions[] = "LOWER(o.status) = 'pending'";
                break;
            case 'completed':
                $whereConditions[] = "LOWER(o.status) = 'completed'";
                break;
            case 'cancelled':
                $whereConditions[] = "LOWER(o.status) = 'cancelled'";
                break;
            case 'this_week':
                $whereConditions[] = 'YEARWEEK(o.order_date, 1) = YEARWEEK(NOW(), 1)';
                break;
                // 'all' means no specific condition
        }

        // Apply search
        if (!empty($search)) {
            $searchEscaped = $conn->real_escape_string($search);
            $whereConditions[] = "(
                o.order_id LIKE '%$searchEscaped%'
                OR u.username LIKE '%$searchEscaped%'
                OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE '%$searchEscaped%'
                OR u.Email LIKE '%$searchEscaped%'
                OR o.order_id IN (
                    SELECT order_id FROM orderitems oi
                    JOIN products p ON oi.product_id = p.ProductID
                    WHERE p.Name LIKE '%$searchEscaped%'
                )
            )";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // === 3. Main query - UPDATED to include item count ===
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
                o.qr_code,
                -- Add item count
                (SELECT COUNT(*) FROM orderitems oi WHERE oi.order_id = o.order_id) as item_count,
                -- Add default values for missing fields
                'Standard Delivery' as shipping_method,
                'Not specified' as shipping_address
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.UserID
            $whereClause
            ORDER BY o.order_date DESC
            LIMIT 100
        ";

        error_log('SQL Query: ' . $query);  // Debug logging

        $result = $conn->query($query);

        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = [
                'order_id' => $row['order_id'],
                'customer_name' => trim($row['full_name']) ?: $row['username'] ?: 'Unknown Customer',
                'email' => $row['Email'],
                'total_amount' => number_format($row['total_price'], 2),
                'status' => ucfirst($row['order_status']),
                'order_date' => date('M d, Y h:i A', strtotime($row['order_date'])),
                'qr_code' => $row['qr_code'],
                'item_count' => (int) $row['item_count'],
                'shipping_method' => $row['shipping_method'],
                'shipping_address' => $row['shipping_address']
            ];
        }

        // === 4. JSON Response ===
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_orders' => (int) $totalOrders,
                'pending_orders' => (int) $pendingOrders,
                'completed_orders' => (int) $completedOrders,
                'cancelled_orders' => (int) $cancelledOrders
            ],
            'orders' => $orders,
            'filter' => $filter,
            'search' => $search
        ]);
    } catch (Exception $e) {
        error_log('Error in order-transaction.php: ' . $e->getMessage());

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
}

function getOrderDetails($conn)
{
    $order_id = $_GET['order_id'] ?? 0;

    if (!$order_id) {
        echo json_encode(['success' => false, 'error' => 'Order ID is required']);
        return;
    }

    try {
        // Get order basic info
        $orderQuery = "
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
            WHERE o.order_id = ?
        ";

        $stmt = $conn->prepare($orderQuery);
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $orderResult = $stmt->get_result();

        if ($orderResult->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            return;
        }

        $order = $orderResult->fetch_assoc();

        // Get order items with product details and images
        $itemsQuery = "
            SELECT 
                oi.order_item_id,
                oi.product_id,
                oi.quantity,
                oi.price,
                p.Name as product_name,
                p.Category,
                pm.ImagePath as image_path
            FROM orderitems oi
            LEFT JOIN products p ON oi.product_id = p.ProductID
            LEFT JOIN ProductMedia pm ON p.ProductID = pm.VariantProductID AND pm.MediaType = 'VARIANT'
            WHERE oi.order_id = ?
        ";

        $stmt = $conn->prepare($itemsQuery);
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $itemsResult = $stmt->get_result();

        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            // If no variant image, try to get parent product image
            if (!$item['image_path']) {
                $parentImageQuery = "
                    SELECT pm.ImagePath 
                    FROM products p 
                    LEFT JOIN ProductMedia pm ON p.ParentProductID = pm.ParentProductID AND pm.MediaType = 'PREVIEW'
                    WHERE p.ProductID = ?
                    LIMIT 1
                ";
                $parentStmt = $conn->prepare($parentImageQuery);
                $parentStmt->bind_param('s', $item['product_id']);
                $parentStmt->execute();
                $parentResult = $parentStmt->get_result();
                if ($parentResult->num_rows > 0) {
                    $parentImage = $parentResult->fetch_assoc();
                    $item['image_path'] = $parentImage['ImagePath'];
                }
            }

            // If still no image, use default
            if (!$item['image_path']) {
                $item['image_path'] = '../images/default-product.jpg';
            }

            $items[] = $item;
        }

        // Format the order data
        $formattedOrder = [
            'order_id' => $order['order_id'],
            'customer_name' => trim($order['full_name']) ?: $order['username'] ?: 'Unknown Customer',
            'email' => $order['Email'],
            'total_price' => number_format($order['total_price'], 2),
            'order_status' => ucfirst($order['order_status']),
            'order_date' => date('M d, Y h:i A', strtotime($order['order_date'])),
            'qr_code' => $order['qr_code']
        ];

        echo json_encode([
            'success' => true,
            'order' => $formattedOrder,
            'items' => $items
        ]);
    } catch (Exception $e) {
        error_log('Error getting order details: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to load order details: ' . $e->getMessage()]);
    }
}

$conn->close();
?>