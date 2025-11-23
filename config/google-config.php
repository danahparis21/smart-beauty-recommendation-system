<?php
// Manual .env loader
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
} else {
    die('Error: .env file not found at ' . $envFile);
}

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI'));
define('GOOGLE_LOGIN_REDIRECT_URI', getenv('GOOGLE_LOGIN_REDIRECT_URI'));

// Email Configuration
define('SMTP_HOST', getenv('SMTP_HOST'));
define('SMTP_PORT', getenv('SMTP_PORT'));
define('SMTP_USERNAME', getenv('SMTP_USERNAME'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL'));
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME'));

// Base URL
define('BASE_URL', getenv('BASE_URL'));

// Development Mode
define('DEV_MODE', filter_var(getenv('DEV_MODE'), FILTER_VALIDATE_BOOLEAN));

// Password Reset Token Expiry
define('PASSWORD_RESET_EXPIRY', getenv('PASSWORD_RESET_EXPIRY'));

// Google OAuth URLs
define('GOOGLE_AUTH_URL', getenv('GOOGLE_AUTH_URL'));
define('GOOGLE_TOKEN_URL', getenv('GOOGLE_TOKEN_URL'));
define('GOOGLE_USERINFO_URL', getenv('GOOGLE_USERINFO_URL'));
?>