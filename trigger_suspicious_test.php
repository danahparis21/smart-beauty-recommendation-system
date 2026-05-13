<?php
// TEST TRIGGER - Sets a fake IP/device so next login fires the suspicious alert
// Visit: https://blessenceph.shop/trigger_suspicious_test.php
// DELETE AFTER USE

session_start();
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/config/db_docker.php';
} else {
    require_once __DIR__ . '/config/db.php';
}

echo "<pre style='font-family:monospace;font-size:14px;padding:20px;background:#0d0d1a;color:#e0e0e0;'>";
echo "<h2 style='color:#ff69b4;'>🧪 Suspicious Login Trigger Test</h2>\n";

// Set fake IP/device for ALL customer users so next login will be "suspicious"
$fakeIp     = '1.2.3.4';
$fakeDevice = 'Firefox on Linux';

$result = $conn->query("UPDATE users SET last_login_ip = '$fakeIp', last_login_device = '$fakeDevice' WHERE email_verified = 1");

if ($result) {
    $affected = $conn->affected_rows;
    echo "✅ Set fake IP/device for $affected user(s).\n\n";
    echo "Stored IP:     $fakeIp\n";
    echo "Stored Device: $fakeDevice\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Now go log in normally at:\n";
    echo "  https://blessenceph.shop/user/html/login.html\n\n";
    echo "Your real browser is Chrome on Windows from a\n";
    echo "different IP — so it WILL be flagged suspicious.\n";
    echo "You should get the alert email + verification email.\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
} else {
    echo "❌ Update failed: " . $conn->error . "\n";
}

$conn->close();
echo "</pre>";
echo "<p style='font-family:monospace;color:red;padding:20px;'>⚠️ DELETE trigger_suspicious_test.php after testing!</p>";
?>
