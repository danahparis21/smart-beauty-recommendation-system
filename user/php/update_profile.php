<?php
// update_profile.php - WITH IMAGE UPLOAD SUPPORT
session_start();

// Disable error display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header FIRST
header('Content-Type: application/json');

function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(false, 'Invalid JSON data.');
}

$user_id = $input['user_id'] ?? null;
$name = $input['name'] ?? null;
$username = $input['username'] ?? null;
$profile_photo = $input['profile_photo'] ?? null;

if (!$user_id) {
    sendResponse(false, 'User ID is required.');
}

try {
    // Include database configuration
    $configPath = __DIR__ . '/../../config/';
    
    if (getenv('DOCKER_ENV') === 'true') {
        if (file_exists($configPath . 'db_docker.php')) {
            require_once $configPath . 'db_docker.php';
        } else {
            require_once $configPath . 'db.php';
        }
    } else {
        require_once $configPath . 'db.php';
    }
    
    // Check connection
    if ($conn->connect_error) {
        sendResponse(false, 'Database connection failed.');
    }
    
    // Split name
    $name_parts = explode(' ', $name, 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
    
    // Check if username already exists (excluding current user)
    $check_stmt = $conn->prepare("SELECT UserID FROM users WHERE username = ? AND UserID != ?");
    $check_stmt->bind_param("si", $username, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        sendResponse(false, 'Username already exists. Please choose a different one.');
    }
    $check_stmt->close();
    
    // Handle profile photo upload
    $photo_filename = null;
    if ($profile_photo && strpos($profile_photo, 'data:image') === 0) {
        $photo_filename = saveBase64Image($profile_photo, $user_id);
    }
    
    // Update user with or without photo
    if ($photo_filename) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, profile_photo = ? WHERE UserID = ?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $username, $photo_filename, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ? WHERE UserID = ?");
        $stmt->bind_param("sssi", $first_name, $last_name, $username, $user_id);
    }
    
    if ($stmt->execute()) {
        // Update session
        $_SESSION['first_name'] = $first_name;
        $_SESSION['username'] = $username;
        
        $stmt->close();
        $conn->close();
        
        sendResponse(true, 'Profile updated successfully!', ['photo_filename' => $photo_filename]);
    } else {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Failed to update profile in database.');
    }
    
} catch (Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}

// Function to save base64 image to file
function saveBase64Image($base64Image, $user_id) {
    // Define upload directory - using your specified path
    $uploadDir = __DIR__ . '/../../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Extract image data from base64 string
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
        $imageType = $matches[1]; // jpg, png, gif, etc.
        $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
        $imageData = base64_decode($imageData);
        
        if ($imageData === false) {
            throw new Exception('Failed to decode base64 image');
        }
        
        // Generate unique filename
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $imageType;
        $filePath = $uploadDir . $filename;
        
        // Save the image file
        if (file_put_contents($filePath, $imageData)) {
            return $filename;
        } else {
            throw new Exception('Failed to save image file');
        }
    } else {
        throw new Exception('Invalid base64 image format');
    }
}
?>