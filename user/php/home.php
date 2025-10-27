<?php
// home.php
session_start();

// Add headers to prevent caching
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Auto-switch between Docker and XAMPP
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
    if (empty($dbPath)) {
        return '';
    }
    $filename = basename($dbPath);
    return '/admin/uploads/product_images/' . $filename;
}

// ===================== FETCH PRODUCTS ===================== //
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
        pm_p.ImagePath AS previewImage
    FROM 
        Products p
    LEFT JOIN ProductMedia pm_v 
        ON pm_v.VariantProductID = p.ProductID 
        AND pm_v.MediaType = 'VARIANT'
    LEFT JOIN ProductMedia pm_p 
        ON pm_p.ParentProductID = COALESCE(p.ParentProductID, p.ProductID)
        AND pm_p.MediaType = 'PREVIEW'
    WHERE 
        p.Status IN ('Available', 'Low Stock') 
    ORDER BY 
        p.CreatedAt DESC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Database query failed: ' . $conn->error]));
}

$groupedProducts = [];

// In your PHP, add debug logging for the variant field
while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];

    // Debug the variant field
    error_log("DB ROW - ID: {$row['id']}, Name: {$row['name']}, Variant Field: " . ($row['variant'] ?? 'NULL') . ', HexCode: ' . ($row['hexCode'] ?? 'NULL'));

    $isParent = ($parentID === null || $parentID === $row['id']);

    $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
    $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');

    if ($isParent) {
        // Parent product
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
        }
    } else {
        // Variant product
        $parentKey = $parentID;

        if (!isset($groupedProducts[$parentKey])) {
            $groupedProducts[$parentKey] = [
                'id' => $parentKey,
                'name' => $row['name'],  // This might be wrong - we should get the actual parent name
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['status'],
                'stockQuantity' => intval($row['stockQuantity']),
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
        }

        // FIX: Make sure we're using the actual variant name and hex code
        $groupedProducts[$parentKey]['variants'][] = [
            'id' => $row['id'],
            'name' => $row['variant'] ?? $row['name'],  // This should be "Rose", "Bear", etc.
            'variant' => $row['variant'] ?? $row['name'],  // Add this field too
            'price' => floatval($row['price']),
            'image' => $row['variantImage'],
            'hexCode' => $row['hexCode'] ?? '#CCCCCC',
            'status' => $row['status'],
            'stockQuantity' => intval($row['stockQuantity']),
        ];

        error_log("Added variant: {$row['variant']} with hex: {$row['hexCode']} to parent: {$parentKey}");
    }
}
// ===================== SAFETY CHECK ===================== //
// Ensure all products have status and stockQuantity
foreach ($groupedProducts as &$product) {
    if (!isset($product['status'])) {
        $product['status'] = 'Available';  // Default status
    }
    if (!isset($product['stockQuantity'])) {
        $product['stockQuantity'] = 0;  // Default stock
    }
}
// Add this debug output in your PHP before the final output
$debugInfo = [];
foreach ($groupedProducts as $product) {
    if (!empty($product['variants'])) {
        $debugInfo[$product['id']] = [
            'product_name' => $product['name'],
            'variants' => array_map(function ($v) {
                return [
                    'variant_name' => $v['name'],
                    'hexCode' => $v['hexCode'],
                    'has_hex' => !empty($v['hexCode'])
                ];
            }, $product['variants'])
        ];
    }
}

error_log('DEBUG - Variant Hex Codes: ' . json_encode($debugInfo));

// Also add this to your response for frontend debugging
$response['debug_hex_codes'] = $debugInfo;

// ===================== OUTPUT ===================== //
$response = [
    'success' => true,
    'products' => array_values($groupedProducts),
    'debug' => [
        'sample_product' => !empty($groupedProducts) ? reset($groupedProducts) : null,
        'total_products' => count($groupedProducts),
        'fields_check' => !empty($groupedProducts) ? array_keys(reset($groupedProducts)) : []
    ]
];
error_log('=== DEBUG SAMPLE PRODUCT ===');
error_log(print_r(reset($groupedProducts), true));

echo json_encode($response);
$conn->close();
?>