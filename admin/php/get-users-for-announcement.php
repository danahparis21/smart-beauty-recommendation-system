<?php
header('Content-Type: application/json');
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Select ID, Name, Role, Email for the selection list
    $sql = "SELECT UserID, username, first_name, last_name, Role, Email FROM users ORDER BY Role, first_name";
    $result = $conn->query($sql);

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
        $users[] = [
            'id' => $row['UserID'],
            'name' => $fullName ?: $row['username'],
            'role' => $row['Role'], // e.g., 'customer', 'admin'
            'email' => $row['Email']
        ];
    }

    echo json_encode(['success' => true, 'users' => $users]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();
?>