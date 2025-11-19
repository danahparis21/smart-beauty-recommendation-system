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

if (empty($productId) || empty($orderId)) {
    echo json_encode(['success' => false, 'reviewExists' => false]);
    exit;
}

try {
    // ✅ UPDATED QUERY - Now checks ratings table with order_id
    $query = "
        SELECT 
            r.stars as product_rating,
            r.review as product_review,
            pf.UserRating as recommendation_rating,
            pf.RecommendationFeedback as recommendation_feedback,
            pf.Comment as recommendation_comment,
            r.created_at as review_date
        FROM ratings r
        LEFT JOIN productfeedback pf ON r.product_id = pf.ProductID AND r.user_id = pf.UserID AND r.order_id = pf.order_id
        WHERE r.product_id = ? AND r.user_id = ? AND r.order_id = ?
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