<?php

header('Content-Type: application/json');

if (getenv('DOCKER_ENV') === 'true') {
    $db_config_path = __DIR__ . '/../../config/db_docker.php';
} else {
    $db_config_path = __DIR__ . '/../../config/db.php';
}

if (!file_exists($db_config_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration file not found.']);
    exit;
}
require_once $db_config_path;

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$sql = "SELECT ProductID, Name, Category, Description FROM Products WHERE ShadeOrVariant = 'PARENT_GROUP'";

$result = $conn->query($sql);

$parents = [];

if ($result === false) {
} else {
    while ($row = $result->fetch_assoc()) {
        $parents[] = $row;
    }
}

echo json_encode($parents);

$conn->close();
?>