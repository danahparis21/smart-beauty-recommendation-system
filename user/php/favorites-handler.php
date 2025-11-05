<?php
// favorites.php — fetches user's favorited products with full parent/variant structure
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

// Get user_id (either from session or GET param)
$user_id = $_SESSION['user_id'] ?? ($_GET['user_id'] ?? null);
if (!$user_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing user_id']));
}

// Utility for public image path
function getPublicImagePath($dbPath)
{
    if (empty($dbPath)) return '';
    $filename = basename($dbPath);
    return '/admin/uploads/product_images/' . $filename;
}

// ===================== SQL ===================== //
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
        parent.Name AS parentName,
        parent.Status AS parentStatus
    FROM 
        favorites f
    INNER JOIN Products p 
        ON f.product_id = p.ProductID
    LEFT JOIN ProductMedia pm_v 
        ON pm_v.VariantProductID = p.ProductID 
        AND pm_v.MediaType = 'VARIANT'
    LEFT JOIN ProductMedia pm_p 
        ON pm_p.ParentProductID = COALESCE(p.ParentProductID, p.ProductID)
        AND pm_p.MediaType = 'PREVIEW'
    LEFT JOIN Products parent 
        ON p.ParentProductID = parent.ProductID
    WHERE 
        f.user_id = ?
        AND p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
        AND p.Stocks > 0
    ORDER BY 
        p.CreatedAt DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Database query failed: ' . $conn->error]));
}

$groupedProducts = [];
$processedParentIds = [];

while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];
    $isParent = ($parentID === null || $parentID === $row['id']);

    $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
    $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');
    $row['name'] = trim(str_ireplace('Product Record', '', $row['name']));
    $row['parentName'] = trim(str_ireplace('Product Record', '', $row['parentName'] ?? ''));

    if ($isParent) {
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
        }
    } else {
        $parentKey = $parentID;
        $parentName = $row['parentName'] ?? $row['name'];

        if (!isset($groupedProducts[$parentKey])) {
            $groupedProducts[$parentKey] = [
                'id' => $parentKey,
                'name' => $parentName,
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['parentStatus'] ?? 'Available',
                'stockQuantity' => 0,
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
            $processedParentIds[] = $parentKey;
        }

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
    }
}

// Calculate stock and finalize
$finalProducts = [];
foreach ($groupedProducts as $productId => $product) {
    if (!empty($product['variants'])) {
        $totalStock = 0;
        $hasAvailable = false;
        foreach ($product['variants'] as $variant) {
            $totalStock += $variant['stockQuantity'];
            if ($variant['stockQuantity'] > 0 && $variant['status'] !== 'No Stock') {
                $hasAvailable = true;
            }
        }
        $product['stockQuantity'] = $totalStock;
        if (!$hasAvailable) continue;
    }

    if (!isset($product['status'])) $product['status'] = 'Available';
    if (!isset($product['stockQuantity'])) $product['stockQuantity'] = 0;

    $finalProducts[] = $product;
}

$response = [
    'success' => true,
    'products' => array_values($finalProducts),
    'debug' => [
        'user_id' => $user_id,
        'total_favorites' => count($finalProducts),
    ]
];

echo json_encode($response);
$conn->close();
?>
