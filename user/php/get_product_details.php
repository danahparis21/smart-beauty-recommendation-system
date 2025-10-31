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

// Helper function to convert internal database path to public URL path
function getPublicImagePath($dbPath)
{
    if (empty($dbPath)) {
        return '';
    }
    $filename = basename($dbPath);

    // Return absolute path from root - adjust this based on your file structure
    return '../../admin/uploads/product_images/' . $filename;
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
            HexCode
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

    // Fetch parent product basic info and all related variants
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
        
        WHERE p.ProductID = ? OR p.ParentProductID = ?
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

    while ($row = $result->fetch_assoc()) {
        // Store the full data for the requested product ID
        if ($row['ProductID'] === $productId) {
            $requestedProductDetails = $row;
        }
        // APPLY IMAGE PATH CONVERSION HERE
        $row['preview_image'] = getPublicImagePath($row['preview_image']);
        $row['gallery_image'] = getPublicImagePath($row['gallery_image']);
        $row['variant_image'] = getPublicImagePath($row['variant_image']);

        if ($row['ParentProductID'] === null) {
            // This is the parent product
            $parentProduct = [
                'id' => $row['ProductID'],
                'name' => $row['Name'] ?? 'Product Name',
                'category' => $row['Category'] ?? '',
                'previewImage' => $row['preview_image'] ?? '',
                'galleryImages' => []
            ];

            // Add gallery images
            if (!empty($row['gallery_image'])) {
                $parentProduct['galleryImages'][] = $row['gallery_image'];
            }
        } else {
            // This is a variant
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

    // If no parent found but we have variants, create parent from first variant
    if (!$parentProduct && !empty($variants)) {
        $firstVariant = $variants[0];
        $parentProduct = [
            'id' => $parentProductId,
            'name' => $firstVariant['name'],
            'category' => '',
            'previewImage' => '',
            'galleryImages' => []
        ];
    }

    // Combine all gallery images
    if ($parentProduct) {
        $parentProduct['galleryImages'] = array_merge($parentProduct['galleryImages'] ?? [], $galleryImages);
        $parentProduct['galleryImages'] = array_unique($parentProduct['galleryImages']);
    }

    // In your PHP, replace the selectedVariant logic with this:

    // Find the currently selected variant
    $selectedVariant = null;

    // 1. Check if the requested ID is a variant
    foreach ($variants as $variant) {
        if ($variant['id'] === $productId) {
            $selectedVariant = $variant;
            break;
        }
    }

    // 2. If no variant was found AND we clicked on a parent product, use the first variant
    if (!$selectedVariant && $parentProduct && $parentProduct['id'] === $productId && !empty($variants)) {
        $selectedVariant = $variants[0];  // Use the first variant
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
            'attributes' => []
        ];
    }

    // Fetch reviews/feedback - simplified to avoid errors
    $reviews = [];

    /*
     * $reviewsSql = '
     *     SELECT
     *         pf.UserID,
     *         pf.UserRating as Rating,
     *         pf.Comment as ReviewText,
     *         pf.CreatedAt as CreatedDate,
     *         u.username as UserName
     *     FROM productfeedback pf
     *     LEFT JOIN users u ON pf.UserID = u.user_id
     *     WHERE pf.ProductID = ?
     *     ORDER BY pf.CreatedAt DESC
     *     LIMIT 10
     * ';
     *
     * $reviewStmt = $conn->prepare($reviewsSql);
     * if ($reviewStmt) {
     *     $reviewStmt->bind_param('s', $parentProductId);
     *     $reviewStmt->execute();
     *     $reviewResult = $reviewStmt->get_result();
     *
     *     while ($review = $reviewResult->fetch_assoc()) {
     *         $reviews[] = [
     *             'userName' => $review['UserName'] ?? 'Anonymous',
     *             'rating' => intval($review['Rating']),
     *             'text' => $review['ReviewText'] ?? '',
     *             'date' => $review['CreatedDate'],
     *             'hasImage' => false
     *         ];
     *     }
     *     $reviewStmt->close();
     * }
     */

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