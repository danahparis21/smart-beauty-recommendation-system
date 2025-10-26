<?php
session_start();

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

// Verify the token from users table (for manual signups)
$stmt = $conn->prepare('SELECT UserID, first_name, email, email_verified FROM users WHERE verification_token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Check if already verified
    if ($user['email_verified'] == 1) {
        $message = "Email already verified! You can now login.";
    } else {
        // Mark email as verified
        $updateStmt = $conn->prepare('UPDATE users SET email_verified = 1, verification_token = NULL WHERE UserID = ?');
        $updateStmt->bind_param('i', $user['UserID']);
        
        if ($updateStmt->execute()) {
            // Auto-login the user after verification
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['role'] = 'customer'; // Default role for manual signups
            
            $message = "Email verified successfully! You have been automatically logged in.";
        } else {
            $message = "Error verifying email. Please try again.";
        }
        $updateStmt->close();
    }
    
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
            <p>' . $message . '</p>
            <a href="/user/html/home.html" class="btn">Go to Dashboard</a>
        </div>
    </body>
    </html>';
    
} else {
    // Invalid token - check if it's a Google registration
    if (isset($_SESSION['pending_google_registration']) && $_SESSION['pending_google_registration']['verification_token'] === $token) {
        // Handle Google verification (your existing code)
        include 'verify-google-email.php';
        exit();
    }
    
    // Invalid token for both manual and Google
    $stmt->close();
    $conn->close();
    
    echo '<script>
        alert("Invalid or expired verification link. Please try signing up again.");
        window.location.href="/user/html/signup.html";
    </script>';
}
?>