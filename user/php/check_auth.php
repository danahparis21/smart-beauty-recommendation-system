<?php
// check-auth.php - Check if user is authenticated
session_start();
header('Content-Type: application/json');

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$response = [
    'isLoggedIn' => false,
    'user' => null
];

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $response['isLoggedIn'] = true;
    $response['user'] = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

echo json_encode($response);
?>