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
require_once __DIR__ . '/security_utils.php';
require_once __DIR__ . '/send-verification.php';

function returnJsonResponse($success, $message, $data = [], $conn = null) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
    exit();
}

function redirectBasedOnRole($role) {
    if ($role === 'admin') {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return [
                'redirect' => true,
                'redirectUrl' => '../../admin/html/admin.html'
            ];
        } else {
            header('Location: /admin/html/admin.html');
            exit();
        }
    }
    return null;
}

if ($conn->connect_error) {
    returnJsonResponse(false, "Database Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonResponse(false, 'Invalid request method.', [], $conn);
}

// 1. reCAPTCHA Validation
$recaptchaToken = $_POST['recaptcha_token'] ?? '';
if (!validateRecaptcha($recaptchaToken)) {
    returnJsonResponse(false, 'reCAPTCHA verification failed. Please try again.', ['error_type' => 'recaptcha'], $conn);
}

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    returnJsonResponse(false, 'Please provide both username and password.', [], $conn);
}

$username = trim($_POST['username'] ?? ''); 
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    returnJsonResponse(false, 'Username and password cannot be empty.', [], $conn);
}

// 2. Check Account Lock
$lockTime = getAccountLockRemainingTime($conn, $username);
if ($lockTime > 0) {
    returnJsonResponse(false, "Account is temporarily locked. Please try again in " . formatRemainingTime($lockTime), [
        'locked' => true,
        'remaining_time' => $lockTime
    ], $conn);
}

$stmt = $conn->prepare("SELECT UserID, Password, first_name, Role, Email, email_verified, login_attempts FROM users WHERE username = ?");
if (!$stmt) {
    returnJsonResponse(false, 'Database error (prepare statement).', [], $conn);
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['Password'])) {
        
        if ($user['email_verified'] == 0) {
            $stmt->close();
            returnJsonResponse(false, 'Please verify your email before logging in.', [], $conn);
        }
        
        // 3. Reset Login Attempts on Success
        resetLoginAttempts($conn, $username);
        
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // 4. Detect Suspicious Login — notify via email but still allow login
        if (isSuspiciousLogin($conn, $user['UserID'], $userIP, $userAgent)) {
            // Send alert email in the background (non-blocking)
            $details = ['ip' => $userIP, 'userAgent' => $userAgent];
            $alertSent = sendSuspiciousLoginAlert($user['Email'], $user['first_name'], $details);
            if (!$alertSent) {
                error_log("Failed to send suspicious login alert to: " . $user['Email']);
            }
        }
        
        // Always update IP/device on every successful login
        updateLastLoginDetails($conn, $user['UserID'], $userIP, $userAgent);

        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['username'] = $username;
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role'] = $user['Role'];
        $_SESSION['email'] = $user['Email']; 
        
        $stmt->close();
        
        $loginDetails = "IP: {$userIP}, Browser: " . substr($userAgent, 0, 100);
        logUserActivity($conn, $user['UserID'], 'User login', $loginDetails);
        
        $redirectInfo = redirectBasedOnRole($user['Role']);
        $responseData = [
            'firstName' => $user['first_name'],
            'role' => $user['Role'],
            'email' => $user['Email']
        ];
        if ($redirectInfo) {
            $responseData = array_merge($responseData, $redirectInfo);
        }
        
        returnJsonResponse(true, 'Login successful!', $responseData, $conn);
        
    } else {
        // 5. Increment Login Attempts on Failure
        $isLocked = recordFailedAttempt($conn, $username);
        
        // Fetch updated attempts
        $stmt_attempts = $conn->prepare("SELECT login_attempts FROM users WHERE username = ?");
        $stmt_attempts->bind_param("s", $username);
        $stmt_attempts->execute();
        $res_attempts = $stmt_attempts->get_result();
        $u_attempts = $res_attempts->fetch_assoc();
        $remaining = 5 - ($u_attempts['login_attempts'] ?? 0);
        
        $stmt->close();
        
        if ($isLocked) {
           returnJsonResponse(false, 'Too many failed attempts. Account locked for 15 minutes.', ['locked' => true, 'remaining_time' => 900], $conn);
        } else {
           returnJsonResponse(false, "Invalid username or password. Remaining attempts: $remaining", ['attempts_left' => $remaining], $conn);
        }
    }
} else {
    $stmt->close();
    returnJsonResponse(false, 'Invalid username or password.', [], $conn);
}
returnJsonResponse(false, 'An unexpected error occurred.', [], $conn);
?>
