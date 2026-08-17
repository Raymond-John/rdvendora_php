<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$response = ['success' => false];

if (isset($_SESSION['user_id'])) {
    $vendor_id = $_SESSION['user_id'];
    $sender_type = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) ? 'vendor' : 'admin';
    $stmt = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE vendor_id = ? AND sender_type = ? AND is_read = 0");
    $stmt->bind_param("is", $vendor_id, $sender_type);
    $stmt->execute();
    $response['success'] = true;
    $stmt->close();
}
echo json_encode($response);
?>