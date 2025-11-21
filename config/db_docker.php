<?php
// config/db_docker.php

define('DB_HOST', 'db');
define('DB_USER', 'user');
define('DB_PASS', 'userpass');
define('DB_NAME', 'beauty_blessed');
define('DB_PORT', '3306');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$conn->query("SET time_zone = '+08:00'");

if ($conn->connect_error) {
    error_log('Database Connection Failed: ' . $conn->connect_error);
} else {
    $conn->set_charset('utf8mb4');
}
