<?php
session_start();
header('Content-Type: application/json');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$order_number = $_GET['order_number'] ?? '';
$order_id = $_GET['order_id'] ?? '';

if (empty($order_number) && empty($order_id)) {
    echo json_encode(['error' => 'Order number or ID required']);
    exit();
}

// Check what columns exist in orders table
$columns_check = $conn->query("SHOW COLUMNS FROM orders");
$order_columns = [];
while ($col = $columns_check->fetch_assoc()) {
    $order_columns[] = $col['Field'];
}

// Determine user ID column
$user_id_column = 'user_id';
if (!in_array('user_id', $order_columns)) {
    if (in_array('store_id', $order_columns)) {
        $user_id_column = 'store_id';
    } elseif (in_array('seller_id', $order_columns)) {
        $user_id_column = 'seller_id';
    }
}

// Fetch order details
if (!empty($order_number)) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ? AND $user_id_column = ?");
    $stmt->bind_param("si", $order_number, $_SESSION['user_id']);
} else {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND $user_id_column = ?");
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
}
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'Order not found']);
    exit();
}

// Fetch items
$items = [];

// Try to get items from items column (JSON)
if (isset($order['items']) && !empty($order['items'])) {
    $decoded = json_decode($order['items'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $items = $decoded;
    }
}

// If no items found, try order_items table
if (empty($items)) {
    $order_items_check = $conn->query("SHOW TABLES LIKE 'order_items'");
    if ($order_items_check && $order_items_check->num_rows > 0) {
        $items_stmt = $conn->prepare("SELECT product_name as name, quantity as qty, price FROM order_items WHERE order_id = ?");
        $items_stmt->bind_param("i", $order['id']);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }
        $items_stmt->close();
    }
}

$order['items'] = $items;

// Ensure total exists
$total_column = 'total';
if (!in_array('total', $order_columns)) {
    if (in_array('order_total', $order_columns)) $total_column = 'order_total';
    elseif (in_array('amount', $order_columns)) $total_column = 'amount';
    elseif (in_array('grand_total', $order_columns)) $total_column = 'grand_total';
}

if (!isset($order['total']) && isset($order[$total_column])) {
    $order['total'] = $order[$total_column];
} elseif (!isset($order['total'])) {
    // Calculate from items
    $calculated_total = 0;
    foreach ($items as $item) {
        $calculated_total += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
    }
    $order['total'] = $calculated_total;
}

$conn->close();

echo json_encode($order);
?>