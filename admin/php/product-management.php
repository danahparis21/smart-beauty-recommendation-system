<?php
// product-management.php 
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

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to output, keep JSON clean

// Add global error handler to catch any unexpected errors
function handleException($e) {
    error_log('Uncaught exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit;
}

set_exception_handler('handleException');

// Configuration & Connection
try {
    if (getenv('DOCKER_ENV') === 'true') {
        require_once __DIR__ . '/../../config/db_docker.php';
    } else {
        require_once __DIR__ . '/../../config/db.php';
    }

    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Set admin ID for triggers
    $conn->query("SET @admin_user_id = $adminId");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database configuration error: ' . $e->getMessage()]);
    exit;
}

// Get the action parameter
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_stats') {
    try {
        // Count all products (including variants)
        $sqlTotal = "SELECT COUNT(*) as total FROM Products WHERE Status != 'Deleted'";
        $resultTotal = $conn->query($sqlTotal);
        if (!$resultTotal) {
            throw new Exception('Total products query failed: ' . $conn->error);
        }
        $totalProducts = $resultTotal->fetch_assoc()['total'];
        
        // Count available products
        $sqlAvailable = "SELECT COUNT(*) as available FROM Products WHERE Status = 'Available' AND Stocks > 0 AND (ExpirationDate IS NULL OR ExpirationDate > CURDATE())";
        $resultAvailable = $conn->query($sqlAvailable);
        if (!$resultAvailable) {
            throw new Exception('Available products query failed: ' . $conn->error);
        }
        $availableProducts = $resultAvailable->fetch_assoc()['available'];
        
        // Count low stock products
        $sqlLowStock = "SELECT COUNT(*) as low_stock FROM Products WHERE Status = 'Low Stock' OR (Stocks > 0 AND Stocks < 5)";
        $resultLowStock = $conn->query($sqlLowStock);
        if (!$resultLowStock) {
            throw new Exception('Low stock products query failed: ' . $conn->error);
        }
        $lowStockProducts = $resultLowStock->fetch_assoc()['low_stock'];
        
        // Count expired products
        $sqlExpired = "SELECT COUNT(*) as expired FROM Products WHERE ExpirationDate IS NOT NULL AND ExpirationDate <= CURDATE() AND Status != 'Deleted'";
        $resultExpired = $conn->query($sqlExpired);
        if (!$resultExpired) {
            throw new Exception('Expired products query failed: ' . $conn->error);
        }
        $expiredProducts = $resultExpired->fetch_assoc()['expired'];
        
        // Count no stock products
        $sqlNoStock = "SELECT COUNT(*) as no_stock FROM Products WHERE Status = 'No Stock' OR Stocks = 0";
        $resultNoStock = $conn->query($sqlNoStock);
        if (!$resultNoStock) {
            throw new Exception('No stock products query failed: ' . $conn->error);
        }
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
        error_log('Error in product stats: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch product stats: ' . $e->getMessage()
        ]);
    }
    exit;
} else {
    // If no valid action, return error
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action specified'
    ]);
    exit;
}

?>