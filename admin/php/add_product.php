<?php

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- IMAGE UPLOAD HELPER FUNCTION ---
function handleFileUpload($fileInputName, $targetDir = 'uploads/')
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

// --- COLLECT ALL INPUTS ---
$type = $_POST['productType'];
$name = $_POST['productName'];
// $category = $_POST['productCategory'];
$variantName = $_POST['productVariantName'];
$price = $_POST['productPrice'];
$description = $_POST['productDescription'];
$stocks = $_POST['productStock'];
// In your add_product.php around line 251, add this:
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
$hexCode = $_POST['hexCode'] ?? null;
if ($hexCode === '')
    $hexCode = null;
$ingredients = $_POST['productIngredients'] ?? NULL;
$category = null;
if ($type === 'new') {
    $category = $_POST['productCategory'] ?? null;
    if (!$category) {
        echo '❌ Error: Category is required for new product lines.';
        exit;
    }
}

// --- DUMMY---
$productRating = 0;

// --- IMAGE UPLOAD---
$targetDir = '../uploads/product_images/';
$variantImagePath = handleFileUpload('variantImage', $targetDir);
$previewImagePath = handleFileUpload('previewImage', $targetDir);

// --- ATTRIBUTE COLLECTION---

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

function getNextProductID($conn, $category)
{
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

    $sql = "SELECT ProductID FROM Products WHERE ProductID LIKE '{$prefix}___' ORDER BY ProductID DESC LIMIT 1";
    $result = $conn->query($sql);

    $nextSequence = 1;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastID = $row['ProductID'];

        // Extract the number part from the end (e.g., CONC001 -> 001)
        if (preg_match("/{$prefix}(\d+)/", $lastID, $matches)) {
            $lastSequence = (int) $matches[1];
            $nextSequence = $lastSequence + 1;
        }
    }

    $newID = $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    return $newID;
}

// Function to handle inserting attributes
function insertAttributes($conn, $productID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting)
{
    $stmt = $conn->prepare('INSERT INTO ProductAttributes (ProductID, SkinType, SkinTone, Undertone, Acne, Dryness, DarkSpots, Matte, Dewy, LongLasting)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $stmt->bind_param('ssssiiiiii',
        $productID,
        $skinType,
        $skinTone,
        $undertone,
        $acne,
        $dryness,
        $darkSpots,
        $matte,
        $dewy,
        $longLasting);
    return $stmt->execute();
}

// --- LOGIC FOR NEW PRODUCT LINE (type = "new") ---
if ($type === 'new') {
    // Generate SEQUENTIAL Variant ID (from Category CONC001)
    $variantID = getNextProductID($conn, $category);

    // Generate UNIQUE Parent ID (from Name ANOTHER01)
    $prefix = strtoupper(substr(strtok($name, ' '), 0, 4));
    $parentProductID = $prefix . '01';

    // Parent ID is unique
    $counter = 1;
    $checkID = $parentProductID;
    while ($conn->query("SELECT ProductID FROM Products WHERE ProductID = '{$checkID}'")->num_rows > 0) {
        $counter++;
        $checkID = $prefix . str_pad($counter, 2, '0', STR_PAD_LEFT);
    }
    $parentProductID = $checkID;  // ANOTHER01, ANOTHER02, etc.
    // ----------------------------------------------------

    // Create PARENT PRODUCT record (12 Columns)
    $parentName = "Parent Record: $name";
    $desc = "Parent product record for $name shades.";
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating)
                          VALUES (?, ?, ?, NULL, 'PARENT_GROUP', 0.00, ?, 0, NULL, ?, NULL, ?)");

    if (!$ingredients)
        $ingredients = NULL;
    if (!$productRating)
        $productRating = 0;
    $stmt->bind_param('sssssi', $parentProductID, $parentName, $category, $desc, $ingredients, $productRating);
    $stmt->execute();

    // 🆕 FIX: Use $expirationDate instead of $expiration
    // Insert FIRST VARIANT record (12 Columns)
    $stmt = $conn->prepare('INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    // 🆕 FIX: Use $expirationDate in bind_param
    $stmt->bind_param('sssssdsisssi',
        $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expirationDate,
        $ingredients, $hexCode, $productRating);
    $stmt->execute();

    // INSERT INTO ProductMedia for Variant-Specific Image
    if ($variantImagePath) {
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, ?, ?, 'VARIANT', 1)");
        $media_stmt->bind_param('sss', $parentProductID, $variantID, $variantImagePath);
        $media_stmt->execute();
    }

    // INSERT INTO ProductMedia for Preview/Hover Image
    if ($previewImagePath) {
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, NULL, ?, 'PREVIEW', 1)");
        $media_stmt->bind_param('ss', $parentProductID, $previewImagePath);
        $media_stmt->execute();
    }

    // INSERT INTO ProductMedia for Additional Gallery Images
    if (isset($_FILES['detailImages'])) {
        $sortOrder = 1;

        foreach ($_FILES['detailImages']['name'] as $key => $name) {
            if ($_FILES['detailImages']['error'][$key] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['detailImages']['tmp_name'][$key];
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $fileName = uniqid('gall_') . '_' . $key . time() . '.' . $ext;
                $targetFile = $targetDir . $fileName;

                if (move_uploaded_file($tmp_name, $targetFile)) {
                    $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, NULL, ?, 'GALLERY', ?)");
                    $media_stmt->bind_param('ssi', $parentProductID, $targetFile, $sortOrder);
                    $media_stmt->execute();
                    $sortOrder++;
                }
            }
        }
    }

    insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);

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

    // 🆕 FIX: Use $expirationDate instead of $expiration
    // Insert NEW VARIANT record (12 Columns)
    $stmt = $conn->prepare('INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    // 🆕 FIX: Use $expirationDate in bind_param
    $stmt->bind_param('sssssdsisssi',
        $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expirationDate,
        $ingredients, $hexCode, $productRating);

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
?>