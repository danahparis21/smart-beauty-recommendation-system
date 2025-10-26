<?php
session_start();

// Disable error display, log instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load database configuration
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

require_once __DIR__ . '/send-verification.php';
require_once __DIR__ . '/../../config/google-config.php';

function returnJsonResponse($success, $message, $data = []) {
    // Clear any output buffers
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if ($conn->connect_error) {
    returnJsonResponse(false, "Database Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonResponse(false, 'Invalid request method.');
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    returnJsonResponse(false, 'Please provide a valid email address.');
}

// Check if email exists and is verified
$stmt = $conn->prepare('SELECT UserID, first_name, email_verified FROM users WHERE Email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    returnJsonResponse(false, 'No account found with this email address.');
}

$user = $result->fetch_assoc();

// Check if email is verified
if ($user['email_verified'] == 0) {
    $stmt->close();
    returnJsonResponse(false, 'Please verify your email address before resetting password.');
}

$stmt->close();

// Generate password reset token
$resetToken = bin2hex(random_bytes(32));
$expiry = time() + PASSWORD_RESET_EXPIRY;

// Store reset token in database
$stmt = $conn->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE UserID = ?');
$stmt->bind_param('sii', $resetToken, $expiry, $user['UserID']);

if (!$stmt->execute()) {
    $stmt->close();
    returnJsonResponse(false, 'Failed to generate reset token. Please try again.');
}

$stmt->close();

// Send password reset email
$resetLink = BASE_URL . "/user/php/reset-password.php?token=" . $resetToken;

// Use the password reset email function
$emailSent = sendPasswordResetEmail($email, $user['first_name'], $resetLink);

if (!$emailSent) {
    returnJsonResponse(false, 'Failed to send password reset email. Please try again.');
}

returnJsonResponse(true, 'Password reset instructions have been sent to your email.');
?>