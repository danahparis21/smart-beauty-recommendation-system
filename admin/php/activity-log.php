<?php
header('Content-Type: application/json');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Simple query to get all activity logs with user information
    $query = "
    SELECT 
        a.log_id,
        a.actor_type,
        a.actor_id,
        a.action,
        a.timestamp,
        a.details,
        u.username,
        u.first_name,
        u.last_name,
        u.Email,
        u.Role
    FROM activitylog a
    LEFT JOIN users u ON a.actor_id = u.UserID
    ORDER BY a.timestamp DESC
    LIMIT 100
";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'log_id' => $row['log_id'],
            'actor_type' => $row['actor_type'],
            'actor_id' => $row['actor_id'],
            'action' => $row['action'],
            'timestamp' => $row['timestamp'],
            'details' => $row['details'],
            'username' => $row['username'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['Email'],
            'role' => $row['Role']
        ];
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'total' => count($activities)
    ]);
} catch (Exception $e) {
    error_log('Error in activity-log.php: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => 'Failed to load activity log: ' . $e->getMessage(),
        'activities' => []
    ]);
}

if ($conn) {
    $conn->close();
}
?>