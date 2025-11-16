<?php
session_start();

// Force Docker environment
putenv('DOCKER_ENV=true');

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

try {
    // Method 1: Get create table and save to file
    $result = $conn->query("SHOW CREATE TABLE productfeedback");
    $row = $result->fetch_assoc();
    $createTable = $row['Create Table'];
    
    echo "<pre>"; // Use <pre> tags for better formatting in browser
    echo "FULL TABLE DEFINITION:\n";
    echo "========================================\n";
    echo htmlspecialchars($createTable) . "\n";
    echo "========================================\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

$conn->close();
?>