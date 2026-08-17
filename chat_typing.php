<?php
session_start();
require_once 'includes/connection.php';
if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die();

$action = $_POST['action'] ?? '';
$vendor_id = $_SESSION['user_id'] ?? 0;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if ($action === 'start') {
    $_SESSION['typing_to'] = $is_admin ? 0 : $vendor_id; // store typing status
    $_SESSION['typing_time'] = time();
} elseif ($action === 'stop') {
    unset($_SESSION['typing_to']);
}
echo json_encode(['success' => true]);
?>