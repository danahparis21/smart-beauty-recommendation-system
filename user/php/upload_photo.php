<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

function returnJsonResponse($success, $message, $data = [], $conn = null) {
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
    
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Check database connection
if ($conn->connect_error) {
    returnJsonResponse(false, "Database Connection failed: " . $conn->connect_error);
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonResponse(false, 'Invalid request method.', [], $conn);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;
$image_data = $input['image'] ?? null;

if (!$user_id || !$image_data) {
    returnJsonResponse(false, 'User ID and image data are required.', [], $conn);
}

try {
    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $filename = 'profile_' . $user_id . '_' . time() . '.jpg';
    $file_path = $upload_dir . $filename;
    
    // Convert base64 to image file
    if (preg_match('/^data:image\/(\w+);base64,/', $image_data, $type)) {
        $image_data = substr($image_data, strpos($image_data, ',') + 1);
        $image_data = base64_decode($image_data);
        
        if ($image_data === false) {
            throw new Exception('Base64 decode failed');
        }
        
        // Save the image file
        if (file_put_contents($file_path, $image_data)) {
            // Update user record with filename
            $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE UserID = ?");
            $stmt->bind_param("si", $filename, $user_id);
            
            if ($stmt->execute()) {
                // ✅ LOG PROFILE PHOTO UPLOAD
                $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $uploadDetails = "Uploaded profile photo: {$filename}, IP: {$userIP}";
                
                logUserActivity($conn, $user_id, 'Profile photo updated', $uploadDetails);
                
                $stmt->close();
                returnJsonResponse(true, 'Photo uploaded successfully.', ['filename' => $filename], $conn);
            } else {
                $stmt->close();
                // Delete the uploaded file if DB update fails
                unlink($file_path);
                
                // ✅ LOG UPLOAD FAILURE
                logUserActivity($conn, $user_id, 'Profile photo upload failed', 'Database update failed');
                
                returnJsonResponse(false, 'Failed to update profile photo in database.', [], $conn);
            }
        } else {
            throw new Exception('Failed to save image file');
        }
    } else {
        throw new Exception('Invalid image data format');
    }
    
} catch (Exception $e) {
    // ✅ LOG UPLOAD ERROR
    logUserActivity($conn, $user_id, 'Profile photo upload error', 'Error: ' . $e->getMessage());
    
    returnJsonResponse(false, 'Error uploading photo: ' . $e->getMessage(), [], $conn);
}
?>