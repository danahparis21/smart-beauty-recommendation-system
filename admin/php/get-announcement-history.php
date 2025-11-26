<?php
header('Content-Type: application/json');
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Group by CreatedAt and Title so we see one entry per "Blast" sent
    $sql = "SELECT 
                n.Title, 
                n.Message, 
                n.CreatedAt, 
                MAX(n.ExpirationDate) as ExpirationDate,
                COUNT(n.UserID) as RecipientCount 
            FROM notifications n
            INNER JOIN users u ON n.UserID = u.id
            WHERE u.role = 'admin'
            GROUP BY n.CreatedAt, n.Title, n.Message 
            ORDER BY n.CreatedAt DESC";
            
    $result = $conn->query($sql);
    $history = [];

    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'title' => $row['Title'],
            'message' => $row['Message'],
            'date' => date('M d, Y h:i A', strtotime($row['CreatedAt'])),
            'raw_date' => $row['CreatedAt'], // Used for deletion identification
            'recipients' => $row['RecipientCount'],
            'status' => (empty($row['ExpirationDate']) || new DateTime($row['ExpirationDate']) > new DateTime()) ? 'Active' : 'Expired'
        ];
    }

    echo json_encode(['success' => true, 'data' => $history]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();
?>