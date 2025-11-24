<?php
session_start();
header('Content-Type: application/json');

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

function returnJsonResponse($success, $message, $data = [], $conn = null)
{
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }

    if (ob_get_length())
        ob_clean();

    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Check database connection
if ($conn->connect_error) {
    returnJsonResponse(false, 'Database Connection failed: ' . $conn->connect_error);
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnJsonResponse(false, 'Invalid request method.', [], $conn);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? null;

if (!$user_id) {
    returnJsonResponse(false, 'User ID is required.', [], $conn);
}

try {
    // Fetch user info from users table
    $stmt = $conn->prepare('
        SELECT UserID, username, first_name, last_name, email, profile_photo, created_at
        FROM users 
        WHERE UserID = ?
    ');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        returnJsonResponse(false, 'User not found.', [], $conn);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Fetch stats from your existing tables WITH STATUS FILTERS
    $stats = [];

    // ✅ Orders count - ONLY PENDING orders
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM orders WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    $stats['orders'] = $orders_result->fetch_assoc()['count'];
    $stmt->close();

    // ✅ Favorites count (no status needed usually)
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM favorites WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $favorites_result = $stmt->get_result();
    $stats['favorites'] = $favorites_result->fetch_assoc()['count'];
    $stmt->close();

    // ✅ Cart items count - ONLY ACTIVE status
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    $stats['cart_items'] = $cart_result->fetch_assoc()['count'];
    $stmt->close();

    // ✅ Unclaimed orders count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'pending'");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $unclaimed_result = $stmt->get_result();
    $stats['unclaimed_orders'] = $unclaimed_result->fetch_assoc()['count'];
    $stmt->close();

    // Prepare response data using existing user table fields
    $profile_data = [
        'user_id' => $user['UserID'],
        'full_name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'profile_photo' => $user['profile_photo'],
        'member_since' => $user['created_at'],
        'stats' => $stats
    ];

    returnJsonResponse(true, 'Profile data fetched successfully.', [
        'profile' => $profile_data
    ], $conn);
} catch (Exception $e) {
    returnJsonResponse(false, 'Error fetching profile: ' . $e->getMessage(), [], $conn);
}
?>