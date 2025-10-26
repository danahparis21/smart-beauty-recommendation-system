<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load database configuration
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if (!isset($_GET['token'])) {
    die('<script>alert("Invalid verification link."); window.location.href="/user/html/signup.html";</script>');
}

$token = $_GET['token'];

// Check if we have a pending GOOGLE registration with this token
if (!isset($_SESSION['pending_google_registration']) || $_SESSION['pending_google_registration']['verification_token'] !== $token) {
    die('<script>alert("Invalid or expired verification link. Please sign up again."); window.location.href="/user/html/signup.html";</script>');
}

// Check if token is expired (24 hours)
if (time() - $_SESSION['pending_google_registration']['created_at'] > 86400) {
    unset($_SESSION['pending_google_registration']);
    die('<script>alert("Verification link has expired. Please sign up again."); window.location.href="/user/html/signup.html";</script>');
}

$pendingUser = $_SESSION['pending_google_registration'];

// Check if email already exists (race condition check)
$stmt = $conn->prepare('SELECT UserID FROM users WHERE Email = ?');
$stmt->bind_param('s', $pendingUser['email']);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    unset($_SESSION['pending_google_registration']);
    die('<script>alert("Email already registered. Please use a different email."); window.location.href="/user/html/signup.html";</script>');
}
$stmt->close();

// FINALLY CREATE THE ACCOUNT (only after verification)
$stmt = $conn->prepare('INSERT INTO users (username, first_name, last_name, Email, google_id, Role, verification_token, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
$stmt->bind_param('sssssss', 
    $pendingUser['username'],
    $pendingUser['first_name'], 
    $pendingUser['last_name'],
    $pendingUser['email'],
    $pendingUser['google_id'],
    $pendingUser['role'],
    $pendingUser['verification_token']
);

if ($stmt->execute()) {
    // Clear pending registration
    unset($_SESSION['pending_google_registration']);
    
    // Auto-login the user
    $userId = $stmt->insert_id;
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $pendingUser['username'];
    $_SESSION['first_name'] = $pendingUser['first_name'];
    $_SESSION['role'] = $pendingUser['role'];
    $_SESSION['email'] = $pendingUser['email'];
    
    $stmt->close();
    $conn->close();
    
    // Show success page
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Email Verified - Beauty & Blessed</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: Montserrat, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 500px; }
            h1 { color: #ff69b4; margin-bottom: 20px; }
            p { color: #555; line-height: 1.6; margin: 15px 0; }
            .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 15px 35px; border: none; border-radius: 10px; font-weight: 600; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
            .success-icon { font-size: 80px; color: #2ecc71; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">✓</div>
            <h1>Email Verified Successfully!</h1>
            <p>Welcome to Beauty & Blessed, ' . htmlspecialchars($pendingUser['first_name']) . '! Your account has been activated.</p>
            <p>You have been automatically logged in.</p>
            <a href="/user/html/home.html" class="btn">Beauty and Blessed</a>
        </div>
    </body>
    </html>';
    
} else {
    unset($_SESSION['pending_google_registration']);
    die('<script>alert("Failed to create account. Please try again."); window.location.href="/user/html/signup.html";</script>');
}
?>