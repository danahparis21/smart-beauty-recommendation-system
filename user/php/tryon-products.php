<?php
header('Content-Type: application/json');
session_start();

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Use the same function as your other file
function getPublicImagePath($dbPath) {
    if (empty($dbPath)) {
        return '';
    }
    $filename = basename($dbPath);
    return '/admin/uploads/product_images/' . $filename;
}

try {
    // Fetch parent lipstick products and their variants WITH VARIANT IMAGES
    $query = "
    SELECT 
        p.ProductID,
        p.Name,
        p.ShadeOrVariant,
        p.Price,
        p.HexCode,
        p.ParentProductID,
        parent.Name as ParentName,
        pm_variant.ImagePath as variant_image
    FROM Products p
    LEFT JOIN Products parent ON p.ParentProductID = parent.ProductID
    LEFT JOIN ProductMedia pm_variant ON p.ProductID = pm_variant.VariantProductID AND pm_variant.MediaType = 'VARIANT'
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
        // Clean the product name
        $cleanName = preg_replace('/^Parent Record:\s*/i', '', $row['Name']);
        $cleanParentName = preg_replace('/^Parent Record:\s*/i', '', $row['ParentName']);

        // Convert image path to public path
        $publicImagePath = getPublicImagePath($row['variant_image']);

        if (empty($row['ParentProductID'])) {
            // This is a parent product
            $parentProducts[$row['ProductID']] = [
                'id' => $row['ProductID'],
                'name' => $cleanName,
                'parentName' => $cleanParentName,
                'variantImage' => $publicImagePath, // Use variant image
                'shades' => []
            ];
        } else {
            // This is a variant/shade
            $parentId = $row['ParentProductID'];
            if (!isset($parentProducts[$parentId])) {
                // Create parent entry if it doesn't exist
                $parentProducts[$parentId] = [
                    'id' => $parentId,
                    'name' => $cleanParentName,
                    'parentName' => $cleanParentName,
                    'variantImage' => $publicImagePath, // Use variant image
                    'shades' => []
                ];
            }

            $parentProducts[$parentId]['shades'][] = [
                'productId' => $row['ProductID'],
                'shadeName' => $row['ShadeOrVariant'],
                'price' => $row['Price'],
                'hexCode' => $row['HexCode'] ?: '#D42F6E',
                'variantImage' => $publicImagePath // Also store variant image for each shade
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