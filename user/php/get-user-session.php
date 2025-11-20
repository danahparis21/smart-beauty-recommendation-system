<?php
// get-user-session.php - Returns current logged-in user data
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in',
        'isLoggedIn' => false
    ]);
    exit();
}

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Check database connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'isLoggedIn' => true
    ]);
    exit();
}

// Get user data from database
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT UserID, username, first_name, last_name, Email, Role FROM users WHERE UserID = ?");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'isLoggedIn' => true
    ]);
    exit();
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Update session data if needed
    $_SESSION['username'] = $user['username'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['role'] = $user['Role'];
    
    echo json_encode([
        'success' => true,
        'isLoggedIn' => true,
        'user' => [
            'id' => $user['UserID'],
            'username' => $user['username'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'email' => $user['Email'],
            'role' => $user['Role']
        ]
    ]);
} else {
    // User not found in database but session exists (shouldn't happen)
    session_destroy();
    echo json_encode([
        'success' => false,
        'message' => 'User not found',
        'isLoggedIn' => false
    ]);
}

$stmt->close();
$conn->close();
?>