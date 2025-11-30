<?php
// test-stats.php - Simple test
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'stats' => [
        'total_products' => 150,
        'available_products' => 120,
        'low_stock_products' => 15,
        'expired_products' => 5,
        'no_stock_products' => 10
    ]
]);
?>