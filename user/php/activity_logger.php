<?php
function logUserActivity($conn, $userId, $action, $details = '') {
    try {
        // Use PHP date with Philippines timezone
        $currentDateTime = date('Y-m-d H:i:s');
        
        // ✅ CORRECT TABLE NAME: activitylog (no underscore)
        $stmt = $conn->prepare("INSERT INTO activitylog (actor_type, actor_id, action, details, timestamp) VALUES ('user', ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("isss", $userId, $action, $details, $currentDateTime);
        if (!$stmt->execute()) {
            error_log("Failed to execute statement: " . $stmt->error);
            return false;
        }
        
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

function logAdminActivity($conn, $adminId, $action, $details = '') {
    try {
        // Use PHP date with Philippines timezone
        $currentDateTime = date('Y-m-d H:i:s');
        
        // ✅ CORRECT TABLE NAME: activitylog (no underscore)
        $stmt = $conn->prepare("INSERT INTO activitylog (actor_type, actor_id, action, details, timestamp) VALUES ('admin', ?, ?, ?, ?)");
        $stmt->bind_param("isss", $adminId, $action, $details, $currentDateTime);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}
?>