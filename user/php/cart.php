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

// Include activity logger
require_once __DIR__ . '/activity_logger.php';
$conn->query("SET time_zone = '+08:00'"); 
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

        // ✅ ADD THIS NEW CASE
        case 'update_status':
            updateCartStatus($conn, $userId, $response);
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

// ==================== FUNCTIONS ==================== //

/**
 * Add product to cart
 */
function addToCart($conn, $userId, &$response)
{
    $productId = $_POST['product_id'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 1);

    if (empty($productId)) {
        $response['message'] = 'Product ID is required';
        return;
    }

    if ($quantity < 1)
        $quantity = 1;

    // Validate product existence
    $productQuery = 'SELECT ProductID, Name, Price, Stocks FROM Products WHERE ProductID = ?';
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param('s', $productId);
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

    // Check if already in cart
    $checkCart = 'SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND status = "active"';
    $stmt = $conn->prepare($checkCart);
    $stmt->bind_param('is', $userId, $productId);
    $stmt->execute();
    $cartResult = $stmt->get_result();

    if ($cartResult->num_rows > 0) {
        // Already in cart → update quantity
        $cartItem = $cartResult->fetch_assoc();
        $newQuantity = $cartItem['quantity'] + $quantity;

        if ($newQuantity > $product['Stocks']) {
            $response['message'] = 'Cannot add more items. Stock limit reached.';
            return;
        }

        $updateQuery = 'UPDATE cart SET quantity = ? WHERE cart_id = ?';
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ii', $newQuantity, $cartItem['cart_id']);
        $stmt->execute();

        $response['success'] = true;
        $response['message'] = 'Cart updated successfully 🛒';
        $response['action'] = 'updated';

        // ✅ LOG CART UPDATE
        logUserActivity($conn, $userId, 'Cart updated',
            "Updated quantity for product {$productId} to {$newQuantity}");
    } else {
        // Add new item
        $insertQuery = 'INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())';
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param('isi', $userId, $productId, $quantity);
        $stmt->execute();

        $response['success'] = true;
        $response['message'] = 'Added to cart';
        $response['action'] = 'added';

        // ✅ LOG CART ADDITION
        logUserActivity($conn, $userId, 'Add to cart',
            "Added product {$productId} (Qty: {$quantity}) - {$product['Name']}");
    }

    getCart($conn, $userId, $response);
}

/**
 * Remove product from cart
 */
function removeFromCart($conn, $userId, &$response)
{
    $productId = $_POST['product_id'] ?? '';

    if (empty($productId)) {
        $response['message'] = 'Product ID is required';
        return;
    }

    // Get product name before deleting for logging
    $productQuery = 'SELECT Name FROM Products WHERE ProductID = ?';
    $stmt = $conn->prepare($productQuery);
    $stmt->bind_param('s', $productId);
    $stmt->execute();
    $productResult = $stmt->get_result();
    $productName = $productResult->num_rows > 0 ? $productResult->fetch_assoc()['Name'] : 'Unknown Product';

    $deleteQuery = 'DELETE FROM cart WHERE user_id = ? AND product_id = ?';
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param('is', $userId, $productId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Removed from cart 🗑️';
        $response['action'] = 'removed';

        // ✅ LOG CART REMOVAL
        logUserActivity($conn, $userId, 'Remove from cart',
            "Removed product {$productId} - {$productName}");
    } else {
        $response['message'] = 'Item not found in cart';
    }

    getCart($conn, $userId, $response);
}

/**
 * Update quantity
 */
function updateCartQuantity($conn, $userId, &$response)
{
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

    // Check stock and get product name
    $stockQuery = 'SELECT Stocks, Name FROM Products WHERE ProductID = ?';
    $stmt = $conn->prepare($stockQuery);
    $stmt->bind_param('s', $productId);
    $stmt->execute();
    $stockResult = $stmt->get_result();

    if ($stockResult->num_rows === 0) {
        $response['message'] = 'Product not found';
        return;
    }

    $product = $stockResult->fetch_assoc();
    if ($quantity > $product['Stocks']) {
        $response['message'] = 'Quantity exceeds stock';
        return;
    }

    $updateQuery = 'UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?';
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('iis', $quantity, $userId, $productId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Quantity updated ✓';
        $response['action'] = 'updated';

        // ✅ LOG QUANTITY UPDATE
        logUserActivity($conn, $userId, 'Cart quantity updated',
            "Updated product {$productId} - {$product['Name']} to quantity {$quantity}");
    } else {
        $response['message'] = 'Item not found in cart';
    }

    getCart($conn, $userId, $response);
}

/**
 * Get cart
 */
function getCart($conn, $userId, &$response)
{
    $query = "
        SELECT 
            c.cart_id,
            c.product_id,
            c.quantity,
            c.status, 
            p.Name AS name,
            p.Price AS price,
            p.Category AS category,
            p.Stocks AS stock_quantity,
            (c.quantity * p.Price) AS subtotal,
            COALESCE(pm_variant.ImagePath, pm_preview.ImagePath) AS image
        FROM cart c
        INNER JOIN Products p ON c.product_id = p.ProductID
        LEFT JOIN ProductMedia pm_variant 
            ON p.ProductID = pm_variant.VariantProductID 
            AND pm_variant.MediaType = 'VARIANT'
        LEFT JOIN ProductMedia pm_preview 
            ON p.ParentProductID = pm_preview.ParentProductID 
            AND pm_preview.MediaType = 'PREVIEW'
        WHERE c.user_id = ? AND c.status = 'active'  
        ORDER BY c.added_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $cartItems = [];
    $totalAmount = 0;

    while ($row = $result->fetch_assoc()) {
        $row['name'] = cleanProductName($row['name']);
        $row['image'] = !empty($row['image'])
            ? '/admin/uploads/product_images/' . basename($row['image'])
            : '/admin/uploads/product_images/no-image.png';

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
 * ✅ ADD THIS NEW FUNCTION - Update cart status
 */
function updateCartStatus($conn, $userId, &$response)
{
    $productId = $_POST['product_id'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if (empty($productId)) {
        $response['message'] = 'Product ID is required';
        return;
    }

    // Validate status
    if (!in_array($status, ['active', 'checked_out'])) {
        $response['message'] = 'Invalid status';
        return;
    }

    $updateQuery = 'UPDATE cart SET status = ? WHERE user_id = ? AND product_id = ?';
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('sis', $status, $userId, $productId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Cart item status updated';

        // ✅ LOG STATUS UPDATE
        logUserActivity($conn, $userId, 'Cart status updated',
            "Updated product {$productId} status to '{$status}'");
    } else {
        $response['message'] = 'Item not found in cart';
    }
}

/**
 * Clear cart
 */
function clearCart($conn, $userId, &$response)
{
    // Get cart items before clearing for logging
    $cartQuery = 'SELECT COUNT(*) as item_count FROM cart WHERE user_id = ? AND status = "active"';
    $stmt = $conn->prepare($cartQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $cartResult = $stmt->get_result();
    $itemCount = $cartResult->fetch_assoc()['item_count'];

    $stmt = $conn->prepare('DELETE FROM cart WHERE user_id = ? AND status = "active"');
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $response['success'] = true;
    $response['message'] = 'Cart cleared successfully';
    $response['cart'] = [];
    $response['cartCount'] = 0;
    $response['totalAmount'] = 0;

    // ✅ LOG CART CLEAR
    if ($itemCount > 0) {
        logUserActivity($conn, $userId, 'Cart cleared',
            "Cleared entire cart with {$itemCount} items");
    }
}

/**
 * Count only
 */
function getCartCount($conn, $userId, &$response)
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS count FROM cart WHERE user_id = ? AND status = "active"');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $response['success'] = true;
    $response['cartCount'] = $result['count'];
    $response['message'] = 'Cart count retrieved';
}

/**
 * Clean product name
 */
function cleanProductName($name)
{
    return trim(preg_replace([
        '/Product Record/i',
        '/Parent Record/i',
        '/:\s*/',
        '/\s+/'
    ], ['', '', '', ' '], $name ?? ''));
}
?>