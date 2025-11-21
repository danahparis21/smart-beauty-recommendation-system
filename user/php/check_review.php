<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'reviewExists' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = $_GET['product_id'] ?? '';
$orderId = $_GET['order_id'] ?? '';

if (empty($productId)) {
    echo json_encode(['success' => false, 'reviewExists' => false]);
    exit;
}

try {
    // ✅ UPDATED QUERY - Check for ANY review of this product by this user
    // First try to find review with matching order_id, if not found, get any review for this product
    $query = "
        SELECT 
            r.stars as product_rating,
            r.review as product_review,
            pf.UserRating as recommendation_rating,
            pf.RecommendationFeedback as recommendation_feedback,
            pf.Comment as recommendation_comment,
            r.created_at as review_date,
            r.order_id
        FROM ratings r
        LEFT JOIN productfeedback pf ON r.product_id = pf.ProductID AND r.user_id = pf.UserID AND (r.order_id = pf.order_id OR (r.order_id IS NULL AND pf.order_id IS NULL))
        WHERE r.product_id = ? AND r.user_id = ?
        ORDER BY 
            CASE WHEN r.order_id = ? THEN 1 ELSE 0 END DESC,
            r.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sis", $productId, $userId, $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reviewData = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'reviewExists' => true,
            'reviewData' => $reviewData
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'reviewExists' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'reviewExists' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>