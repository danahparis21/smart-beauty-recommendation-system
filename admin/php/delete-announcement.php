<?php
header('Content-Type: application/json');
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

$input = json_decode(file_get_contents('php://input'), true);
$createdAt = $input['date'] ?? '';
$title = $input['title'] ?? '';

if (empty($createdAt) || empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Delete all notifications that match this specific batch (Same Title AND Same Creation Time)
    $stmt = $conn->prepare("DELETE FROM notifications WHERE Title = ? AND CreatedAt = ?");
    $stmt->bind_param("ss", $title, $createdAt);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No records found to delete.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();
?>