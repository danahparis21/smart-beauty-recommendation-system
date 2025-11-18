<?php
// get_product_attributes.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? null;

    if (!$productId) {
        echo json_encode(['success' => false, 'error' => 'Product ID is required']);
        exit;
    }

    // Query to get product attributes
    $query = "
        SELECT 
            pa.SkinTone, 
            pa.Undertone, 
            pa.SkinType,
            p.Name,
            p.ShadeOrVariant
        FROM productattributes pa
        JOIN products p ON pa.ProductID = p.ProductID
        WHERE pa.ProductID = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $productId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'product_attributes' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Product not found'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>