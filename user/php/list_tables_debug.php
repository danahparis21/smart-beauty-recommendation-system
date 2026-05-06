<?php
require_once __DIR__ . '/../../config/db.php';

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "Tables in database:\n";
    while ($row = $result->fetch_array()) {
        echo $row[0] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
$conn->close();
?>
