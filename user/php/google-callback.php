<?php
session_start();

// Load configuration
require_once __DIR__ . '/../../config/google-config.php';

// Load database configuration
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Load email verification function
require_once __DIR__ . '/send-verification.php';

// Define DEV_MODE if not already defined
if (!defined('DEV_MODE')) define('DEV_MODE', true);

// Ensure Google returned a code
if (!isset($_GET['code'])) {
    echo '<script>alert("Google authentication failed."); window.location.href="/user/html/signup.html";</script>';
    exit();
}

$code = $_GET['code'];

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
$response = curl_exec($ch);
curl_close($ch);

$tokenInfo = json_decode($response, true);

if (!isset($tokenInfo['access_token'])) {
    echo '<script>alert("Failed to get access token."); window.location.href="/user/html/signup.html";</script>';
    exit();
}

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenInfo['access_token']
]);
$userInfoResponse = curl_exec($ch);
curl_close($ch);

$userInfo = json_decode($userInfoResponse, true);

if (!isset($userInfo['email'])) {
    echo '<script>alert("Failed to get user information."); window.location.href="/user/html/signup.html";</script>';
    exit();
}

// Extract user details
$googleId = $userInfo['id'];
$email = $userInfo['email'];
$firstName = $userInfo['given_name'] ?? '';
$lastName = $userInfo['family_name'] ?? '';
$username = explode('@', $email)[0] . '_' . substr($googleId, -4);

// ===== CHECK IF USER ALREADY EXISTS =====
$stmt = $conn->prepare('SELECT UserID, email_verified, Role, google_id FROM users WHERE Email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ===== USER EXISTS =====
    $user = $result->fetch_assoc();

    if ($user['email_verified'] == 0) {
        echo '<script>alert("Your account exists but is not verified. Please check your email for the verification link."); window.location.href="/user/html/signup.html";</script>';
        exit();
    }

    // If verified, ensure google_id is set
    if (empty($user['google_id'])) {
        $updateStmt = $conn->prepare('UPDATE users SET google_id = ? WHERE UserID = ?');
        $updateStmt->bind_param('si', $googleId, $user['UserID']);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Log user in
    $_SESSION['user_id'] = $user['UserID'];
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['role'] = $user['Role'];

    echo '<script>alert("Welcome back! Logging you in..."); window.location.href="/user/html/home.html";</script>';
    exit();

} else {
    // ===== NEW GOOGLE USER SIGNUP =====
    $stmt->close();

    $role = 'customer';
    $emailVerified = 0;
    $verificationToken = bin2hex(random_bytes(32));

    // Insert new user (unverified)
    $insert = $conn->prepare('INSERT INTO users (username, first_name, last_name, Email, Role, email_verified, google_id, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->bind_param('sssssiss', $username, $firstName, $lastName, $email, $role, $emailVerified, $googleId, $verificationToken);

    if (!$insert->execute()) {
        echo '<script>alert("Database error during signup."); window.location.href="/user/html/signup.html";</script>';
        exit();
    }

    $insert->close();

    // ===== SEND VERIFICATION EMAIL =====
    $emailSent = sendVerificationEmail($email, $firstName, $verificationToken);

    if (!$emailSent) {
        echo '<script>alert("Failed to send verification email. Please contact support."); window.location.href="/user/html/signup.html";</script>';
        exit();
    }

    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Verify Your Email</title>
        <style>
            body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 500px; }
            .success-icon { color: #ff69b4; font-size: 80px; margin-bottom: 20px; }
            h1 { color: #ff69b4; margin-bottom: 20px; font-size: 28px; }
            p { color: #555; line-height: 1.6; margin: 15px 0; font-size: 16px; }
            .email { color: #ff69b4; font-weight: bold; font-size: 18px; margin: 20px 0; }
            .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 15px 35px; border: none; border-radius: 10px; font-weight: 600; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">📧</div>
            <h1>Please Verify Your Email</h1>
            <p>We have sent a verification link to:</p>
            <div class="email">' . htmlspecialchars($email) . '</div>
            <p>Click the link in your inbox to activate your account.</p>
            <button class="btn" onclick="window.location.href=\'/user/html/login.html\'">Go to Login</button>
        </div>
    </body>
    </html>';
}

$conn->close();
?>