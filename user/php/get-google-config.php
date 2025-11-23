<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load configuration
require_once __DIR__ . '/../../config/google-config.php';

// Only send non-sensitive configuration to frontend
$config = [
    'clientId' => GOOGLE_CLIENT_ID,
    'redirectUri' => GOOGLE_REDIRECT_URI,
    'loginRedirectUri' => GOOGLE_LOGIN_REDIRECT_URI,
    'authUrl' => GOOGLE_AUTH_URL
];

echo json_encode($config);
?>