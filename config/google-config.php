<?php
// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', '1002579907182-03h0l3s7dend47reghc4j805herork3l.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost:8080/user/php/google-callback.php');
define('GOOGLE_LOGIN_REDIRECT_URI', 'http://localhost:8080/user/php/google-callback.php');

// Email Configuration (for sending verification emails)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'christianpaulmendoza10@gmail.com');
define('SMTP_PASSWORD', 'ryhi tfdi ndwf asfv');
define('SMTP_FROM_EMAIL', 'christianpaulmendoza10@gmail.com');
define('SMTP_FROM_NAME', 'Beauty & Blessed');

// Base URL
define('BASE_URL', 'http://localhost:8080');

// ===== DEVELOPMENT MODE =====
define('DEV_MODE', false);  // Set to false in production

// Password Reset Token Expiry (1 hour)
define('PASSWORD_RESET_EXPIRY', 3600);

// Google OAuth URLs
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');
?>