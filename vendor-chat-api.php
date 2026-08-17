<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die(json_encode(['success' => false, 'error' => 'Database error']));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'get_messages') {
    $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE vendor_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'messages' => $messages]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>