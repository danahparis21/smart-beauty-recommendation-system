<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Pagination setup
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$sql = "
SELECT 
    p.ProductID, 
    p.ParentProductID,
    p.Name, 
    p.Price,
    p.Stocks, 
    p.Status, 
    p.Category,
    p.Ingredients,
    p.Description,
    p.HexCode,
    p.ShadeOrVariant,
    p.CreatedAt,

    p_parent.Name AS ParentName,

    a.SkinType,
    a.SkinTone,
    a.Undertone,
    a.Acne,
    a.Dryness,
    a.DarkSpots,
    a.Matte,
    a.Dewy,
    a.LongLasting,

    pm.ImagePath AS MediaImage,
    pm.MediaType
FROM 
    Products p
LEFT JOIN 
    Products p_parent ON p.ParentProductID = p_parent.ProductID
LEFT JOIN 
    ProductAttributes a ON p.ProductID = a.ProductID
LEFT JOIN 
    ProductMedia pm 
        ON (pm.VariantProductID = p.ProductID OR pm.ParentProductID = p.ProductID)
WHERE 
    p.Status != 'Deleted'
ORDER BY 
    COALESCE(p.ParentProductID, p.ProductID),
    p.CreatedAt DESC,
    pm.SortOrder ASC
";

$result = $conn->query($sql);
$products_by_parent = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grouping_id = $row['ParentProductID'] ?? $row['ProductID'];

        if (!isset($products_by_parent[$grouping_id])) {
            $products_by_parent[$grouping_id] = [
                'ProductID' => $grouping_id,
                'ParentName' => $row['ParentName'] ?? $row['Name'],
                'Category' => $row['Category'],
                'Status' => $row['Status'],
                'Description' => $row['Description'],
                'HexCode' => $row['HexCode'],
                'Image' => null,
                'Variants' => []
            ];
        }

        // ✅ Set parent image (only once)
        if ($row['MediaType'] === 'PREVIEW' && empty($products_by_parent[$grouping_id]['Image'])) {
            $products_by_parent[$grouping_id]['Image'] = str_replace('../', '/', $row['MediaImage']);
        }

        // ✅ Skip Parent_GROUP pseudo variants
        if ($row['ShadeOrVariant'] === 'PARENT_GROUP')
            continue;

            $variant_image = $row['MediaType'] === 'VARIANT' ? str_replace('../', '/', $row['MediaImage']) : null;

        // Build skin concern
        $concerns = [];
        if ($row['Acne'] == 1)
            $concerns[] = 'Acne';
        if ($row['Dryness'] == 1)
            $concerns[] = 'Dryness';
        if ($row['DarkSpots'] == 1)
            $concerns[] = 'Dark Spots';
        $skin_concern_value = empty($concerns) ? 'N/A' : implode(', ', $concerns);

        // NEW Finish Logic (Complete)
        $finish_attributes = [];

        if ($row['Matte'] == 1) {
            $finish_attributes[] = 'Matte';
        }
        if ($row['Dewy'] == 1) {
            $finish_attributes[] = 'Dewy';
        }
        if ($row['LongLasting'] == 1) {
            $finish_attributes[] = 'Long-lasting';
        }

        $finish_value = empty($finish_attributes) ? 'N/A' : implode(', ', $finish_attributes);

        $status = $row['Status'];
        if ((int) $row['Stocks'] <= 0) {
            $status = 'No Stock';
        } elseif ((int) $row['Stocks'] < 5 && $row['Status'] !== 'No Stock') {
            $status = 'Low Stock';
        }
        // Add variant
        $products_by_parent[$grouping_id]['Variants'][] = [
            'ProductID' => $row['ProductID'],
            'Name' => $row['Name'],
            'ShadeOrVariant' => $row['ShadeOrVariant'],
            'Price' => $row['Price'],
            'Stocks' => $row['Stocks'],
            'Status' => $status,
            'HexCode' => $row['HexCode'],
            'Image' => $variant_image,
            'Skin Type' => $row['SkinType'],
            'Skin Tone' => $row['SkinTone'],
            'Undertone' => $row['Undertone'],
            'Skin Concern' => $skin_concern_value,
            'Finish' => $finish_value
        ];
        // ✅ Compute "Available Shades" count for each parent
        foreach ($products_by_parent as $grouping_id => &$parentData) {
            $availableCount = 0;

            foreach ($parentData['Variants'] as $variant) {
                if (!in_array($variant['Status'], ['No Stock', 'Expired'])) {
                    $availableCount++;
                }
            }

            $parentData['Status'] = $availableCount > 0
                ? $availableCount . ' Shades'
                : 'No Shades';
        }
        unset($parentData);  // break reference
    }
}

$total_query = $conn->query("SELECT COUNT(*) as total FROM Products WHERE Status != 'Deleted'");
$total_row = $total_query->fetch_assoc();
$total_count = (int) $total_row['total'];

header('Content-Type: application/json');
echo json_encode([
    'parents' => array_values($products_by_parent),
    'total' => $total_count
]);

$conn->close();
?>
