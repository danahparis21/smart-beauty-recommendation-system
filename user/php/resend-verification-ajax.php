<?php
// Enable error logging but prevent display
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session
@session_start();

// Set JSON header
header('Content-Type: application/json');

// Helper functions for JSON responses
function jsonError($message) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function jsonSuccess($message, $extra = []) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(array_merge(['success' => true, 'message' => $message], $extra));
    exit();
}

// Load database
try {
    $dbConfig = (getenv('DOCKER_ENV') === 'true')
        ? __DIR__ . '/../../config/db_docker.php'
        : __DIR__ . '/../../config/db.php';

    if (!file_exists($dbConfig)) jsonError('Database configuration not found.');
    require_once $dbConfig;

    if (!isset($conn)) jsonError('Database connection failed.');
} catch (Throwable $e) {
    jsonError('Database error: ' . $e->getMessage());
}

// Load email function
require_once __DIR__ . '/send-verification.php';
if (!function_exists('sendVerificationEmail')) jsonError('Email function missing.');

// Get input
$rawInput = file_get_contents('php://input');
if (!$rawInput) jsonError('No input data received.');

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) jsonError('Invalid JSON: ' . json_last_error_msg());

if (empty($input['email']) || empty($input['token'])) jsonError('Email and token required.');

$email = trim($input['email']);
$token = trim($input['token']);

// Lookup user with the token
$stmt = $conn->prepare('SELECT UserID, first_name, email_verified, verification_token FROM users WHERE Email = ? AND verification_token = ?');
if (!$stmt) jsonError('Database prepare error.');

$stmt->bind_param('ss', $email, $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    jsonError('Invalid email or token.');
}

$user = $result->fetch_assoc();
$stmt->close();

// Already verified?
if ($user['email_verified'] == 1) {
    $conn->close();
    jsonError('Email already verified.');
}

// Send email with correct parameter order: email, firstName, token
$emailSent = sendVerificationEmail($email, $user['first_name'], $token);

if ($emailSent) {
    $conn->close();
    jsonSuccess('Verification email sent successfully.');
} else {
    $conn->close();
    jsonError('Failed to send verification email. Check server logs.');
}
?>