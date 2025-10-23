<?php
session_start();
// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}


function returnJsonResponse($success, $message, $data = [], $conn = null) {
    if ($conn && $conn->connect_error === null) {
        $conn->close();
    }
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if ($conn->connect_error) {
    returnJsonResponse(false, "Database Connection failed: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonResponse(false, 'Invalid request method.', [], $conn);
}

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    returnJsonResponse(false, 'Please provide both username and password.', [], $conn);
}

$username = trim($_POST['username'] ?? ''); 
$password = $_POST['password'] ?? '';

// Basic input validation
if (empty($username) || empty($password)) {
    returnJsonResponse(false, 'Username and password cannot be empty.', [], $conn);
}

$stmt = $conn->prepare("SELECT UserID, Password, first_name, Role, Email FROM users WHERE username = ?");
if (!$stmt) {
    returnJsonResponse(false, 'Database error (prepare statement).', [], $conn);
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
   
    $user = $result->fetch_assoc();
    $password_hash = $user['Password']; 

    
    if (password_verify($password, $password_hash)) {
        
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['username'] = $username;
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role'] = $user['Role'];
        $_SESSION['email'] = $user['Email']; 
         
        $stmt->close();
        
        // Success response
        returnJsonResponse(true, 'Login successful!', [
            'firstName' => $user['first_name'],
            'role' => $user['Role'],
            'email' => $user['Email'] // Optional: Send email back in the response
        ], $conn);

    } else {
        // Incorrect password
        $stmt->close();
        returnJsonResponse(false, 'Invalid username or password.', [], $conn);
    }
} else {
    // Username not found
    $stmt->close();
    returnJsonResponse(false, 'Invalid username or password.', [], $conn);
}

// Fallback error
returnJsonResponse(false, 'An unexpected error occurred.', [], $conn);
?>