<?php
function logUserActivity($conn, $userId, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activitylog (actor_type, actor_id, action, details) VALUES ('user', ?, ?, ?)");
    $stmt->bind_param("iss", $userId, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function logAdminActivity($conn, $adminId, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activitylog (actor_type, actor_id, action, details) VALUES ('admin', ?, ?, ?)");
    $stmt->bind_param("iss", $adminId, $action, $details);
    $stmt->execute();
    $stmt->close();
}
?>