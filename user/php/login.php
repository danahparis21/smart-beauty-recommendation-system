<?php
// NO OUTPUT BEFORE THIS - No spaces, no blank lines!
session_start();

// Disable error display, log instead
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn OFF for production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log'); // Create logs folder

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

require_once __DIR__ . '/activity_logger.php';

function returnJsonResponse($success, $message, $data = [], $conn = null) {
    // Clear any output buffers
    if (ob_get_length()) ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Send JSON response
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    
    // Close database connection AFTER sending response
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
    exit();
}

function redirectBasedOnRole($role) {
    if ($role === 'admin') {
        // For AJAX responses, return redirect information
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return [
                'redirect' => true,
                'redirectUrl' => '/admin/html/admin.html'
            ];
        } else {
            // Direct browser redirect
            header('Location: /admin/html/admin.html');
            exit();
        }
    }
    // Add other role-based redirects here if needed
    return null;
}

// Check database connection
if ($conn->connect_error) {
    returnJsonResponse(false, "Database Connection failed: " . $conn->connect_error);
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonResponse(false, 'Invalid request method.', [], $conn);
}

// Check if username and password are provided
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    returnJsonResponse(false, 'Please provide both username and password.', [], $conn);
}

$username = trim($_POST['username'] ?? ''); 
$password = $_POST['password'] ?? '';

// Basic input validation
if (empty($username) || empty($password)) {
    returnJsonResponse(false, 'Username and password cannot be empty.', [], $conn);
}

// Prepare SQL statement
$stmt = $conn->prepare("SELECT UserID, Password, first_name, Role, Email, email_verified FROM users WHERE username = ?");
if (!$stmt) {
    returnJsonResponse(false, 'Database error (prepare statement).', [], $conn);
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['Password'])) {
        
        // Check if email is verified
        if ($user['email_verified'] == 0) {
            $stmt->close();
            returnJsonResponse(false, 'Please verify your email before logging in. Check your inbox for the verification link.', [], $conn);
        }
        
        // Email is verified - proceed with login
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['username'] = $username;
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role'] = $user['Role'];
        $_SESSION['email'] = $user['Email']; 
        
        $stmt->close();
        
        // ✅ LOG USER LOGIN - MOVE THIS AFTER SESSION IS SET
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $loginDetails = "IP: {$userIP}, Browser: " . substr($userAgent, 0, 100);
        
        // Log the activity BEFORE sending response
        $logSuccess = logUserActivity($conn, $user['UserID'], 'User login', $loginDetails);
        
        if (!$logSuccess) {
            error_log("Failed to log user login activity for user ID: " . $user['UserID']);
        }
        
        // Check if user is admin and handle redirect
        $redirectInfo = redirectBasedOnRole($user['Role']);
        
        // Success response
        $responseData = [
            'firstName' => $user['first_name'],
            'role' => $user['Role'],
            'email' => $user['Email']
        ];
        
        // Add redirect info if applicable
        if ($redirectInfo) {
            $responseData = array_merge($responseData, $redirectInfo);
        }
        
        returnJsonResponse(true, 'Login successful!', $responseData, $conn);
        
    } else {
        // Incorrect password
        $stmt->close();
        returnJsonResponse(false, 'Invalid username or password.', [], $conn);
    }
} else {
    // Username not found
    $stmt->close();
    returnJsonResponse(false, 'Invalid username or password.', [], $conn);
}

// This should never be reached
$stmt->close();
returnJsonResponse(false, 'An unexpected error occurred.', [], $conn);
?>