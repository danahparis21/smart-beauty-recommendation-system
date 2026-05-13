<?php
// LIVE DEBUG v2 - DELETE AFTER USE
// Visit: https://blessenceph.shop/debug_login_test.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/config/db_docker.php';
} else {
    require_once __DIR__ . '/config/db.php';
}
require_once __DIR__ . '/user/php/security_utils.php';

echo "<pre style='font-family:monospace;font-size:13px;padding:20px;background:#0d0d1a;color:#e0e0e0;'>";
echo "<h2 style='color:#ff69b4;'>🔍 Login Debug v2</h2>\n";

// ── 1. What code is ACTUALLY on the server right now? ──
echo "━━━ 1. ACTUAL server file content (security_utils.php lines 129-155) ━━━\n";
$lines = file(__DIR__ . '/user/php/security_utils.php');
for ($i = 128; $i <= min(154, count($lines)-1); $i++) {
    echo sprintf("%3d: %s", $i+1, $lines[$i]);
}
echo "\n";

// ── 2. OPcache check ──
echo "━━━ 2. OPcache ━━━\n";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    echo "OPcache enabled: " . ($status['opcache_enabled'] ? '⚠️ YES (may cache old files)' : '✅ NO') . "\n";
    if ($status['opcache_enabled']) {
        echo "Try adding: opcache_reset(); at top of this file\n";
        opcache_reset();
        echo "OPcache cleared ✅\n";
    }
} else {
    echo "OPcache extension not available\n";
}
echo "\n";

// ── 3. Simulate full login flow for a specific user ──
echo "━━━ 3. Simulating login flow ━━━\n";
$testUserId = 6; // danahparis
$currentIp  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$currentUA  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$parsedDevice = parseUserAgent($currentUA);

echo "Testing for UserID: $testUserId\n";
echo "Your IP: $currentIp\n";
echo "Parsed device: $parsedDevice\n\n";

// Check current DB state
$r = $conn->query("SELECT last_login_ip, last_login_device FROM users WHERE UserID = $testUserId");
$before = $r->fetch_assoc();
echo "BEFORE → IP: " . ($before['last_login_ip'] ?? 'NULL') . " | Device: " . ($before['last_login_device'] ?? 'NULL') . "\n";

// Call isSuspiciousLogin
$suspicious = isSuspiciousLogin($conn, $testUserId, $currentIp, $currentUA);
echo "isSuspiciousLogin() returned: " . ($suspicious ? '⚠️ TRUE (suspicious)' : '✅ FALSE (not suspicious)') . "\n";

// Call updateLastLoginDetails
updateLastLoginDetails($conn, $testUserId, $currentIp, $currentUA);

// Check DB after
$r = $conn->query("SELECT last_login_ip, last_login_device FROM users WHERE UserID = $testUserId");
$after = $r->fetch_assoc();
echo "AFTER  → IP: " . ($after['last_login_ip'] ?? 'NULL') . " | Device: " . ($after['last_login_device'] ?? 'NULL') . "\n\n";

if ($after['last_login_ip'] === $currentIp) {
    echo "✅ IP saved correctly\n";
} else {
    echo "❌ IP NOT saved\n";
}

if ($after['last_login_device'] === $parsedDevice) {
    echo "✅ Device saved as PARSED string (new code running)\n";
} elseif ($after['last_login_device'] === $currentUA) {
    echo "⚠️ Device saved as RAW User-Agent (OLD code still running)\n";
} else {
    echo "❌ Device not saved\n";
}

echo "\n";

// ── 4. Check if new error-log lines exist in file ──
echo "━━━ 4. Version fingerprint ━━━\n";
$src = implode('', $lines);
$checks = [
    'Has error logging (new)'       => strpos($src, '[updateLastLoginDetails] SUCCESS') !== false,
    'Uses parseUserAgent in update'  => (bool) preg_match('/function updateLastLoginDetails.*?parseUserAgent/s', $src),
    'empty IP returns false (fixed)' => strpos($src, "empty(\$user['last_login_ip'])) return false") !== false,
];
foreach ($checks as $label => $pass) {
    echo ($pass ? '✅' : '❌') . " $label\n";
}

$conn->close();
echo "\n</pre>";
echo "<p style='font-family:monospace;color:red;padding:20px;'>⚠️ DELETE debug_login_test.php after use!</p>";
?>
