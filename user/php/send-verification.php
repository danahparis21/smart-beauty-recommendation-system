<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/google-config.php';
// Add PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php'; // Path to composer autoload
require_once __DIR__ . '/security_utils.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Manual fallback if autoloader doesn't load PHPMailer (common in some hosting environments)
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $phpMailerPath = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/';
    if (file_exists($phpMailerPath . 'PHPMailer.php')) {
        require_once $phpMailerPath . 'PHPMailer.php';
        require_once $phpMailerPath . 'Exception.php';
        require_once $phpMailerPath . 'SMTP.php';
    }
}

function sendVerificationEmail($email, $firstName, $token) {
    // Determine the base URL dynamically if BASE_URL is not set or potentially wrong
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // If BASE_URL is available and looks valid, use it. Otherwise, construct from environment.
    $currentBase = defined('BASE_URL') && !empty(BASE_URL) ? BASE_URL : "$protocol://$host/smart-beauty-recommendation-system";
    $verificationLink = rtrim($currentBase, '/') . "/user/php/verify-email.php?token=" . $token;
    
    // ===== DEVELOPMENT MODE - Show link instead of sending email =====
    if (defined('DEV_MODE') && DEV_MODE === true) {
        // Store verification link in session for display
        $_SESSION['verification_link'] = $verificationLink;
        $_SESSION['verification_email'] = $email;
        return true; // Always return success in dev mode
    }
    
    // ===== PRODUCTION MODE - Use PHPMailer =====
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log("PHPMailer Error: PHPMailer class not found. Please re-upload the vendor folder.");
        return false;
    }
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Beauty & Blessed Account';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Montserrat', Arial, sans-serif; background-color: #ffe6f2; padding: 20px; margin: 0; }
                .container { background-color: #ffffff; border-radius: 15px; padding: 40px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 30px rgba(255, 105, 180, 0.2); }
                .header { text-align: center; color: #ff69b4; font-size: 32px; margin-bottom: 25px; font-weight: 700; }
                .content { color: #555; line-height: 1.8; font-size: 16px; }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #ff69b4, #ff1493); 
                    color: white !important; 
                    padding: 16px 40px; 
                    text-decoration: none; 
                    border-radius: 10px; 
                    margin: 25px 0; 
                    font-weight: 700;
                    font-size: 16px;
                    box-shadow: 0 5px 15px rgba(255, 105, 180, 0.3);
                }
                .footer { text-align: center; color: #888; font-size: 13px; margin-top: 35px; padding-top: 25px; border-top: 1px solid #e0e0e0; }
                .link-text { word-break: break-all; color: #ff69b4; background: #ffe6f2; padding: 10px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>✨ Beauty & Blessed ✨</div>
                <div class='content'>
                    <p>Hi <strong>{$firstName}</strong>,</p>
                    <p>Welcome to Beauty & Blessed! We're excited to have you join our community.</p>
                    <p>To complete your registration, please verify your email address by clicking the button below:</p>
                    <p style='text-align: center;'>
                        <a href='{$verificationLink}' class='button'>Verify Email Address</a>
                    </p>
                    <p style='font-size: 14px; color: #666;'>Or copy and paste this link into your browser:</p>
                    <div class='link-text'>{$verificationLink}</div>
                    <p style='margin-top: 25px; font-size: 14px; color: #888;'>⏰ This link will not expire. You can verify anytime.</p>
                </div>
                <div class='footer'>
                    <p>If you didn't create this account, please ignore this email.</p>
                    <p style='margin-top: 10px;'>&copy; 2025 Beauty & Blessed. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Alternative plain text version
        $mail->AltBody = "Hi {$firstName},\n\nWelcome to Beauty & Blessed! Please verify your email by clicking this link: {$verificationLink}";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
function sendPasswordResetEmail($email, $firstName, $resetLink) {
    // ===== DEVELOPMENT MODE - Show link instead of sending email =====
    if (defined('DEV_MODE') && DEV_MODE === true) {
        $_SESSION['reset_link'] = $resetLink;
        $_SESSION['reset_email'] = $email;
        return true;
    }
    
    // ===== PRODUCTION MODE - Use PHPMailer =====
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Beauty & Blessed Password';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Montserrat', Arial, sans-serif; background-color: #ffe6f2; padding: 20px; margin: 0; }
                .container { background-color: #ffffff; border-radius: 15px; padding: 40px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 30px rgba(255, 105, 180, 0.2); }
                .header { text-align: center; color: #ff69b4; font-size: 32px; margin-bottom: 25px; font-weight: 700; }
                .content { color: #555; line-height: 1.8; font-size: 16px; }
                .button { 
                    display: inline-block; 
                    background: linear-gradient(135deg, #ff69b4, #ff1493); 
                    color: white !important; 
                    padding: 16px 40px; 
                    text-decoration: none; 
                    border-radius: 10px; 
                    margin: 25px 0; 
                    font-weight: 700;
                    font-size: 16px;
                    box-shadow: 0 5px 15px rgba(255, 105, 180, 0.3);
                }
                .footer { text-align: center; color: #888; font-size: 13px; margin-top: 35px; padding-top: 25px; border-top: 1px solid #e0e0e0; }
                .link-text { word-break: break-all; color: #ff69b4; background: #ffe6f2; padding: 10px; border-radius: 5px; margin: 15px 0; }
                .warning { color: #e74c3c; background: #ffe6e6; padding: 10px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>✨ Beauty & Blessed ✨</div>
                <div class='content'>
                    <p>Hi <strong>{$firstName}</strong>,</p>
                    <p>We received a request to reset your password for your Beauty & Blessed account.</p>
                    <p>Click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </p>
                    <div class='warning'>
                        <strong>⚠️ Important:</strong> This link will expire in 1 hour for security reasons.
                    </div>
                    <p style='font-size: 14px; color: #666;'>Or copy and paste this link into your browser:</p>
                    <div class='link-text'>{$resetLink}</div>
                    <p>If you didn't request a password reset, please ignore this email. Your account remains secure.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p style='margin-top: 10px;'>&copy; 2025 Beauty & Blessed. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Alternative plain text version
        $mail->AltBody = "Hi {$firstName},\n\nWe received a request to reset your password. Click this link to reset: {$resetLink}\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
function sendSuspiciousLoginAlert($email, $firstName, $details) {
    if (defined('DEV_MODE') && DEV_MODE === true) {
        $_SESSION['suspicious_login_alert'] = true;
        $_SESSION['suspicious_details'] = $details;
        return true;
    }
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName);
        
        $mail->isHTML(true);
        $mail->Subject = 'Suspicious Login Detected - Beauty & Blessed';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Montserrat', Arial, sans-serif; background-color: #ffe6f2; padding: 20px; }
                .container { background-color: #ffffff; border-radius: 15px; padding: 40px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 30px rgba(255, 105, 180, 0.2); }
                .header { text-align: center; color: #ff69b4; font-size: 28px; font-weight: 700; margin-bottom: 20px; }
                .details { background-color: #f9f9f9; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #ff69b4; }
                .footer { text-align: center; color: #888; font-size: 12px; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>Security Alert ⚠️</div>
                <p>Hi <strong>{$firstName}</strong>,</p>
                <p>We detected a login to your account from a new device or location.</p>
                <div class='details'>
                    <strong>Time:</strong> " . date('Y-m-d H:i:s') . "<br>
                    <strong>IP Address:</strong> {$details['ip']}<br>
                    <strong>Browser/Device:</strong> " . parseUserAgent($details['userAgent']) . "
                </div>
                <p>If this was you, you can ignore this message. If you don't recognize this activity, please reset your password immediately.</p>
                <div class='footer'>&copy; " . date('Y') . " Beauty & Blessed. All rights reserved.</div>
            </div>
        </body>
        </html>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
