<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/google-config.php';

$config = [
    'recaptchaSiteKey' => RECAPTCHA_SITE_KEY,
    'devMode' => defined('DEV_MODE') ? DEV_MODE : false
];

echo json_encode($config);
?>
