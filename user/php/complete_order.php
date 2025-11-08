<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = $_POST['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Verify order belongs to user
    $checkOrder = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
    $checkOrder->bind_param("ii", $orderId, $userId);
    $checkOrder->execute();
    
    if ($checkOrder->get_result()->num_rows === 0) {
        throw new Exception('Order not found or access denied');
    }
    
    // Update order status to 'completed'
    $updateOrder = $conn->prepare("UPDATE orders SET status = 'completed' WHERE order_id = ?");
    $updateOrder->bind_param("i", $orderId);
    $updateOrder->execute();
    
    // Get product IDs from this order
    $getProducts = $conn->prepare("SELECT product_id FROM orderitems WHERE order_id = ?");
    $getProducts->bind_param("i", $orderId);
    $getProducts->execute();
    $result = $getProducts->get_result();
    
    $productIds = [];
    while ($row = $result->fetch_assoc()) {
        $productIds[] = $row['product_id'];
    }
    
    // Remove these items from user's cart
    if (!empty($productIds)) {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        $deleteStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id IN ($placeholders)");
        
        $types = 'i' . str_repeat('s', count($productIds));
        $params = array_merge([$userId], $productIds);
        $deleteStmt->bind_param($types, ...$params);
        $deleteStmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order completed successfully! 🎉'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error completing order: ' . $e->getMessage()
    ]);
}

$conn->close();
?>