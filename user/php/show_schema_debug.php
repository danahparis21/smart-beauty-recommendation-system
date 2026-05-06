<?php
require_once __DIR__ . '/../../config/db.php';

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

$conn->close();
?>
