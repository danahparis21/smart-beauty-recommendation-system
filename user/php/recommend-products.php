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

    // NEW: Get collaborative data for current user
    $collaborative_data = getCollaborativeData($user_id, $conn);
    
    // Prepare data for Python ML
    $ml_data = [
        'user_input' => [
            'Skin_Type' => $input['skinType'] ?? 'Normal',
            'Skin_Tone' => $input['skinTone'] ?? 'Medium', 
            'Undertone' => $input['undertone'] ?? 'Neutral',
            'Skin_Concerns' => $input['concerns'] ?? [],
            'Preference' => $input['finish'] ?? 'Dewy'
        ],
        'products' => $products,
        'collaborative_data' => $collaborative_data, // NEW: Pass collaborative data
        'current_user_id' => $user_id // NEW: Pass user ID for ML processing
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

    $command = 'python3 ' . escapeshellarg($python_script) . ' ' . escapeshellarg($temp_file) . ' 2>nul';
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
        $json_error = json_last_error_msg();
        error_log("JSON decode error: " . $json_error);
        error_log("Raw output: " . $output);
        throw new Exception('Python output is not valid JSON: ' . $json_error);
    }

    // Enhance recommendations with product details AND collaborative data
    $enhanced_recommendations = enhanceRecommendations($recommendations, $conn, $user_id);

    echo json_encode([
        'success' => true,
        'recommendations' => $enhanced_recommendations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'fallback' => true,
        'recommendations' => getFallbackRecommendations($conn, $input, $user_id)
    ]);
}

// NEW: Function to get collaborative data
function getCollaborativeData($user_id, $conn) {
    $collaborative_data = [];
    
    // Get similar users and their ratings
    $query = "
        SELECT 
            pf.ProductID,
            COUNT(*) as total_similar_ratings,
            SUM(CASE WHEN pf.RecommendationFeedback = 'Good' THEN 1 ELSE 0 END) as liked_count,
            ROUND(
                (SUM(CASE WHEN pf.RecommendationFeedback = 'Good' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0)),
                0
            ) as liked_percentage,
            COUNT(DISTINCT pf.UserID) as similar_users_count
        FROM productfeedback pf
        JOIN user_preferences up ON pf.UserID = up.user_id
        WHERE pf.ProductID IN (SELECT ProductID FROM product_attributes_view)
        AND up.user_id IN (
            SELECT u2.user_id
            FROM user_preferences u1
            JOIN user_preferences u2 ON u1.user_id != u2.user_id
            WHERE u1.user_id = ?
            AND (
                (u1.skin_type = u2.skin_type) +
                (u1.skin_tone = u2.skin_tone) + 
                (u1.undertone = u2.undertone) +
                (CASE WHEN JSON_OVERLAPS(u1.skin_concerns, u2.skin_concerns) THEN 1 ELSE 0 END) +
                (CASE WHEN u1.preferred_finish = u2.preferred_finish THEN 0.5 ELSE 0 END)
            ) >= 2
        )
        AND pf.RecommendationFeedback IS NOT NULL
        GROUP BY pf.ProductID
        HAVING total_similar_ratings >= 2
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // FIX: Use ProductID as the key, not numeric index
        $collaborative_data[$row['ProductID']] = [
            'similar_users_liked' => $row['liked_percentage'],
            'similar_users_count' => $row['similar_users_count'],
            'total_ratings' => $row['total_similar_ratings']
        ];
    }
    
    // Get global popularity as fallback
    $global_query = "
        SELECT 
            ProductID,
            COUNT(*) as total_ratings,
            SUM(CASE WHEN RecommendationFeedback = 'Good' THEN 1 ELSE 0 END) as liked_count,
            ROUND(
                (SUM(CASE WHEN RecommendationFeedback = 'Good' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0)),
                0
            ) as liked_percentage
        FROM productfeedback 
        WHERE ProductID IN (SELECT ProductID FROM product_attributes_view)
        AND RecommendationFeedback IS NOT NULL
        GROUP BY ProductID
        HAVING total_ratings >= 3
    ";
    
    $global_stmt = $conn->prepare($global_query);
    $global_stmt->execute();
    $global_result = $global_stmt->get_result();
    
    while ($row = $global_result->fetch_assoc()) {
        $product_id = $row['ProductID'];
        // FIX: Use ProductID as the key
        if (!isset($collaborative_data[$product_id])) {
            $collaborative_data[$product_id] = [
                'global_popularity' => $row['liked_percentage'],
                'global_ratings_count' => $row['total_ratings']
            ];
        }
    }
    
    return $collaborative_data;
}

function enhanceRecommendations($recommendations, $conn, $user_id)
{
    if (empty($recommendations))
        return [];

    $product_ids = array_column($recommendations, 'id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';

    // Get product details
    $query = "
        SELECT 
            p.ProductID,
            p.Name,
            p.ShadeOrVariant,
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

    // NEW: Get user's personal feedback for these products
    $feedback_query = "
        SELECT ProductID, UserRating, RecommendationFeedback, CreatedAt 
        FROM productfeedback 
        WHERE UserID = ? AND ProductID IN ($placeholders)
    ";
    
    $feedback_stmt = $conn->prepare($feedback_query);
    $feedback_types = 'i' . $types;
    $feedback_params = array_merge([$user_id], $product_ids);
    $feedback_stmt->bind_param($feedback_types, ...$feedback_params);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    $user_feedback = $feedback_result->fetch_all(MYSQLI_ASSOC);
    
    // Convert to associative array for easy lookup
    $feedback_lookup = [];
    foreach ($user_feedback as $feedback) {
        $feedback_lookup[$feedback['ProductID']] = [
            'UserRating' => $feedback['UserRating'],
            'RecommendationFeedback' => $feedback['RecommendationFeedback'],
            'CreatedAt' => $feedback['CreatedAt']
        ];
    }

    // NEW: Get collaborative data for these specific products
    $collaborative_data = getCollaborativeData($user_id, $conn);

    // Merge ML scores with product details, user feedback, and collaborative data
    $enhanced = [];
    foreach ($recommendations as $rec) {
        $details = array_filter($product_details, function ($p) use ($rec) {
            return $p['ProductID'] == $rec['id'];
        });

        if (!empty($details)) {
            $details = reset($details);
            
            // Add user feedback if exists
            if (isset($feedback_lookup[$rec['id']])) {
                $rec['user_feedback'] = $feedback_lookup[$rec['id']];
            }
            
            // Add collaborative data if exists
            if (isset($collaborative_data[$rec['id']])) {
                $rec = array_merge($rec, $collaborative_data[$rec['id']]);
            }
            
            $enhanced[] = array_merge($rec, $details);
        }
    }

    return $enhanced;
}

function getFallbackRecommendations($conn, $input, $user_id)
{
    $query = "
        SELECT 
            p.ProductID as id,
            p.Name,
            p.ShadeOrVariant,
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

    // NEW: Get user feedback for fallback products
    if (!empty($products)) {
        $product_ids = array_column($products, 'id');
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        
        $feedback_query = "
            SELECT ProductID, UserRating, RecommendationFeedback, CreatedAt 
            FROM productfeedback 
            WHERE UserID = ? AND ProductID IN ($placeholders)
        ";
        
        $feedback_stmt = $conn->prepare($feedback_query);
        $types = str_repeat('s', count($product_ids));
        $feedback_types = 'i' . $types;
        $feedback_params = array_merge([$user_id], $product_ids);
        $feedback_stmt->bind_param($feedback_types, ...$feedback_params);
        $feedback_stmt->execute();
        $feedback_result = $feedback_stmt->get_result();
        $user_feedback = $feedback_result->fetch_all(MYSQLI_ASSOC);
        
        $feedback_lookup = [];
        foreach ($user_feedback as $feedback) {
            $feedback_lookup[$feedback['ProductID']] = [
                'UserRating' => $feedback['UserRating'],
                'RecommendationFeedback' => $feedback['RecommendationFeedback'],
                'CreatedAt' => $feedback['CreatedAt']
            ];
        }
    }

    // Add mock ML data for fallback + real user feedback
    foreach ($products as &$product) {
        $product['Predicted_Score'] = rand(35, 50) / 10;  // 3.5-5.0
        $product['Match_Type'] = '🌸 FALLBACK MATCH';
        $product['Initial_Fit_Score'] = rand(5, 10) / 10;  // 0.5-1.0
        
        // Add real user feedback if exists
        if (isset($feedback_lookup[$product['id']])) {
            $product['user_feedback'] = $feedback_lookup[$product['id']];
        }
    }

    return $products;
}
?>