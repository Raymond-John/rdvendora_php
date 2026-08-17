<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once 'includes/connection.php';
require_once 'includes/notifications_helper.php';

$count = getUnreadNotificationCount($conn, $_SESSION['user_id']);
$conn->close();

echo json_encode(['count' => $count]);
?>