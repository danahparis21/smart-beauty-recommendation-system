<?php

session_start();
// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$sql = "SELECT 
    ProductID, 
    Name, 
    Category, 
    ParentProductID,  
    ShadeOrVariant,          
    Price, 
    ImagePath, 
    PreviewImage 
FROM products 
ORDER BY CreatedAt DESC"; 

$result = $conn->query($sql);

// ... existing error checking

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id' => $row['ProductID'],
        'name' => $row['Name'],
        'category' => strtolower($row['Category']),
        'parentID' => $row['ParentProductID'], // <-- CRUCIAL: Used for grouping
        'variant' => $row['ShadeOrVariant'],           // <-- CRUCIAL: Used for displaying variant info
        'price' => floatval($row['Price']),
        'image' => $row['ImagePath'] ?? '',
        'previewImage' => $row['PreviewImage'] ?? ''
    ];
}

echo json_encode(['success' => true, 'products' => $products]);
$conn->close();
?>