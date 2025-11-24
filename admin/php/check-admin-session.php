<?php
session_start();

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'isLoggedIn' => false,
        'isAdmin' => false,
        'message' => 'Admin authentication required'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'isLoggedIn' => true,
    'isAdmin' => true,
    'user' => [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'firstName' => $_SESSION['first_name'],
        'role' => $_SESSION['role'],
        'email' => $_SESSION['email']
    ]
]);
?>