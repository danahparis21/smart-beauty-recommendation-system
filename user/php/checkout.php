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
    echo json_encode(['success' => false, 'message' => 'Please login to checkout']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get selected items from POST request
$input = json_decode(file_get_contents('php://input'), true);
$selectedItems = $input['items'] ?? [];

if (empty($selectedItems)) {
    echo json_encode(['success' => false, 'message' => 'No items selected for checkout']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Calculate total amount
    $totalAmount = 0;
    $orderItems = [];
    
    foreach ($selectedItems as $item) {
        // Verify product exists and get current price
        $stmt = $conn->prepare("SELECT ProductID, Price, Stocks FROM products WHERE ProductID = ?");
        $stmt->bind_param("s", $item['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Product not found: " . $item['id']);
        }
        
        $product = $result->fetch_assoc();
        
        // Check stock availability
        if ($product['Stocks'] < $item['quantity']) {
            throw new Exception("Insufficient stock for product: " . $item['id']);
        }
        
        $subtotal = $product['Price'] * $item['quantity'];
        $totalAmount += $subtotal;
        
        $orderItems[] = [
            'product_id' => $product['ProductID'],
            'quantity' => $item['quantity'],
            'price' => $product['Price'],
            'subtotal' => $subtotal
        ];
    }
    
    // Create order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, order_date) VALUES (?, ?, 'pending', NOW())");
    $stmt->bind_param("id", $userId, $totalAmount);
    $stmt->execute();
    $orderId = $stmt->insert_id;
    
    // Generate QR code reference
    $qrCode = "BB-ORDER-" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . "-" . date('Ymd');
    
    // Update order with QR code
    $stmt = $conn->prepare("UPDATE orders SET qr_code = ? WHERE order_id = ?");
    $stmt->bind_param("si", $qrCode, $orderId);
    $stmt->execute();
    
    // Insert order items
    $stmt = $conn->prepare("INSERT INTO orderitems (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    
    foreach ($orderItems as $item) {
        $stmt->bind_param("isid", 
            $orderId, 
            $item['product_id'], 
            $item['quantity'], 
            $item['price']
        );
        $stmt->execute();
        
        // Update product stock
        $updateStock = $conn->prepare("UPDATE products SET Stocks = Stocks - ? WHERE ProductID = ?");
        $updateStock->bind_param("is", $item['quantity'], $item['product_id']);
        $updateStock->execute();
    }
    
    // DON'T remove cart items yet - they stay until order is completed
    // Cart items will be removed when order status changes to 'completed'
    
    // Commit transaction
    $conn->commit();
    
    // Return success response with CORRECT format
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully! 🎉',
        'order' => [
            'order_id' => $orderId,
            'qr_code' => $qrCode,
            'total_amount' => $totalAmount, // Return as number, not formatted
            'status' => 'pending',
            'order_date' => date('Y-m-d H:i:s'),
            'items_count' => count($orderItems)
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Checkout failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>