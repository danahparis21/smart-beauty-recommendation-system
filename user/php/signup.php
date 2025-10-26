<?php
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

require_once __DIR__ . '/send-verification.php';

function returnJsonError($message, $conn = null)
{
    if ($conn && $conn->connect_error === null) {
        $conn->close();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit();
}

function returnSuccessPage($email, $firstName) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Check Your Email - Beauty & Blessed</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: "Montserrat", Arial, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
            .container { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 500px; width: 100%; }
            .success-icon { color: #ff69b4; font-size: 80px; margin-bottom: 20px; }
            h1 { color: #ff69b4; margin-bottom: 20px; font-size: 28px; }
            p { color: #555; line-height: 1.6; margin: 15px 0; font-size: 16px; }
            .email { color: #ff69b4; font-weight: bold; font-size: 18px; margin: 20px 0; background: #ffe6f2; padding: 12px; border-radius: 8px; }
            .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 15px 35px; border: none; border-radius: 10px; font-weight: 600; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; transition: all 0.3s ease; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
            .note { font-size: 14px; color: #888; margin-top: 25px; line-height: 1.5; }
            
            @media (max-width: 480px) {
                .container { padding: 35px 25px; }
                h1 { font-size: 24px; }
                .success-icon { font-size: 60px; }
                .email { font-size: 16px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-icon">📧</div>
            <h1>Please Check Your Email</h1>
            <p>We have sent a verification link to:</p>
            <div class="email">' . htmlspecialchars($email) . '</div>
            <p>Please check your inbox and click the verification link to complete your registration.</p>
            <p class="note">Make sure to check your spam folder if you don\'t see the email.<br>The verification link does not expire.</p>
            <button class="btn" onclick="window.location.href=\'/user/html/login.html\'">Go to Login</button>
        </div>
    </body>
    </html>';
    exit();
}

if ($conn->connect_error) {
    returnJsonError('Database Connection failed: ' . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validations
    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        returnJsonError('Please fill in all required fields.', $conn);
    }

    if (strlen($username) < 4) {
        returnJsonError('Username must be at least 4 characters long.', $conn);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        returnJsonError('Invalid email format.', $conn);
    }

    if ($password !== $confirmPassword) {
        returnJsonError('Passwords do not match.', $conn);
    }

    if (strlen($password) < 8) {
        returnJsonError('Password must be at least 8 characters.', $conn);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Check if email or username exists
    $stmt = $conn->prepare('SELECT UserID FROM users WHERE Email = ? OR username = ?');
    $stmt->bind_param('ss', $email, $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $check_email_stmt = $conn->prepare('SELECT UserID FROM users WHERE Email = ?');
        $check_email_stmt->bind_param('s', $email);
        $check_email_stmt->execute();
        $check_email_stmt->store_result();

        if ($check_email_stmt->num_rows > 0) {
            returnJsonError('Email already registered.', $conn);
        } else {
            returnJsonError('Username already taken.', $conn);
        }
        $check_email_stmt->close();
    }
    $stmt->close();

    // Generate verification token
    $verificationToken = bin2hex(random_bytes(32));
    $role = 'customer';

    // ===== CREATE ACCOUNT BUT MARK AS UNVERIFIED =====
    $stmt = $conn->prepare('INSERT INTO users (username, first_name, last_name, Email, Password, Role, verification_token, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, 0)');
    $stmt->bind_param('sssssss', $username, $firstName, $lastName, $email, $passwordHash, $role, $verificationToken);

    if ($stmt->execute()) {
        // ===== SEND VERIFICATION EMAIL =====
        $emailSent = sendVerificationEmail($email, $firstName, $verificationToken);
        
        if (!$emailSent) {
            // If email fails, delete the unverified account
            $deleteStmt = $conn->prepare('DELETE FROM users WHERE Email = ? AND email_verified = 0');
            $deleteStmt->bind_param('s', $email);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            returnJsonError('Failed to send verification email. Please check your email address or try again later.', $conn);
        }

        // Success - Show beautiful UI page instead of JSON
        $stmt->close();
        $conn->close();
        returnSuccessPage($email, $firstName);
        
    } else {
        returnJsonError('Database Error: Could not create account. ' . $stmt->error, $conn);
    }
} else {
    returnJsonError('Invalid request method.', $conn);
}
?>