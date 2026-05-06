<?php
// Load environment variables if not already loaded (for standalone scripts)
if (!defined('BASE_URL')) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
$pass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');
$db_name = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'beauty_blessed');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306);

$conn = new mysqli($host, $user, $pass, $db_name, $port);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die('❌ Database connection failed. Please try again later.');
}

$conn->set_charset('utf8mb4');
?>

