<?php
// fetch_existing_variant.php
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

header('Content-Type: application/json');

if (!isset($_GET['parent_id']) || empty($_GET['parent_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing Parent Product ID.']));
}

$parentID = $_GET['parent_id'];

try {
    // Fetch any existing variant for this parent to use as template
    $sql = '
    SELECT 
        p.ProductID, 
        p.Name, 
        p.Description, 
        p.Ingredients,
        p.Stocks,
        p.Price, 
        p.ExpirationDate,
        p.HexCode,
        p.ShadeOrVariant,
        
        a.SkinType,
        a.SkinTone,
        a.Undertone,
        a.Acne,
        a.Dryness,
        a.DarkSpots,
        a.Matte,
        a.Dewy,
        a.LongLasting
    FROM Products p
    LEFT JOIN ProductAttributes a ON p.ProductID = a.ProductID
    WHERE p.ParentProductID = ? 
    AND p.ProductID != p.ParentProductID
    LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $parentID);
    $stmt->execute();
    $result = $stmt->get_result();
    $variant = $result->fetch_assoc();
    $stmt->close();

    if (!$variant) {
        echo json_encode(['exists' => false]);
    } else {
        // Debug output to see what's actually being returned
        error_log("Variant data fetched: " . print_r($variant, true));
        
        echo json_encode([
            'exists' => true, 
            'data' => $variant
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>