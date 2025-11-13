<?php
session_start();
header('Content-Type: application/json');

// Configuration & Connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$action = $_GET['action'] ?? '';

try {
    // ACTION: Get order details by QR code
    if ($action === 'get_order') {
        $qrCode = $_GET['qr_code'] ?? '';
        
        if (empty($qrCode)) {
            echo json_encode(['success' => false, 'message' => 'QR code is required']);
            exit;
        }
        
        // Debug: Log the QR code being searched
        error_log("Searching for QR Code: " . $qrCode);
        
        // First, let's check what QR codes exist in the database
        $checkQuery = "SELECT qr_code, order_id FROM orders ORDER BY order_id DESC LIMIT 5";
        $checkResult = $conn->query($checkQuery);
        $existingQRs = [];
        while ($row = $checkResult->fetch_assoc()) {
            $existingQRs[] = $row;
        }
        error_log("Recent QR codes in DB: " . json_encode($existingQRs));
        
        // Get order details - try exact match first
        $orderQuery = "
            SELECT 
                o.order_id,
                o.user_id,
                o.total_price,
                o.status,
                o.order_date,
                o.qr_code,
                u.username,
                u.first_name,
                u.last_name,
                u.Email
            FROM orders o
            JOIN users u ON o.user_id = u.UserID
            WHERE o.qr_code = ?
        ";
        
        $stmt = $conn->prepare($orderQuery);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $qrCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // If exact match not found, try LIKE search
        if ($result->num_rows === 0) {
            error_log("Exact match not found, trying LIKE search");
            
            $likeQuery = "
                SELECT 
                    o.order_id,
                    o.user_id,
                    o.total_price,
                    o.status,
                    o.order_date,
                    o.qr_code,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.Email
                FROM orders o
                JOIN users u ON o.user_id = u.UserID
                WHERE o.qr_code LIKE ?
                ORDER BY o.order_id DESC
                LIMIT 1
            ";
            
            $stmt = $conn->prepare($likeQuery);
            $searchPattern = "%" . $qrCode . "%";
            $stmt->bind_param("s", $searchPattern);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Order not found. Scanned: ' . $qrCode . '. Recent orders: ' . json_encode($existingQRs)
            ]);
            exit;
        }
        
        $order = $result->fetch_assoc();
        
        error_log("Found order: " . $order['order_id'] . " with QR: " . $order['qr_code']);
        
        // Check if order is already completed
        if ($order['status'] === 'completed') {
            echo json_encode([
                'success' => false, 
                'message' => 'This order (#' . $order['order_id'] . ') has already been completed.',
                'order_id' => $order['order_id']
            ]);
            exit;
        }
        
        // Get order items
        $itemsQuery = "
            SELECT 
                oi.order_item_id,
                oi.product_id,
                oi.quantity,
                oi.price,
                p.Name as product_name,
                p.ShadeOrVariant,
                p.Category,
                (oi.quantity * oi.price) as subtotal
            FROM orderitems oi
            JOIN products p ON oi.product_id = p.ProductID
            WHERE oi.order_id = ?
        ";
        
        $stmt = $conn->prepare($itemsQuery);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $order['order_id']);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        
        $items = [];
        while ($row = $itemsResult->fetch_assoc()) {
            $items[] = [
                'order_item_id' => $row['order_item_id'],
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'shade' => $row['ShadeOrVariant'] ?: 'N/A',
                'category' => $row['Category'],
                'quantity' => $row['quantity'],
                'price' => number_format($row['price'], 2),
                'subtotal' => number_format($row['subtotal'], 2)
            ];
        }
        
        // Format customer name
        $customerName = trim($order['first_name'] . ' ' . $order['last_name']);
        if (empty($customerName)) {
            $customerName = $order['username'];
        }
        
        // Format order date
        $orderDate = date('M d, Y h:i A', strtotime($order['order_date']));
        
        echo json_encode([
            'success' => true,
            'order' => [
                'order_id' => $order['order_id'],
                'qr_code' => $order['qr_code'],
                'customer_name' => $customerName,
                'customer_email' => $order['Email'],
                'total_price' => number_format($order['total_price'], 2),
                'total_price_raw' => $order['total_price'],
                'status' => $order['status'],
                'order_date' => $orderDate,
                'items' => $items,
                'items_count' => count($items)
            ]
        ]);
    }
    
    // ACTION: Complete order
    elseif ($action === 'complete_order') {
        $input = json_decode(file_get_contents('php://input'), true);
        $orderId = $input['order_id'] ?? 0;
        
        if (empty($orderId)) {
            echo json_encode(['success' => false, 'message' => 'Order ID is required']);
            exit;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Get order details first
        $stmt = $conn->prepare("SELECT user_id, status FROM orders WHERE order_id = ?");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found");
        }
        
        $orderData = $result->fetch_assoc();
        
        // Check if already completed
        if ($orderData['status'] === 'completed') {
            $conn->rollback();
            echo json_encode([
                'success' => false, 
                'message' => 'Order is already completed'
            ]);
            exit;
        }
        
        // Update order status to completed
        $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ?");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        
        // Remove items from cart for this user
        $userId = $orderData['user_id'];
        
        // Get product IDs from order items
        $stmt = $conn->prepare("SELECT product_id FROM orderitems WHERE order_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Delete cart items that match order products
        while ($row = $result->fetch_assoc()) {
            $deleteCart = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $deleteCart->bind_param("is", $userId, $row['product_id']);
            $deleteCart->execute();
        }
        
        // Log activity
        $actorType = 'admin';
        $actorId = $_SESSION['user_id'] ?? 1; // Admin ID
        $actionLog = 'Order completed';
        $details = "Order #$orderId marked as completed via QR scanner";
        
        $logStmt = $conn->prepare("
            INSERT INTO activitylog (actor_type, actor_id, action, timestamp, details) 
            VALUES (?, ?, ?, NOW(), ?)
        ");
        
        if ($logStmt) {
            $logStmt->bind_param("siss", $actorType, $actorId, $actionLog, $details);
            $logStmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order completed successfully! ✅',
            'order_id' => $orderId
        ]);
    }
    
    // ACTION: Test - Get all recent orders (for debugging)
    elseif ($action === 'test') {
        $query = "SELECT order_id, qr_code, status, order_date FROM orders ORDER BY order_id DESC LIMIT 10";
        $result = $conn->query($query);
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Recent orders',
            'orders' => $orders
        ]);
    }
    
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log the actual error for debugging
    error_log("QR Scanner Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>