<?php
// Configuration (Your existing connection block)
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing Variant Product ID.']));
}

$variantID = $_GET['id'];

try {
    // 1. Fetch Variant Details and Attributes (LEFT JOIN is crucial)
    $sql_variant = '
        SELECT 
            p.ProductID, 
            p.ParentProductID,
            p.Name, 
            p.Description, 
            p.Ingredients,
            p.Stocks, 
            p.Price, 
            p.Category, 
            p.ExpirationDate,
            p.HexCode,
            p.ShadeOrVariant,
            
            a.SkinType,
            a.SkinTone,
            a.Undertone,
            a.Acne,
            a.Dryness,
            a.DarkSpots,
            a.Matte,
            a.Dewy,
            a.LongLasting
        FROM Products p
        LEFT JOIN ProductAttributes a ON p.ProductID = a.ProductID
        WHERE p.ProductID = ? AND p.ParentProductID IS NOT NULL 
        LIMIT 1';

    $stmt_variant = $conn->prepare($sql_variant);
    $stmt_variant->bind_param('s', $variantID);
    $stmt_variant->execute();
    $result_variant = $stmt_variant->get_result();
    $variant = $result_variant->fetch_assoc();
    $stmt_variant->close();

    if (!$variant) {
        http_response_code(404);
        die(json_encode(['error' => 'Variant not found or is a Parent Product.']));
    }

    // 2. Fetch the single Variant Image (MediaType = 'VARIANT')
    $sql_media = "
        SELECT 
            ImagePath
        FROM ProductMedia 
        WHERE VariantProductID = ? AND MediaType = 'VARIANT'
        ORDER BY SortOrder ASC
        LIMIT 1";

    $stmt_media = $conn->prepare($sql_media);
    $stmt_media->bind_param('s', $variantID);
    $stmt_media->execute();
    $result_media = $stmt_media->get_result();
    $media = $result_media->fetch_assoc();  // Use fetch_assoc to get a single row
    $stmt_media->close();

    // Attach the variant image path
    $variant['VariantImage'] = $media['ImagePath'] ?? null;

    // Remove the ParentProductID field as it's not needed for the form
    unset($variant['ParentProductID']);

    echo json_encode($variant);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>