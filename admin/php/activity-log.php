<?php
header('Content-Type: application/json');

// Database connection
require_once __DIR__ . '/../../config/db.php';

// Get filter parameters
$dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
$dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
$actionType = isset($_GET['actionType']) ? $_GET['actionType'] : '';

try {
    // Build WHERE conditions
    $whereConditions = ["1=1"];
    
    // Date range filter
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(a.timestamp) >= '" . $conn->real_escape_string($dateFrom) . "'";
    }
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(a.timestamp) <= '" . $conn->real_escape_string($dateTo) . "'";
    }
    
    // Action type filter
    if (!empty($actionType)) {
        switch ($actionType) {
            case 'create':
                $whereConditions[] = "a.action LIKE '%added%' OR a.action LIKE '%created%' OR a.action LIKE '%placed%'";
                break;
            case 'update':
                $whereConditions[] = "a.action LIKE '%updated%' OR a.action LIKE '%modified%' OR a.action LIKE '%changed%'";
                break;
            case 'delete':
                $whereConditions[] = "a.action LIKE '%deleted%' OR a.action LIKE '%removed%' OR a.action LIKE '%cancelled%'";
                break;
            case 'system':
                $whereConditions[] = "a.actor_type = 'system' OR a.action LIKE '%system%'";
                break;
        }
    }

    $whereClause = "WHERE " . implode(" AND ", $whereConditions);

    // Main query to fetch activity logs
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
        LEFT JOIN users u ON a.actor_id = u.UserID AND a.actor_type IN ('admin', 'customer')
        $whereClause
        ORDER BY a.timestamp DESC
        LIMIT 100
    ";

    error_log("Activity Log Query: " . $query); // Debug logging

    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
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
        'filters' => [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'actionType' => $actionType
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in activity-log.php: " . $e->getMessage());
    
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