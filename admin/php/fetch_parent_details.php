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
    die(json_encode(['error' => 'Missing Product ID.']));
}

$productID = $_GET['id'];

try {
    // 1. Fetch Main Product Details
    // Note: We select columns based on your provided schema (e.g., Stocks, Category).
    // We explicitly look for a Parent product (ParentProductID IS NULL)
    $sql_product = '
        SELECT 
            ProductID, 
            Name, 
            Description, 
            Ingredients,
            Stocks, 
            Price, 
            Category, 
            ExpirationDate 
        FROM Products 
        WHERE ProductID = ? AND ParentProductID IS NULL 
        LIMIT 1';

    $stmt_product = $conn->prepare($sql_product);
    // 's' indicates the ProductID is a string/text type
    $stmt_product->bind_param('s', $productID);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    $product = $result_product->fetch_assoc();
    $stmt_product->close();

    if (!$product) {
        http_response_code(404);
        // Check if the ID belongs to a variant instead of a parent
        $check_variant = $conn->query("SELECT ProductID FROM Products WHERE ProductID = '{$productID}' AND ParentProductID IS NOT NULL LIMIT 1")->fetch_assoc();

        if ($check_variant) {
            die(json_encode(['error' => 'This ID belongs to a variant. Use the separate variant edit handler.']));
        }
        die(json_encode(['error' => 'Product not found.']));
    }

    // 2. Fetch Media (Images) for the Parent Product
    $sql_media = "
SELECT 
    ImagePath, 
    MediaType 
FROM ProductMedia 
WHERE ParentProductID = ? AND MediaType IN ('PREVIEW', 'GALLERY')
ORDER BY FIELD(MediaType, 'PREVIEW', 'GALLERY'), SortOrder ASC";  // Use FIELD() to prioritize PREVIEW

    $stmt_media = $conn->prepare($sql_media);
    $stmt_media->bind_param('s', $productID);
    $stmt_media->execute();
    $result_media = $stmt_media->get_result();
    $media = $result_media->fetch_all(MYSQLI_ASSOC);
    $stmt_media->close();

    $product['MainImage'] = null;
    $product['DetailImages'] = [];

    // Separate the images based on MediaType
    foreach ($media as $img) {
        if ($img['MediaType'] === 'PREVIEW' && $product['MainImage'] === null) {
            // Assign the PREVIEW type image as the MainImage
            $product['MainImage'] = $img['ImagePath'];
        } elseif ($img['MediaType'] === 'GALLERY') {
            // Collect GALLERY type images as DetailImages
            if (count($product['DetailImages']) < 4) {
                $product['DetailImages'][] = $img['ImagePath'];
            }
        }
    }

    // Ensure 4 placeholders for detail images
    while (count($product['DetailImages']) < 4) {
        $product['DetailImages'][] = null;
    }

    echo json_encode($product);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    // Only close the connection if it was successfully opened and is not null
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>