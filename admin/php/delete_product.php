<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $isParent = isset($_POST['isParent']) ? (int)$_POST['isParent'] : 0;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'No product ID provided.']);
        exit;
    }

    if ($isParent) {
        // 🔴 Mark parent and all its variants as Deleted
        $sql = "UPDATE Products SET Status = 'Deleted' 
                WHERE ProductID = ? OR ParentProductID = ?";
    } else {
        // 🔴 Mark only the variant as Deleted
        $sql = "UPDATE Products SET Status = 'Deleted' WHERE ProductID = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($isParent) {
        $stmt->bind_param("ss", $id, $id);
    } else {
        $stmt->bind_param("s", $id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $isParent 
                ? 'Product and all its variants marked as deleted.' 
                : 'Variant marked as deleted.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?>
