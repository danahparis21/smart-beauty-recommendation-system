<?php
// product-management.php - Add this section for stats
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Your existing database connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set admin ID for triggers (if doing database operations)
$conn->query("SET @admin_user_id = $adminId");

header('Content-Type: application/json');

// Get the action parameter
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_stats') {
    try {
        // Count all products (including variants)
        $sqlTotal = "SELECT COUNT(*) as total FROM Products WHERE Status != 'Deleted'";
        $resultTotal = $conn->query($sqlTotal);
        $totalProducts = $resultTotal->fetch_assoc()['total'];
        
        // Count available products
        $sqlAvailable = "SELECT COUNT(*) as available FROM Products WHERE Status = 'Available' AND Stocks > 0 AND (ExpirationDate IS NULL OR ExpirationDate > CURDATE())";
        $resultAvailable = $conn->query($sqlAvailable);
        $availableProducts = $resultAvailable->fetch_assoc()['available'];
        
        // Count low stock products (assuming low stock is < 20)
        $sqlLowStock = "SELECT COUNT(*) as low_stock FROM Products WHERE Status = 'Low Stock' OR (Stocks > 0 AND Stocks < 20)";
        $resultLowStock = $conn->query($sqlLowStock);
        $lowStockProducts = $resultLowStock->fetch_assoc()['low_stock'];
        
        // Count expired products
        $sqlExpired = "SELECT COUNT(*) as expired FROM Products WHERE ExpirationDate IS NOT NULL AND ExpirationDate <= CURDATE() AND Status != 'Deleted'";
        $resultExpired = $conn->query($sqlExpired);
        $expiredProducts = $resultExpired->fetch_assoc()['expired'];
        
        // Count no stock products
        $sqlNoStock = "SELECT COUNT(*) as no_stock FROM Products WHERE Status = 'No Stock' OR Stocks = 0";
        $resultNoStock = $conn->query($sqlNoStock);
        $noStockProducts = $resultNoStock->fetch_assoc()['no_stock'];
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_products' => (int)$totalProducts,
                'available_products' => (int)$availableProducts,
                'low_stock_products' => (int)$lowStockProducts,
                'expired_products' => (int)$expiredProducts,
                'no_stock_products' => (int)$noStockProducts
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch product stats: ' . $e->getMessage()
        ]);
    }
    exit;
}

?>