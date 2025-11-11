<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'hasPreferences' => false]);
    exit;
}

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $preferences = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'hasPreferences' => true,
            'preferences' => $preferences
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'hasPreferences' => false
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'hasPreferences' => false,
        'error' => $e->getMessage()
    ]);
}
?>