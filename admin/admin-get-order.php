<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php'; // for adminHasPermission()

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die(json_encode(['error' => 'DB connection failed']));

// Check admin login
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die(json_encode(['error' => 'Unauthorized']));
}

// Check if admin has permission to view orders
if (!adminHasPermission('orders', $conn)) {
    die(json_encode(['error' => 'Access denied']));
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT o.*, s.store_name, u.fullname as customer_name 
                        FROM orders o 
                        LEFT JOIN stores s ON o.store_id = s.id 
                        LEFT JOIN users u ON o.user_id = u.id 
                        WHERE o.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) die(json_encode(['error' => 'Order not found']));

// Fetch items
$items = [];
$items_stmt = $conn->prepare("SELECT product_name as name, quantity as qty, price FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $id);
$items_stmt->execute();
$res = $items_stmt->get_result();
while ($item = $res->fetch_assoc()) $items[] = $item;
$order['items'] = $items;

// Ensure order number
if (!isset($order['order_number']) || empty($order['order_number'])) {
    $order['order_number'] = 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
}

// Ensure total
if (!isset($order['total_amount']) || $order['total_amount'] == 0) {
    $total = 0;
    foreach ($items as $i) $total += $i['price'] * $i['qty'];
    $order['total'] = $total;
} else {
    $order['total'] = $order['total_amount'];
}

header('Content-Type: application/json');
echo json_encode($order);
?>