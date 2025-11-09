<?php
// favorites-handler.php — fetches user's favorites and allows like/unlike
session_start();

// Enable error reporting but hide from users
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch unexpected output
ob_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Load DB config
    if (getenv('DOCKER_ENV') === 'true') {
        require_once __DIR__ . '/../../config/db_docker.php';
    } else {
        require_once __DIR__ . '/../../config/db.php';
    }

    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }

    // Get user_id and action
    $user_id = $_SESSION['user_id'] ?? ($_GET['user_id'] ?? null);
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    $product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? null;

    // Check JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $product_id = $input['product_id'] ?? $product_id;
        $action = $input['action'] ?? $action;
    }

    if (!$user_id) {
        throw new Exception('Missing user_id. Please log in.');
    }

    $user_id = (int) $user_id;

    function getPublicImagePath($dbPath)
    {
        if (empty($dbPath))
            return '';
        $filename = basename($dbPath);
        return '/admin/uploads/product_images/' . $filename;
    }

    // ===================== TOGGLE FAVORITE ===================== //
    if ($action === 'toggle') {
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing product_id']);
            exit;
        }

        $product_id = (string) $product_id;

        // 1. Get the parent product ID (if it exists)
        $stmtParent = $conn->prepare('SELECT ParentProductID FROM Products WHERE ProductID=?');
        $stmtParent->bind_param('s', $product_id);
        $stmtParent->execute();
        $resParent = $stmtParent->get_result();
        $parentID = null;

        if ($row = $resParent->fetch_assoc()) {
            $parentID = $row['ParentProductID'] ?? $product_id;  // if no parent, use product itself
        }

        // 2. Check if the product is already in favorites
        $stmtCheck = $conn->prepare('SELECT favorite_id FROM favorites WHERE user_id=? AND product_id=?');
        $stmtCheck->bind_param('is', $user_id, $product_id);
        $stmtCheck->execute();
        $res = $stmtCheck->get_result();

        if ($res->num_rows > 0) {
            // 3. Remove all favorites for the parent product and its variants
            $stmtDelete = $conn->prepare('
            DELETE f 
            FROM favorites f
            JOIN Products p ON f.product_id = p.ProductID
            WHERE f.user_id = ? 
            AND (p.ProductID = ? OR p.ParentProductID = ?)
        ');
            $stmtDelete->bind_param('iss', $user_id, $parentID, $parentID);
            $stmtDelete->execute();

            echo json_encode([
                'success' => $stmtDelete->affected_rows > 0,
                'action' => 'unliked'
            ]);
        } else {
            // 4. Add favorite for the single product
            $stmtInsert = $conn->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
            $stmtInsert->bind_param('is', $user_id, $product_id);
            $stmtInsert->execute();

            echo json_encode([
                'success' => $stmtInsert->affected_rows > 0,
                'action' => 'liked'
            ]);
        }

        $conn->close();
        exit;
    }

    // ===================== FETCH FAVORITES ===================== //
    // This version shows ALL items under the same parent when any variant/parent is favorited
    $sql = "
SELECT DISTINCT 
    p.ProductID AS id,
    p.Name AS name,
    p.Category AS category,
    p.ParentProductID AS parentID,
    p.ShadeOrVariant AS variant,
    p.Price AS price,
    p.HexCode AS hexCode,
    p.Status AS status,
    p.Stocks AS stockQuantity,
    p.CreatedAt AS createdAt,
    pm_v.ImagePath AS variantImage,
    pm_p.ImagePath AS previewImage,
    parent.Name AS parentName,
    parent.Status AS parentStatus
FROM Products p
LEFT JOIN Products parent 
    ON p.ParentProductID = parent.ProductID
LEFT JOIN ProductMedia pm_v 
    ON pm_v.VariantProductID = p.ProductID AND pm_v.MediaType = 'VARIANT'
LEFT JOIN ProductMedia pm_p 
    ON pm_p.ParentProductID = COALESCE(p.ParentProductID, p.ProductID) AND pm_p.MediaType = 'PREVIEW'
WHERE 
    (
        p.ParentProductID IN (
            SELECT COALESCE(pr.ParentProductID, pr.ProductID)
            FROM favorites f
            JOIN Products pr ON f.product_id = pr.ProductID
            WHERE f.user_id = ?
        )
        OR p.ProductID IN (
            SELECT COALESCE(pr.ParentProductID, pr.ProductID)
            FROM favorites f
            JOIN Products pr ON f.product_id = pr.ProductID
            WHERE f.user_id = ?
        )
    )
    AND p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
    AND p.Stocks > 0
ORDER BY p.CreatedAt DESC
";

    $stmt = $conn->prepare($sql);
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Group products by parent
    $groupedProducts = [];
    while ($row = $result->fetch_assoc()) {
        $parentID = $row['parentID'];
        $isParent = ($parentID === null || $parentID === $row['id']);

        $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
        $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');
        $row['name'] = trim(str_ireplace('Product Record', '', $row['name']));
        $row['parentName'] = trim(str_ireplace('Product Record', '', $row['parentName'] ?? ''));

        $parentKey = $isParent ? $row['id'] : $parentID;
        $parentName = $row['parentName'] ?? $row['name'];

        if (!isset($groupedProducts[$parentKey])) {
            $groupedProducts[$parentKey] = [
                'id' => $parentKey,
                'name' => $parentName,
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),
                'status' => $row['status'] ?? ($row['parentStatus'] ?? 'Available'),
                'stockQuantity' => intval($row['stockQuantity']),
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
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
            'stockQuantity' => intval($row['stockQuantity']),
            'favorited' => 1
        ];
    }

    // Final cleanup (same as before)
    $finalProducts = [];
    foreach ($groupedProducts as $productId => $product) {
        if (!empty($product['variants'])) {
            $totalStock = 0;
            $hasAvailable = false;
            foreach ($product['variants'] as $variant) {
                $totalStock += $variant['stockQuantity'];
                if ($variant['stockQuantity'] > 0)
                    $hasAvailable = true;
            }
            $product['stockQuantity'] = $totalStock;
            if (!$hasAvailable)
                continue;
        }

        if (!isset($product['status']))
            $product['status'] = 'Available';
        if (!isset($product['stockQuantity']))
            $product['stockQuantity'] = 0;

        $finalProducts[] = $product;
    }

    echo json_encode([
        'success' => true,
        'products' => array_values($finalProducts),
        'debug' => [
            'user_id' => $user_id,
            'total_favorites' => count($finalProducts),
        ]
    ]);

    $conn->close();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'An error occurred while processing your request'
    ]);
}

ob_end_flush();
?>
