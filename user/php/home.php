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

// ===================== SIMPLE GROUPING ===================== //
while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];

    // Parent products have NULL parentID (or empty string/0 depending on DB schema)
    // Use COALESCE(p.ParentProductID, p.ProductID) in query for reliable grouping
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
                'price' => floatval($row['price']), // Use parent's price as initial price
                
                // --- FIX: Ensure status and stockQuantity are set for parent record initialization ---
                'status' => $row['status'], 
                'stockQuantity' => intval($row['stockQuantity']), 
                // --------------------------------------------------------------------------------------
                
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
        } else {
            // If the parent was already initialized by a variant, ensure the parent's data (like its name/image) is dominant.
            // This is more complex but is why the single-pass loop is difficult.
            // For now, we rely on the first entry to define the structure.
        }
    } else {
        // Variant product
        $parentKey = $parentID;

        if (!isset($groupedProducts[$parentKey])) {
            // Create parent from variant (This is the parent's initial data)
            $groupedProducts[$parentKey] = [
                'id' => $parentKey,
                'name' => $row['name'], // Use variant name as placeholder
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']), // Use variant price as initial price
                
                // --- Set status and stockQuantity when initializing parent from variant ---
                'status' => $row['status'], 
                'stockQuantity' => intval($row['stockQuantity']), 
                // -------------------------------------------------------------------------
                
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
        } else {
            // Parent container already exists, update aggregated data
            
            // Price Aggregation (Simplified: Find the first > 0 price)
            $variantPrice = floatval($row['price']);
            if ($variantPrice > 0 && $groupedProducts[$parentKey]['price'] == 0) {
                $groupedProducts[$parentKey]['price'] = $variantPrice;
            }

            // Status Aggregation (If any variant is 'Low Stock', parent is 'Low Stock')
            if ($row['status'] === 'Low Stock') {
                $groupedProducts[$parentKey]['status'] = 'Low Stock';
            }

            // Stock Aggregation (Sum or minimum, depending on your business logic. Sum is usually safer.)
            // NOTE: If you are relying on the main parent record for stock, this variant logic is wrong.
            // Assuming you want the LOWEST stock among variants for 'stockQuantity' display:
            $currentVariantStock = intval($row['stockQuantity']);
            if (isset($groupedProducts[$parentKey]['stockQuantity'])) {
                $groupedProducts[$parentKey]['stockQuantity'] = min($currentVariantStock, $groupedProducts[$parentKey]['stockQuantity']);
            } else {
                 $groupedProducts[$parentKey]['stockQuantity'] = $currentVariantStock;
            }
        }

        // Add variant
        $groupedProducts[$parentKey]['variants'][] = [
            'id' => $row['id'],
            'name' => $row['variant'] ?? $row['name'],
            'price' => floatval($row['price']),
            'image' => $row['variantImage'],
            'hexCode' => $row['hexCode'] ?? '#CCCCCC',
            // Also add status/stock to the variant if needed for detailed view
            'status' => $row['status'], 
            'stockQuantity' => intval($row['stockQuantity']), 
        ];
    }
}
    
// ===================== SAFETY CHECK ===================== //
// Ensure all products have status and stockQuantity
foreach ($groupedProducts as &$product) {
    if (!isset($product['status'])) {
        $product['status'] = 'Available'; // Default status
    }
    if (!isset($product['stockQuantity'])) {
        $product['stockQuantity'] = 0; // Default stock
    }
}

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
error_log("=== DEBUG SAMPLE PRODUCT ===");
error_log(print_r(reset($groupedProducts), true));


echo json_encode($response);
$conn->close();
?>