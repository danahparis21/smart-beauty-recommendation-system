
<?php
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

function returnJsonError($message, $conn = null)
{
    if ($conn && $conn->connect_error === null) {
        $conn->close();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit();
}

if ($conn->connect_error) {
    returnJsonError('Database Connection failed: ' . $conn->connect_error);
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the POST array keys exist, otherwise set to an empty string.
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    // Basic validations
    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        returnJsonError('Please fill in all required fields.', $conn);
    }

    // Check if username meets any additional minimum length requirements (optional)
    if (strlen($username) < 4) {
        returnJsonError('Username must be at least 4 characters long.', $conn);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        returnJsonError('Invalid email format.', $conn);
    }

    if ($password !== $confirmPassword) {
        returnJsonError('Passwords do not match.', $conn);
    }

    if (strlen($password) < 8) {
        returnJsonError('Password must be at least 8 characters.', $conn);
    }

    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('SELECT UserID FROM users WHERE Email = ? OR username = ?');
    $stmt->bind_param('ss', $email, $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Since we checked both, we tell the user which one is taken.
        $check_email_stmt = $conn->prepare('SELECT UserID FROM users WHERE Email = ?');
        $check_email_stmt->bind_param('s', $email);
        $check_email_stmt->execute();
        $check_email_stmt->store_result();

        if ($check_email_stmt->num_rows > 0) {
            returnJsonError('Email already registered.', $conn);
        } else {
            returnJsonError('Username already taken.', $conn);
        }
        $check_email_stmt->close();
    }
    $stmt->close();

    // --- INSERT USER INTO DATABASE ---
    $role = 'customer';

    $stmt = $conn->prepare('INSERT INTO users (username, first_name, last_name, Email, Password, Role) VALUES (?, ?, ?, ?, ?, ?)');

    $stmt->bind_param('ssssss', $username, $firstName, $lastName, $email, $passwordHash, $role);

    if ($stmt->execute()) {
        // Signup successful
        $_SESSION['user_id'] = $conn->insert_id;  // Store the new UserID
        $_SESSION['username'] = $username;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['role'] = $role;

        // Return JSON instead of redirect
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'firstName' => $firstName,
            'role' => $role,
            'message' => 'Signup successful! Redirecting to login.'
        ]);
        $stmt->close();
        $conn->close();
    } else {
        returnJsonError('Database Error: Could not insert user. ' . $stmt->error, $conn);
    }
} else {
    returnJsonError('Invalid request method.', $conn);
}
?>