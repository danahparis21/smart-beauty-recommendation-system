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

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare('
        SELECT rating, comment, created_at 
        FROM store_ratings 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $feedback = $result->fetch_assoc();
        echo json_encode(['success' => true, 'feedback' => $feedback]);
    } else {
        echo json_encode(['success' => true, 'feedback' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>