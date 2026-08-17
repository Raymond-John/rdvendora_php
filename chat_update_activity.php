<?php
session_start();
require_once 'includes/connection.php';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}
?>
