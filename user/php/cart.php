<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'cart' => [],
    'cartCount' => 0
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Please login to manage your cart';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            addToCart($conn, $userId, $response);
            break;
        
        case 'remove':
            removeFromCart($conn, $userId, $response);
            break;
        
        case 'update':
            updateCartQuantity($conn, $userId, $response);
            break;
        
        case 'get':
            getCart($conn, $userId, $response);
            break;
        
        case 'clear':
            clearCart($conn, $userId, $response);
            break;
        
        case 'count':
            getCartCount($conn, $userId, $response);
            break;
        
        default:
            $response['message'] = 'Invalid action';
            break;
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
$conn->close();

// ==================== FUNCTIONS ====================

/**
 * Add product to cart
 */
function addToCart($conn, $userId, &$response) {
    $productId = $_POST['product_id'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (empty($productId)) {
        $response['message'] = 'Product ID is required';
        return;
    }
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Check if product exists and get details
    $productQuery = "SELECT ProductID, Name, Price, Stocks FROM products WHERE ProductID = ?";
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param("s", $productId);
    $stmt->execute();
    $productResult = $stmt->get_result();
    
    if ($productResult->num_rows === 0) {
        $response['message'] = 'Product not found';
        return;
    }
    
    $product = $productResult->fetch_assoc();
    
    // Check stock availability
    if ($product['Stocks'] < $quantity) {
        $response['message'] = 'Insufficient stock available';
        return;
    }
    
    // Check if product already in cart
    $checkCart = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($checkCart);
    $stmt->bind_param("is", $userId, $productId);
    $stmt->execute();
    $cartResult = $stmt->get_result();
    
    if ($cartResult->num_rows > 0) {
        // Product exists in cart, update quantity
        $cartItem = $cartResult->fetch_assoc();
        $newQuantity = $cartItem['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($newQuantity > $product['Stocks']) {
            $response['message'] = 'Cannot add more items. Stock limit reached.';
            return;
        }
        
        $updateQuery = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ii", $newQuantity, $cartItem['cart_id']);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Cart updated successfully! 🛒';
            $response['action'] = 'updated';
        }
    } else {
        // Add new item to cart
        $insertQuery = "INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("isi", $userId, $productId, $quantity);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Added to cart! 🎉';
            $response['action'] = 'added';
        }
    }
    
    // Get updated cart
    getCart($conn, $userId, $response);
}

/**
 * Remove product from cart
 */
function removeFromCart($conn, $userId, &$response) {
    $productId = $_POST['product_id'] ?? '';
    
    if (empty($productId)) {
        $response['message'] = 'Product ID is required';
        return;
    }
    
    $deleteQuery = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("is", $userId, $productId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Removed from cart 🗑️';
            $response['action'] = 'removed';
        } else {
            $response['message'] = 'Item not found in cart';
        }
    }
    
    // Get updated cart
    getCart($conn, $userId, $response);
}

/**
 * Update cart item quantity
 */
function updateCartQuantity($conn, $userId, &$response) {
    $productId = $_POST['product_id'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (empty($productId)) {
        $response['message'] = 'Product ID is required';
        return;
    }
    
    if ($quantity < 1) {
        $response['message'] = 'Quantity must be at least 1';
        return;
    }
    
    // Check stock availability
    $stockQuery = "SELECT Stocks FROM products WHERE ProductID = ?";
    $stmt = $conn->prepare($stockQuery);
    $stmt->bind_param("s", $productId);
    $stmt->execute();
    $stockResult = $stmt->get_result();
    
    if ($stockResult->num_rows === 0) {
        $response['message'] = 'Product not found';
        return;
    }
    
    $product = $stockResult->fetch_assoc();
    
    if ($quantity > $product['Stocks']) {
        $response['message'] = 'Quantity exceeds available stock';
        return;
    }
    
    // Update quantity
    $updateQuery = "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("iis", $quantity, $userId, $productId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Quantity updated ✓';
            $response['action'] = 'updated';
        } else {
            $response['message'] = 'Item not found in cart';
        }
    }
    
    // Get updated cart
    getCart($conn, $userId, $response);
}

/**
 * Get user's cart
 */
function getCart($conn, $userId, &$response) {
    $query = "
        SELECT 
            c.cart_id,
            c.product_id,
            c.quantity,
            p.Name as name,
            p.Price as price,
            p.Category as category,
            p.Stocks as stock_quantity,
            (c.quantity * p.Price) as subtotal,
            COALESCE(pm_variant.ImagePath, pm_preview.ImagePath) as image
        FROM cart c
        INNER JOIN products p ON c.product_id = p.ProductID
        LEFT JOIN productmedia pm_variant 
            ON p.ProductID = pm_variant.VariantProductID 
            AND pm_variant.MediaType = 'VARIANT'
        LEFT JOIN productmedia pm_preview 
            ON p.ParentProductID = pm_preview.ParentProductID 
            AND pm_preview.MediaType = 'PREVIEW'
        WHERE c.user_id = ?
        ORDER BY c.added_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cartItems = [];
    $totalAmount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Clean product name
        $row['name'] = cleanProductName($row['name']);
        
        // Use fallback image if none available
        if (empty($row['image'])) {
            $row['image'] = '/admin/uploads/product_images/no-image.png';
        } else {
            // Convert to public path
            $filename = basename($row['image']);
            $row['image'] = '/admin/uploads/product_images/' . $filename;
        }
        
        $cartItems[] = $row;
        $totalAmount += $row['subtotal'];
    }
    
    $response['success'] = true;
    $response['cart'] = $cartItems;
    $response['cartCount'] = count($cartItems);
    $response['totalAmount'] = $totalAmount;
    $response['message'] = $response['message'] ?: 'Cart retrieved successfully';
}

/**
 * Clear entire cart
 */
function clearCart($conn, $userId, &$response) {
    $deleteQuery = "DELETE FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Cart cleared successfully';
        $response['cart'] = [];
        $response['cartCount'] = 0;
        $response['totalAmount'] = 0;
    }
}

/**
 * Get cart count only
 */
function getCartCount($conn, $userId, &$response) {
    $query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $response['success'] = true;
    $response['cartCount'] = $row['count'];
    $response['message'] = 'Cart count retrieved';
}

/**
 * Clean product name helper function
 */
function cleanProductName($name) {
    if (!$name) return "";
    
    return trim(preg_replace([
        '/Product Record/i',
        '/Parent Record/i',
        '/:\s*/',
        '/\s+/'
    ], [
        '',
        '',
        '',
        ' '
    ], $name));
}
?>