<?php
header('Content-Type: application/json');
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Receive JSON input
$input = json_decode(file_get_contents('php://input'), true);

$title = $input['title'] ?? '';
$message = $input['message'] ?? '';
$scheduledDate = $input['scheduledDate'] ?? ''; // If empty, publish now
$expirationDate = $input['expirationDate'] ?? null;
$targetUserIds = $input['recipients'] ?? [];

if (empty($title) || empty($message) || empty($targetUserIds)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

try {
    $conn->begin_transaction();

    // Determine Publish Date (CreatedAt)
    // If scheduledDate is provided, use it. Otherwise, use NOW().
    $publishTime = !empty($scheduledDate) ? $scheduledDate : date('Y-m-d H:i:s');

    // Prepare Statement
    // Note: Ensure your table has ExpirationDate column, or remove that part of the query
    $stmt = $conn->prepare("INSERT INTO notifications (UserID, Title, Message, CreatedAt, ExpirationDate) VALUES (?, ?, ?, ?, ?)");

    foreach ($targetUserIds as $userId) {
        // ExpirationDate can be null
        $exp = empty($expirationDate) ? null : $expirationDate;
        $stmt->bind_param("issss", $userId, $title, $message, $publishTime, $exp);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'count' => count($targetUserIds)]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>