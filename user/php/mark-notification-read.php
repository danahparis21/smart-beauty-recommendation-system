<?php
date_default_timezone_set('Asia/Manila');
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

if (!isset($_SESSION['user_id']) || !isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userId = $_SESSION['user_id'];
$notifId = $_POST['notification_id'];

try {
    // First, get the notification details for logging
    $getNotifStmt = $conn->prepare("SELECT Title, Message FROM notifications WHERE NotificationID = ? AND UserID = ?");
    $getNotifStmt->bind_param("ii", $notifId, $userId);
    $getNotifStmt->execute();
    $notifResult = $getNotifStmt->get_result();
    
    if ($notifResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        exit;
    }
    
    $notification = $notifResult->fetch_assoc();
    $getNotifStmt->close();
    
    // Update IsRead to 1 for the specific notification and user
    $stmt = $conn->prepare("UPDATE notifications SET IsRead = 1 WHERE NotificationID = ? AND UserID = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    
    if ($stmt->execute()) {
        $userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $notificationDetails = "Notification #{$notifId} - '{$notification['Title']}' - IP: {$userIP}";
        logUserActivity($conn, $userId, 'Notification read', $notificationDetails);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>