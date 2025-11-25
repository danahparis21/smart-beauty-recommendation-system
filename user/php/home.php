<?php
// home.php - WITH STRICT VARIANT-LEVEL FILTERING + FAVORITE STATUS + SIMPLE RATINGS
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
    
    // Handle old paths with '../'
    if (strpos($dbPath, '../') === 0) {
        // Convert ../uploads/... to /admin/uploads/...
        return str_replace('../', '/admin/', $dbPath);
    }
    
    // Handle new paths that already start with '/'
    if (strpos($dbPath, '/') === 0) {
        return $dbPath;
    }
    
    // Fallback: add leading slash
    return '/' . $dbPath;
}
// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? null;

// ===================== STRICT FILTERING SQL ===================== //
$sql = '
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
        p.ProductRating AS product_rating,
        p.ExpirationDate AS expiration_date,
        pm_v.ImagePath AS variantImage,
        pm_p.ImagePath AS previewImage,
        parent.Name AS parentName,
        parent.Status AS parentStatus,
        parent.ProductRating AS parent_rating,
        ' . ($user_id ? 'IF(f.favorite_id IS NOT NULL, 1, 0) AS liked' : '0 AS liked') . "
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
    " . ($user_id ? 'LEFT JOIN favorites f 
        ON (f.product_id = p.ProductID OR f.product_id = COALESCE(p.ParentProductID, p.ProductID))
        AND f.user_id = ?' : '') . "
    WHERE 
        p.Status IN ('Available', 'Low Stock')  -- CHANGED THIS LINE
        AND p.Stocks > 0
        AND (p.ExpirationDate IS NULL OR p.ExpirationDate > CURDATE())
    ORDER BY 
        p.CreatedAt DESC
";

// Update to use prepared statement if user is logged in
if ($user_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Database query failed: ' . $conn->error]));
}

$groupedProducts = [];
$processedParentIds = [];

error_log('Total rows from SQL (after filtering): ' . $result->num_rows);

// Clean product name function
$cleanName = function ($name) {
    if (empty($name))
        return '';
    return trim(str_ireplace(['Parent Record:', 'Product Record:', ':'], '', $name));
};

while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];
    $isParent = ($parentID === null || $parentID === $row['id']);

    // Convert image paths
    $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
    $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');
    $row['name'] = $cleanName($row['name']);
    $row['parentName'] = $cleanName($row['parentName'] ?? '');

    // Store liked status
    $isLiked = (bool) ($row['liked'] ?? false);

    // Ratings data - use the highest rating among all variants
    $variantRating = floatval($row['product_rating']);
    $parentRating = floatval($row['parent_rating']);

    error_log("Processing: ID={$row['id']}, Name={$row['name']}, Variant Rating={$variantRating}, Parent Rating={$parentRating}, Liked={$isLiked}");

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
                'liked' => $isLiked,
                'average_rating' => $parentRating,
                'variants' => []
            ];
            $processedParentIds[] = $row['id'];
            error_log("Added parent: {$row['id']} - {$row['name']} - Rating: {$parentRating}");
        }
    } else {
        // Variant product - use the actual parent name
        $parentKey = $parentID;
        $parentName = $row['parentName'] ?? $row['name'];

        // Only create parent if it has at least one available variant
        if (!isset($groupedProducts[$parentKey])) {
            // Fetch the actual parent product details for the name
            $parentSql = 'SELECT Name, Category FROM Products WHERE ProductID = ?';
            $parentStmt = $conn->prepare($parentSql);
            $parentStmt->bind_param('s', $parentKey);
            $parentStmt->execute();
            $parentResult = $parentStmt->get_result();
            $actualParent = $parentResult->fetch_assoc();
            $parentStmt->close();

            $actualParentName = $actualParent ? $cleanName($actualParent['Name']) : $parentName;

            $groupedProducts[$parentKey] = [
                'id' => $parentKey,
                'name' => $actualParentName,
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['parentStatus'] ?? 'Available',
                'stockQuantity' => 0,
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'liked' => $isLiked,
                'average_rating' => $variantRating,
                'variants' => []
            ];
            $processedParentIds[] = $parentKey;
            error_log("Created parent from variant: {$parentKey} - {$actualParentName} - Rating: {$variantRating}");
        } else {
            // Update liked status if any variant is liked
            if ($isLiked) {
                $groupedProducts[$parentKey]['liked'] = true;
            }
            // Update to the highest rating among variants
            if ($variantRating > $groupedProducts[$parentKey]['average_rating']) {
                $groupedProducts[$parentKey]['average_rating'] = $variantRating;
                error_log("Updated parent {$parentKey} rating to: {$variantRating}");
            }
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
            'product_rating' => $variantRating,
        ];

        error_log("Added variant: {$row['variant']} - Rating: {$variantRating} - Status: {$row['status']} - Stock: {$row['stockQuantity']}");
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
    if (!isset($product['liked']))
        $product['liked'] = false;
    if (!isset($product['average_rating']))
        $product['average_rating'] = 0;

    $finalProducts[] = $product;
}

// Final cleanup: Remove parents with no variants (if any slipped through)
$finalProducts = array_filter($finalProducts, function ($product) {
    return !empty($product['variants']) || $product['stockQuantity'] > 0;
});

// Additional check: Remove parent products that have no available variants
$finalProducts = array_filter($finalProducts, function ($product) {
    if (empty($product['variants'])) {
        return false;  // Remove products with no variants
    }

    // Check if at least one variant is actually available (including Low Stock)
    foreach ($product['variants'] as $variant) {
        if ($variant['stockQuantity'] > 0 && in_array($variant['status'], ['Available', 'Low Stock'])) {
            return true;  // Keep product if at least one variant is available
        }
    }

    error_log("Removing product {$product['id']} - no available variants found");
    return false;  // Remove product if no variants are available
});

// Debug final output
error_log('Final products count after filtering: ' . count($finalProducts));
foreach ($finalProducts as $product) {
    $availableVariants = array_filter($product['variants'], function ($v) {
        return $v['stockQuantity'] > 0 && $v['status'] === 'Available';
    });
    error_log("Final product: {$product['id']} - {$product['name']} - Available Variants: " . count($availableVariants) . " - Rating: {$product['average_rating']}");
}

// ===================== OUTPUT ===================== //
$response = [
    'success' => true,
    'products' => array_values($finalProducts),  // Reindex array
    'debug' => [
        'total_products' => count($finalProducts),
        'product_ids' => array_column($finalProducts, 'id'),
        'filter_info' => 'Excluded: No Stock, Expired, Deleted, Disabled, Zero Stock, No Available Variants',
        'user_id' => $user_id
    ]
];

echo json_encode($response);
$conn->close();
?>