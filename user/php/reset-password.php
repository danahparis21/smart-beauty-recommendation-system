<?php
session_start();

// Load database configuration
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

require_once __DIR__ . '/../../config/google-config.php';

// Check if token is provided
if (!isset($_GET['token']) && !isset($_POST['token'])) {
    die('<script>alert("Invalid reset link."); window.location.href="/user/html/login.html";</script>');
}

$token = $_GET['token'] ?? $_POST['token'];

// If it's a POST request (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit();
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit();
    }
    
    // Verify token and check expiry
    $stmt = $conn->prepare('SELECT UserID, first_name, reset_token_expiry FROM users WHERE reset_token = ?');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Check if token is expired
    if (time() > $user['reset_token_expiry']) {
        echo json_encode(['success' => false, 'message' => 'Reset token has expired. Please request a new one.']);
        exit();
    }
    
    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and clear reset token
    $updateStmt = $conn->prepare('UPDATE users SET Password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE UserID = ?');
    $updateStmt->bind_param('si', $hashedPassword, $user['UserID']);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Password reset successfully!']);
    } else {
        $updateStmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
    }
    exit();
}

// If it's a GET request, verify token and show form
$stmt = $conn->prepare('SELECT UserID, first_name, reset_token_expiry FROM users WHERE reset_token = ?');
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Invalid Reset Link - Beauty & Blessed</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: Montserrat, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 450px; }
            .error { color: #e74c3c; font-size: 70px; margin-bottom: 20px; }
            h1 { color: #ff69b4; margin-bottom: 15px; font-size: 28px; }
            p { color: #666; line-height: 1.6; margin-bottom: 25px; font-size: 16px; }
            .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 14px 32px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error"><i class="fas fa-exclamation-triangle"></i></div>
            <h1>Invalid Reset Link</h1>
            <p>This password reset link is invalid or has already been used.</p>
            <a href="/user/html/forgot-password.html" class="btn">Request New Link</a>
        </div>
    </body>
    </html>';
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Check if token is expired
if (time() > $user['reset_token_expiry']) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Link Expired - Beauty & Blessed</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: Montserrat, sans-serif; background: linear-gradient(135deg, #ffe6f2, #fff); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .container { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(255, 105, 180, 0.25); text-align: center; max-width: 450px; }
            .warning { color: #f39c12; font-size: 70px; margin-bottom: 20px; }
            h1 { color: #ff69b4; margin-bottom: 15px; font-size: 28px; }
            p { color: #666; line-height: 1.6; margin-bottom: 25px; font-size: 16px; }
            .btn { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 14px 32px; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255, 105, 180, 0.4); }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="warning"><i class="fas fa-clock"></i></div>
            <h1>Link Expired</h1>
            <p>This password reset link has expired. For your security, reset links are only valid for 1 hour.</p>
            <a href="/user/html/forgot-password.html" class="btn">Request New Link</a>
        </div>
    </body>
    </html>';
    exit();
}

// Show reset password form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty & Blessed | Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            --hover-shadow: 0 25px 50px rgba(255, 105, 180, 0.12);
        }

        body {
            background: linear-gradient(135deg, #ffe6f2 0%, #ffffff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: "Montserrat", sans-serif;
            overflow-x: hidden;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 440px;
            padding: 45px 40px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease forwards;
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.4s ease;
        }

        .container:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-5px);
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-pink), var(--gold));
            transform: scaleX(0);
            transform-origin: left;
            animation: expandLine 1s ease 0.5s forwards;
        }

        @keyframes expandLine {
            to {
                transform: scaleX(1);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
            opacity: 0;
            animation: fadeIn 1s ease 0.3s forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .logo h1 {
            color: var(--primary-pink);
            font-size: 38px;
            font-weight: 600;
            letter-spacing: 1.5px;
            font-family: "Cormorant Garamond", serif;
            margin-bottom: 8px;
            position: relative;
            display: inline-block;
        }

        .logo h1::after {
            content: "";
            position: absolute;
            bottom: -5px;
            left: 10%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            transform: scaleX(0);
            transform-origin: center;
            animation: expandLine 1s ease 1s forwards;
        }

        .logo p {
            color: var(--text-gray);
            font-size: 15px;
            margin-top: 12px;
            letter-spacing: 1.2px;
            font-weight: 400;
        }

        .icon-container {
            text-align: center;
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeIn 1s ease 0.5s forwards;
        }

        .icon-container i {
            font-size: 55px;
            color: var(--primary-pink);
            background: linear-gradient(135deg, var(--primary-pink), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .description {
            text-align: center;
            color: var(--dark-gray);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0;
            animation: fadeIn 1s ease 0.7s forwards;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            opacity: 0;
            transform: translateX(-20px);
            animation: slideInLeft 0.6s ease forwards;
        }

        .input-group:nth-child(2) {
            animation-delay: 0.9s;
        }

        .input-group:nth-child(4) {
            animation-delay: 1.1s;
        }

        @keyframes slideInLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .input-field {
            width: 100%;
            padding: 18px 50px 18px 18px;
            border: 1.5px solid var(--medium-gray);
            border-radius: 12px;
            font-size: 16px;
            background-color: var(--light-gray);
            transition: all 0.4s ease;
            outline: none;
            font-family: "Montserrat", sans-serif;
            letter-spacing: 0.5px;
        }

        .input-field:focus {
            border-color: var(--primary-pink);
            background-color: var(--soft-white);
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.15);
        }

        .input-label {
            position: absolute;
            top: 18px;
            left: 18px;
            font-size: 16px;
            color: var(--text-gray);
            pointer-events: none;
            transition: all 0.4s ease;
            background-color: var(--light-gray);
            padding: 0 5px;
            font-family: "Montserrat", sans-serif;
        }

        .input-field:focus + .input-label,
        .input-field:not(:placeholder-shown) + .input-label {
            top: -10px;
            left: 12px;
            font-size: 13px;
            color: var(--primary-pink);
            background-color: var(--soft-white);
            font-weight: 600;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--gold);
            background-color: rgba(212, 175, 55, 0.1);
        }

        .password-strength {
            margin-top: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            color: var(--text-gray);
            opacity: 0;
            animation: fadeIn 0.8s ease 1s forwards;
        }

        .strength-bar {
            height: 4px;
            background-color: var(--medium-gray);
            border-radius: 2px;
            margin-top: 8px;
            margin-bottom: 5px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak {
            background-color: #e74c3c;
            width: 33%;
        }

        .strength-medium {
            background-color: #f39c12;
            width: 66%;
        }

        .strength-strong {
            background-color: #2ecc71;
            width: 100%;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-pink), var(--dark-pink));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(255, 105, 180, 0.3);
            font-family: "Montserrat", sans-serif;
            letter-spacing: 1px;
            opacity: 0;
            animation: fadeIn 0.8s ease 1.3s forwards;
            position: relative;
            overflow: hidden;
            margin-top: 10px;
        }

        .submit-btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(255, 105, 180, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .decoration {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 182, 193, 0.3), rgba(255, 105, 180, 0.2));
            z-index: -1;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        .decoration-1 {
            top: -80px;
            right: -80px;
            width: 200px;
            height: 200px;
            animation-delay: 0s;
        }

        .decoration-2 {
            bottom: -100px;
            left: -100px;
            width: 250px;
            height: 250px;
            animation-delay: 2s;
        }

        .toast {
            visibility: hidden;
            min-width: 250px;
            margin-left: -125px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 5px;
            padding: 16px;
            position: fixed;
            z-index: 999;
            left: 50%;
            bottom: 30px;
            font-size: 17px;
            opacity: 0;
            transition: opacity 0.5s, visibility 0s 0.5s;
        }

        .toast.show {
            visibility: visible;
            opacity: 1;
            animation: fadein 0.5s, fadeout 0.5s 2.5s;
            transition: opacity 0.5s, visibility 0s 0s;
        }

        @keyframes fadein {
            from {
                bottom: 0;
                opacity: 0;
            }
            to {
                bottom: 30px;
                opacity: 1;
            }
        }

        @keyframes fadeout {
            from {
                bottom: 30px;
                opacity: 1;
            }
            to {
                bottom: 0;
                opacity: 0;
            }
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .container {
                padding: 30px 25px;
            }

            .logo h1 {
                font-size: 32px;
            }

            .icon-container i {
                font-size: 50px;
            }

            .input-field {
                padding: 16px 45px 16px 16px;
            }

            .decoration-1 {
                width: 120px;
                height: 120px;
                top: -50px;
                right: -50px;
            }

            .decoration-2 {
                width: 150px;
                height: 150px;
                bottom: -60px;
                left: -60px;
            }
        }
    </style>
</head>
<body>
    <div class="decoration decoration-1"></div>
    <div class="decoration decoration-2"></div>

    <div class="container">
        <div class="logo">
            <h1>Beauty & Blessed</h1>
            <p>Create New Password</p>
        </div>

        <div class="icon-container">
            <i class="fas fa-key"></i>
        </div>

        <p class="description">
            Hello <strong><?php echo htmlspecialchars($user['first_name']); ?></strong>! Please enter your new password below.
        </p>

        <form id="resetPasswordForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="input-group">
                <input type="password" id="newPassword" name="new_password" class="input-field" placeholder=" " required>
                <label for="newPassword" class="input-label">New Password</label>
                <button type="button" class="password-toggle" id="toggleNew">
                    <i class="far fa-eye"></i>
                </button>
            </div>

            <div class="password-strength">
                <div class="strength-bar">
                    <div class="strength-bar-fill" id="strengthBar"></div>
                </div>
                <span id="strengthText"></span>
            </div>

            <div class="input-group">
                <input type="password" id="confirmPassword" name="confirm_password" class="input-field" placeholder=" " required>
                <label for="confirmPassword" class="input-label">Confirm Password</label>
                <button type="button" class="password-toggle" id="toggleConfirm">
                    <i class="far fa-eye"></i>
                </button>
            </div>

            <button type="submit" class="submit-btn">Reset Password</button>
        </form>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const toggleNewBtn = document.getElementById('toggleNew');
        const toggleConfirmBtn = document.getElementById('toggleConfirm');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const form = document.getElementById('resetPasswordForm');

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Toggle password visibility for new password
        toggleNewBtn.addEventListener('click', function() {
            const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            newPasswordInput.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Toggle password visibility for confirm password
        toggleConfirmBtn.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });

        // Password strength checker
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            strengthBar.className = 'strength-bar-fill';
            
            if (strength === 0) {
                strengthText.textContent = '';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#e74c3c';
            } else if (strength === 3) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#f39c12';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#2ecc71';
            }
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (newPassword.length < 8) {
                showToast('Password must be at least 8 characters long');
                return;
            }

            if (newPassword !== confirmPassword) {
                showToast('Passwords do not match');
                return;
            }

            const submitBtn = document.querySelector('.submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;

                if (data.success) {
                    showToast('Password reset successfully!');
                    setTimeout(() => {
                        window.location.href = '/user/html/login.html';
                    }, 2000);
                } else {
                    showToast(data.message || 'Failed to reset password');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Something went wrong. Please try again.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>
</html>