<?php
require_once __DIR__ . '/../../config/google-config.php';

/**
 * Validates reCAPTCHA token
 */
function validateRecaptcha($token) {
    // Skip reCAPTCHA validation in development mode
    if (defined('DEV_MODE') && DEV_MODE === true) {
        return true;
    }

    if (empty($token)) return false;
    
    $secret = RECAPTCHA_SECRET_KEY;
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = [
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        // Fallback to CURL if file_get_contents fails
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
    }

    $responseKeys = json_decode($response, true);
    return ($responseKeys["success"] ?? false);
}

/**
 * Checks if account is locked
 * Returns remaining lock time in seconds, or 0 if not locked
 */
function getAccountLockRemainingTime($conn, $username) {
    $stmt = $conn->prepare("SELECT lock_until FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if ($user['lock_until']) {
            $lockUntil = strtotime($user['lock_until']);
            $now = time();
            if ($lockUntil > $now) {
                return $lockUntil - $now;
            }
        }
    }
    return 0;
}

/**
 * Records a failed login attempt
 */
function recordFailedAttempt($conn, $username) {
    $stmt = $conn->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    // Check if limit reached
    $stmt = $conn->prepare("SELECT login_attempts FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && $user['login_attempts'] >= 5) {
        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $stmt = $conn->prepare("UPDATE users SET lock_until = ? WHERE username = ?");
        $stmt->bind_param("ss", $lockUntil, $username);
        $stmt->execute();
        return true; // Account locked
    }
    return false;
}

/**
 * Resets login attempts on success
 */
function resetLoginAttempts($conn, $username) {
    $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lock_until = NULL WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
}

/**
 * Detects suspicious login
 */
function isSuspiciousLogin($conn, $userId, $currentIp, $currentUserAgent) {
    $stmt = $conn->prepare("SELECT last_login_ip, last_login_device FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) return false;
    
    // If it's the first login ever, it's not "suspicious" in the sense of comparing, but we might want to record it
    if (empty($user['last_login_ip'])) return false;
    
    if ($user['last_login_ip'] !== $currentIp || $user['last_login_device'] !== $currentUserAgent) {
        return true;
    }
    
    return false;
}

/**
 * Updates last login details
 */
function updateLastLoginDetails($conn, $userId, $ip, $userAgent) {
    $stmt = $conn->prepare("UPDATE users SET last_login_ip = ?, last_login_device = ? WHERE UserID = ?");
    $stmt->bind_param("ssi", $ip, $userAgent, $userId);
    $stmt->execute();
}

/**
 * Formats time remaining into a readable string
 */
function formatRemainingTime($seconds) {
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $secs);
}

/**
 * Parses raw User Agent into a readable string
 */
function parseUserAgent($userAgent) {
    $browser = "Unknown Browser";
    $platform = "Unknown OS";

    // Detect Platform
    if (preg_match('/windows|win32/i', $userAgent)) $platform = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $platform = 'macOS';
    elseif (preg_match('/linux/i', $userAgent)) $platform = 'Linux';
    elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) $platform = 'iOS';
    elseif (preg_match('/android/i', $userAgent)) $platform = 'Android';

    // Detect Browser
    if (preg_match('/opera|opr/i', $userAgent)) $browser = 'Opera';
    elseif (preg_match('/edge/i', $userAgent)) $browser = 'Microsoft Edge';
    elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Google Chrome';
    elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/msie|trident/i', $userAgent)) $browser = 'Internet Explorer';

    return "$browser on $platform";
}
?>
