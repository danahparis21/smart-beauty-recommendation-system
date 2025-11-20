<?php
// get_product_details.php
// Start output buffering to prevent any accidental output
ob_start();
header('Content-Type: application/json');

function sendError($message, $code = 500)
{
    http_response_code($code);
    // Clean any output buffer before sending error
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Helper function for profile photos
function getPublicProfilePhotoPath($dbPath)
{
    if (empty($dbPath)) {
        return '';
    }
    $filename = basename($dbPath);
    return '/uploads/profiles/' . $filename;
}

// Product image helper
function getPublicImagePath($dbPath)
{
    if (empty($dbPath)) {
        return '';
    }
    $filename = basename($dbPath);
    return '/admin/uploads/product_images/' . $filename;
}

try {
    // Database connection
    if (getenv('DOCKER_ENV') === 'true') {
        require_once __DIR__ . '/../../config/db_docker.php';
    } else {
        require_once __DIR__ . '/../../config/db.php';
    }

    if ($conn->connect_error) {
        sendError('Connection failed: ' . $conn->connect_error);
    }

    $productId = $_GET['productId'] ?? '';
    if (empty($productId)) {
        sendError('Product ID is required', 400);
    }

    // First, determine if this is a parent or variant product
    $productTypeSql = '
        SELECT 
            ProductID,
            ParentProductID,
            Name,
            Price,
            Description,
            Ingredients,
            Stocks,
            HexCode,
            Status
        FROM Products 
        WHERE ProductID = ?
    ';

    $typeStmt = $conn->prepare($productTypeSql);
    if (!$typeStmt) {
        sendError('Failed to prepare product type query: ' . $conn->error);
    }

    $typeStmt->bind_param('s', $productId);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    $productType = $typeResult->fetch_assoc();

    if (!$productType) {
        sendError('Product not found', 404);
    }

    // Determine the parent product ID
    $parentProductId = $productType['ParentProductID'] ?: $productType['ProductID'];

    // Check if there are ANY available variants for this parent
    $availableVariantsSql = "
        SELECT COUNT(*) as available_count 
        FROM Products 
        WHERE ParentProductID = ? 
            AND Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
            AND Stocks > 0
            AND (ExpirationDate IS NULL OR ExpirationDate > CURDATE())
    ";
    
    $availableStmt = $conn->prepare($availableVariantsSql);
    $availableStmt->bind_param('s', $parentProductId);
    $availableStmt->execute();
    $availableResult = $availableStmt->get_result();
    $availableData = $availableResult->fetch_assoc();
    $availableStmt->close();

    // If no variants are available and this is a parent product, return 404
    if ($availableData['available_count'] == 0 && $productType['ParentProductID'] === null) {
        sendError('No available variants for this product', 404);
    }

    // If it's a variant, check if it's available
    if ($productType['ParentProductID'] !== null) {
        if (in_array($productType['Status'], ['No Stock', 'Expired', 'Deleted', 'Disabled']) || $productType['Stocks'] <= 0) {
            sendError('Product variant is not available', 404);
        }
    }

    // Fetch parent product basic info and all related variants - WITH STATUS FILTERING
    $sql = "
        SELECT 
            p.ProductID,
            p.Name,
            p.Category,
            p.ParentProductID,
            p.ShadeOrVariant,
            p.Price,
            p.Description,
            p.Ingredients,
            p.HexCode,
            p.ProductRating,
            p.Status,
            p.Stocks,
            p.ExpirationDate,
            
            -- Product attributes for variants
            pa.SkinType,
            pa.SkinTone,
            pa.Undertone,
            pa.Acne,
            pa.Dryness,
            pa.DarkSpots,
            pa.Matte,
            pa.Dewy,
            pa.LongLasting,
            
            -- Product images
            pm_preview.ImagePath as preview_image,
            pm_gallery.ImagePath as gallery_image,
            pm_variant.ImagePath as variant_image
            
        FROM Products p
        
        -- Get preview image (for parent)
        LEFT JOIN ProductMedia pm_preview 
            ON pm_preview.ParentProductID = p.ProductID 
            AND pm_preview.MediaType = 'PREVIEW'
        
        -- Get gallery images
        LEFT JOIN ProductMedia pm_gallery 
            ON pm_gallery.ParentProductID = p.ProductID 
            AND pm_gallery.MediaType = 'GALLERY'
        
        -- Get variant images
        LEFT JOIN ProductMedia pm_variant 
            ON pm_variant.VariantProductID = p.ProductID 
            AND pm_variant.MediaType = 'VARIANT'
        
        -- Get product attributes (for variants)
        LEFT JOIN ProductAttributes pa 
            ON pa.ProductID = p.ProductID
        
        WHERE (p.ProductID = ? OR p.ParentProductID = ?)
            AND p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
            AND p.Stocks > 0
            AND (p.ExpirationDate IS NULL OR p.ExpirationDate > CURDATE())
        ORDER BY 
            CASE WHEN p.ParentProductID IS NULL THEN 0 ELSE 1 END, 
            p.ProductID
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendError('Failed to prepare main query: ' . $conn->error);
    }

    $stmt->bind_param('ss', $parentProductId, $parentProductId);
    $stmt->execute();
    $result = $stmt->get_result();

    $parentProduct = null;
    $variants = [];
    $galleryImages = [];
    $requestedProductDetails = null;

   // In the main processing loop, update the parent product name cleaning:
while ($row = $result->fetch_assoc()) {
    // Store the full data for the requested product ID
    if ($row['ProductID'] === $productId) {
        $requestedProductDetails = $row;
    }
    // APPLY IMAGE PATH CONVERSION HERE
    $row['preview_image'] = getPublicImagePath($row['preview_image']);
    $row['gallery_image'] = getPublicImagePath($row['gallery_image']);
    $row['variant_image'] = getPublicImagePath($row['variant_image']);

    // Clean product name - remove "Parent Record:" and "Product Record:"
    $cleanName = function($name) {
        if (empty($name)) return '';
        return trim(str_ireplace(['Parent Record:', 'Product Record:', ':'], '', $name));
    };

    if ($row['ParentProductID'] === null) {
        // This is the parent product
        $parentProduct = [
            'id' => $row['ProductID'],
            'name' => $cleanName($row['Name'] ?? 'Product Name'),
            'category' => $row['Category'] ?? '',
            'previewImage' => $row['preview_image'] ?? '',
            'galleryImages' => []
        ];

        // Add gallery images
        if (!empty($row['gallery_image'])) {
            $parentProduct['galleryImages'][] = $row['gallery_image'];
        }
    } else {
        // This is a variant - use parent name + shade variant
        $variantData = [
            'id' => $row['ProductID'],
            'name' => $row['ShadeOrVariant'] ?: $row['Name'],
            'price' => floatval($row['Price'] ?? 0),
            'description' => $row['Description'] ?? '',
            'ingredients' => $row['Ingredients'] ?? '',
            'hexCode' => !empty($row['HexCode']) ? $row['HexCode'] : '#CCCCCC',
            'image' => $row['variant_image'] ?? '',
            'stock' => intval($row['Stocks'] ?? 0),
            'attributes' => []
        ];

        // Add attributes if they exist
        if ($row['SkinType']) {
            $variantData['attributes'] = [
                'SkinType' => $row['SkinType'],
                'SkinTone' => $row['SkinTone'],
                'Undertone' => $row['Undertone'],
                'Acne' => $row['Acne'],
                'Dryness' => $row['Dryness'],
                'DarkSpots' => $row['DarkSpots'],
                'Matte' => $row['Matte'],
                'Dewy' => $row['Dewy'],
                'LongLasting' => $row['LongLasting']
            ];
        }

        $variants[] = $variantData;

        // Add variant's gallery image if exists
        if (!empty($row['gallery_image']) && !in_array($row['gallery_image'], $galleryImages)) {
            $galleryImages[] = $row['gallery_image'];
        }
    }
}

// If no parent found but we have variants, fetch the actual parent product
if (!$parentProduct && !empty($variants)) {
    // Fetch the actual parent product details
    $parentSql = "
        SELECT Name, Category 
        FROM Products 
        WHERE ProductID = ?
    ";
    $parentStmt = $conn->prepare($parentSql);
    $parentStmt->bind_param('s', $parentProductId);
    $parentStmt->execute();
    $parentResult = $parentStmt->get_result();
    $actualParent = $parentResult->fetch_assoc();
    $parentStmt->close();
    
    if ($actualParent) {
        $parentProduct = [
            'id' => $parentProductId,
            'name' => $cleanName($actualParent['Name'] ?? 'Product Name'),
            'category' => $actualParent['Category'] ?? '',
            'previewImage' => '',
            'galleryImages' => []
        ];
    } else {
        // Fallback: use first variant name if parent really doesn't exist
        $firstVariant = $variants[0];
        $parentProduct = [
            'id' => $parentProductId,
            'name' => $cleanName($firstVariant['name']),
            'category' => '',
            'previewImage' => '',
            'galleryImages' => []
        ];
    }
}

    // Combine all gallery images
    if ($parentProduct) {
        $parentProduct['galleryImages'] = array_merge($parentProduct['galleryImages'] ?? [], $galleryImages);
        $parentProduct['galleryImages'] = array_unique($parentProduct['galleryImages']);
    }

    // Find the currently selected variant
    $selectedVariant = null;

    // 1. Check if the requested ID is a variant
    foreach ($variants as $variant) {
        if ($variant['id'] === $productId) {
            $selectedVariant = $variant;
            break;
        }
    }

    // 2. If no variant was found AND we clicked on a parent product, use the first AVAILABLE variant
    if (!$selectedVariant && $parentProduct && $parentProduct['id'] === $productId && !empty($variants)) {
        // Find first available variant (stock > 0)
        foreach ($variants as $variant) {
            if ($variant['stock'] > 0) {
                $selectedVariant = $variant;
                break;
            }
        }
        // If no available variants found, still use the first one but show out of stock message
        if (!$selectedVariant) {
            $selectedVariant = $variants[0];
            $selectedVariant['is_out_of_stock'] = true;
        }
    }

    // 3. If still no variant selected, fallback to parent as variant
    if (!$selectedVariant && $parentProduct) {
        $selectedVariant = [
            'id' => $parentProduct['id'],
            'name' => $parentProduct['name'],
            'price' => 0,
            'description' => $parentProduct['description'] ?? '',
            'ingredients' => '',
            'hexCode' => '#CCCCCC',
            'image' => $parentProduct['previewImage'] ?? '',
            'stock' => 0,
            'attributes' => [],
            'is_out_of_stock' => true
        ];
    }

    // 4. Check if the selected variant is actually available
    if ($selectedVariant && $selectedVariant['stock'] <= 0 && !isset($selectedVariant['is_out_of_stock'])) {
        $selectedVariant['is_out_of_stock'] = true;
    }

    // Fetch reviews/feedback for ALL variants of this parent product from ratings table
    $reviews = [];

    try {
        // Get all product IDs that belong to this parent (including the parent itself) - WITH STATUS FILTER
        $productIdsSql = "
            SELECT ProductID 
            FROM products 
            WHERE (ParentProductID = ? OR ProductID = ?)
                AND Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
                AND Stocks > 0
                AND (ExpirationDate IS NULL OR ExpirationDate > CURDATE())
        ";
        $productIdsStmt = $conn->prepare($productIdsSql);
        $productIdsStmt->bind_param('ss', $parentProductId, $parentProductId);
        $productIdsStmt->execute();
        $productIdsResult = $productIdsStmt->get_result();
        
        $allProductIds = [];
        while ($row = $productIdsResult->fetch_assoc()) {
            $allProductIds[] = $row['ProductID'];
        }
        $productIdsStmt->close();
        
        if (!empty($allProductIds)) {
            // Create placeholders for the IN clause
            $placeholders = str_repeat('?,', count($allProductIds) - 1) . '?';
            
            $reviewsSql = "
                SELECT
                    r.product_id,
                    r.user_id,
                    r.stars as Rating,
                    r.review as ReviewText,
                    r.created_at as CreatedDate,
                    r.media_url,
                    u.username as UserName,
                    u.profile_photo as UserProfilePhoto,  
                    p.Name as variant_name,
                    p.ShadeOrVariant,
                    p.HexCode
                FROM ratings r
                LEFT JOIN users u ON r.user_id = u.UserID
                LEFT JOIN products p ON r.product_id = p.ProductID
                WHERE r.product_id IN ($placeholders)
                ORDER BY r.created_at DESC
                LIMIT 50
            ";
            
            $reviewStmt = $conn->prepare($reviewsSql);
            if ($reviewStmt) {
                // Bind parameters
                $types = str_repeat('s', count($allProductIds));
                $reviewStmt->bind_param($types, ...$allProductIds);
                
                if ($reviewStmt->execute()) {
                    $reviewResult = $reviewStmt->get_result();
                    
                    while ($review = $reviewResult->fetch_assoc()) {
                        $reviews[] = [
                            'user_name' => $review['UserName'] ?? 'Anonymous',
                            'rating' => intval($review['Rating']),
                            'review_text' => $review['ReviewText'] ?? '',
                            'review_date' => $review['CreatedDate'],
                            'image_url' => $review['media_url'] ?? '',
                            'profile_photo' => getPublicProfilePhotoPath($review['UserProfilePhoto'] ?? ''),
                            'variant_name' => $review['variant_name'] ?? '',
                            'shade_variant' => $review['ShadeOrVariant'] ?? '',
                            'hex_code' => $review['HexCode'] ?? '#CCCCCC',
                            'product_id' => $review['product_id'] ?? ''
                        ];
                    }
                }
                $reviewStmt->close();
            }
        }
    } catch (Exception $e) {
        // Silently fail - reviews are optional
    }

    $response = [
        'success' => true,
        'parentProduct' => $parentProduct,
        'variants' => $variants,
        'selectedVariant' => $selectedVariant,
        'reviews' => $reviews
    ];

    echo json_encode($response);
} catch (Exception $e) {
    sendError('Unexpected error: ' . $e->getMessage());
}
?>