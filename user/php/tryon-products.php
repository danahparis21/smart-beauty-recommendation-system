<?php
header('Content-Type: application/json');
session_start();

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Fetch parent lipstick products and their variants
    $query = "
    SELECT 
        p.ProductID,
        p.Name,
        p.ShadeOrVariant,
        p.Price,
        p.HexCode,
        p.ParentProductID,
        parent.Name as ParentName
    FROM Products p
    LEFT JOIN Products parent ON p.ParentProductID = parent.ProductID
    WHERE p.Category = 'Lipstick' 
    AND p.ShadeOrVariant IS NOT NULL
    AND p.ShadeOrVariant != 'PARENT_GROUP'
    AND p.Status = 'Available'
    ORDER BY parent.Name, p.ShadeOrVariant
";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    $parentProducts = [];

    while ($row = $result->fetch_assoc()) {
        if (empty($row['ParentProductID'])) {
            // This is a parent product
            $parentProducts[$row['ProductID']] = [
                'id' => $row['ProductID'],
                'name' => $row['Name'],
                'parentName' => $row['ParentName'],
                'shades' => []
            ];
        } else {
            // This is a variant/shade
            $parentId = $row['ParentProductID'];
            if (!isset($parentProducts[$parentId])) {
                // Create parent entry if it doesn't exist
                $parentProducts[$parentId] = [
                    'id' => $parentId,
                    'name' => $row['ParentName'],
                    'parentName' => $row['ParentName'],
                    'shades' => []
                ];
            }

            $parentProducts[$parentId]['shades'][] = [
                'productId' => $row['ProductID'],
                'shadeName' => $row['ShadeOrVariant'],
                'price' => $row['Price'],
                'hexCode' => $row['HexCode'] ?: '#D42F6E'  // Default color if none
            ];
        }
    }

    // Convert to simple array and remove empty parents
    $products = array_values(array_filter($parentProducts, function ($product) {
        return !empty($product['shades']);
    }));

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>