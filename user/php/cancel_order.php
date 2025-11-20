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
    echo json_encode(['success' => false, 'message' => 'Please login to cancel orders']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get order ID from POST request
$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['order_id'] ?? '';

if (empty($orderId)) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Verify order belongs to user and is pending
    $stmt = $conn->prepare("SELECT order_id, status FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found or you don't have permission to cancel it");
    }
    
    $order = $result->fetch_assoc();
    
    if ($order['status'] !== 'pending') {
        throw new Exception("Only pending orders can be cancelled");
    }
    
    // Get order items to restore stock and cart
    $stmt = $conn->prepare("
        SELECT oi.product_id, oi.quantity, oi.price 
        FROM orderitems oi 
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $orderItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Restore product stock
    $updateStock = $conn->prepare("UPDATE products SET Stocks = Stocks + ? WHERE ProductID = ?");
    foreach ($orderItems as $item) {
        $updateStock->bind_param("is", $item['quantity'], $item['product_id']);
        $updateStock->execute();
    }
    
    // Move items back to cart (update status from checked_out to active)
    $updateCart = $conn->prepare("
        UPDATE cart 
        SET status = 'active' 
        WHERE user_id = ? AND product_id = ? AND status = 'checked_out'
    ");
    
    foreach ($orderItems as $item) {
        $updateCart->bind_param("is", $userId, $item['product_id']);
        $updateCart->execute();
        
        // If item doesn't exist in cart, insert it
        if ($updateCart->affected_rows === 0) {
            $insertCart = $conn->prepare("
                INSERT INTO cart (user_id, product_id, quantity, status, added_at) 
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $insertCart->bind_param("isi", $userId, $item['product_id'], $item['quantity']);
            $insertCart->execute();
            $insertCart->close();
        }
    }
    
    // Update order status to cancelled
    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully. Items have been moved back to your cart.',
        'order_id' => $orderId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Cancellation failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>