<?php
// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', '561361392985-e94cdftuvt3efrr8rkt8ikh7q8er2alv.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost:8000/user/php/google-callback.php');
define('GOOGLE_LOGIN_REDIRECT_URI', 'http://localhost:8000/user/php/google-login-callback.php');

// Email Configuration (for sending verification emails)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'beautyandblessed.business@gmail.com');
define('SMTP_PASSWORD', 'bhsx riiz eceg ryvw');
define('SMTP_FROM_EMAIL', 'beautyandblessed.business@gmail.com');
define('SMTP_FROM_NAME', 'Beauty & Blessed');

// Base URL
define('BASE_URL', 'http://localhost:8000');

// ===== DEVELOPMENT MODE =====
define('DEV_MODE', false); // Set to false in production

// Password Reset Token Expiry (1 hour)
define('PASSWORD_RESET_EXPIRY', 3600);

// Google OAuth URLs
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');
?>