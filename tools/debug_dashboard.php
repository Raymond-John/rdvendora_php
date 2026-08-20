<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SCRIPT_NAME'] = '/RD Vendora/dashboard.php';
$_SERVER['REQUEST_URI'] = '/RD Vendora/dashboard';

chdir(dirname(__DIR__));
session_start();
require_once dirname(__DIR__) . '/includes/connection.php';
$conn = $conn ?? $connect;

$q = $conn->query("SELECT u.id AS uid, s.id AS sid, s.store_slug FROM users u JOIN stores s ON s.user_id=u.id WHERE s.store_slug='novanest' LIMIT 1");
$row = $q->fetch_assoc();
$_SESSION['user_id'] = (int) $row['uid'];
$_SESSION['store_id'] = (int) $row['sid'];
$_SESSION['store_slug'] = (string) $row['store_slug'];

$conn->query("UPDATE subscriptions SET status='active', end_date=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id=29");

ob_start();
$ok = true;
try {
    include dirname(__DIR__) . '/dashboard.php';
} catch (Throwable $e) {
    $ok = false;
    echo 'CATCH:' . $e->getMessage();
}
$out = ob_get_clean();

echo 'ok=' . ($ok ? '1' : '0') . ' len=' . strlen($out) . "\n";
echo (stripos($out, 'Welcome back') !== false) ? "HAS_WELCOME\n" : "NO_WELCOME\n";
echo (stripos($out, 'Something went wrong') !== false) ? "HAS_500\n" : "NO_500\n";
echo (stripos($out, 'My Store URL') !== false) ? "HAS_STORE_URL\n" : "NO_STORE_URL\n";
echo (stripos($out, 'Fatal') !== false || stripos($out, 'Uncaught') !== false || stripos($out, 'Unknown column') !== false) ? "HAS_ERR\n" : "NO_ERR\n";

// Reconnect to restore
require dirname(__DIR__) . '/includes/connection.php';
$conn2 = $conn ?? $connect;
if ($conn2) {
    $conn2->query("UPDATE subscriptions SET status='expired', end_date='2026-07-22 14:16:16' WHERE id=29");
    echo "restored\n";
}
