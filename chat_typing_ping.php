<?php
session_start();
$action = $_POST['action'] ?? '';
if ($action === 'start') {
    $_SESSION['typing_time'] = time();
    $_SESSION['is_typing'] = true;
} elseif ($action === 'stop') {
    unset($_SESSION['is_typing']);
}
echo json_encode(['ok'=>true]);
?>