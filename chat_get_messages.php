<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

header('Content-Type: application/json');
$response = ['success' => false, 'messages' => []];

// Admin: fetch messages for a specific vendor
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    if (isset($_GET['action']) && $_GET['action'] === 'get_messages' && isset($_GET['vendor_id'])) {
        $vendor_id = intval($_GET['vendor_id']);
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE vendor_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response['messages'][] = $row;
        }
        $response['success'] = true;
    } elseif (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['vendor_id'])) {
        $vendor_id = intval($_GET['vendor_id']);
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE vendor_id = $vendor_id AND sender_type = 'vendor'");
        $response['success'] = true;
    }
}
// Vendor: fetch messages for their own store
elseif (isset($_SESSION['user_id'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'get_messages') {
        $vendor_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE vendor_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response['messages'][] = $row;
        }
        $response['success'] = true;
    }
}

echo json_encode($response);
?>