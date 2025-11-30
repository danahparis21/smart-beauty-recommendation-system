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

// Add the same image path conversion function
function getPublicImagePath($dbPath)
{
    if (empty($dbPath)) {
        return '';
    }

    if (strpos($dbPath, '../') === 0) {
        return str_replace('../', '/admin/', $dbPath);
    }

    if (strpos($dbPath, '/') === 0) {
        return $dbPath;
    }

    return '/' . $dbPath;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

try {
    $query = 'SELECT * FROM product_attributes_view';
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($products)) {
        echo json_encode(['success' => true, 'recommendations' => []]);
        exit;
    }

    $user_feedback = getUserFeedback($user_id, $conn);

    foreach ($products as &$product) {
        $product_id = $product['id'];
        if (isset($user_feedback[$product_id])) {
            $product['user_feedback'] = $user_feedback[$product_id];
        }
    }

    $collaborative_data = getCollaborativeData($user_id, $conn);

    $ml_data = [
        'user_input' => [
            'Skin_Type' => $input['skinType'] ?? 'Normal',
            'Skin_Tone' => $input['skinTone'] ?? 'Medium',
            'Undertone' => $input['undertone'] ?? 'Neutral',
            'Skin_Concerns' => $input['concerns'] ?? [],
            'Preference' => $input['finish'] ?? 'Dewy'
        ],
        'products' => $products,
        'collaborative_data' => $collaborative_data,
        'current_user_id' => $user_id
    ];

    $temp_file = tempnam(sys_get_temp_dir(), 'ml_data_');
    file_put_contents($temp_file, json_encode($ml_data));

    $python_script = __DIR__ . '/ml-recommender.py';

    if (!file_exists($python_script)) {
        throw new Exception('Python script not found at: ' . $python_script);
    }

    $command = 'python3 ' . escapeshellarg($python_script) . ' ' . escapeshellarg($temp_file) . ' 2>/dev/null';
    $output = shell_exec($command);

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
        error_log('JSON decode error: ' . $json_error);
        error_log('Raw output: ' . $output);
        throw new Exception('Python output is not valid JSON: ' . $json_error);
    }

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

function getUserFeedback($user_id, $conn)
{
    $feedback_query = '
        SELECT ProductID, UserRating, RecommendationFeedback, CreatedAt 
        FROM productfeedback 
        WHERE UserID = ?
    ';

    $feedback_stmt = $conn->prepare($feedback_query);
    $feedback_stmt->bind_param('i', $user_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    $user_feedback = $feedback_result->fetch_all(MYSQLI_ASSOC);

    $feedback_lookup = [];
    foreach ($user_feedback as $feedback) {
        $feedback_lookup[$feedback['ProductID']] = [
            'UserRating' => floatval($feedback['UserRating']),
            'RecommendationFeedback' => $feedback['RecommendationFeedback'],
            'CreatedAt' => $feedback['CreatedAt']
        ];
    }

    return $feedback_lookup;
}

function getCollaborativeData($user_id, $conn)
{
    $collaborative_data = [];

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
    $stmt->bind_param('i', $user_id);
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
            p.ParentProductID,
            p.ExpirationDate,
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
        AND p.Status IN ('Available', 'Low Stock')  -- ONLY available products
        AND p.Stocks > 0                           -- Must have stock
        AND (p.ExpirationDate IS NULL OR p.ExpirationDate > CURDATE())  -- Not expired
    ";

    $stmt = $conn->prepare($query);
    $types = str_repeat('s', count($product_ids));
    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_details = $result->fetch_all(MYSQLI_ASSOC);

    $user_feedback = getUserFeedback($user_id, $conn);

    $collaborative_data = getCollaborativeData($user_id, $conn);

    $enhanced = [];
    foreach ($recommendations as $rec) {
        $details = array_filter($product_details, function ($p) use ($rec) {
            return $p['ProductID'] == $rec['id'];
        });

        if (!empty($details)) {
            $details = reset($details);

            $stock = intval($details['Stocks'] ?? 0);
            $status = $details['Status'] ?? '';
            $expiration = $details['ExpirationDate'] ?? null;

            $isAvailable = $stock > 0 &&
                in_array($status, ['Available', 'Low Stock']) &&
                ($expiration === null || strtotime($expiration) > time());

            if (!$isAvailable) {
                continue;
            }

            if (!empty($details['image'])) {
                $details['image'] = getPublicImagePath($details['image']);
            }

            if (isset($user_feedback[$rec['id']])) {
                $rec['user_feedback'] = $user_feedback[$rec['id']];
            }

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
            p.Stocks,
            p.Status,
            p.ExpirationDate,
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
        AND p.Status IN ('Available', 'Low Stock')  -- ONLY available
        AND p.Stocks > 0                           -- Must have stock
        AND (p.ExpirationDate IS NULL OR p.ExpirationDate > CURDATE())  -- Not expired
        ORDER BY p.ProductRating DESC
        LIMIT 6
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);

 
    $user_feedback = getUserFeedback($user_id, $conn);

    // Add mock ML data for fallback + real user feedback
    foreach ($products as &$product) {
       
        if (!empty($product['image'])) {
            $product['image'] = getPublicImagePath($product['image']);
        }

        $product['Predicted_Score'] = rand(35, 50) / 10;  // 3.5-5.0
        $product['Match_Type'] = '🌸 FALLBACK MATCH';
        $product['Initial_Fit_Score'] = rand(5, 10) / 10;  // 0.5-1.0

      
        if (isset($user_feedback[$product['id']])) {
            $product['user_feedback'] = $user_feedback[$product['id']];
        }
    }

    return $products;
}
?>