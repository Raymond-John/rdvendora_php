<?php
session_start();
$other_user_id = $_GET['user_id'] ?? 0;
$typing = false;
if ($other_user_id && isset($_SESSION['typing_time']) && (time() - $_SESSION['typing_time'] < 4)) {
    $typing = true;
}
echo json_encode(['typing' => $typing]);
?>
