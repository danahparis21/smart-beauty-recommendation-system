<?php
// ... (Your existing config and input collection) ...
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// --- IMAGE UPLOAD HELPER FUNCTION ---
function handleFileUpload($fileInputName, $targetDir = "uploads/") {
    // Check if a file was actually uploaded for this input name
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Ensure the target directory exists and is writable
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $file = $_FILES[$fileInputName];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Create a unique file name using a prefix and timestamp
    $fileName = uniqid("img_") . time() . "." . $ext; 
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        // Return the path
        return $targetFile;
    }
    return null;
}
// --- COLLECT ALL INPUTS ---
$type = $_POST['productType'];
$name = $_POST['productName'];
$category = $_POST['productCategory'] ?? null;
$variantName = $_POST['productVariantName'];
$price = $_POST['productPrice'];
$description = $_POST['productDescription'];
$stocks = $_POST['productStock'];
$expiration = $_POST['productExpiration'] ?? null;
$hexCode = $_POST['hexCode'] ?? null;
if ($hexCode === '') $hexCode = null;

// --- DUMMY/NULL VALUES FOR UNUSED COLUMNS---
$ingredients = null;
$productRating = 0;

// --- IMAGE UPLOAD COLLECTION ---
$targetDir = "../uploads/product_images/";
// 🛑 NEW: Call the helper function to get the paths
$variantImagePath = handleFileUpload('variantImage', $targetDir);
$previewImagePath = handleFileUpload('previewImage', $targetDir);

// --- ATTRIBUTE COLLECTION (NEW LOGIC) ---

$skinType = isset($_POST['skinType']) ? implode(',', $_POST['skinType']) : null;
$skinTone = isset($_POST['skinTone']) ? implode(',', $_POST['skinTone']) : null;

// 2. Dropdown Attributes (Undertone)
$undertone = $_POST['undertone'] ?? null;
// Set to NULL if 'None' is selected or if no value is present
if ($undertone === '' || strtolower($undertone) === 'none') {
    $undertone = null;
}

// 3. Boolean/TINYINT (0 or 1) Attributes
$acne = isset($_POST['acne']) ? 1 : 0;
$dryness = isset($_POST['dryness']) ? 1 : 0;
$darkSpots = isset($_POST['darkSpots']) ? 1 : 0;

$matte = isset($_POST['matte']) ? 1 : 0;
$dewy = isset($_POST['dewy']) ? 1 : 0;
$longLasting = isset($_POST['longLasting']) ? 1 : 0;



function getNextProductID($conn, $category) {
    // 1. Define the Prefix Map (No change needed here)
    $prefixMap = [
        'Blush On' => 'BLUS',
        'Eyeliner' => 'EYLN',
        'Eyeshadow' => 'EYE',
        'Eyebrow'  => 'EBR',
        'Concealer'=> 'CONC',
        'Body Care'=> 'BODY',
        // Add more categories as needed
    ];

    // Get the prefix. If category is new/unknown, use a generic prefix like 'PROD'.
    $prefix = $prefixMap[$category] ?? 'PROD';
    
    // 2. Find the highest existing sequence number for this prefix
    $sql = "SELECT ProductID FROM Products WHERE ProductID LIKE '{$prefix}___' ORDER BY ProductID DESC LIMIT 1";
    $result = $conn->query($sql);
    
    $nextSequence = 1;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastID = $row['ProductID'];
        
        // Extract the number part from the end (e.g., CONC001 -> 001)
        if (preg_match("/{$prefix}(\d+)/", $lastID, $matches)) {
            $lastSequence = (int)$matches[1];
            $nextSequence = $lastSequence + 1;
        }
    }
    
  
    $newID = $prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
    return $newID;
}

// Function to handle inserting attributes (called by both blocks)
function insertAttributes($conn, $productID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting) {
    $stmt = $conn->prepare("INSERT INTO ProductAttributes (ProductID, SkinType, SkinTone, Undertone, Acne, Dryness, DarkSpots, Matte, Dewy, LongLasting)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Bind parameters: sssssiiiiii (string x4, integer x6)
    $stmt->bind_param("ssssiiiiii", 
        $productID, 
        $skinType, 
        $skinTone, 
        $undertone, 
        $acne, 
        $dryness, 
        $darkSpots, 
        $matte, 
        $dewy, 
        $longLasting
    );
    return $stmt->execute();
}

// --- LOGIC FOR NEW PRODUCT LINE (type = "new") ---
if ($type === "new") {
    
    // 🛑 1. Generate SEQUENTIAL Variant ID (from Category, e.g., CONC0001)
    $variantID = getNextProductID($conn, $category); 
    
    // 🛑 2. Generate UNIQUE Parent ID (from Name, e.g., ANOTHER01)
    $prefix = strtoupper(substr(strtok($name, " "), 0, 4));
    $parentProductID = $prefix . "01";
    
    // Check database to ensure this Parent ID is unique
    $counter = 1;
    $checkID = $parentProductID;
    while ($conn->query("SELECT ProductID FROM Products WHERE ProductID = '{$checkID}'")->num_rows > 0) {
        $counter++;
        $checkID = $prefix . str_pad($counter, 2, '0', STR_PAD_LEFT);
    }
    $parentProductID = $checkID; // e.g., ANOTHER01, ANOTHER02, etc.
    // ----------------------------------------------------

    // 1. Create PARENT PRODUCT record (12 Columns)
    $parentName = "Parent Record: $name";
    $desc = "Parent product record for $name shades.";
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating)
                             VALUES (?, ?, ?, NULL, 'PARENT_GROUP', 0.00, ?, 0, NULL, ?, NULL, ?)");
    // Bind parameters: ssss (for ID, Name, Category, Desc), s (for Ingredients), i (for Rating) 
    $stmt->bind_param("sssssi", $parentProductID, $parentName, $category, $desc, $ingredients, $productRating);
    $stmt->execute();


   // 2. Insert FIRST VARIANT record (12 Columns)
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Bind parameters: sssss (for strings), d (for price), s (for desc), i (for stocks), s (for exp), s (for ingr), s (for hex), i (for rating)
    $stmt->bind_param("sssssdsisssi", 
        $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expiration, 
        $ingredients, $hexCode, $productRating // Hex Code is inserted, Image paths are gone!
    );
    $stmt->execute();

    // 3. INSERT INTO ProductMedia for Variant-Specific Image 🛑 NEW STEP
    if ($variantImagePath) {
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, ?, ?, 'VARIANT', 1)");
        $media_stmt->bind_param("sss", $parentProductID, $variantID, $variantImagePath);
        $media_stmt->execute();
    }

    //4. INSERT INTO ProductMedia for Preview/Hover Image
    if ($previewImagePath) {
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, NULL, ?, 'PREVIEW', 1)");
        $media_stmt->bind_param("ss", $parentProductID, $previewImagePath);
        $media_stmt->execute();
    }


    // 5. INSERT INTO ProductMedia for Additional Gallery Images
    if (isset($_FILES['detailImages'])) {
        $sortOrder = 1;
        // Loop through the multiple file array structure
        foreach ($_FILES['detailImages']['name'] as $key => $name) {
            if ($_FILES['detailImages']['error'][$key] === UPLOAD_ERR_OK) {
                
                // Manually handle the upload for this specific file from the array
                $tmp_name = $_FILES['detailImages']['tmp_name'][$key];
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $fileName = uniqid("gall_") . "_" . $key . time() . "." . $ext;
                $targetFile = $targetDir . $fileName;

                if (move_uploaded_file($tmp_name, $targetFile)) {
                    $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, NULL, ?, 'GALLERY', ?)");
                    $media_stmt->bind_param("ssi", $parentProductID, $targetFile, $sortOrder);
                    $media_stmt->execute();
                    $sortOrder++;
                }
            }
        }
    }
    
    // 6. Insert ATTRIBUTES for the variant
    insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);

    echo "✅ New product line and first variant added successfully! (Parent ID: $parentProductID, Variant ID: $variantID)";
    exit;
}


// --- LOGIC FOR NEW VARIANT (type = "variant") ---
if ($type === "variant") {
    // 1. Get Parent ID from form
    $parentProductID = $_POST['parentProductID'];

    // 2. Look up the Category from the Parent record in the DB
    $stmt_parent = $conn->prepare("SELECT Category, Name, Description FROM Products WHERE ProductID = ?");
    $stmt_parent->bind_param("s", $parentProductID);
    $stmt_parent->execute();
    $result_parent = $stmt_parent->get_result();
    
    if ($result_parent->num_rows === 0) {
        echo "❌ Error: Parent Product ID not found.";
        exit;
    }

    $parentData = $result_parent->fetch_assoc();
    $category = $parentData['Category']; // Now we have the category!
    // 🛑 NAME CLEANUP: Remove "Parent Record: " from the inherited name.
    $name = str_replace('Parent Record: ', '', $parentData['Name']);
    
    $description = $_POST['productDescription'];
    
    // 3. Generate SEQUENTIAL Variant ID based on the looked-up Category
    $variantID = getNextProductID($conn, $category); // E.g., EYLN006

    
    // 4. Insert NEW VARIANT record (12 Columns)
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, HexCode, ProductRating)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Bind: sssssdsisssi
    $stmt->bind_param("sssssdsisssi", 
        $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expiration, 
        $ingredients, $hexCode, $productRating // Hex Code is inserted, Image paths are gone!
    );
    
    if (!$stmt->execute()) {
        echo "❌ Error inserting variant: " . $stmt->error;
        exit;
    }
    
    // 5. INSERT INTO ProductMedia for Variant-Specific Image 🛑 NEW STEP
    if ($variantImagePath) {
        $media_stmt = $conn->prepare("INSERT INTO ProductMedia (ParentProductID, VariantProductID, ImagePath, MediaType, SortOrder) VALUES (?, ?, ?, 'VARIANT', 1)");
        $media_stmt->bind_param("sss", $parentProductID, $variantID, $variantImagePath);
        $media_stmt->execute();
    }

    // 6. Insert ATTRIBUTES for the new variant (Original Step 5)
    insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);
    echo "✅ New variant added successfully! (Variant ID: $variantID)";
    exit;
}
?>