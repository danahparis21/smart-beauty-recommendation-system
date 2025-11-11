<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Get user preferences from POST
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

try {
    // Get products with attributes for ML processing
    $query = 'SELECT * FROM product_attributes_view';
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);

    // If no products found, return empty array
    if (empty($products)) {
        echo json_encode(['success' => true, 'recommendations' => []]);
        exit;
    }

    // Prepare data for Python ML
    // Prepare data for Python ML
    $ml_data = [
        'user_input' => [
            'Skin_Type' => $input['skinType'] ?? 'Normal',  // Keep as is or change to 'Normal'
            'Skin_Tone' => $input['skinTone'] ?? 'Medium',  // Keep as is or change to 'Medium'
            'Undertone' => $input['undertone'] ?? 'Neutral',  // Keep as is or change to 'Neutral'
            'Skin_Concerns' => $input['concerns'] ?? [],
            'Preference' => $input['finish'] ?? 'Dewy'  // Keep as is or change to 'Dewy'
        ],
        'products' => $products
    ];

    // Save to temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'ml_data_');
    file_put_contents($temp_file, json_encode($ml_data));

    // Execute Python script
    $python_script = __DIR__ . '/ml-recommender.py';

    // Check if Python script exists
    if (!file_exists($python_script)) {
        throw new Exception('Python script not found at: ' . $python_script);
    }

    $command = 'python3 ' . escapeshellarg($python_script) . ' ' . escapeshellarg($temp_file) . ' 2>&1';
    $output = shell_exec($command);

    // Clean up
    unlink($temp_file);

    if ($output === null) {
        throw new Exception('Python script returned NULL');
    }

    if (empty(trim($output))) {
        throw new Exception('Python script returned empty output');
    }

    $recommendations = json_decode($output, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Python output is not valid JSON');
    }

    // Enhance recommendations with product details
    $enhanced_recommendations = enhanceRecommendations($recommendations, $conn);

    echo json_encode([
        'success' => true,
        'recommendations' => $enhanced_recommendations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'fallback' => true,
        'recommendations' => getFallbackRecommendations($conn, $input)
    ]);
}

function enhanceRecommendations($recommendations, $conn)
{
    if (empty($recommendations))
        return [];

    $product_ids = array_column($recommendations, 'id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';

    $query = "
        SELECT 
            p.ProductID,
            p.Name,
            p.Category,
            p.Price,
            p.Description,
            p.ProductRating,
            p.Stocks,
            p.Status,
            COALESCE(
                (SELECT pm.ImagePath FROM ProductMedia pm 
                 WHERE pm.VariantProductID = p.ProductID AND pm.MediaType = 'VARIANT' 
                 LIMIT 1),
                (SELECT pm.ImagePath FROM ProductMedia pm 
                 WHERE pm.ParentProductID = p.ProductID AND pm.MediaType = 'PREVIEW' 
                 LIMIT 1),
                (SELECT pm.ImagePath FROM ProductMedia pm 
                 WHERE pm.ParentProductID = COALESCE(p.ParentProductID, p.ProductID) AND pm.MediaType = 'PREVIEW' 
                 LIMIT 1)
            ) as image
        FROM Products p
        WHERE p.ProductID IN ($placeholders)
    ";

    $stmt = $conn->prepare($query);
    $types = str_repeat('s', count($product_ids));
    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_details = $result->fetch_all(MYSQLI_ASSOC);

    // Merge ML scores with product details
    $enhanced = [];
    foreach ($recommendations as $rec) {
        $details = array_filter($product_details, function ($p) use ($rec) {
            return $p['ProductID'] == $rec['id'];
        });

        if (!empty($details)) {
            $details = reset($details);
            $enhanced[] = array_merge($rec, $details);
        }
    }

    return $enhanced;
}

function getFallbackRecommendations($conn, $input)
{
    $query = "
        SELECT 
            p.ProductID as id,
            p.Name,
            p.Category, 
            p.Price,
            p.Description,
            p.ProductRating,
            COALESCE(
                (SELECT pm.ImagePath FROM ProductMedia pm 
                 WHERE pm.VariantProductID = p.ProductID AND pm.MediaType = 'VARIANT' 
                 LIMIT 1),
                (SELECT pm.ImagePath FROM ProductMedia pm 
                 WHERE pm.ParentProductID = p.ProductID AND pm.MediaType = 'PREVIEW' 
                 LIMIT 1),
                (SELECT pm.ImagePath FROM ProductMedia pm 
                 WHERE pm.ParentProductID = COALESCE(p.ParentProductID, p.ProductID) AND pm.MediaType = 'PREVIEW' 
                 LIMIT 1)
            ) as image
        FROM Products p
        LEFT JOIN ProductAttributes pa ON p.ProductID = pa.ProductID
        WHERE pa.ProductID IS NOT NULL
        ORDER BY p.ProductRating DESC
        LIMIT 6
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);

    // Add mock ML data for fallback
    foreach ($products as &$product) {
        $product['Predicted_Score'] = rand(35, 50) / 10;  // 3.5-5.0
        $product['Match_Type'] = '🌸 FALLBACK MATCH';
        $product['Initial_Fit_Score'] = rand(5, 10) / 10;  // 0.5-1.0
    }

    return $products;
}
?>