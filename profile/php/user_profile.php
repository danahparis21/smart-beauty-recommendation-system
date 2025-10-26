<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Database connection
$servername = "localhost";
$dbUsername = "root"; 
$dbPassword = "";     
$dbName = "your_database_name"; // Replace with your DB name

$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    http_response_code(500); // Server error
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get logged-in username from session
$username = $_SESSION['username'];

// Fetch user data
$stmt = $conn->prepare("SELECT full_name, profile_pic FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Close connection
$stmt->close();
$conn->close();

// Return JSON
if ($user) {
    echo json_encode($user);
} else {
    echo json_encode([
        'full_name' => 'Guest',
        'profile_pic' => 'https://via.placeholder.com/150'
    ]);
}
?>
