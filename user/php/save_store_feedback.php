<?php
session_start();
header('Content-Type: application/json');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$rating = intval($input['rating'] ?? 0);
$comment = $input['comment'] ?? '';

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid rating']);
    exit;
}

try {
    // Check if user already has feedback
    $checkStmt = $conn->prepare('SELECT store_rating_id FROM store_ratings WHERE user_id = ?');
    $checkStmt->bind_param('i', $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing feedback
        $stmt = $conn->prepare('
            UPDATE store_ratings 
            SET rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP 
            WHERE user_id = ?
        ');
        $stmt->bind_param('isi', $rating, $comment, $user_id);
    } else {
        // Insert new feedback
        $stmt = $conn->prepare('
            INSERT INTO store_ratings (user_id, rating, comment) 
            VALUES (?, ?, ?)
        ');
        $stmt->bind_param('iis', $user_id, $rating, $comment);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to save feedback');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>