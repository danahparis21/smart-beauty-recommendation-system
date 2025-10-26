<?php
// fetch_home_products.php (or your combined fetch script)
session_start();

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

header('Content-Type: application/json');

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// ------------------------------------------
// FIX: Helper function to get the clean public path
// ------------------------------------------
function getPublicImagePath($dbPath)
{
    if (empty($dbPath)) {
        return '';
    }
    $filename = basename($dbPath);
    return '/admin/uploads/product_images/' . $filename;
}

// ------------------------------------------

// 1. Fetch ALL products (Parents and Variants) with their respective media and hex code.
$sql = "
    SELECT 
        p.ProductID AS id, 
        p.Name AS name, 
        p.Category AS category, 
        p.ParentProductID AS parentID,
        p.ShadeOrVariant AS variant,
        p.Price AS price,
        p.HexCode AS hexCode,

        -- Variant image (join on the variant's ProductID)
        pm_v.ImagePath AS variantImage,

        -- Parent preview image (join on parent's ProductID)
        pm_p.ImagePath AS previewImage
    FROM 
        Products p
    LEFT JOIN ProductMedia pm_v 
        ON pm_v.VariantProductID = p.ProductID 
        AND pm_v.MediaType = 'VARIANT'
    LEFT JOIN ProductMedia pm_p 
        ON pm_p.ParentProductID = 
           (CASE 
                WHEN p.ParentProductID = p.ProductID THEN p.ProductID 
                ELSE p.ParentProductID 
            END)
        AND pm_p.MediaType = 'PREVIEW'
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
    $isParent = ($row['id'] === $parentID);

    // FIX: Use the new helper function to get the correct web-accessible path
    $row['variantImage'] = getPublicImagePath($row['variantImage'] ?? '');
    $row['previewImage'] = getPublicImagePath($row['previewImage'] ?? '');

    // 2. Grouping Logic
    if ($isParent) {
        // Initialize the Parent Product container
        if (!isset($groupedProducts[$parentID])) {
            $groupedProducts[$parentID] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => strtolower($row['category']),
                'price' => floatval($row['price']),  // Parent's base price
                'image' => $row['previewImage'],  // Use previewImage as the main image for the parent card
                'previewImage' => $row['previewImage'],  // The large image for hover
                'variants' => []
            ];
        }
    } else {
        // If the parent container hasn't been initialized yet, do it now (handles cases where variant appears first)
        if (!isset($groupedProducts[$parentID])) {
            // Basic structure, will be overwritten if the actual parent record is found later
            $groupedProducts[$parentID] = [
                'id' => $parentID,
                'name' => $row['name'],
                'category' => strtolower($row['category']),
                'price' => 0.0,  // Placeholder
                'image' => $row['previewImage'],
                'previewImage' => $row['previewImage'],
                'variants' => []
            ];
        }

        // Add the variant details to the parent's 'variants' array
        $groupedProducts[$parentID]['variants'][] = [
            'id' => $row['id'],
            'name' => $row['variant'] ?? $row['name'],  // Use ShadeOrVariant as the variant name
            'price' => floatval($row['price']),
            'image' => $row['variantImage'],
            'hexCode' => $row['hexCode'] ?? '#CCCCCC',  // Default gray
        ];
    }
}

// 3. Final array contains only the parent product records
echo json_encode(['success' => true, 'products' => array_values($groupedProducts)]);
$conn->close();
?>
