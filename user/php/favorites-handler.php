<?php
// favorites-handler.php — SIMPLIFIED using home.php logic
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

// ✅ ADDED: Set timezone
date_default_timezone_set('Asia/Manila');

try {
    // Load DB config
    if (getenv('DOCKER_ENV') === 'true') {
        require_once __DIR__ . '/../../config/db_docker.php';
    } else {
        require_once __DIR__ . '/../../config/db.php';
    }

    // ✅ ADDED: Set database timezone
    $conn->query("SET time_zone = '+08:00'");

    // ✅ ADDED: Include activity logger
    require_once __DIR__ . '/activity_logger.php';

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

    // Clean product name function
    $cleanName = function ($name) {
        if (empty($name))
            return '';
        return trim(str_ireplace(['Parent Record:', 'Product Record:', ':'], '', $name));
    };

    // ===================== TOGGLE FAVORITE ===================== //
    if ($action === 'toggle') {
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing product_id']);
            exit;
        }

        $product_id = (string) $product_id;

        // 1. Get the parent product ID (if it exists) and product name for logging
        $stmtParent = $conn->prepare('SELECT ParentProductID, Name FROM Products WHERE ProductID=?');
        $stmtParent->bind_param('s', $product_id);
        $stmtParent->execute();
        $resParent = $stmtParent->get_result();
        $parentID = null;
        $productName = 'Unknown Product';

        if ($row = $resParent->fetch_assoc()) {
            $parentID = $row['ParentProductID'] ?? $product_id;
            $productName = $cleanName($row['Name']);
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

            $affectedRows = $stmtDelete->affected_rows;

            // ✅ LOG FAVORITE REMOVAL
            if ($affectedRows > 0) {
                $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $logDetails = "Removed product {$product_id} - {$productName} from favorites. Parent ID: {$parentID}, IP: {$userIP}";
                logUserActivity($conn, $user_id, 'Remove from favorites', $logDetails);
            }

            echo json_encode([
                'success' => $affectedRows > 0,
                'action' => 'unliked'
            ]);
        } else {
            // ✅ OPTIONAL: Use PHP date if you change to DATETIME
            $currentDateTime = date('Y-m-d H:i:s');

            // 4. Add favorite for the single product
            // If using DATETIME:
            // $stmtInsert = $conn->prepare('INSERT INTO favorites (user_id, product_id, created_at) VALUES (?, ?, ?)');
            // $stmtInsert->bind_param('iss', $user_id, $product_id, $currentDateTime);

            // If keeping TIMESTAMP (uses MySQL NOW()):
            $stmtInsert = $conn->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?, ?)');
            $stmtInsert->bind_param('is', $user_id, $product_id);
            $stmtInsert->execute();

            $affectedRows = $stmtInsert->affected_rows;

            // ✅ LOG FAVORITE ADDITION
            if ($affectedRows > 0) {
                $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $logDetails = "Added product {$product_id} - {$productName} to favorites. Parent ID: {$parentID}, IP: {$userIP}";
                logUserActivity($conn, $user_id, 'Add to favorites', $logDetails);
            }

            echo json_encode([
                'success' => $affectedRows > 0,
                'action' => 'liked'
            ]);
        }

        $conn->close();
        exit;
    }

    // ===================== GET FAVORITES USING SIMPLIFIED HOME.PHP LOGIC ===================== //
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
        1 AS liked  -- All these are favorites by definition
    FROM 
        favorites f
    JOIN Products p ON (
        f.product_id = p.ProductID 
        OR f.product_id = p.ParentProductID
        OR (p.ParentProductID IS NOT NULL AND f.product_id = p.ParentProductID)
    )
    LEFT JOIN ProductMedia pm_v 
        ON pm_v.VariantProductID = p.ProductID 
        AND pm_v.MediaType = 'VARIANT'
        AND pm_v.ImagePath IS NOT NULL
        AND pm_v.ImagePath != ''
    LEFT JOIN ProductMedia pm_p 
        ON pm_p.ParentProductID = COALESCE(p.ParentProductID, p.ProductID)
        AND pm_p.MediaType = 'PREVIEW'
        AND pm_p.ImagePath IS NOT NULL
        AND pm_p.ImagePath != ''
    LEFT JOIN Products parent 
        ON p.ParentProductID = parent.ProductID
    WHERE 
        f.user_id = ?
        AND p.Status IN ('Available', 'Low Stock')
        AND p.Stocks > 0
        AND (p.ExpirationDate IS NULL OR p.ExpirationDate > CURDATE())
    ORDER BY 
        f.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $groupedProducts = [];
    $processedParentIds = [];

    while ($row = $result->fetch_assoc()) {
        $parentID = $row['parentID'];
        $isParent = ($parentID === null || $parentID === $row['id']);

        // Convert image paths using the corrected function
        $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
        $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');

        // Choose the best available image
        $displayImage = '';
        if (!empty($row['variantImage'])) {
            $displayImage = $row['variantImage'];
        } elseif (!empty($row['previewImage'])) {
            $displayImage = $row['previewImage'];
        }

        $row['name'] = $cleanName($row['name']);
        $row['parentName'] = $cleanName($row['parentName'] ?? '');

        // Ratings data - use the highest rating among all variants
        $variantRating = floatval($row['product_rating']);
        $parentRating = floatval($row['parent_rating']);

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
                    'image' => $displayImage,
                    'previewImage' => $displayImage,
                    'liked' => true,  // All are favorites
                    'average_rating' => $parentRating,
                    'variants' => []
                ];
                $processedParentIds[] = $row['id'];
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
                    'image' => $displayImage,  // ← CHANGED THIS
                    'previewImage' => $displayImage,  // ← AND THIS
                    'liked' => true,  // All are favorites
                    'average_rating' => $variantRating,
                    'variants' => []
                ];
                $processedParentIds[] = $parentKey;
            } else {
                // Update to the highest rating among variants
                if ($variantRating > $groupedProducts[$parentKey]['average_rating']) {
                    $groupedProducts[$parentKey]['average_rating'] = $variantRating;
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

        // Additional check: Remove parent products that have no available variants
        if (empty($product['variants'])) {
            continue;
        }

        // Check if at least one variant is actually available
        $hasAvailable = false;
        foreach ($product['variants'] as $variant) {
            if ($variant['stockQuantity'] > 0 && in_array($variant['status'], ['Available', 'Low Stock'])) {
                $hasAvailable = true;
                break;
            }
        }

        if (!$hasAvailable) {
            continue;
        }

        // Ensure required fields
        if (!isset($product['status']))
            $product['status'] = 'Available';
        if (!isset($product['stockQuantity']))
            $product['stockQuantity'] = 0;
        if (!isset($product['average_rating']))
            $product['average_rating'] = 0;

        $finalProducts[] = $product;
    }

    // ✅ LOG FAVORITES VIEW
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $logDetails = 'Viewed favorites list. Total items: ' . count($finalProducts) . ", IP: {$userIP}";
    logUserActivity($conn, $user_id, 'View favorites', $logDetails);

    echo json_encode([
        'success' => true,
        'products' => array_values($finalProducts),
        'debug' => [
            'user_id' => $user_id,
            'total_favorites' => count($finalProducts),
            'raw_count' => $result->num_rows
        ]
    ]);

    $conn->close();
} catch (Exception $e) {
    // ✅ LOG ERROR
    if (isset($user_id)) {
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $errorDetails = 'Error: ' . $e->getMessage() . ", Action: {$action}, IP: {$userIP}";
        logUserActivity($conn, $user_id, 'Favorites error', $errorDetails);
    }

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