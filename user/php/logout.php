<?php
// logout.php - Handle user logout
session_start();

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

// Store user info for logging BEFORE destroying session
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;
$username = $_SESSION['username'] ?? null;

// Log the logout activity if user was logged in
if ($userId) {
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $logoutDetails = "IP: {$userIP}, Browser: " . substr($userAgent, 0, 100);
    
    // Use appropriate logging function based on role
    if ($userRole === 'admin') {
        logAdminActivity($conn, $userId, 'Admin logout', $logoutDetails);
    } else {
        logUserActivity($conn, $userId, 'User logout', $logoutDetails);
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any client-side caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);

// Close database connection
if ($conn && !$conn->connect_error) {
    $conn->close();
}
exit();
?>