<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Please login to submit review');
}

$userId = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? null;
$orderId = $input['order_id'] ?? null;
$productRating = $input['product_rating'] ?? null;
$recommendationRating = $input['recommendation_rating'] ?? null;
$productReview = $input['product_review'] ?? '';
$recommendationComment = $input['recommendation_comment'] ?? '';

if (!$productId || !$orderId || !$productRating || !$recommendationRating) {
    sendResponse(false, 'All rating fields are required');
}

if ($productRating < 1 || $productRating > 5 || $recommendationRating < 1 || $recommendationRating > 5) {
    sendResponse(false, 'Ratings must be between 1-5 stars');
}

try {
    $conn->begin_transaction();
    
    // 1. Insert into ratings table (product quality rating + review)
    $stmt = $conn->prepare("INSERT INTO ratings (user_id, product_id, stars, review) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $userId, $productId, $productRating, $productReview);
    $stmt->execute();
    
    // 2. Insert into productfeedback table 
    // UserRating: Use the actual recommendation rating (1-5) as float
    // RecommendationFeedback: Store the actual rating as string + optional comment
    $userRating = (float)$recommendationRating; // Store as float: 1.0, 2.0, 3.0, 4.0, 5.0
    
    // Build RecommendationFeedback string - include both rating and comment
    $recommendationFeedback = "Rating: {$recommendationRating}/5 stars";
    if (!empty($recommendationComment)) {
        $recommendationFeedback .= " - " . substr($recommendationComment, 0, 150); // Truncate if too long
    }
    
    $feedbackStmt = $conn->prepare("INSERT INTO productfeedback (ProductID, UserID, UserRating, RecommendationFeedback, Comment, order_id, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $feedbackStmt->bind_param("siissi", $productId, $userId, $userRating, $recommendationFeedback, $recommendationComment, $orderId);
    $feedbackStmt->execute();
    
    // 3. Update product's average rating in products table
    $updateStmt = $conn->prepare("
        UPDATE products 
        SET ProductRating = (
            SELECT AVG(stars) FROM ratings WHERE product_id = ?
        )
        WHERE ProductID = ?
    ");
    $updateStmt->bind_param("ss", $productId, $productId);
    $updateStmt->execute();
    
    $conn->commit();
    
    sendResponse(true, 'Review submitted successfully! Thank you for your detailed feedback.');
    
} catch (Exception $e) {
    $conn->rollback();
    sendResponse(false, 'Error submitting review: ' . $e->getMessage());
}

$conn->close();
?>