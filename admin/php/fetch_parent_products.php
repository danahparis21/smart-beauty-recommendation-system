<?php
// fetch_parent_products.php
header('Content-Type: application/json');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Fetch parent products with Name and Category
    $sql = "SELECT ProductID, Name, Category 
            FROM Products 
            WHERE ShadeOrVariant = 'PARENT_GROUP'
            ORDER BY Name ASC";

    $result = $conn->query($sql);

    $products = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    echo json_encode($products);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch parent products', 'details' => $e->getMessage()]);
} finally {
    if (isset($conn))
        $conn->close();
}
?>
