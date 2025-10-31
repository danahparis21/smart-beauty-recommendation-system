<?php
// update_product.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuration & Connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed.']));
}

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Ensure ProductID is present
$productID = $_POST['ProductID'] ?? null;
$isParentUpdate = filter_var($_POST['isParentUpdate'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

if (!$productID) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing Product ID for update.']));
}

// Start Transaction to ensure data integrity
$conn->begin_transaction();

try {
    // --- Collect ALL necessary data first ---

    // Data common to BOTH tables (or used in decision making)
    $name = $_POST['productName'] ?? '';
    $description = $_POST['productDescription'] ?? '';
    $ingredients = $_POST['productIngredients'] ?? '';
    $stocks = $_POST['productStock'] ?? 0;
    $price = $_POST['productPrice'] ?? 0.0;

    // FIX: Convert empty date string '' to NULL for MySQL
    $expirationDate = $_POST['productExpiration'] ?? null;
    if ($expirationDate && $expirationDate !== '') {
        $today = date('Y-m-d');
        if ($expirationDate < $today) {
            http_response_code(400);
            die(json_encode(['error' => 'Expiration date cannot be in the past']));
        }
    }

    // Convert empty string to NULL for MySQL
    if ($expirationDate === '') {
        $expirationDate = null;
    }
    // Category is only updated for the Parent product
    $category = $isParentUpdate ? ($_POST['productCategory'] ?? null) : null;

    // --- Variables for Variant Products ONLY ---
    $attribute_columns_products = '';
    $attribute_values_products = [];
    $attribute_types_products = '';

    if (!$isParentUpdate) {
        // Attributes for the PRODUCTS table

        $hexCode = $_POST['hexCode'] ?? '';
        $shadeOrVariant = $_POST['productVariantName'] ?? '';
        $parentProductID = $_POST['ParentProductID'] ?? null;

        // If ParentProductID is not provided in POST, fetch the existing one from the DB
        if (empty($parentProductID)) {
            $stmt_fetch_parent = $conn->prepare('SELECT ParentProductID FROM Products WHERE ProductID = ?');
            $stmt_fetch_parent->bind_param('s', $productID);
            $stmt_fetch_parent->execute();
            $result_fetch_parent = $stmt_fetch_parent->get_result();
            $row_fetch_parent = $result_fetch_parent->fetch_assoc();
            $stmt_fetch_parent->close();

            // Use the existing ParentProductID if found, otherwise keep null (though it shouldn't be null)
            $parentProductID = $row_fetch_parent['ParentProductID'] ?? null;
        }

        $attribute_columns_products = ', HexCode = ?, ShadeOrVariant = ?, ParentProductID = ?';
        $attribute_values_products = [$hexCode, $shadeOrVariant, $parentProductID];
        $attribute_types_products = 'sss';

        // Attributes for the PRODUCTATTRIBUTES table (used in the separate block below)
        $skinType = implode(',', $_POST['skinType'] ?? []);
        $skinTone = implode(',', $_POST['skinTone'] ?? []);
        $undertone = $_POST['undertone'] ?? null;
        $acne = isset($_POST['acne']) ? 1 : 0;
        $dryness = isset($_POST['dryness']) ? 1 : 0;
        $darkSpots = isset($_POST['darkspots']) ? 1 : 0;
        $matte = isset($_POST['matte']) ? 1 : 0;
        $dewy = isset($_POST['dewy']) ? 1 : 0;
        $longLasting = isset($_POST['longLasting']) ? 1 : 0;

        // This is the data array for the attributes table (used later)
        $attribute_data_for_insert_update = [
            $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting
        ];
        $attribute_types_for_insert_update = 'sssiiiiii';
    }

    // ----------------------------------------------------------------------
    // --- 1. BASE PRODUCT UPDATE (Applies to Parent AND Variant via dynamic SQL) ---
    // ----------------------------------------------------------------------

    // Build the SQL statement
    $sql_product_update = '
        UPDATE Products SET 
            Name = ?, 
            Description = ?, 
            Ingredients = ?, 
            Stocks = ?, 
            Price = ?, 
            ExpirationDate = ?, 
            UpdatedAt = NOW() 
            ' . ($isParentUpdate ? ', Category = ?' : '')  // Parent only
        . (!$isParentUpdate ? $attribute_columns_products : '')  // Variant only
        . ' WHERE ProductID = ?';

    // Build the array of values and types for the prepared statement
    $values = [
        $name, $description, $ingredients, $stocks, $price, $expirationDate
    ];
    $types = 'sssids';  // Base types (Name, Desc, Ingred, Stocks, Price, ExpDate)

    if ($isParentUpdate) {
        $values[] = $category;
        $types .= 's';
    } else {
        // Append Products table variant attributes (HexCode, ShadeOrVariant)
        $values = array_merge($values, $attribute_values_products);
        $types .= $attribute_types_products;
    }

    $values[] = $productID;  // Final value is the WHERE clause ID
    $types .= 's';

    // Execute the product update
    // Execute the product update
    $stmt = $conn->prepare($sql_product_update);

    // Fix: create references for bind_param()
    $bindParams = array_merge([$types], $values);
    $refParams = [];
    foreach ($bindParams as $key => $value) {
        $refParams[$key] = &$bindParams[$key];  // make them references
    }

    call_user_func_array([$stmt, 'bind_param'], $refParams);

    $stmt->execute();
    $stmt->close();

    // ----------------------------------------------------------------------
    // --- 1.5. CATEGORY PROPAGATION (Parent Update ONLY) ---------------------
    // ----------------------------------------------------------------------
    if ($isParentUpdate && $category !== null) {
        // Update all direct variants of this parent product with the new category
        $sql_propagate_category = '
        UPDATE Products SET 
            Category = ?, 
            UpdatedAt = NOW()
        WHERE 
            ParentProductID = ? 
            AND ProductID != ParentProductID';  // Ensure we don't update the parent again (though harmless)

        $stmt_propagate = $conn->prepare($sql_propagate_category);

        // Values: Category, ParentProductID
        $stmt_propagate->bind_param('ss', $category, $productID);

        $stmt_propagate->execute();
        $stmt_propagate->close();
    }
    // ----------------------------------------------------------------------
    // --- END CATEGORY PROPAGATION -----------------------------------------
    // ----------------------------------------------------------------------

    // ----------------------------------------------------------------------
    // --- 2. CONDITIONAL UPDATE/INSERT PRODUCT ATTRIBUTES (VARIANTS ONLY) ---
    // ----------------------------------------------------------------------
    if (!$isParentUpdate) {
        // Check if a row exists in ProductAttributes for this ProductID
        $stmt_check = $conn->prepare('SELECT COUNT(*) FROM ProductAttributes WHERE ProductID=?');
        $stmt_check->bind_param('s', $productID);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        $attr_values = [];
        $attr_types = '';

        if ($count > 0) {
            // Row exists: PERFORM UPDATE
            $sql_attr = '
                UPDATE ProductAttributes SET 
                    SkinType = ?, SkinTone = ?, Undertone = ?, 
                    Acne = ?, Dryness = ?, DarkSpots = ?, 
                    Matte = ?, Dewy = ?, LongLasting = ?
                WHERE ProductID = ?';

            // Values = attributes + ProductID
            $attr_values = array_merge($attribute_data_for_insert_update, [$productID]);
            // Types = attributes types + 's' (for ProductID)
            $attr_types = $attribute_types_for_insert_update . 's';
        } else {
            // Row does NOT exist: PERFORM INSERT
            $sql_attr = '
                INSERT INTO ProductAttributes (ProductID, SkinType, SkinTone, Undertone, Acne, Dryness, DarkSpots, Matte, Dewy, LongLasting) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            // Values = ProductID + attributes
            $attr_values = array_merge([$productID], $attribute_data_for_insert_update);
            // Types = 's' (for ProductID) + attributes types
            $attr_types = 's' . $attribute_types_for_insert_update;
        }

        // Execute the final attribute query
        $stmt_attr = $conn->prepare($sql_attr);

        // Fix: create references for bind_param()
        $bindParams2 = array_merge([$attr_types], $attr_values);
        $refParams2 = [];
        foreach ($bindParams2 as $key => $value) {
            $refParams2[$key] = &$bindParams2[$key];
        }

        call_user_func_array([$stmt_attr, 'bind_param'], $refParams2);

        if (!$stmt_attr->execute()) {
            throw new Exception('ProductAttributes update/insert failed: ' . $stmt_attr->error);
        }
        $stmt_attr->close();
    }
    // ----------------------------------------------------------------------
    // --- END CONDITIONAL ATTRIBUTES UPDATE/INSERT ---
    // ----------------------------------------------------------------------

    // --- 3. IMAGE MEDIA HANDLING ---
    handle_media_update($conn, $productID, $isParentUpdate);

    // Commit the transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
} catch (Exception $e) {
    // Rollback on any failure
    $conn->rollback();
    http_response_code(500);
    die(json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]));
} finally {
    if (isset($conn))
        $conn->close();
}

function handleFileUpload($fileInputName, $targetDir = '../uploads/product_images/')
{
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $file = $_FILES[$fileInputName];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('img_') . time() . '.' . $ext;
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $targetFile;
    }
    return null;
}

function deleteOldFile($filePath)
{
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

// -----------------------------------------------------------------------------------
// IMPLEMENTATION OF handle_media_update (Inside update_product.php)
// -----------------------------------------------------------------------------------

function handle_media_update($conn, $productID, $isParent)
{
    $targetDir = '../uploads/product_images/';
    $parentID = $isParent ? $productID : getParentIDFromVariant($conn, $productID);
    $variantID = $isParent ? null : $productID;

    // 1. HANDLE DELETIONS (Gallery images deleted via hidden inputs from JS)
    $deletedPaths = $_POST['deleted_images'] ?? [];
    if (!empty($deletedPaths)) {
        $inQuery = str_repeat('?,', count($deletedPaths) - 1) . '?';

        // Find existing file paths in DB to physically delete them
        $stmt_select = $conn->prepare("SELECT ImagePath FROM ProductMedia WHERE ParentProductID = ? AND ImagePath IN ($inQuery)");
        $params = array_merge(['s'], [$parentID], $deletedPaths);
        call_user_func_array([$stmt_select, 'bind_param'], $params);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        $physical_delete_paths = [];
        while ($row = $result->fetch_assoc()) {
            $physical_delete_paths[] = $targetDir . basename($row['ImagePath']);
        }
        $stmt_select->close();

        // Delete records from the DB
        $stmt_delete = $conn->prepare("DELETE FROM ProductMedia WHERE ParentProductID = ? AND ImagePath IN ($inQuery)");
        call_user_func_array([$stmt_delete, 'bind_param'], $params);
        $stmt_delete->execute();
        $stmt_delete->close();

        // Physically delete files
        foreach ($physical_delete_paths as $path) {
            deleteOldFile($path);
        }
    }

    // 2. HANDLE MAIN/PREVIEW IMAGE UPDATE (Applies to Parent Product)
    if ($isParent) {
        $previewNewPath = handleFileUpload('previewImage', $targetDir);
        if ($previewNewPath) {
            // *** FIX: Fetch and Delete OLD File ***
            $oldPath = getOldImagePath($conn, $parentID, 'PREVIEW', true);
            if ($oldPath)
                deleteOldFile($targetDir . basename($oldPath));
            // Upsert (Update or Insert) the new Preview Image
            $stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, ImagePath, MediaType, SortOrder) 
                                    VALUES (?, ?, 'PREVIEW', 1) 
                                    ON DUPLICATE KEY UPDATE ImagePath = VALUES(ImagePath)");
            // NOTE: This requires a unique index on (ParentProductID, MediaType)
            $stmt->bind_param('ss', $parentID, $previewNewPath);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 3. HANDLE VARIANT IMAGE UPDATE (Applies to Variant)
    if (!$isParent) {
        $variantNewPath = handleFileUpload('variantImage', $targetDir);
        if ($variantNewPath) {
            // *** FIX: Fetch and Delete OLD File ***
            $oldPath = getOldImagePath($conn, $variantID, 'VARIANT', false);
            if ($oldPath)
                deleteOldFile($targetDir . basename($oldPath));
            // Upsert the new Variant Image
            $stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) 
                                    VALUES (?, ?, ?, 'VARIANT', 1) 
                                    ON DUPLICATE KEY UPDATE ImagePath = VALUES(ImagePath)");
            // NOTE: This requires a unique index on (VariantProductID, MediaType)
            $stmt->bind_param('sss', $parentID, $variantID, $variantNewPath);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 4. HANDLE ADDITIONAL GALLERY IMAGES (Applies to Parent Product)
    if ($isParent && isset($_FILES['detailImages'])) {
        // Find the current highest SortOrder to continue numbering new images
        $sortOrder = getCurrentMaxSortOrder($conn, $parentID, 'GALLERY') + 1;

        // Loop through the array of files
        $files = $_FILES['detailImages'];
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                // Re-run the file upload logic
                $file = [
                    'name' => $files['name'][$key],
                    'type' => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key],
                ];

                // Temporary set $_FILES for the helper
                $_FILES['temp_file'] = $file;

                // Call file upload (you may need to adapt your handleFileUpload if it doesn't support temp renaming)
                // Simpler approach: manual move and direct insertion
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $fileName = uniqid('gall_') . '_' . $key . time() . '.' . $ext;
                $targetFile = $targetDir . $fileName;

                if (move_uploaded_file($files['tmp_name'][$key], $targetFile)) {
                    $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, ImagePath, MediaType, SortOrder) VALUES (?, ?, 'GALLERY', ?)");
                    $media_stmt->bind_param('ssi', $parentID, $targetFile, $sortOrder);
                    $media_stmt->execute();
                    $sortOrder++;
                }
                unset($_FILES['temp_file']);  // Cleanup
            }
        }
    }
}

// -----------------------------------------------------------------------------------
// HELPER FUNCTIONS (Need to be defined in update_product.php)
// -----------------------------------------------------------------------------------
function getParentIDFromVariant($conn, $variantID)
{
    $stmt = $conn->prepare('SELECT ParentProductID FROM Products WHERE ProductID = ?');
    $stmt->bind_param('s', $variantID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['ParentProductID'] ?? null;
}

function getCurrentMaxSortOrder($conn, $parentID, $mediaType)
{
    $stmt = $conn->prepare('SELECT MAX(SortOrder) FROM ProductMedia WHERE ParentProductID = ? AND MediaType = ?');
    $stmt->bind_param('ss', $parentID, $mediaType);
    $stmt->execute();
    $result = $stmt->get_result();
    $max = $result->fetch_row()[0] ?? 0;
    $stmt->close();
    return (int) $max;
}

function getOldImagePath($conn, $id, $mediaType, $isParent)
{
    // Check if Parent (PREVIEW) or Variant (VARIANT) media type
    $idColumn = $isParent ? 'ParentProductID' : 'ProductID';
    $idValue = $id;

    // Use ProductID for Variant Media, ParentProductID for Preview/Gallery Media
    $sql = "SELECT ImagePath FROM ProductMedia WHERE {$idColumn} = ? AND MediaType = ?";

    // If it's the Parent's media type (PREVIEW/GALLERY), use ParentProductID
    if ($isParent && ($mediaType === 'PREVIEW' || $mediaType === 'GALLERY')) {
        $idColumn = 'ParentProductID';
    } elseif (!$isParent && $mediaType === 'VARIANT') {
        // If it's a Variant, we use VariantProductID which is the $productID for the variant
        $idColumn = 'VariantProductID';
    }

    $stmt = $conn->prepare("SELECT ImagePath FROM ProductMedia WHERE {$idColumn} = ? AND MediaType = ?");
    $stmt->bind_param('ss', $idValue, $mediaType);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['ImagePath'] ?? null;
}

?>