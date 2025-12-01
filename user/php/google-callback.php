<?php
// Prevent any accidental output
ob_start();
session_start();

// Ensure we're outputting HTML, not JSON
header('Content-Type: text/html; charset=UTF-8');

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

// Determine if this is login or signup based on the 'state' parameter
$isLogin = isset($_GET['state']) && $_GET['state'] === 'login';

// Ensure Google returned a code
if (!isset($_GET['code'])) {
    $redirectPage = $isLogin ? '/user/html/login.html' : '/user/html/signup.html';
    echo '<script>alert("Google authentication failed."); window.location.href="' . $redirectPage . '";</script>';
    exit();
}

$code = $_GET['code'];

// Determine which redirect URI to use based on state
$redirectUri = $isLogin ? GOOGLE_LOGIN_REDIRECT_URI : GOOGLE_REDIRECT_URI;

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug log
error_log("Token Response Code: " . $httpCode);
error_log("Token Response: " . $response);

$tokenInfo = json_decode($response, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON Decode Error: " . json_last_error_msg());
    $redirectPage = $isLogin ? '/user/html/login.html' : '/user/html/signup.html';
    echo '<script>alert("Invalid response from Google."); window.location.href="' . $redirectPage . '";</script>';
    exit();
}

if (!isset($tokenInfo['access_token'])) {
    $redirectPage = $isLogin ? '/user/html/login.html' : '/user/html/signup.html';
    echo '<script>alert("Failed to get access token."); window.location.href="' . $redirectPage . '";</script>';
    exit();
}

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenInfo['access_token'],
    'Accept: application/json'
]);
$userInfoResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug log
error_log("UserInfo Response Code: " . $httpCode);
error_log("UserInfo Response: " . $userInfoResponse);

$userInfo = json_decode($userInfoResponse, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON Decode Error on UserInfo: " . json_last_error_msg());
    $redirectPage = $isLogin ? '/user/html/login.html' : '/user/html/signup.html';
    echo '<script>alert("Failed to decode user information."); window.location.href="' . $redirectPage . '";</script>';
    exit();
}

if (!isset($userInfo['email'])) {
    $redirectPage = $isLogin ? '/user/html/login.html' : '/user/html/signup.html';
    echo '<script>alert("Failed to get user information."); window.location.href="' . $redirectPage . '";</script>';
    exit();
}

// Extract user details
$googleId = $userInfo['id'];
$email = $userInfo['email'];
$firstName = $userInfo['given_name'] ?? '';
$lastName = $userInfo['family_name'] ?? '';
$username = explode('@', $email)[0] . '_' . substr($googleId, -4);

// ===== CHECK IF USER EXISTS =====
$stmt = $conn->prepare('SELECT UserID, username, first_name, last_name, Role, email_verified, google_id, verification_token FROM users WHERE Email = ?');
if (!$stmt) {
    $redirectPage = $isLogin ? '/user/html/login.html' : '/user/html/signup.html';
    echo '<script>alert("Database error."); window.location.href="' . $redirectPage . '";</script>';
    exit();
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ===== USER EXISTS =====
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
                body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
                .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 450px; width: 100%; }
                .warning { color: #e74c3c; font-size: 48px; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; margin-bottom: 25px; }
                .email-display { background: #ffe6f2; padding: 10px; border-radius: 8px; margin: 15px 0; color: #ff69b4; font-weight: 600; word-break: break-all; }
                .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 12px 30px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; margin: 5px; transition: all 0.3s ease; display: inline-block; }
                .btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
                .btn:disabled { opacity: 0.5; cursor: not-allowed; background: linear-gradient(135deg, #ccc, #999); }
                .btn-secondary { background: linear-gradient(135deg, #888, #666); }
                .timer-text { color: #666; font-size: 14px; margin-top: 10px; min-height: 20px; }
                .success-message { color: #2ecc71; font-weight: 600; margin-top: 15px; padding: 10px; background: #d4edda; border-radius: 8px; display: none; }
                .error-message { color: #e74c3c; font-weight: 600; margin-top: 15px; padding: 10px; background: #f8d7da; border-radius: 8px; display: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="warning">⚠️</div>
                <h1>Account Not Verified</h1>
                <p>Your account exists but is not verified. Please check your email for the verification link.</p>
                <div class="email-display">' . htmlspecialchars($email) . '</div>
                <p style="font-size: 14px; color: #888;">Didn\'t receive the email? Check your spam folder or click below to resend.</p>
                
                <button class="btn" id="resendBtn" onclick="resendVerification()">
                    📧 Resend Verification Email
                </button>
                <button class="btn btn-secondary" onclick="window.location.href=\'/user/html/login.html\'">
                    Back to Login
                </button>
                
                <div class="timer-text" id="timerText"></div>
                <div class="success-message" id="successMsg">✅ Verification email sent successfully!</div>
                <div class="error-message" id="errorMsg">❌ Failed to send email. Please try again.</div>
            </div>
            
            <script>
                let cooldownTimer = 0;
                let timerInterval = null;
                
                const userEmail = ' . json_encode($email) . ';
                const userToken = ' . json_encode($user['verification_token']) . ';
                
                // Check if there is an active cooldown in localStorage
                const cooldownEnd = localStorage.getItem("resendCooldownEnd_" + userEmail);
                if (cooldownEnd) {
                    const remainingTime = Math.floor((parseInt(cooldownEnd) - Date.now()) / 1000);
                    if (remainingTime > 0) {
                        startTimer(remainingTime);
                    } else {
                        localStorage.removeItem("resendCooldownEnd_" + userEmail);
                    }
                }
                
                function startTimer(seconds) {
                    cooldownTimer = seconds;
                    document.getElementById("resendBtn").disabled = true;
                    updateTimerDisplay();
                    
                    timerInterval = setInterval(() => {
                        cooldownTimer--;
                        updateTimerDisplay();
                        
                        if (cooldownTimer <= 0) {
                            clearInterval(timerInterval);
                            document.getElementById("resendBtn").disabled = false;
                            document.getElementById("timerText").textContent = "";
                            localStorage.removeItem("resendCooldownEnd_" + userEmail);
                        }
                    }, 1000);
                }
                
                function updateTimerDisplay() {
                    document.getElementById("timerText").textContent = 
                        `Please wait ${cooldownTimer} second${cooldownTimer !== 1 ? "s" : ""} before resending...`;
                }
                
                async function resendVerification() {
                    // Hide previous messages
                    document.getElementById("successMsg").style.display = "none";
                    document.getElementById("errorMsg").style.display = "none";
                    
                    try {
                        const resendUrl = "/user/php/resend-verification-ajax.php";
                        
                        console.log("Sending request to:", resendUrl);
                        console.log("Email:", userEmail);
                        console.log("Token:", userToken);
                        
                        const response = await fetch(resendUrl, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json"
                            },
                            body: JSON.stringify({
                                email: userEmail,
                                token: userToken
                            })
                        });
                        
                        console.log("Response status:", response.status);
                        
                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error("HTTP Error:", response.status, errorText);
                            document.getElementById("errorMsg").textContent = "❌ Server error (" + response.status + "). Please try again.";
                            document.getElementById("errorMsg").style.display = "block";
                            return;
                        }
                        
                        const result = await response.json();
                        console.log("Response:", result);
                        
                        if (result.success) {
                            document.getElementById("successMsg").style.display = "block";
                            
                            // Start 10-second cooldown
                            const cooldownEndTime = Date.now() + (10 * 1000);
                            localStorage.setItem("resendCooldownEnd_" + userEmail, cooldownEndTime);
                            startTimer(10);
                        } else {
                            document.getElementById("errorMsg").textContent = "❌ " + (result.message || "Failed to send email. Please try again.");
                            document.getElementById("errorMsg").style.display = "block";
                        }
                    } catch (error) {
                        console.error("Error:", error);
                        document.getElementById("errorMsg").textContent = "❌ Network error: " + error.message;
                        document.getElementById("errorMsg").style.display = "block";
                    }
                }
            </script>
        </body>
        </html>';
        exit();
    }

    // === LOGIN FLOW: User exists and is verified ===
    if ($isLogin) {
        // Must have google_id for login flow
        if (empty($user['google_id'])) {
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
            exit();
        }
    } else {
        // === SIGNUP FLOW: User exists, ensure google_id is set ===
        if (empty($user['google_id'])) {
            $updateStmt = $conn->prepare('UPDATE users SET google_id = ? WHERE UserID = ?');
            $updateStmt->bind_param('si', $googleId, $user['UserID']);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    // Log user in
    $_SESSION['user_id'] = $user['UserID'];
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['Role'];

    $stmt->close();
    $conn->close();

    // Redirect based on role with welcome screen
    $redirectUrl = ($user['Role'] === 'admin') ? '../../admin/html/admin.html' : '/user/html/home.html';
    
    // Clear any previous output
    if (ob_get_level()) ob_end_clean();
    
    // Ensure no output before this
    header('Content-Type: text/html; charset=UTF-8');
    
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome Back | Beauty & Blessed</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet" />
    <style>
        :root {
        --primary-pink: #ff69b4;
        --light-pink: #ffb6c1;
        --dark-pink: #ff1493;
        --gold: #d4af37;
        --light-gold: #f7e8c4;
        --soft-white: #fefefe;
        --light-gray: #f5f5f5;
        --medium-gray: #e0e0e0;
        --text-gray: #888;
        --dark-gray: #555;
        --card-shadow: 0 20px 40px rgba(255, 105, 180, 0.08);
        }

        body {
        background: linear-gradient(135deg, #fff5f9 0%, #ffe6f2 50%, #ffd1dc 100%);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        font-family: "Montserrat", sans-serif;
        overflow: hidden;
        margin: 0;
        }

        .welcome-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        width: 100%;
        max-width: 460px;
        padding: 50px 40px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.8);
        animation: fadeInUp 0.8s ease forwards;
        }

        .brand-name {
        font-family: "Cormorant Garamond", serif;
        font-size: 32px;
        font-weight: 600;
        color: var(--primary-pink);
        margin-bottom: 6px;
        text-shadow: 0 2px 6px rgba(255, 182, 193, 0.3);
        }

        .brand-tagline {
        font-size: 13px;
        color: var(--text-gray);
        letter-spacing: 1px;
        margin-bottom: 30px;
        }

        .welcome-message {
        font-size: 20px;
        font-weight: 500;
        color: var(--dark-gray);
        margin-bottom: 10px;
        }

        .sub-message {
        color: var(--text-gray);
        font-size: 14px;
        margin-bottom: 30px;
        }

        .spinner {
        width: 70px;
        height: 70px;
        border: 6px solid var(--light-pink);
        border-top: 6px solid var(--gold);
        border-radius: 50%;
        margin: 0 auto 25px;
        animation: spin 1s linear infinite;
        }

        .loading-text {
        color: var(--dark-pink);
        font-weight: 500;
        font-size: 14px;
        letter-spacing: 0.5px;
        }

        @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
        }

        @keyframes fadeOut {
        to { opacity: 0; transform: scale(0.95); }
        }

        .fade-out {
        animation: fadeOut 0.6s ease forwards;
        }
    </style>
    </head>
    <body>
    <div class="welcome-container" id="welcomeScreen">
        <h1 class="brand-name">Beauty & Blessed</h1>
        <p class="brand-tagline">Elevate Your Everyday Glam.</p>

        <div class="welcome-message">Welcome back, ' . htmlspecialchars($user['first_name']) . '! 💖</div>
        <p class="sub-message">We\'re preparing the store for you...</p>

        <div class="spinner"></div>
        <p class="loading-text">Loading your personalized experience...</p>
    </div>

    <script>
        setTimeout(() => {
        document.getElementById("welcomeScreen").classList.add("fade-out");
        setTimeout(() => {
            window.location.href = "' . $redirectUrl . '";
        }, 600);
        }, 2500);
    </script>
    </body>
    </html>';
    exit();

} else {
    // ===== NEW USER - ONLY ALLOWED DURING SIGNUP FLOW =====
    $stmt->close();

    if ($isLogin) {
        // User trying to login but doesn't exist
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
                <p>No account found with email <strong>' . htmlspecialchars($email) . '</strong>. Please sign up first.</p>
                <button class="btn" onclick="window.location.href=\'/user/html/signup.html\'">Sign Up</button>
                <button class="btn" onclick="window.location.href=\'/user/html/login.html\'">Back to Login</button>
            </div>
        </body>
        </html>';
        exit();
    }

   // === SIGNUP FLOW: Create new user ===
$role = 'customer';
$emailVerified = 0;
$verificationToken = bin2hex(random_bytes(32));

// Generate a unique username
$attempts = 0;
do {
    if ($attempts > 0) {
        $username = explode('@', $email)[0] . '_' . substr($googleId, -4) . '_' . $attempts;
    } else {
        $username = explode('@', $email)[0] . '_' . substr($googleId, -4);
    }
    
    // Check if username already exists
    $checkStmt = $conn->prepare('SELECT UserID FROM users WHERE username = ?');
    $checkStmt->bind_param('s', $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $usernameExists = $checkResult->num_rows > 0;
    $checkStmt->close();
    
    $attempts++;
    
    // Prevent infinite loop
    if ($attempts > 10) {
        $conn->close();
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Signup Error</title>
            <style>
                body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 500px; }
                .error { color: #e74c3c; font-size: 48px; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; margin-bottom: 25px; }
                .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 12px 30px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; margin: 5px; }
                .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error">⚠️</div>
                <h1>Unable to Create Account</h1>
                <p>We encountered an issue generating a unique username. Please try again or contact support.</p>
                <button class="btn" onclick="window.location.href=\'/user/html/signup.html\'">Try Again</button>
            </div>
        </body>
        </html>';
        exit();
    }
} while ($usernameExists);

// Insert new user (unverified)
$insert = $conn->prepare('INSERT INTO users (username, first_name, last_name, Email, Role, email_verified, google_id, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$insert->bind_param('sssssiss', $username, $firstName, $lastName, $email, $role, $emailVerified, $googleId, $verificationToken);

if (!$insert->execute()) {
    // If still fails, show user-friendly error
    $conn->close();
    
    // Check if it's a duplicate entry error
    if (strpos($conn->error, 'Duplicate entry') !== false) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Account Already Exists</title>
            <style>
                body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 500px; }
                .info-icon { color: #3498db; font-size: 60px; margin-bottom: 20px; }
                h1 { color: #ff69b4; margin-bottom: 20px; font-size: 28px; }
                p { color: #555; line-height: 1.6; margin: 15px 0; font-size: 16px; }
                .email { color: #ff69b4; font-weight: bold; font-size: 18px; margin: 20px 0; padding: 10px; background: #ffe6f2; border-radius: 8px; }
                .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 15px 35px; border: none; border-radius: 10px; font-weight: 600; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px; }
                .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
                .btn-secondary { background: linear-gradient(135deg, #888, #666); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="info-icon">👤</div>
                <h1>Account Already Exists</h1>
                <p>It looks like an account with this email already exists:</p>
                <div class="email">' . htmlspecialchars($email) . '</div>
                <p>Please proceed to login using your existing account.</p>
                <button class="btn" onclick="window.location.href=\'/user/html/login.html\'">Go to Login</button>
                <button class="btn btn-secondary" onclick="window.location.href=\'/user/html/signup.html\'">Try Different Email</button>
            </div>
        </body>
        </html>';
    } else {
        // Other database error
        error_log("Database insert error: " . $conn->error);
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Signup Error</title>
            <style>
                body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 500px; }
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
                <h1>Something Went Wrong</h1>
                <p>We encountered an error creating your account. Please try again.</p>
                <button class="btn" onclick="window.location.href=\'/user/html/signup.html\'">Try Again</button>
            </div>
        </body>
        </html>';
    }
    exit();
}

   // SUCCESS: Close the insert statement
   $insert->close();

   // ===== SEND VERIFICATION EMAIL =====
   $emailSent = sendVerificationEmail($email, $firstName, $verificationToken);

   if (!$emailSent) {
       $conn->close();
       echo '<script>alert("Failed to send verification email. Please contact support."); window.location.href="/user/html/signup.html";</script>';
       exit();
   }

   $conn->close();

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
?>