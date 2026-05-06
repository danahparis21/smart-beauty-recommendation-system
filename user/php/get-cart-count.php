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
    $user_id = intval($_SESSION['user_id']);

    // ✅ FIX: Only count active cart items (not checked_out)
    $query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id AND status = 'active'";
    $result = $conn->query($query);
    $row = $result ? $result->fetch_assoc() : ['total' => 0];

    $count = $row['total'] ?: 0;
    echo json_encode(['success' => true, 'count' => (int)$count]);
} catch (Exception $e) {
    error_log('Cart count error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}
?>
