<?php
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load database config
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Check DB connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Fetch user profile - using your actual database structure
    $stmtUser = $conn->prepare("SELECT UserID, username, first_name, last_name, profile_photo FROM users WHERE UserID = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($user = $resultUser->fetch_assoc()) {
        // Format the response to match what your JavaScript expects
        $response = [
            'success' => true,
            'user' => [
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'profile_photo' => $user['profile_photo'] // This is what contains the image path
            ]
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>