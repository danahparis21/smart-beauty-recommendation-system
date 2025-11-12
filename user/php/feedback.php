<?php
session_start();
// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Add this to feedback.php - GET USER RATINGS ENDPOINT
if ($_GET['action'] == 'get_user_ratings') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare('
            SELECT ProductID, UserRating, RecommendationFeedback, CreatedAt 
            FROM productfeedback 
            WHERE UserID = ?
        ');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $ratings = [];
        while ($row = $result->fetch_assoc()) {
            $ratings[] = $row;
        }

        echo json_encode([
            'success' => true,
            'ratings' => $ratings
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_POST['action'] == 'save_product_feedback') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login to provide feedback']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $user_rating_thumbs = $_POST['user_rating'];  // This is "1", "0.5", or "0"
    $product_name = $_POST['product_name'];

    error_log("📝 Feedback received - User: $user_id, Product: $product_id, Rating: $user_rating_thumbs");

    try {
        // FIXED: Convert to float first, then compare
        $user_rating_float = floatval($user_rating_thumbs);
        
        if ($user_rating_float == 1.0) {
            $rating_scale = 5.0;  // Good - Perfect match
        } elseif ($user_rating_float == 0.5) {
            $rating_scale = 3.0;  // Okay - Neutral
        } else {
            $rating_scale = 1.0;  // Poor - Bad recommendation
        }

        $recommendation_feedback = $user_rating_float;  // This will be 1.0, 0.5, or 0.0

        // Check if feedback already exists
        $check_stmt = $conn->prepare('SELECT FeedbackID FROM productfeedback WHERE UserID = ? AND ProductID = ?');
        if (!$check_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $check_stmt->bind_param('is', $user_id, $product_id);
        if (!$check_stmt->execute()) {
            throw new Exception('Execute failed: ' . $check_stmt->error);
        }

        $check_result = $check_stmt->get_result();
        $existing_feedback = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($existing_feedback) {
            // Update existing feedback
            $stmt = $conn->prepare('
                UPDATE productfeedback 
                SET UserRating = ?, 
                    RecommendationFeedback = ?,
                    Comment = ?,
                    CreatedAt = NOW()
                WHERE UserID = ? AND ProductID = ?
            ');

            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            // FIXED: Use the float value for RecommendationFeedback
            $stmt->bind_param('ddsss',
                $rating_scale,  // d = double (UserRating is float)
                $recommendation_feedback,  // d = double (RecommendationFeedback is float)
                $product_name,  // s = string (Comment is text)
                $user_id,  // s = string (UserID is int but bind as string to be safe)
                $product_id  // s = string (ProductID is varchar)
            );

            $success = $stmt->execute();
            if (!$success) {
                throw new Exception('Update failed: ' . $stmt->error);
            }
            $stmt->close();

            error_log("🔄 Updated existing feedback for product $product_id");
        } else {
            // Insert new feedback
            $stmt = $conn->prepare('
                INSERT INTO productfeedback (ProductID, UserID, UserRating, RecommendationFeedback, Comment, CreatedAt) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ');

            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            // FIXED: Correct parameter types
            $stmt->bind_param('sidds',
                $product_id,  // s = string
                $user_id,  // i = integer
                $rating_scale,  // d = double (UserRating: 5.0, 3.0, or 1.0)
                $recommendation_feedback,  // d = double (RecommendationFeedback: 1.0, 0.5, or 0.0)
                $product_name  // s = string
            );

            $success = $stmt->execute();
            if (!$success) {
                throw new Exception('Insert failed: ' . $stmt->error);
            }
            $stmt->close();

            error_log("🆕 Created new feedback for product $product_id");
        }

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Feedback saved successfully',
                'user_rating' => $rating_scale,
                'thumbs_rating' => $user_rating_thumbs,
                'recommendation_feedback' => $recommendation_feedback
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save feedback: ' . $conn->error]);
        }
    } catch (Exception $e) {
        error_log('❌ Database error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
// If no action matched
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>