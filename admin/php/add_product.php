<?php
// ... (Your existing config and input collection) ...
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
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

// --- DUMMY/NULL VALUES FOR UNUSED COLUMNS (MATCHES YOUR SCHEMA) ---
$ingredients = null;
$imagePath = null;
$previewImage = null;
$hexCode = null;
$productRating = 0; // Assuming default 0 or NULL

// // --- ATTRIBUTE COLLECTION (Needs form data to work fully) ---
// $skinType = null;
// $skinTone = null;
// $undertone = null;
// $acne = 0; // Use 0/1 for boolean/TINYINT fields
// $dryness = 0;
// $darkSpots = 0;
// $matte = 0;
// $dewy = 0;
// $longLasting = 0;

// --- ATTRIBUTE COLLECTION (NEW LOGIC) ---
// 1. Array/String Attributes (Skin Type, Skin Tone)
// If an array exists, implode it with a comma. If not, set to NULL.
$skinType = isset($_POST['skinType']) ? implode(',', $_POST['skinType']) : null;
$skinTone = isset($_POST['skinTone']) ? implode(',', $_POST['skinTone']) : null;

// 2. Dropdown Attributes (Undertone)
$undertone = $_POST['undertone'] ?? null;
// Set to NULL if 'None' is selected or if no value is present
if ($undertone === '' || strtolower($undertone) === 'none') {
    $undertone = null;
}

// 3. Boolean/TINYINT (0 or 1) Attributes
// If the checkbox name exists in $_POST, it means it was checked (value 1). If not, it's 0.
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
    // 🛑 CHANGE 1: Use three underscores (___) to match CONC001 format
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

    // 1. Create PARENT PRODUCT record (Using $parentProductID)
$parentName = "Parent Record: $name";
$desc = "Parent product record for $name shades.";
$stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, ImagePath, PreviewImage, HexCode, ProductRating)
                         VALUES (?, ?, ?, NULL, 'PARENT_GROUP', 0.00, ?, 0, NULL, ?, ?, ?, ?, ?)");
// Correct Type string for the 9 variables:
$stmt->bind_param("ssssssssi", 
    $parentProductID, $parentName, $category, $desc, 
    $ingredients, $imagePath, $previewImage, $hexCode, $productRating
);
$stmt->execute();


    // 2. Insert FIRST VARIANT record (Using $variantID)
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, ImagePath, PreviewImage, HexCode, ProductRating)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // CORRECTED BIND PARAM FOR 14 variables: ProductID(s), Name(s), Category(s), ParentID(s), VariantName(s), Price(d), Description(s), Stocks(i), ExpDate(s), Ing(s), Path(s), Preview(s), Hex(s), Rating(i)
    $stmt->bind_param("sssssdsisssssi", 
        $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expiration, 
        $ingredients, $imagePath, $previewImage, $hexCode, $productRating
    );
    $stmt->execute();

    // 3. Insert ATTRIBUTES for the variant
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

    // 4. Insert NEW VARIANT record
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, ImagePath, PreviewImage, HexCode, ProductRating)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Bind: sssssdsisssssi
    $stmt->bind_param("sssssdsisssssi", 
        $variantID, $name, $category, $parentProductID, $variantName, $price, $description, $stocks, $expiration, 
        $ingredients, $imagePath, $previewImage, $hexCode, $productRating
    );
    
    if (!$stmt->execute()) {
        echo "❌ Error inserting variant: " . $stmt->error;
        exit;
    }
    
    // 5. Insert ATTRIBUTES for the new variant
    insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);
    
    echo "✅ New variant added successfully! (Variant ID: $variantID)";
    exit;
}
?>