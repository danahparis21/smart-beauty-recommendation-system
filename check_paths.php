<?php
require_once 'config/db.php';
$result = $conn->query("SELECT ImagePath FROM ProductMedia LIMIT 10");
while($row = $result->fetch_assoc()) {
    echo $row['ImagePath'] . "\n";
}
?>
