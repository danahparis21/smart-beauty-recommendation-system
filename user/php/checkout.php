<?php
date_default_timezone_set('Asia/Manila');
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set database timezone too
$conn->query("SET time_zone = '+08:00'");

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

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
    $productNames = [];

    foreach ($selectedItems as $item) {
        // Verify product exists and get current price
        $stmt = $conn->prepare('SELECT ProductID, Name, Price, Stocks FROM Products WHERE ProductID = ?');
        $stmt->bind_param('s', $item['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Product not found: ' . $item['id']);
        }

        $product = $result->fetch_assoc();

        // Check stock availability
        if ($product['Stocks'] < $item['quantity']) {
            throw new Exception('Insufficient stock for product: ' . $product['Name']);
        }

        $subtotal = $product['Price'] * $item['quantity'];
        $totalAmount += $subtotal;

        $orderItems[] = [
            'product_id' => $product['ProductID'],
            'product_name' => $product['Name'],
            'quantity' => $item['quantity'],
            'price' => $product['Price'],
            'subtotal' => $subtotal
        ];

        $productNames[] = $product['Name'] . " (Qty: {$item['quantity']})";
    }

    // ✅ FIXED: Generate QR code first and insert everything at once
    $currentDateTime = date('Y-m-d H:i:s');
    $qrCode = 'BB-ORDER-' . str_pad($conn->insert_id ?: '000000', 6, '0', STR_PAD_LEFT) . '-' . date('Ymd');

    // Insert order with all data including QR code
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, status, order_date, qr_code) VALUES (?, ?, 'pending', ?, ?)");
    $stmt->bind_param('idss', $userId, $totalAmount, $currentDateTime, $qrCode);
    $stmt->execute();
    $orderId = $stmt->insert_id;

    // Update QR code with actual order ID
    $qrCode = 'BB-ORDER-' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd');
    $stmt = $conn->prepare('UPDATE orders SET qr_code = ? WHERE order_id = ?');
    $stmt->bind_param('si', $qrCode, $orderId);
    $stmt->execute();

    // Insert order items
    $stmt = $conn->prepare('INSERT INTO orderitems (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');

    foreach ($orderItems as $item) {
        $stmt->bind_param('isid',
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['price']);
        $stmt->execute();

        // Update product stock
        $updateStock = $conn->prepare('UPDATE Products SET Stocks = Stocks - ? WHERE ProductID = ?');
        $updateStock->bind_param('is', $item['quantity'], $item['product_id']);
        $updateStock->execute();

        // ✅ MARK CART ITEMS AS CHECKED_OUT
        $updateCart = $conn->prepare("UPDATE cart SET status = 'checked_out' WHERE user_id = ? AND product_id = ? AND status = 'active'");
        $updateCart->bind_param('is', $userId, $item['product_id']);
        $updateCart->execute();
        $updateCart->close();
    }

    // ✅ ADD NOTIFICATION FOR ORDER PLACEMENT
    $notificationTitle = 'Order Placed Successfully! 🎉';
    $notificationMessage = "Your order #{$orderId} has been placed. Total: ₱{$totalAmount}. Show the QR code at the counter to complete your purchase.";

    $notificationStmt = $conn->prepare('INSERT INTO notifications (UserID, Title, Message, IsRead, CreatedAt) VALUES (?, ?, ?, 0, NOW())');
    $notificationStmt->bind_param('iss', $userId, $notificationTitle, $notificationMessage);
    $notificationStmt->execute();
    $notificationStmt->close();

    // Commit transaction
    $conn->commit();

    // ✅ LOG SUCCESSFUL ORDER CREATION
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $orderDetails = "Order #{$orderId} - Total: ₱{$totalAmount}, Items: " . count($orderItems) . ', Products: ' . implode(', ', $productNames) . ", QR: {$qrCode}, IP: {$userIP}";
    logUserActivity($conn, $userId, 'Order placed', $orderDetails);

    // Return success response with CORRECT format
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully! 🎉',
        'order' => [
            'order_id' => $orderId,
            'qr_code' => $qrCode,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'order_date' => $currentDateTime,  // Use the same PHP date
            'items_count' => count($orderItems)
        ]
    ]);
} catch (Exception $e) {
    // Rollback transaction on error

    // ✅ LOG CHECKOUT FAILURE
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $errorDetails = 'Error: ' . $e->getMessage() . ', Items attempted: ' . count($selectedItems) . ", IP: {$userIP}";
    logUserActivity($conn, $userId, 'Checkout failed', $errorDetails);

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Checkout failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>