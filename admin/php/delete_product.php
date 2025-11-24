<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// Prevent caching for admin pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');


// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Admin privileges required.']));
}

$adminId = $_SESSION['user_id'];

// Your existing database connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Set admin ID for triggers (if doing database operations)
$conn->query("SET @admin_user_id = $adminId");

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
