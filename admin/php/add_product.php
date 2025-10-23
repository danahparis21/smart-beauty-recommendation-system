<?php
// ... (Your existing config and input collection) ...
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// --- COLLECT ALL INPUTS ---
// The following variables are collected from your form.
$type = $_POST['productType'];
$name = $_POST['productName'];
$category = $_POST['productCategory'];
$variantName = $_POST['productVariantName'];
$price = $_POST['productPrice'];
$description = $_POST['productDescription'];
$stocks = $_POST['productStock'];
$expiration = $_POST['productExpiration'] ?? null;

// --- DUMMY/NULL VALUES FOR UNUSED COLUMNS (MATCHES YOUR SCHEMA) ---
// Initialize placeholders for columns not yet in your form
$ingredients = null;
$imagePath = null;
$previewImage = null;
$hexCode = null;
$productRating = 0; // Assuming default 0 or NULL

// --- ATTRIBUTE COLLECTION (Needs form data to work fully) ---
// For now, these are NULL/FALSE, but you need to read them from $_POST later
$skinType = null;
$skinTone = null;
$undertone = null;
$acne = 0; // Use 0/1 for boolean/TINYINT fields
$dryness = 0;
$darkSpots = 0;
$matte = 0;
$dewy = 0;
$longLasting = 0;

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
    
    // 3. Format the new ID (e.g., CONC001)
    // 🛑 CHANGE 2: Use 3 digits for the sequence length
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
}


// --- LOGIC FOR NEW VARIANT (type = "variant") ---
if ($type === "variant") {
    $parentProductID = $_POST['parentProductID'];
    $variantID = uniqid("VAR");

    // Select Name, Category, Description, etc., from the Parent, and use new values for Variant-specific fields
    // NOTE: Price and Stocks, Ingredients, ImagePath, ExpirationDate must NOT be inherited from parent (Parent has 0.00 and 0 for price/stock)
    $stmt = $conn->prepare("INSERT INTO Products (ProductID, Name, Category, ParentProductID, ShadeOrVariant, Price, Description, Stocks, ExpirationDate, Ingredients, ImagePath, PreviewImage, HexCode, ProductRating)
                            SELECT 
                                ?, 
                                Name, 
                                Category, 
                                ?, 
                                ?, 
                                ?,              -- Price (from POST)
                                Description, 
                                ?,              -- Stocks (from POST)
                                ?,              -- ExpirationDate (from POST)
                                Ingredients, 
                                ImagePath, 
                                PreviewImage, 
                                HexCode, 
                                ProductRating 
                            FROM Products 
                            WHERE ProductID = ?");
    
    // Bind: ssssdsss
    $stmt->bind_param("sssdsis", 
        $variantID,         // 1. s (string)
        $parentProductID,   // 2. s (string)
        $variantName,       // 3. s (string)
        $price,             // 4. d (double/decimal)
        $stocks,            // 5. i (integer)
        $expiration,        // 6. s (string/date)
        $parentProductID    // 7. s (string)
    );
    $stmt->execute(); // <-- Now this will work!
    
    // 2. Insert ATTRIBUTES for the new variant
    insertAttributes($conn, $variantID, $skinType, $skinTone, $undertone, $acne, $dryness, $darkSpots, $matte, $dewy, $longLasting);

    echo "✅ New variant added successfully! (Variant ID: $variantID)";
}
?>