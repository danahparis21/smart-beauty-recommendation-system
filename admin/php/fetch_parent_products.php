<?php
// Set content type to JSON immediately
header('Content-Type: application/json');

// --- Database Connection Setup ---
// The connection logic based on environment variables is correct, but ensure the files exist and work.
if (getenv('DOCKER_ENV') === 'true') {
    $db_config_path = __DIR__ . '/../../config/db_docker.php';
} else {
    $db_config_path = __DIR__ . '/../../config/db.php';
}

if (!file_exists($db_config_path)) {
    // If the config file isn't found, stop and report an error.
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration file not found.']);
    exit;
}
require_once $db_config_path;

// Check for a valid connection object (assuming your config files create $conn)
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// --- Query Execution ---
// IMPORTANT: Added Category and Description for the client-side autofill logic
$sql = "SELECT ProductID, Name, Category, Description FROM Products WHERE ShadeOrVariant = 'PARENT_GROUP'";

$result = $conn->query($sql);

$parents = [];

// Check if the query itself failed
if ($result === false) {
    // Log the error for debugging and return an empty array
    // error_log("SQL Error: " . $conn->error); 
    // You might also want to set a 500 header here, but returning an empty list is safer for the UI
} else {
    while ($row = $result->fetch_assoc()) {
        $parents[] = $row;
    }
}

// Ensure PHP errors/warnings are not polluting the output before this line
echo json_encode($parents);

$conn->close();
?>