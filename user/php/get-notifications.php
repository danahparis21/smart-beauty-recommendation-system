<?php
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

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$userId = $_SESSION['user_id'];

try {
    // Get notifications: 
    // 1. Belonging to User
    // 2. Not Expired
    // 3. Ordered by: Unread first, then Newest date
    $stmt = $conn->prepare("
        SELECT NotificationID, Title, Message, IsRead, CreatedAt, ExpirationDate 
        FROM notifications
        WHERE UserID = ? 
        AND (ExpirationDate IS NULL OR ExpirationDate > NOW())
        ORDER BY IsRead ASC, CreatedAt DESC
    ");
    
    $stmt->bind_param("i", $userId); 
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    $unreadCount = 0;

    while ($row = $result->fetch_assoc()) {
        // Format date nicely (e.g., "Nov 22, 2025 2:30 PM")
        $row['FormattedDate'] = date('M j, g:i a', strtotime($row['CreatedAt']));
        
        if ($row['IsRead'] == 0) {
            $unreadCount++;
        }
        
        $notifications[] = $row;
    }

    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'unreadCount' => $unreadCount
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>