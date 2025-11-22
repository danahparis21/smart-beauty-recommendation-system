<?php
session_start();

// Turn off error display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// ✅ ADDED: Set timezone at the very top
date_default_timezone_set('Asia/Manila');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// ✅ ADDED: Set database timezone
$conn->query("SET time_zone = '+08:00'");

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

function sendResponse($success, $message, $data = [])
{
    // Clear any output buffers
    while (ob_get_level())
        ob_end_clean();

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

    // ✅ ADDED: Get current datetime for consistent timing
    $currentDateTime = date('Y-m-d H:i:s');

    // Get product name for logging
    $productNameStmt = $conn->prepare('SELECT Name FROM products WHERE ProductID = ?');
    $productNameStmt->bind_param('s', $productId);
    $productNameStmt->execute();
    $productNameResult = $productNameStmt->get_result();
    $productName = $productNameResult->num_rows > 0 ? $productNameResult->fetch_assoc()['Name'] : 'Unknown Product';
    $productNameStmt->close();

    // ✅ UPDATED: CHECK FOR EXISTING REVIEW FOR THIS PRODUCT BY THIS USER (ANY ORDER)
    $checkStmt = $conn->prepare('SELECT rating_id, order_id FROM ratings WHERE user_id = ? AND product_id = ?');
    if (!$checkStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $checkStmt->bind_param('is', $userId, $productId);
    if (!$checkStmt->execute()) {
        throw new Exception('Execute failed: ' . $checkStmt->error);
    }

    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        // User has already reviewed this product - UPDATE existing review
        $existingReview = $checkResult->fetch_assoc();
        $existingRatingId = $existingReview['rating_id'];
        $existingOrderId = $existingReview['order_id'];

        $checkStmt->close();

        // Update existing rating
        $updateStmt = $conn->prepare('UPDATE ratings SET stars = ?, review = ?, order_id = ? WHERE rating_id = ?');
        if (!$updateStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $updateStmt->bind_param('issi', $productRating, $productReview, $orderId, $existingRatingId);
        if (!$updateStmt->execute()) {
            throw new Exception('Execute failed: ' . $updateStmt->error);
        }
        $updateStmt->close();

        // ✅ FIXED: Use PHP date for productfeedback update
        $updateFeedbackStmt = $conn->prepare('
            UPDATE productfeedback 
            SET UserRating = ?, RecommendationFeedback = ?, Comment = ?, order_id = ?, CreatedAt = ? 
            WHERE ProductID = ? AND UserID = ? AND order_id = ?
        ');

        if ($updateFeedbackStmt) {
            $recommendationFeedback = "Rating: {$recommendationRating}/5 stars";
            if (!empty($recommendationComment)) {
                $recommendationFeedback .= ' - ' . substr($recommendationComment, 0, 150);
            }

            $updateFeedbackStmt->bind_param('dsssisis', $recommendationRating, $recommendationFeedback, $recommendationComment, $orderId, $currentDateTime, $productId, $userId, $existingOrderId);
            $updateFeedbackStmt->execute();
            $updateFeedbackStmt->close();
        }

        $action = 'updated';

        // ✅ LOG REVIEW UPDATE
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $reviewDetails = "Updated review for {$productName} (ID: {$productId}) - Product: {$productRating}/5, Recommendation: {$recommendationRating}/5, IP: {$userIP}";
        logUserActivity($conn, $userId, 'Review updated', $reviewDetails);
    } else {
        $checkStmt->close();

        // No existing review - INSERT new review
        // 1. Insert into ratings table
        $stmt = $conn->prepare('INSERT INTO ratings (user_id, product_id, order_id, stars, review) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('isiss', $userId, $productId, $orderId, $productRating, $productReview);
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        $ratingId = $stmt->insert_id;
        $stmt->close();

        // 2. ✅ FIXED: Insert into productfeedback table with PHP date
        $userRating = (float) $recommendationRating;

        $recommendationFeedback = "Rating: {$recommendationRating}/5 stars";
        if (!empty($recommendationComment)) {
            $recommendationFeedback .= ' - ' . substr($recommendationComment, 0, 150);
        }

        $feedbackStmt = $conn->prepare('INSERT INTO productfeedback (ProductID, UserID, UserRating, RecommendationFeedback, Comment, order_id, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if (!$feedbackStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $feedbackStmt->bind_param('siissis', $productId, $userId, $userRating, $recommendationFeedback, $recommendationComment, $orderId, $currentDateTime);
        if (!$feedbackStmt->execute()) {
            throw new Exception('Execute failed: ' . $feedbackStmt->error);
        }
        $feedbackStmt->close();

        $action = 'created';

        // ✅ LOG NEW REVIEW SUBMISSION
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $hasProductReview = !empty($productReview) ? 'with comment' : 'without comment';
        $hasRecommendationComment = !empty($recommendationComment) ? 'with feedback' : 'without feedback';

        $reviewDetails = "Submitted review for {$productName} (ID: {$productId}) - Product: {$productRating}/5, Recommendation: {$recommendationRating}/5, {$hasProductReview}, {$hasRecommendationComment}, IP: {$userIP}";
        logUserActivity($conn, $userId, 'Review submitted', $reviewDetails);
    }

    // ✅ UPDATE PRODUCT'S AVERAGE RATING (for both new and updated reviews)
    // Note: Using MySQL NOW() for UpdatedAt since it's just an internal timestamp
    $updateProductStmt = $conn->prepare('
        UPDATE products 
        SET 
            ProductRating = COALESCE(
                (SELECT ROUND(AVG(stars), 1) FROM ratings WHERE product_id = ? AND stars > 0),
                0
            ),
            UpdatedAt = NOW()
        WHERE ProductID = ?
    ');
    if (!$updateProductStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $updateProductStmt->bind_param('ss', $productId, $productId);
    if (!$updateProductStmt->execute()) {
        throw new Exception('Execute failed: ' . $updateProductStmt->error);
    }
    $updateProductStmt->close();

    // ✅ GET THE UPDATED RATING TO RETURN TO CLIENT
    $getRatingStmt = $conn->prepare('SELECT ProductRating FROM products WHERE ProductID = ?');
    $getRatingStmt->bind_param('s', $productId);
    $getRatingStmt->execute();
    $ratingResult = $getRatingStmt->get_result();
    $productData = $ratingResult->fetch_assoc();
    $newAverageRating = $productData['ProductRating'] ?? 0;
    $getRatingStmt->close();

    $conn->commit();

    $message = $action === 'updated'
        ? 'Review updated successfully! Your feedback has been refreshed.'
        : 'Review submitted successfully! Thank you for your detailed feedback.';

    sendResponse(true, $message, [
        'new_average_rating' => $newAverageRating,
        'product_id' => $productId,
        'action' => $action,
        'created_at' => $currentDateTime // ✅ Return the timestamp to client
    ]);
} catch (Exception $e) {
    // Rollback if transaction was started
    if (isset($conn) && $conn) {
        $conn->rollback();
    }

    // ✅ LOG REVIEW SUBMISSION ERROR
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $errorDetails = "Product: {$productId}, Error: " . $e->getMessage() . ", IP: {$userIP}";
    logUserActivity($conn, $userId, 'Review submission failed', $errorDetails);

    error_log('Review submission error: ' . $e->getMessage());
    sendResponse(false, 'Error submitting review. Please try again.');
}
?>