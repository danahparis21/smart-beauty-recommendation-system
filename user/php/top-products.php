<?php
// top-products.php - WITH GROUPING LIKE HOME.PHP + TOP PRODUCTS FILTERING
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

// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? null;

// ===================== TOP PRODUCTS SQL ===================== //
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
        p.ProductRating AS product_rating,
        p.ExpirationDate AS expiration_date,
        pm_v.ImagePath AS variantImage,
        pm_p.ImagePath AS previewImage,
        parent.Name AS parentName,
        parent.Status AS parentStatus,
        parent.ProductRating AS parent_rating,
        (SELECT COUNT(*) FROM orderitems oi WHERE oi.product_id = p.ProductID) AS order_count,
        (SELECT COUNT(*) FROM ratings r WHERE r.product_id = p.ProductID) AS review_count,
        " . ($user_id ? "IF(f.favorite_id IS NOT NULL, 1, 0) AS liked" : "0 AS liked") . "
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
    " . ($user_id ? "LEFT JOIN favorites f 
        ON (f.product_id = p.ProductID OR f.product_id = COALESCE(p.ParentProductID, p.ProductID))
        AND f.user_id = ?" : "") . "
    WHERE 
        p.Status IN ('Available', 'Low Stock')
        AND p.Stocks > 0
        AND (p.ExpirationDate IS NULL OR p.ExpirationDate > CURDATE())
    ORDER BY 
        p.CreatedAt DESC
";

// Update to use prepared statement if user is logged in
if ($user_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
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
$cleanName = function($name) {
    if (empty($name)) return '';
    return trim(str_ireplace(['Parent Record:', 'Product Record:', ':'], '', $name));
};

// Store all variants data for calculating parent ratings and popularity
$allVariantsData = [];
while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];
    $isParent = ($parentID === null || $parentID === $row['id']);

    // Convert image paths
    $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
    $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');
    $row['name'] = $cleanName($row['name']);
    $row['parentName'] = $cleanName($row['parentName'] ?? '');
    
    // Store liked status
    $isLiked = (bool)($row['liked'] ?? false);
    
    // Store variant data for parent calculations
    $variantData = [
        'product_rating' => floatval($row['product_rating']),
        'order_count' => intval($row['order_count']),
        'review_count' => intval($row['review_count']),
        'variant_id' => $row['id']
    ];
    
    // ========== FIXED GROUPING LOGIC ==========
    if ($isParent) {
        // Use the cleaned name as the key to detect duplicates
        $cleanedName = $cleanName($row['name']);
        $productKey = $cleanedName . '_' . strtolower($row['category']);
        
        // Check if we already have this product by name + category
        $existingKey = null;
        foreach ($groupedProducts as $key => $existingProduct) {
            if ($existingProduct['name'] === $cleanedName && 
                $existingProduct['category'] === strtolower($row['category'])) {
                $existingKey = $key;
                break;
            }
        }
        
        if ($existingKey !== null) {
            // Merge with existing product
            $groupedProducts[$existingKey]['liked'] = $groupedProducts[$existingKey]['liked'] || $isLiked;
            $allVariantsData[$existingKey][] = $variantData;
        } else {
            // New parent product
            $groupedProducts[$row['id']] = [
                'id' => $row['id'],
                'name' => $cleanedName,
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['status'],
                'stockQuantity' => intval($row['stockQuantity']),
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'liked' => $isLiked,
                'average_rating' => floatval($row['product_rating']),
                'total_orders' => intval($row['order_count']),
                'total_reviews' => intval($row['review_count']),
                'variants' => []
            ];
            $allVariantsData[$row['id']] = [$variantData];
        }
    } else {
        // Variant product - use parent ID as key
        $parentKey = $parentID;
        $parentName = $row['parentName'] ?? $row['name'];
        $cleanedParentName = $cleanName($parentName);

        if (!isset($groupedProducts[$parentKey])) {
            // Check if we already have this product by name + category
            $existingKey = null;
            foreach ($groupedProducts as $key => $existingProduct) {
                if ($existingProduct['name'] === $cleanedParentName && 
                    $existingProduct['category'] === strtolower($row['category'])) {
                    $existingKey = $key;
                    break;
                }
            }
            
            if ($existingKey !== null) {
                // Use existing product key
                $parentKey = $existingKey;
                $groupedProducts[$parentKey]['liked'] = $groupedProducts[$parentKey]['liked'] || $isLiked;
                $allVariantsData[$parentKey][] = $variantData;
            } else {
                // Create new parent from variant
                $groupedProducts[$parentKey] = [
                    'id' => $parentKey,
                    'name' => $cleanedParentName,
                    'category' => strtolower($row['category']),
                    'price' => floatval($row['price']),
                    'status' => $row['parentStatus'] ?? 'Available',
                    'stockQuantity' => 0,
                    'image' => $row['previewImage'],
                    'previewImage' => $row['previewImage'],
                    'liked' => $isLiked,
                    'average_rating' => floatval($row['product_rating']),
                    'total_orders' => intval($row['order_count']),
                    'total_reviews' => intval($row['review_count']),
                    'variants' => []
                ];
                $allVariantsData[$parentKey] = [$variantData];
            }
        } else {
            // Update existing parent
            $groupedProducts[$parentKey]['liked'] = $groupedProducts[$parentKey]['liked'] || $isLiked;
            $allVariantsData[$parentKey][] = $variantData;
        }

        // Add variant to the correct parent
        $groupedProducts[$parentKey]['variants'][] = [
            'id' => $row['id'],
            'name' => $row['variant'] ?? $row['name'],
            'variant' => $row['variant'] ?? $row['name'],
            'price' => floatval($row['price']),
            'image' => $row['variantImage'],
            'hexCode' => $row['hexCode'] ?? '#CCCCCC',
            'status' => $row['status'],
            'stockQuantity' => intval($row['stockQuantity']),
            'product_rating' => floatval($row['product_rating']),
        ];
    }
}

// Calculate parent statistics based on all variants
foreach ($groupedProducts as $parentId => &$product) {
    if (isset($allVariantsData[$parentId])) {
        $variantsData = $allVariantsData[$parentId];
        
        // Calculate average rating from all variants
        $totalRating = 0;
        $ratedVariants = 0;
        $totalOrders = 0;
        $totalReviews = 0;
        
        foreach ($variantsData as $variant) {
            if ($variant['product_rating'] > 0) {
                $totalRating += $variant['product_rating'];
                $ratedVariants++;
            }
            $totalOrders += $variant['order_count'];
            $totalReviews += $variant['review_count'];
        }
        
        // Update parent with calculated values
        $product['average_rating'] = $ratedVariants > 0 ? $totalRating / $ratedVariants : 0;
        $product['total_orders'] = $totalOrders;
        $product['total_reviews'] = $totalReviews;
        $product['popularity_score'] = ($product['average_rating'] * 0.4) + ($totalOrders * 0.4) + ($totalReviews * 0.2);
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
    if (!isset($product['total_orders']))
        $product['total_orders'] = 0;
    if (!isset($product['total_reviews']))
        $product['total_reviews'] = 0;
    if (!isset($product['popularity_score']))
        $product['popularity_score'] = 0;

    $finalProducts[] = $product;
}

// Final cleanup: Remove parents with no variants
$finalProducts = array_filter($finalProducts, function ($product) {
    return !empty($product['variants']) || $product['stockQuantity'] > 0;
});

$uniqueProducts = [];
$seenProducts = [];

foreach ($finalProducts as $product) {
    $productKey = $product['name'] . '_' . $product['category'];
    
    if (!isset($seenProducts[$productKey])) {
        $seenProducts[$productKey] = true;
        $uniqueProducts[] = $product;
    } else {
        error_log("Removed duplicate: " . $productKey);
    }
}

$finalProducts = $uniqueProducts;

// FILTER FOR TOP PRODUCTS: High ratings OR high orders
$topProducts = array_filter($finalProducts, function ($product) {
    $rating = $product['average_rating'];
    $orders = $product['total_orders'];
    $reviews = $product['total_reviews'];
    
    // Include products that meet at least one criteria:
    return $rating >= 4.0 ||           // High ratings (4.0+ stars)
           $orders >= 3 ||              // Popular (3+ orders)
           $reviews >= 2 ||             // Well-reviewed (2+ reviews)
           $rating >= 3.5;              // Good ratings (3.5+ stars)
});

// Sort top products: Highest ratings first, then highest orders
usort($topProducts, function ($a, $b) {
    // First by rating (descending)
    if ($b['average_rating'] != $a['average_rating']) {
        return $b['average_rating'] <=> $a['average_rating'];
    }
    // Then by orders (descending)
    if ($b['total_orders'] != $a['total_orders']) {
        return $b['total_orders'] <=> $a['total_orders'];
    }
    // Then by reviews (descending)
    return $b['total_reviews'] <=> $a['total_reviews'];
});

// Limit to top 20 products
$topProducts = array_slice($topProducts, 0, 20);

// ===================== OUTPUT ===================== //
$response = [
    'success' => true,
    'products' => array_values($topProducts),
    'debug' => [
        'total_products' => count($topProducts),
        'filter_criteria' => 'Rating >= 4.0 OR Orders >= 3 OR Reviews >= 2 OR Rating >= 3.5',
        'sorted_by' => 'Rating DESC, Orders DESC, Reviews DESC'
    ]
];

echo json_encode($response);
$conn->close();
?>