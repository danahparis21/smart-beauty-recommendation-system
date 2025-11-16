<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Please login to view order history');
}

$userId = $_SESSION['user_id'];

try {
    // Fetch orders with their items - USING CORRECT COLUMN NAMES
    $stmt = $conn->prepare("
        SELECT 
            o.order_id,
            o.total_price,
            o.status,
            o.order_date,
            o.qr_code,
            oi.order_item_id,
            oi.quantity,
            oi.price as unit_price,
            p.ProductID,
            p.Name as product_name,
            p.Description as product_description,
            p.ShadeOrVariant,
            p.Category,
            p.ParentProductID,
            COALESCE(pm_variant.ImagePath, pm_preview.ImagePath) AS product_image
        FROM orders o
        LEFT JOIN orderitems oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.ProductID
        LEFT JOIN productmedia pm_variant 
            ON p.ProductID = pm_variant.VariantProductID 
            AND pm_variant.MediaType = 'VARIANT'
        LEFT JOIN productmedia pm_preview 
            ON p.ParentProductID = pm_preview.ParentProductID 
            AND pm_preview.MediaType = 'PREVIEW'
        WHERE o.user_id = ?
        ORDER BY o.order_date DESC, o.order_id DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    $currentOrderId = null;
    $currentOrder = null;
    
    while ($row = $result->fetch_assoc()) {
        // If this is a new order
        if ($currentOrderId !== $row['order_id']) {
            if ($currentOrder !== null) {
                $orders[] = $currentOrder;
            }
            
            $currentOrderId = $row['order_id'];
            $currentOrder = [
                'id' => 'BB-' . str_pad($row['order_id'], 6, '0', STR_PAD_LEFT),
                'order_id' => $row['order_id'],
                'date' => date('M j, Y', strtotime($row['order_date'])),
                'status' => $row['status'],
                'total_price' => $row['total_price'],
                'qr_code' => $row['qr_code'],
                'products' => []
            ];
        }
        
        // Add product to current order if product exists
        if ($row['ProductID']) {
            $currentOrder['products'][] = [
                'id' => $row['ProductID'],
                'name' => $row['product_name'],
                'description' => $row['product_description'],
                'image' => $row['product_image'],
                'variant' => $row['ShadeOrVariant'] ? 'Shade: ' . $row['ShadeOrVariant'] : 'Standard',
                'category' => $row['Category'],
                'price' => $row['unit_price'],
                'quantity' => $row['quantity'],
                'total' => $row['unit_price'] * $row['quantity'],
                'rated' => false, // You'll need to check reviews table for this
                'quickRating' => 0
            ];
        }
    }
    
    // Add the last order
    if ($currentOrder !== null) {
        $orders[] = $currentOrder;
    }
    
    $stmt->close();
    $conn->close();
    
    sendResponse(true, 'Orders fetched successfully', ['orders' => $orders]);
    
} catch (Exception $e) {
    sendResponse(false, 'Error fetching orders: ' . $e->getMessage());
}
?>