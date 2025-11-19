<?php
session_start();

// Turn off error display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

function sendResponse($success, $message, $data = []) {
    // Clear any output buffers
    while (ob_get_level()) ob_end_clean();
    
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

if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(false, 'Invalid JSON data received');
}

$productId = $input['product_id'] ?? null;
$orderId = $input['order_id'] ?? null;
$productRating = $input['product_rating'] ?? null;
$recommendationRating = $input['recommendation_rating'] ?? null;
$productReview = $input['product_review'] ?? '';
$recommendationComment = $input['recommendation_comment'] ?? '';

// Validation
if (!$productId || !$orderId || !$productRating || !$recommendationRating) {
    sendResponse(false, 'All rating fields are required');
}

if ($productRating < 1 || $productRating > 5 || $recommendationRating < 1 || $recommendationRating > 5) {
    sendResponse(false, 'Ratings must be between 1-5 stars');
}

try {
    // Check database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }
    
    $conn->begin_transaction();
    
    // ✅ CHECK FOR EXISTING REVIEW FOR THIS PRODUCT IN THIS ORDER
    $checkStmt = $conn->prepare("SELECT rating_id FROM ratings WHERE user_id = ? AND product_id = ? AND order_id = ?");
    if (!$checkStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $checkStmt->bind_param("isi", $userId, $productId, $orderId);
    if (!$checkStmt->execute()) {
        throw new Exception('Execute failed: ' . $checkStmt->error);
    }
    
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        sendResponse(false, 'You have already reviewed this product from this order.');
    }
    $checkStmt->close();
    
    // 1. Insert into ratings table
    $stmt = $conn->prepare("INSERT INTO ratings (user_id, product_id, order_id, stars, review) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("isiss", $userId, $productId, $orderId, $productRating, $productReview);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $ratingId = $stmt->insert_id;
    $stmt->close();
    
    // 2. Insert into productfeedback table 
    $userRating = (float)$recommendationRating;
    
    $recommendationFeedback = "Rating: {$recommendationRating}/5 stars";
    if (!empty($recommendationComment)) {
        $recommendationFeedback .= " - " . substr($recommendationComment, 0, 150);
    }
    
    $feedbackStmt = $conn->prepare("INSERT INTO productfeedback (ProductID, UserID, UserRating, RecommendationFeedback, Comment, order_id, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if (!$feedbackStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $feedbackStmt->bind_param("siissi", $productId, $userId, $userRating, $recommendationFeedback, $recommendationComment, $orderId);
    if (!$feedbackStmt->execute()) {
        throw new Exception('Execute failed: ' . $feedbackStmt->error);
    }
    $feedbackStmt->close();
    
    // ✅ ENHANCED: Update product's average rating with better calculation
    $updateStmt = $conn->prepare("
        UPDATE products 
        SET 
            ProductRating = COALESCE(
                (SELECT ROUND(AVG(stars), 1) FROM ratings WHERE product_id = ? AND stars > 0),
                0
            ),
            UpdatedAt = NOW()
        WHERE ProductID = ?
    ");
    if (!$updateStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $updateStmt->bind_param("ss", $productId, $productId);
    if (!$updateStmt->execute()) {
        throw new Exception('Execute failed: ' . $updateStmt->error);
    }
    
    // ✅ GET THE UPDATED RATING TO RETURN TO CLIENT
    $getRatingStmt = $conn->prepare("SELECT ProductRating FROM products WHERE ProductID = ?");
    $getRatingStmt->bind_param("s", $productId);
    $getRatingStmt->execute();
    $ratingResult = $getRatingStmt->get_result();
    $productData = $ratingResult->fetch_assoc();
    $newAverageRating = $productData['ProductRating'] ?? 0;
    $getRatingStmt->close();
    
    $conn->commit();
    
    sendResponse(true, 'Review submitted successfully! Thank you for your detailed feedback.', [
        'new_average_rating' => $newAverageRating,
        'product_id' => $productId
    ]);
    
} catch (Exception $e) {
    // Rollback if transaction was started
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    
    error_log('Review submission error: ' . $e->getMessage());
    sendResponse(false, 'Error submitting review. Please try again.');
}
?>