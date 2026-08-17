<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/connection.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No input']);
    exit;
}

$order_id = (int)($input['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID missing']);
    exit;
}

// For now, mark order as paid (remove this stub when integrating real payment verification)
$conn->query("UPDATE orders SET status = 'completed', payment_status = 'paid' WHERE id = $order_id");

echo json_encode(['success' => true, 'message' => 'Payment verified', 'order_id' => $order_id]);
?>