<?php
// test-image-path.php
session_start();

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

echo "<h1>Image Path Debug</h1>";

// Test a specific product
$productId = 'LIP007';
$query = "SELECT ImagePath FROM ProductMedia WHERE VariantProductID = ? AND MediaType = 'VARIANT' LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $productId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<h3>Product: $productId</h3>";
    echo "ImagePath from DB: " . $row['ImagePath'] . "<br>";
    
    $dbPath = $row['ImagePath'];
    
    // Try different paths
    $paths = [
        'original' => $dbPath,
        'no_dots' => str_replace('../', '', $dbPath),
        'from_user_php' => '../../admin/' . str_replace('../', '', $dbPath),
        'absolute' => __DIR__ . '/../../admin/' . str_replace('../', '', $dbPath)
    ];
    
    foreach ($paths as $name => $path) {
        echo "<br><strong>$name:</strong> $path<br>";
        echo "Exists: " . (file_exists($path) ? "✅ YES" : "❌ NO") . "<br>";
        if (file_exists($path)) {
            echo "<img src='$path' style='width: 100px; border: 2px solid green;'><br>";
        }
    }
} else {
    echo "No image found for $productId";
}
?>