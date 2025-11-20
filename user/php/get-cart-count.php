<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Use the same auto-switch logic as your other files
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'count' => 0, 'reason' => 'not_logged_in']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];

    // ✅ FIX: Only count active cart items (not checked_out)
    $stmt = $conn->prepare('SELECT SUM(quantity) as total FROM cart WHERE user_id = ? AND status = "active"');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $count = $row['total'] ?: 0;
    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    error_log('Cart count error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}
?>
