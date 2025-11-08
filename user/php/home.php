<?php
// home.php - WITH VARIANT-LEVEL FILTERING
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

function getPublicImagePath($dbPath)
{
    if (empty($dbPath))
        return '';
    $filename = basename($dbPath);
    return '/admin/uploads/product_images/' . $filename;
}

// ===================== IMPROVED SQL WITH VARIANT FILTERING ===================== //
$sql = "
    SELECT 
        p.ProductID AS id, 
        p.Name AS name, 
        p.Category AS category, 
        p.ParentProductID AS parentID,
        p.ShadeOrVariant AS variant,
        p.Price AS price,
        p.HexCode AS hexCode,
        p.Status AS status, 
        p.Stocks AS stockQuantity, 
        pm_v.ImagePath AS variantImage,
        pm_p.ImagePath AS previewImage,
        -- Get the actual parent product name
        parent.Name AS parentName,
        -- Get parent status for reference
        parent.Status AS parentStatus
    FROM 
        Products p
    LEFT JOIN ProductMedia pm_v 
        ON pm_v.VariantProductID = p.ProductID 
        AND pm_v.MediaType = 'VARIANT'
    LEFT JOIN ProductMedia pm_p 
        ON pm_p.ParentProductID = COALESCE(p.ParentProductID, p.ProductID)
        AND pm_p.MediaType = 'PREVIEW'
    LEFT JOIN Products parent 
        ON p.ParentProductID = parent.ProductID
    WHERE 
        -- Filter out bad statuses for ALL products (parents and variants)
        p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
        AND p.Stocks > 0  -- Only products with stock
    ORDER BY 
        p.CreatedAt DESC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Database query failed: ' . $conn->error]));
}

$groupedProducts = [];
$processedParentIds = [];

error_log('Total rows from SQL (after filtering): ' . $result->num_rows);

while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];
    $isParent = ($parentID === null || $parentID === $row['id']);

    // Convert image paths
    $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
    $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');
    $row['name'] = trim(str_ireplace('Product Record', '', $row['name']));
    $row['parentName'] = trim(str_ireplace('Product Record', '', $row['parentName'] ?? ''));

    error_log("Processing: ID={$row['id']}, Name={$row['name']}, Status={$row['status']}, Stock={$row['stockQuantity']}");

    if ($isParent) {
        // Parent product - only add if not already processed
        if (!isset($groupedProducts[$row['id']])) {
            $groupedProducts[$row['id']] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['status'],
                'stockQuantity' => intval($row['stockQuantity']),
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
            $processedParentIds[] = $row['id'];
            error_log("Added parent: {$row['id']} - {$row['name']} - Status: {$row['status']}");
        }
    } else {
        // Variant product - use the actual parent name
        $parentKey = $parentID;
        $parentName = $row['parentName'] ?? $row['name'];

        // Only create parent if it has at least one available variant
        if (!isset($groupedProducts[$parentKey])) {
            $groupedProducts[$parentKey] = [
                'id' => $parentKey,
                'name' => $parentName,
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['parentStatus'] ?? 'Available',  // Use parent status
                'stockQuantity' => 0,  // Will calculate total from variants
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
            $processedParentIds[] = $parentKey;
            error_log("Created parent from variant: {$parentKey} - {$parentName}");
        }

        // Add variant (already filtered by SQL to exclude bad statuses)
        $groupedProducts[$parentKey]['variants'][] = [
            'id' => $row['id'],
            'name' => $row['variant'] ?? $row['name'],
            'variant' => $row['variant'] ?? $row['name'],
            'price' => floatval($row['price']),
            'image' => $row['variantImage'],
            'hexCode' => $row['hexCode'] ?? '#CCCCCC',
            'status' => $row['status'],
            'stockQuantity' => intval($row['stockQuantity']),
        ];

        error_log("Added variant: {$row['variant']} - Status: {$row['status']} - Stock: {$row['stockQuantity']}");
    }
}

// Calculate parent stock based on available variants and clean up empty parents
$finalProducts = [];
foreach ($groupedProducts as $productId => $product) {
    // If parent has variants, calculate total stock from available variants
    if (!empty($product['variants'])) {
        $totalStock = 0;
        $hasAvailableVariants = false;

        foreach ($product['variants'] as $variant) {
            $totalStock += $variant['stockQuantity'];
            if ($variant['stockQuantity'] > 0 && $variant['status'] !== 'No Stock') {
                $hasAvailableVariants = true;
            }
        }

        $product['stockQuantity'] = $totalStock;

        // If no variants are available, skip this product
        if (!$hasAvailableVariants) {
            error_log("Skipping product {$productId} - no available variants");
            continue;
        }
    }

    // Ensure required fields
    if (!isset($product['status']))
        $product['status'] = 'Available';
    if (!isset($product['stockQuantity']))
        $product['stockQuantity'] = 0;

    $finalProducts[] = $product;
}

// Final cleanup: Remove parents with no variants (if any slipped through)
$finalProducts = array_filter($finalProducts, function ($product) {
    return !empty($product['variants']) || $product['stockQuantity'] > 0;
});

// Debug final output
error_log('Final products count after filtering: ' . count($finalProducts));
foreach ($finalProducts as $product) {
    $variantStatuses = array_column($product['variants'], 'status');
    error_log("Final product: {$product['id']} - {$product['name']} - Total Stock: {$product['stockQuantity']} - Variant Statuses: " . implode(', ', $variantStatuses));
}

// ===================== OUTPUT ===================== //
$response = [
    'success' => true,
    'products' => $finalProducts,
    'debug' => [
        'total_products' => count($finalProducts),
        'product_ids' => array_column($finalProducts, 'id'),
        'filter_info' => 'Excluded: No Stock, Expired, Deleted, Disabled, Zero Stock'
    ]
];

echo json_encode($response);
$conn->close();
?>