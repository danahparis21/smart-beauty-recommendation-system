<?php
// get_profile_image.php - Serve profile images
header('Content-Type: image/jpeg');

$filename = $_GET['filename'] ?? '';
$user_id = $_GET['user_id'] ?? '';

if (empty($filename)) {
    // Return a default avatar image
    header('Content-Type: image/svg+xml');
    echo '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="50" fill="#ccc"/></svg>';
    exit();
}

// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

// Define upload directory
$uploadDir = __DIR__ . '/../../uploads/profiles/';
$filePath = $uploadDir . $filename;

// Check if file exists and is readable
if (file_exists($filePath) && is_readable($filePath)) {
    // Get file extension to set correct content type
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'gif':
            header('Content-Type: image/gif');
            break;
        default:
            header('Content-Type: image/jpeg');
    }
    
    readfile($filePath);
} else {
    // File not found, return default avatar
    header('Content-Type: image/svg+xml');
    echo '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="50" fill="#ccc"/><text x="50" y="60" text-anchor="middle" font-family="Arial" font-size="40" fill="white">?</text></svg>';
}
exit();
?>