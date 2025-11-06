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

// ========== GET USER FAVORITES (PARENT LEVEL) ==========
$user_id = $_SESSION['user_id'] ?? null;
$userFavorites = [];

if ($user_id) {
    $stmtFav = $conn->prepare("
        SELECT 
            COALESCE(p.ParentProductID, f.product_id) AS parent_id
        FROM favorites f
        LEFT JOIN Products p ON f.product_id = p.ProductID
        WHERE f.user_id = ?
    ");
    $stmtFav->bind_param("i", $user_id);
    $stmtFav->execute();
    $resFav = $stmtFav->get_result();
    while ($fav = $resFav->fetch_assoc()) {
        $userFavorites[] = $fav['parent_id'];
    }
    $stmtFav->close();
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
        parent.Name AS parentName,
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
        p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
        AND p.Stocks > 0
    ORDER BY 
        p.CreatedAt DESC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    die(json_encode(['error' => 'Database query failed: ' . $conn->error]));
}

$groupedProducts = [];

while ($row = $result->fetch_assoc()) {
    $parentID = $row['parentID'];
    $isParent = ($parentID === null || $parentID === $row['id']);
    $effectiveParentID = $isParent ? $row['id'] : $parentID;

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
                'variants' => [],
                'liked' => in_array($row['id'], $userFavorites)
            ];
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
                'variants' => [],
                'liked' => in_array($parentKey, $userFavorites)
            ];
        }

        $groupedProducts[$parentKey]['variants'][] = [
            'id' => $row['id'],
            'name' => $row['variant'] ?? $row['name'],
            'variant' => $row['variant'] ?? $row['name'],
            'price' => floatval($row['price']),
            'image' => $row['variantImage'],
            'hexCode' => $row['hexCode'] ?? '#CCCCCC',
            'status' => $row['status'],
            'stockQuantity' => intval($row['stockQuantity'])
        ];
    }
}

// ========== Finalize ==========
$finalProducts = array_values($groupedProducts);

$response = [
    'success' => true,
    'products' => $finalProducts
];

echo json_encode($response);
$conn->close();
?>
