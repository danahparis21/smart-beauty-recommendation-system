<?php
// ADD THIS AT THE VERY TOP
error_log('=== RAW POST DATA ===');
error_log(print_r($_POST, true));
error_log('=== RAW FILES DATA ===');
error_log(print_r($_FILES, true));

while (ob_get_level()) {
    ob_end_clean();
}

session_start();

// Prevent caching for admin pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Admin privileges required.']));
}

$adminId = $_SESSION['user_id'];

// Your database connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set admin ID for triggers
$conn->query("SET @admin_user_id = $adminId");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ADD DEBUGGING - Log that we're starting
error_log('=== ADD_PRODUCT.PHP STARTED ===');
error_log('Product Type: ' . ($_POST['productType'] ?? 'not set'));

// --- IMAGE UPLOAD HELPER FUNCTION ---
function handleFileUpload($fileInputName, $targetDir = 'uploads/')
{
    error_log("Handling file upload for: $fileInputName");
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        error_log("File upload failed or not set: $fileInputName");
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
        error_log("File uploaded successfully: $targetFile");
        return $targetFile;
    }
    error_log("File upload failed: $fileInputName");
    return null;
}

// --- COLLECT ALL INPUTS ---
$type = $_POST['productType'] ?? 'unknown';
$name = $_POST['productName'] ?? '';
$variantName = $_POST['productVariantName'] ?? '';
$price = $_POST['productPrice'] ?? '';
$description = $_POST['productDescription'] ?? '';
$stocks = $_POST['productStock'] ?? '';
$expirationDate = $_POST['productExpiration'] ?? null;

error_log("Collected inputs - Type: $type, Name: $name, Variant: $variantName");

// Validate expiration date
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

$hexCode = $_POST['hexCode'] ?? null;
if ($hexCode === '')
    $hexCode = null;

$ingredients = $_POST['productIngredients'] ?? NULL;
$category = null;

if ($type === 'new') {
    $category = $_POST['productCategory'] ?? null;
    if (!$category) {
        error_log('Category missing for new product line');
        echo '❌ Error: Category is required for new product lines.';
        exit;
    }
}

// --- ATTRIBUTE COLLECTION ---
$skinType = isset($_POST['skinType']) ? implode(',', $_POST['skinType']) : null;
$skinTone = isset($_POST['skinTone']) ? implode(',', $_POST['skinTone']) : null;

$undertone = $_POST['undertone'] ?? null;
if ($undertone === '' || strtolower($undertone) === 'none') {
    $undertone = null;
}

$acne = isset($_POST['acne']) ? 1 : 0;
$dryness = isset($_POST['dryness']) ? 1 : 0;
$darkSpots = isset($_POST['darkSpots']) ? 1 : 0;

$matte = isset($_POST['matte']) ? 1 : 0;
$dewy = isset($_POST['dewy']) ? 1 : 0;
$longLasting = isset($_POST['longLasting']) ? 1 : 0;

error_log("Attributes collected - SkinType: $skinType, SkinTone: $skinTone, Undertone: $undertone");

// --- DUMMY---
$productRating = 0;

// --- IMAGE UPLOAD---
$targetDir = '../uploads/product_images/';
$variantImagePath = handleFileUpload('variantImage', $targetDir);
$previewImagePath = handleFileUpload('previewImage', $targetDir);

error_log('Variant image path: ' . ($variantImagePath ?? 'null'));
error_log('Preview image path: ' . ($previewImagePath ?? 'null'));

// Function to handle inserting attributes
function insertAttributes($conn, $productID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting)
{
    error_log("Inserting attributes for product: $productID");

    $stmt = $conn->prepare('INSERT INTO ProductAttributes (ProductID, SkinType, SkinTone, Undertone, Acne, Dryness, DarkSpots, Matte, Dewy, LongLasting) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    if (!$stmt) {
        error_log('Attributes prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('ssssiiiiii', $productID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);

    $result = $stmt->execute();

    if (!$result) {
        error_log('Attributes execute failed: ' . $stmt->error);
        return false;
    }

    error_log("Attributes inserted successfully for product: $productID");
    return true;
}

// In your getNextProductID function, add debugging:
function getNextProductID($conn, $category)
{
    error_log("Getting next product ID for category: $category");

    $prefixMap = [
        'Blush On' => 'BLUSH',
        'Eyeliner' => 'EYLN',
        'Eyeshadow' => 'EYE',
        'Eyebrow' => 'EBR',
        'Concealer' => 'CONC',
        'Body Care' => 'BODY',
        'Face Care' => 'FACE',
        'Foundation' => 'FDN',
        'Highlighter' => 'HIGH',
        'Lipstick' => 'LIP',
        'Mascara' => 'MASC',
        'Makeup Tools' => 'MTOOL',
        'Nails' => 'NAIL',
        'Powder' => 'PDR',
        'Hair Care' => 'HAIR',
        'Contact Lense' => 'CONLENSE',
    ];

    $prefix = $prefixMap[$category] ?? 'PROD';
    error_log("Using prefix: $prefix");

    $sql = "SELECT ProductID FROM Products WHERE ProductID LIKE '{$prefix}___' ORDER BY ProductID DESC LIMIT 1";
    error_log("SQL: $sql");

    $result = $conn->query($sql);
    $nextSequence = 1;

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastID = $row['ProductID'];
        error_log("Last ID found: $lastID");

        if (preg_match("/{$prefix}(\d+)/", $lastID, $matches)) {
            $lastSequence = (int) $matches[1];
            $nextSequence = $lastSequence + 1;
            error_log("Last sequence: $lastSequence, Next sequence: $nextSequence");
        }
    } else {
        error_log("No existing products found for prefix: $prefix");
    }

    $newID = $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    error_log("Generated new ID: $newID");
    return $newID;
}

// --- LOGIC FOR NEW PRODUCT LINE (type = "new") ---
if ($type === 'new') {
    error_log('=== PROCESSING NEW PRODUCT LINE ===');

    // Generate SEQUENTIAL Variant ID (from Category CONC001)
    $variantID = getNextProductID($conn, $category);
    error_log("Variant ID generated: $variantID");

    // Generate UNIQUE Parent ID (from Name ANOTHER01)
    $prefix = strtoupper(substr(strtok($name, ' '), 0, 4));
    $parentProductID = $prefix . '01';
    error_log("Initial parent ID: $parentProductID");

    // Parent ID is unique
    $counter = 1;
    $checkID = $parentProductID;
    while ($conn->query("SELECT ProductID FROM Products WHERE ProductID = '{$checkID}'")->num_rows > 0) {
        $counter++;
        $checkID = $prefix . str_pad($counter, 2, '0', STR_PAD_LEFT);
        error_log("Parent ID conflict, trying: $checkID");
    }
    $parentProductID = $checkID;
    error_log("Final parent ID: $parentProductID");

    // Create PARENT PRODUCT record
    error_log('Creating parent product...');
    $parentName = "Parent Record: $name";
    $desc = "Parent product record for $name shades.";

    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating) VALUES (?, ?, ?, NULL, 'PARENT_GROUP', 0.00, ?, 0, NULL, ?, NULL, ?)");

    if (!$stmt) {
        error_log('Parent product prepare failed: ' . $conn->error);
        die('❌ Database error preparing parent product');
    }

    if (!$ingredients)
        $ingredients = NULL;
    if (!$productRating)
        $productRating = 0;

    $stmt->bind_param('sssssi', $parentProductID, $parentName, $category, $desc, $ingredients, $productRating);

    if (!$stmt->execute()) {
        error_log('Parent product execute failed: ' . $stmt->error);
        die('❌ Error creating parent product: ' . $stmt->error);
    }
    error_log('Parent product created successfully');

    // Insert FIRST VARIANT record
    error_log('Creating variant product...');
    $stmt = $conn->prepare('INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    if (!$stmt) {
        error_log('Variant product prepare failed: ' . $conn->error);
        die('❌ Database error preparing variant product');
    }

    $stmt->bind_param('sssssdsisssi', $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expirationDate, $ingredients, $hexCode, $productRating);

    if (!$stmt->execute()) {
        error_log('Variant product execute failed: ' . $stmt->error);
        die('❌ Error inserting variant: ' . $stmt->error);
    }
    error_log('Variant product created successfully');

    // INSERT INTO ProductMedia for Variant-Specific Image
    if ($variantImagePath) {
        error_log('Adding variant image media...');
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, ?, ?, 'VARIANT', 1)");
        if ($media_stmt) {
            $media_stmt->bind_param('sss', $parentProductID, $variantID, $variantImagePath);
            if (!$media_stmt->execute()) {
                error_log('Variant image media insert failed: ' . $media_stmt->error);
            } else {
                error_log('Variant image media inserted successfully');
            }
        } else {
            error_log('Variant image media prepare failed: ' . $conn->error);
        }
    }

    // INSERT INTO ProductMedia for Preview/Hover Image
    if ($previewImagePath) {
        error_log('Adding preview image media...');
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, NULL, ?, 'PREVIEW', 1)");
        if ($media_stmt) {
            $media_stmt->bind_param('ss', $parentProductID, $previewImagePath);
            if (!$media_stmt->execute()) {
                error_log('Preview image media insert failed: ' . $media_stmt->error);
            } else {
                error_log('Preview image media inserted successfully');
            }
        } else {
            error_log('Preview image media prepare failed: ' . $conn->error);
        }
    }

    // INSERT INTO ProductMedia for Additional Gallery Images
    if (isset($_FILES['detailImages'])) {
        error_log('Processing gallery images...');
        $sortOrder = 1;

        foreach ($_FILES['detailImages']['name'] as $key => $name) {
            if ($_FILES['detailImages']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['detailImages']['tmp_name'][$key];
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $fileName = uniqid('gall_') . '_' . $key . time() . '.' . $ext;
                $targetFile = $targetDir . $fileName;

                if (move_uploaded_file($tmp_name, $targetFile)) {
                    $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, NULL, ?, 'GALLERY', ?)");
                    if ($media_stmt) {
                        $media_stmt->bind_param('ssi', $parentProductID, $targetFile, $sortOrder);
                        if (!$media_stmt->execute()) {
                            error_log('Gallery image media insert failed: ' . $media_stmt->error);
                        } else {
                            error_log('Gallery image media inserted successfully');
                        }
                        $sortOrder++;
                    } else {
                        error_log('Gallery image media prepare failed: ' . $conn->error);
                    }
                }
            }
        }
    }

    // Insert attributes
    if (!insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting)) {
        error_log("Failed to insert attributes for variant: $variantID");
        // Don't die here, just log the error and continue
    }

    // At the end, make sure to output the success message
    error_log('=== PRODUCT CREATION COMPLETED SUCCESSFULLY ===');
    echo "✅ New product line and first variant added successfully! (Parent ID: $parentProductID, Variant ID: $variantID)";
    exit;
}

// --- LOGIC FOR NEW VARIANT (type = "variant") ---
if ($type === 'variant') {
    // Get Parent ID
    $parentProductID = $_POST['parentProductID'];

    $stmt_parent = $conn->prepare('SELECT Category, Name, Description FROM Products WHERE ProductID = ?');
    $stmt_parent->bind_param('s', $parentProductID);
    $stmt_parent->execute();
    $result_parent = $stmt_parent->get_result();

    if ($result_parent->num_rows === 0) {
        echo '❌ Error: Parent Product ID not found.';
        exit;
    }

    $parentData = $result_parent->fetch_assoc();
    $category = $parentData['Category'];

    $name = str_replace('Parent Record: ', '', $parentData['Name']);

    $description = $_POST['productDescription'];

    $variantID = getNextProductID($conn, $category);  // EYLN006

    // Insert NEW VARIANT record
    $stmt = $conn->prepare('INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $stmt->bind_param('sssssdsisssi', $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expirationDate, $ingredients, $hexCode, $productRating);

    if (!$stmt->execute()) {
        echo '❌ Error inserting variant: ' . $stmt->error;
        exit;
    }

    if ($variantImagePath) {
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, ?, ?, 'VARIANT', 1)");
        $media_stmt->bind_param('sss', $parentProductID, $variantID, $variantImagePath);
        $media_stmt->execute();
    }

    insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);
    echo "✅ New variant added successfully! (Variant ID: $variantID)";
    exit;
}

echo "❌ Unknown product type: $type";
?>