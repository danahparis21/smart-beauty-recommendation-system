<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// ✅ ADDED: Set timezone at the top
date_default_timezone_set('Asia/Manila');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// ✅ ADDED: Set database timezone
$conn->query("SET time_zone = '+08:00'");

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

// Log the request for debugging
error_log('Save preferences request received');

if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the input data
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];

error_log('User ID: ' . $user_id);
error_log('Input data: ' . print_r($input, true));

try {
    // ✅ ADDED: Get current datetime for consistent timing
    $currentDateTime = date('Y-m-d H:i:s');

    // Check if user already has preferences
    $checkStmt = $conn->prepare('SELECT user_pref_id FROM user_preferences WHERE user_id = ?');
    $checkStmt->bind_param('i', $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    $concernsJson = json_encode($input['concerns'] ?? []);

    error_log('Concerns JSON: ' . $concernsJson);

    if ($checkResult->num_rows > 0) {
        // Update existing preferences
        error_log('Updating existing preferences');
        
        // ✅ FIXED: Use PHP date instead of NOW()
        $updateStmt = $conn->prepare('
            UPDATE user_preferences 
            SET skin_type = ?, skin_tone = ?, undertone = ?, skin_concerns = ?, preferred_finish = ?, updated_at = ? 
            WHERE user_id = ?
        ');
        $updateStmt->bind_param('ssssssi',
            $input['skinType'],
            $input['skinTone'],
            $input['undertone'],
            $concernsJson,
            $input['finish'],
            $currentDateTime, // ✅ Use PHP datetime
            $user_id);
        $updateStmt->execute();
        error_log('Update affected rows: ' . $updateStmt->affected_rows);
        
        // ✅ LOG PREFERENCES UPDATE
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $preferencesDetails = "Updated beauty preferences - Skin Type: {$input['skinType']}, Skin Tone: {$input['skinTone']}, Undertone: {$input['undertone']}, Finish: {$input['finish']}, Concerns: " . implode(', ', $input['concerns'] ?? []) . ", IP: {$userIP}";
        logUserActivity($conn, $user_id, 'Preferences updated', $preferencesDetails);
        
    } else {
        // Insert new preferences
        error_log('Inserting new preferences');
        
        // ✅ FIXED: Use PHP date instead of NOW()
        $insertStmt = $conn->prepare('
            INSERT INTO user_preferences (user_id, skin_type, skin_tone, undertone, skin_concerns, preferred_finish, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insertStmt->bind_param('isssssss',
            $user_id,
            $input['skinType'],
            $input['skinTone'],
            $input['undertone'],
            $concernsJson,
            $input['finish'],
            $currentDateTime, // ✅ created_at
            $currentDateTime  // ✅ updated_at
        );
        $insertStmt->execute();
        error_log('Insert ID: ' . $insertStmt->insert_id);
        
        // ✅ LOG NEW PREFERENCES CREATION
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $preferencesDetails = "Created beauty preferences - Skin Type: {$input['skinType']}, Skin Tone: {$input['skinTone']}, Undertone: {$input['undertone']}, Finish: {$input['finish']}, Concerns: " . implode(', ', $input['concerns'] ?? []) . ", IP: {$userIP}";
        logUserActivity($conn, $user_id, 'Preferences created', $preferencesDetails);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Preferences saved successfully',
        'timestamp' => $currentDateTime // ✅ Return the actual timestamp used
    ]);
    error_log('Preferences saved successfully');
    
} catch (Exception $e) {
    // ✅ LOG PREFERENCES SAVE ERROR
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $errorDetails = "Error: " . $e->getMessage() . ", IP: {$userIP}";
    logUserActivity($conn, $user_id, 'Preferences save failed', $errorDetails);
    
    error_log('Error saving preferences: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving preferences: ' . $e->getMessage()]);
}
?>