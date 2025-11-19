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
$old_password = $input['old_password'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// Validation
if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long']);
    exit;
}

if ($old_password === $new_password) {
    echo json_encode(['success' => false, 'error' => 'New password must be different from old password']);
    exit;
}

try {
    // Get current user's password
    $stmt = $conn->prepare('SELECT Password FROM users WHERE UserID = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $current_hashed_password = $user['Password'];
    
    // Verify old password
    if (!password_verify($old_password, $current_hashed_password)) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash new password
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $update_stmt = $conn->prepare('UPDATE users SET Password = ? WHERE UserID = ?');
    $update_stmt->bind_param('si', $new_hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update password');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>