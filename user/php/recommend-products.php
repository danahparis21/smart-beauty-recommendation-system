<?php
header('Content-Type: application/json');
error_reporting(0);  // Turn off error display to avoid HTML in JSON

require_once 'db-connect.php';

session_start();

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
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no products found, return empty array
    if (empty($products)) {
        echo json_encode(['success' => true, 'recommendations' => []]);
        exit;
    }

    // Prepare data for Python ML
    $ml_data = [
        'user_input' => [
            'Skin_Type' => $input['skinType'] ?? 'Normal',
            'Skin_Tone' => $input['skinTone'] ?? 'Medium',
            'Undertone' => $input['undertone'] ?? 'Neutral',
            'Skin_Concerns' => $input['concerns'] ?? [],
            'Preference' => $input['finish'] ?? 'Dewy'
        ],
        'products' => $products
    ];

    // Save to temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'ml_data_');
    file_put_contents($temp_file, json_encode($ml_data));

    // Execute Python script - with full path
    $python_script = __DIR__ . '/ml-recommender.py';

    // Check if Python script exists
    if (!file_exists($python_script)) {
        throw new Exception('Python script not found at: ' . $python_script);
    }

    $command = 'python3 ' . escapeshellarg($python_script) . ' ' . escapeshellarg($temp_file) . ' 2>&1';
    $output = shell_exec($command);

    // Clean up
    unlink($temp_file);

    if ($output) {
        $recommendations = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON decode failed, show the raw output for debugging
            throw new Exception('Python output is not valid JSON: ' . substr($output, 0, 200));
        }

        // Enhance recommendations with product details
        $enhanced_recommendations = enhanceRecommendations($recommendations, $pdo);

        echo json_encode([
            'success' => true,
            'recommendations' => $enhanced_recommendations,
            'debug' => ['python_output' => substr($output, 0, 100)]  // For debugging
        ]);
    } else {
        throw new Exception('Python script returned no output');
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'fallback' => true,
        'recommendations' => getFallbackRecommendations($pdo, $input)
    ]);
}

function enhanceRecommendations($recommendations, $pdo)
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
            pm.ImagePath as image
        FROM Products p
        LEFT JOIN ProductMedia pm ON (
            (pm.ParentProductID = p.ProductID AND pm.MediaType = 'PREVIEW') OR
            (pm.VariantProductID = p.ProductID AND pm.MediaType = 'VARIANT')
        )
        WHERE p.ProductID IN ($placeholders)
        GROUP BY p.ProductID
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($product_ids);
    $product_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function getFallbackRecommendations($pdo, $input)
{
    // Simple rule-based fallback if ML fails
    $query = "
        SELECT 
            p.ProductID as id,
            p.Name,
            p.Category, 
            p.Price,
            p.Description,
            p.ProductRating,
            pm.ImagePath as image
        FROM Products p
        LEFT JOIN ProductAttributes pa ON p.ProductID = pa.ProductID
        LEFT JOIN ProductMedia pm ON p.ProductID = pm.ParentProductID AND pm.MediaType = 'PREVIEW'
        WHERE pa.ProductID IS NOT NULL
        ORDER BY p.ProductRating DESC
        LIMIT 6
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add mock ML data for fallback
    foreach ($products as &$product) {
        $product['Predicted_Score'] = rand(35, 50) / 10;  // 3.5-5.0
        $product['Match_Type'] = '🌸 FALLBACK MATCH';
        $product['Initial_Fit_Score'] = rand(5, 10) / 10;  // 0.5-1.0
    }

    return $products;
}
?>