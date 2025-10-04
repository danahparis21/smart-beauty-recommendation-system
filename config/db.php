<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'beauty_blessed';
$port = 3307;  

$conn = new mysqli($host, $user, $pass, $db_name, $port);

if ($conn->connect_error) {
    die('❌ Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
