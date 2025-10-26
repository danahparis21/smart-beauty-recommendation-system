
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

// Ensure Google returned a code
if (!isset($_GET['code'])) {
    echo '<script>alert("Google authentication failed."); window.location.href="/user/html/login.html";</script>';
    exit();
}

$code = $_GET['code'];

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_LOGIN_REDIRECT_URI,
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
    echo '<script>alert("Failed to get access token."); window.location.href="/user/html/login.html";</script>';
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
    echo '<script>alert("Failed to get user information."); window.location.href="/user/html/login.html";</script>';
    exit();
}

// Extract user details
$googleId = $userInfo['id'];
$email = $userInfo['email'];
$firstName = $userInfo['given_name'] ?? '';
$lastName = $userInfo['family_name'] ?? '';

// ===== CHECK IF USER EXISTS AND IS VERIFIED =====
$stmt = $conn->prepare('SELECT UserID, username, first_name, Role, email_verified FROM users WHERE Email = ? AND google_id IS NOT NULL');
if (!$stmt) {
    echo '<script>alert("Database error."); window.location.href="/user/html/login.html";</script>';
    exit();
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Check if email is verified
    if ($user['email_verified'] == 0) {
        $stmt->close();
        $conn->close();
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Account Not Verified</title>
            <style>
                body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 400px; }
                .warning { color: #e74c3c; font-size: 48px; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; margin-bottom: 25px; }
                .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 12px 30px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; }
                .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="warning">⚠️</div>
                <h1>Account Not Verified</h1>
                <p>Your Google account exists but is not verified. Please check your email <strong>' . htmlspecialchars($email) . '</strong> for the verification link.</p>
                <button class="btn" onclick="window.location.href=\'/user/html/login.html\'">Back to Login</button>
            </div>
        </body>
        </html>';
        exit();
    }
    
    // Log them in (only if verified)
    $_SESSION['user_id'] = $user['UserID'];
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['Role'];
    
    $stmt->close();
    $conn->close();
    
    // Redirect based on role
    $redirectUrl = ($user['Role'] === 'admin') ? '../../admin/html/admin.html' : '/user/html/home.html';
    echo '<script>alert("Welcome back! Logging you in..."); window.location.href="' . $redirectUrl . '";</script>';
    exit();
    
} else {
    // User not found with Google login
    $stmt->close();
    $conn->close();
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Account Not Found</title>
        <style>
            body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 400px; }
            .error { color: #e74c3c; font-size: 48px; margin-bottom: 20px; }
            h1 { color: #333; margin-bottom: 20px; }
            p { color: #666; line-height: 1.6; margin-bottom: 25px; }
            .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 12px 30px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; margin: 5px; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error">❌</div>
            <h1>Account Not Found</h1>
            <p>No Google account found with email <strong>' . htmlspecialchars($email) . '</strong>. Please sign up first.</p>
            <button class="btn" onclick="window.location.href=\'/user/html/signup.html\'">Sign Up</button>
            <button class="btn" onclick="window.location.href=\'/user/html/login.html\'">Back to Login</button>
        </div>
    </body>
    </html>';
}
?>