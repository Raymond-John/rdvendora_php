<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SCRIPT_NAME'] = '/RD Vendora/dashboard.php';
$_SERVER['REQUEST_URI'] = '/RD Vendora/dashboard';
$_SERVER['SERVER_NAME'] = 'localhost';

chdir(dirname(__DIR__));
session_start();
require_once dirname(__DIR__) . '/includes/connection.php';
$conn = $conn ?? $connect;

$q = $conn->query("SELECT u.id AS uid, s.id AS sid, s.store_slug FROM users u JOIN stores s ON s.user_id=u.id WHERE s.store_slug='novanest' LIMIT 1");
$row = $q->fetch_assoc();
$_SESSION['user_id'] = (int) $row['uid'];
$_SESSION['store_id'] = (int) $row['sid'];
$_SESSION['store_slug'] = (string) $row['store_slug'];

ob_start();
include dirname(__DIR__) . '/dashboard.php';
$out = ob_get_clean();
echo 'len=' . strlen($out) . "\n";
echo (stripos($out, 'Welcome back') !== false) ? "HAS_WELCOME\n" : "NO_WELCOME\n";
echo (stripos($out, 'Something went wrong') !== false) ? "HAS_500\n" : "NO_500\n";
echo (stripos($out, 'Unknown column') !== false) ? "HAS_SQL_ERR\n" : "NO_SQL_ERR\n";
echo (stripos($out, 'My Store URL') !== false) ? "HAS_STORE_URL\n" : "NO_STORE_URL\n";
