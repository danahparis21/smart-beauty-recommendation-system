<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Add the image path conversion function
function getPublicImagePath($dbPath) {
    if (empty($dbPath)) {
        return '';
    }
    
    // If it's already the correct admin path, return as-is
    if (strpos($dbPath, '/admin/uploads/product_images/') === 0) {
        return $dbPath;
    }
    
    // If it's a user path, convert to admin path
    if (strpos($dbPath, '/user/uploads/product_images/') === 0) {
        return str_replace('/user/uploads/product_images/', '/admin/uploads/product_images/', $dbPath);
    }
    
    // Handle old paths with '../'
    if (strpos($dbPath, '../') === 0) {
        return str_replace('../', '/admin/', $dbPath);
    }
    
    // If it starts with any other slash, assume it's already a public path
    if (strpos($dbPath, '/') === 0) {
        return $dbPath;
    }
    
    // For everything else (just filenames), add the correct path
    return '/admin/uploads/product_images/' . $dbPath;
}

/**
 * Clean product name - remove "Parent Record:" prefix
 */
function cleanProductName($name) {
    return trim(preg_replace([
        '/Parent Record:\s*/i',
        '/Product Record:\s*/i',
        '/:\s*/',
        '/\s+/'
    ], ['', '', '', ' '], $name ?? ''));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    // Fetch order details
    $orderQuery = "
        SELECT 
            o.order_id,
            o.total_price,
            o.status,
            o.qr_code,
            o.order_date
        FROM orders o
        WHERE o.order_id = ? AND o.user_id = ?
    ";
    
    $stmt = $conn->prepare($orderQuery);
    $stmt->bind_param("ii", $orderId, $userId);
    $stmt->execute();
    $orderResult = $stmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }
    
    $order = $orderResult->fetch_assoc();
    
    // Fetch order items with product details
    $itemsQuery = "
        SELECT 
            oi.product_id,
            oi.quantity,
            oi.price,
            p.Name as name,
            p.ShadeOrVariant,
            p.Category,
            COALESCE(pm_variant.ImagePath, pm_preview.ImagePath, '/admin/uploads/product_images/no-image.png') AS image
        FROM orderitems oi
        INNER JOIN Products p ON oi.product_id = p.ProductID
        LEFT JOIN ProductMedia pm_variant 
            ON p.ProductID = pm_variant.VariantProductID 
            AND pm_variant.MediaType = 'VARIANT'
        LEFT JOIN ProductMedia pm_preview 
            ON p.ParentProductID = pm_preview.ParentProductID 
            AND pm_preview.MediaType = 'PREVIEW'
        WHERE oi.order_id = ?
    ";
    
    $stmt = $conn->prepare($itemsQuery);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        // Clean product name
        $row['name'] = cleanProductName($row['name']);
        
        // FIX: Use the getPublicImagePath function for proper image path conversion
        if (!empty($row['image']) && $row['image'] !== '/admin/uploads/product_images/no-image.png') {
            $row['image'] = getPublicImagePath($row['image']);
        }
        
        $items[] = $row;
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'order' => [
            'order_id' => (int)$order['order_id'],
            'total_price' => (float)$order['total_price'],
            'status' => $order['status'],
            'qr_code' => $order['qr_code'],
            'order_date' => $order['order_date']
        ],
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching order: ' . $e->getMessage()
    ]);
}

$conn->close();
?>