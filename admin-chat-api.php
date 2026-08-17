<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die(json_encode(['success' => false, 'error' => 'Database error']));

// Admin only
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_messages') {
    $vendor_id = intval($_GET['vendor_id']);
    $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE vendor_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'messages' => $messages]);
}
elseif ($action === 'mark_read') {
    $vendor_id = intval($_GET['vendor_id']);
    $conn->query("UPDATE chat_messages SET is_read = 1 WHERE vendor_id = $vendor_id AND sender_type = 'vendor' AND is_read = 0");
    echo json_encode(['success' => true]);
}
elseif ($action === 'get_vendors') {
    $vendors = [];
    $query = $conn->query("
        SELECT s.user_id as vendor_id, s.store_name,
            (SELECT COUNT(*) FROM chat_messages WHERE vendor_id = s.user_id AND sender_type = 'vendor' AND is_read = 0) as unread
        FROM stores s
        WHERE s.status = 'active'
    ");
    while ($row = $query->fetch_assoc()) {
        $vendors[] = $row;
    }
    echo json_encode(['success' => true, 'vendors' => $vendors]);
}
else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>