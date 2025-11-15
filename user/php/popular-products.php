<?php
// popular-products.php - Add this at the VERY TOP
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Add strict error handling
error_reporting(0);
ini_set('display_errors', 0);

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$response = ['success' => false, 'products' => [], 'error' => ''];

try {
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }

    function getPublicImagePath($dbPath) {
        if (empty($dbPath)) return '';
        $filename = basename($dbPath);
        return '/admin/uploads/product_images/' . $filename;
    }

    function processProducts($result, $user_id) {
        $groupedProducts = [];
        
        while ($row = $result->fetch_assoc()) {
            $parentID = $row['parentID'];
            $isParent = ($parentID === null || $parentID === $row['id']);

            // Skip products that should be excluded based on status
            $excludedStatuses = ['No Stock', 'Expired', 'Deleted', 'Disabled'];
            if (in_array($row['status'], $excludedStatuses) && $isParent) {
                continue;
            }

            // Convert image paths
            $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
            $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');
            $row['name'] = trim(str_ireplace(['Product Record', 'Parent Record'], '', $row['name']));
            $row['parentName'] = trim(str_ireplace(['Product Record', 'Parent Record'], '', $row['parentName'] ?? ''));
            
            $isLiked = (bool)($row['liked'] ?? false);

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
                        'liked' => $isLiked,
                        'order_count' => intval($row['order_count'] ?? 0),
                        'variants' => []
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
                        'liked' => $isLiked,
                        'order_count' => intval($row['order_count'] ?? 0),
                        'variants' => []
                    ];
                } else {
                    if ($isLiked) {
                        $groupedProducts[$parentKey]['liked'] = true;
                    }
                }

                // Only add variant if it's available
                if (!in_array($row['status'], $excludedStatuses) && $row['stockQuantity'] > 0) {
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
        }

        // Calculate parent stock and clean up
        $finalProducts = [];
        foreach ($groupedProducts as $productId => $product) {
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

                if (!$hasAvailableVariants) {
                    continue;
                }
            } else {
                // For products without variants, check if they should be excluded
                if (in_array($product['status'], ['No Stock', 'Expired', 'Deleted', 'Disabled']) || $product['stockQuantity'] <= 0) {
                    continue;
                }
            }

            if (!isset($product['status'])) $product['status'] = 'Available';
            if (!isset($product['stockQuantity'])) $product['stockQuantity'] = 0;
            if (!isset($product['liked'])) $product['liked'] = false;

            $finalProducts[] = $product;
        }

        return $finalProducts;
    }

    // Get logged-in user ID
    $user_id = $_SESSION['user_id'] ?? null;

    // ===================== GET POPULAR PRODUCTS BASED ON ORDER COUNT ===================== //
    $sql_popular = "
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
            MAX(pm_v.ImagePath) AS variantImage,
            MAX(pm_p.ImagePath) AS previewImage,
            MAX(parent.Name) AS parentName,
            MAX(parent.Status) AS parentStatus,
            COUNT(oi.order_item_id) AS order_count,
            " . ($user_id ? "MAX(IF(f.favorite_id IS NOT NULL, 1, 0)) AS liked" : "0 AS liked") . "
        FROM 
            Products p
        LEFT JOIN orderitems oi ON oi.product_id = p.ProductID
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
            p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
            AND p.Stocks > 0
        GROUP BY 
            p.ProductID, p.Name, p.ParentProductID, p.Category, p.ShadeOrVariant, 
            p.Price, p.HexCode, p.Status, p.Stocks
        HAVING 
            order_count > 0
        ORDER BY 
            order_count DESC,
            p.CreatedAt DESC
        LIMIT 12
    ";

    $finalProducts = [];

    // Try to get popular products first
    if ($user_id) {
        $stmt = $conn->prepare($sql_popular);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            throw new Exception('Failed to prepare statement: ' . $conn->error);
        }
    } else {
        $result = $conn->query($sql_popular);
    }

    if ($result && $result->num_rows > 0) {
        $finalProducts = processProducts($result, $user_id);
    }

    // If no popular products found, fall back to categories
    if (empty($finalProducts)) {
        $sql_fallback = "
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
                MAX(pm_v.ImagePath) AS variantImage,
                MAX(pm_p.ImagePath) AS previewImage,
                MAX(parent.Name) AS parentName,
                MAX(parent.Status) AS parentStatus,
                0 AS order_count,
                " . ($user_id ? "MAX(IF(f.favorite_id IS NOT NULL, 1, 0)) AS liked" : "0 AS liked") . "
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
                p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
                AND p.Stocks > 0
                AND (LOWER(p.Category) LIKE '%lipstick%' OR LOWER(p.Category) LIKE '%blush%')
            GROUP BY 
                p.ProductID, p.Name, p.ParentProductID, p.Category, p.ShadeOrVariant, 
                p.Price, p.HexCode, p.Status, p.Stocks
            ORDER BY 
                p.CreatedAt DESC
            LIMIT 12
        ";
        
        if ($user_id) {
            $stmt = $conn->prepare($sql_fallback);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result_fallback = $stmt->get_result();
            }
        } else {
            $result_fallback = $conn->query($sql_fallback);
        }
        
        if ($result_fallback && $result_fallback->num_rows > 0) {
            $fallbackProducts = processProducts($result_fallback, $user_id);
            
            $lipstickProducts = array_filter($fallbackProducts, function($product) {
                return stripos($product['category'], 'lipstick') !== false;
            });
            
            $blushProducts = array_filter($fallbackProducts, function($product) {
                return stripos($product['category'], 'blush') !== false;
            });
            
            $finalProducts = array_merge(
                array_slice($lipstickProducts, 0, 4),
                array_slice($blushProducts, 0, 4)
            );
            
            if (count($finalProducts) < 8) {
                $remainingNeeded = 8 - count($finalProducts);
                $allProducts = array_merge($lipstickProducts, $blushProducts);
                $usedIds = array_column($finalProducts, 'id');
                $additionalProducts = array_filter($allProducts, function($product) use ($usedIds) {
                    return !in_array($product['id'], $usedIds);
                });
                $finalProducts = array_merge($finalProducts, array_slice($additionalProducts, 0, $remainingNeeded));
            }
        }
    }

    // Final fallback: get any available products
    if (empty($finalProducts)) {
        $sql_final_fallback = "
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
                MAX(pm_v.ImagePath) AS variantImage,
                MAX(pm_p.ImagePath) AS previewImage,
                MAX(parent.Name) AS parentName,
                MAX(parent.Status) AS parentStatus,
                0 AS order_count,
                " . ($user_id ? "MAX(IF(f.favorite_id IS NOT NULL, 1, 0)) AS liked" : "0 AS liked") . "
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
                p.Status NOT IN ('No Stock', 'Expired', 'Deleted', 'Disabled')
                AND p.Stocks > 0
            GROUP BY 
                p.ProductID, p.Name, p.ParentProductID, p.Category, p.ShadeOrVariant, 
                p.Price, p.HexCode, p.Status, p.Stocks
            ORDER BY 
                p.CreatedAt DESC
            LIMIT 8
        ";
        
        if ($user_id) {
            $stmt = $conn->prepare($sql_final_fallback);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result_final = $stmt->get_result();
            }
        } else {
            $result_final = $conn->query($sql_final_fallback);
        }
        
        if ($result_final && $result_final->num_rows > 0) {
            $finalProducts = processProducts($result_final, $user_id);
        }
    }

    // Ensure we have exactly 8 products for the carousel
    if (count($finalProducts) > 8) {
        $finalProducts = array_slice($finalProducts, 0, 8);
    }

    $response = [
        'success' => true,
        'products' => $finalProducts,
        'source' => empty($finalProducts) ? 'none' : 
                    (isset($result) && $result->num_rows > 0 ? 'popular' : 
                    (count(array_filter($finalProducts, function($p) { 
                        return stripos($p['category'], 'lipstick') !== false || stripos($p['category'], 'blush') !== false; 
                    })) > 0 ? 'categories' : 'fallback')),
        'debug' => [
            'total_products' => count($finalProducts),
            'product_categories' => array_column($finalProducts, 'category')
        ]
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'products' => [],
        'error' => 'Server error: ' . $e->getMessage()
    ];
}

echo json_encode($response);

if (isset($conn) && $conn) {
    $conn->close();
}
exit;
?>