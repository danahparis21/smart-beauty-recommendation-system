<?php
session_start();

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

function sendVerificationEmail($email, $token, $firstName) {
    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/user/php/verify-email.php?token=" . $token;
    
    $subject = "Verify Your Email - Beauty & Blessed";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: 'Montserrat', sans-serif; color: #555; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 30px; text-align: center; border-radius: 15px 15px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 15px 15px; }
            .button { background: linear-gradient(135deg, #ff69b4, #ff1493); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Beauty & Blessed</h1>
                <p>Elevate Your Everyday Glam</p>
            </div>
            <div class='content'>
                <h2>Hello $firstName!</h2>
                <p>Thank you for signing up with Beauty & Blessed. Please verify your email address to complete your account creation.</p>
                <p>Click the button below to verify your email:</p>
                <a href='$verification_link' class='button'>Verify Email Address</a>
                <p>Or copy and paste this link in your browser:<br><code>$verification_link</code></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't create an account with Beauty & Blessed, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 Beauty & Blessed. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Beauty & Blessed <noreply@beautyandblessed.com>" . "\r\n";
    
    // In production, use a proper email service like PHPMailer
    // For development, we'll log the verification link
    error_log("Verification link for $email: $verification_link");
    
    return mail($email, $subject, $message, $headers);
}
?>