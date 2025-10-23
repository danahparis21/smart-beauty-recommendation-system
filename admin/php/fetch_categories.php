<?php
session_start();
// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}
header('Content-Type: application/json');

// Fetch unique categories that have at least one product
$sql = "SELECT DISTINCT Category FROM products WHERE Category IS NOT NULL AND Category != '' ORDER BY Category ASC";
$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}


while ($row = $result->fetch_assoc()) {
    $categories[] = $row['Category'];
}

echo json_encode(['success' => true, 'categories' => $categories]);
$conn->close();
?>
