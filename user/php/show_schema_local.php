<?php
$conn = new mysqli('localhost', 'root', '', 'u213175529_beauty_blessed');
if ($conn->connect_error) {
    $conn = new mysqli('localhost', 'root', '', 'beauty_blessed');
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function showTable($conn, $tableName) {
    echo "========================================\n";
    echo "TABLE: $tableName\n";
    $result = $conn->query("SHOW CREATE TABLE $tableName");
    if ($result) {
        $row = $result->fetch_assoc();
        echo $row['Create Table'] . "\n";
    } else {
        echo "Error showing table $tableName: " . $conn->error . "\n";
    }
}

showTable($conn, 'products');
showTable($conn, 'cart');
showTable($conn, 'productmedia');
showTable($conn, 'orders');
showTable($conn, 'orderitems');
showTable($conn, 'activitylog');

$conn->close();
?>
