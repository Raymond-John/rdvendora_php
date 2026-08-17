<?php
session_start();
header('Content-Type: application/json');

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/storage/logs/order_errors.log');
error_reporting(E_ALL);

require_once 'includes/connection.php';
require_once 'includes/notification_helper.php';  // <-- ADD THIS

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$storePk = (int)($input['store_id'] ?? 0);
$cart = $input['cart'] ?? [];
$customer = $input['customer'] ?? [];
$subtotal = (float)($input['subtotal'] ?? 0);
$shipping = (float)($input['shipping'] ?? 0);
$tax = (float)($input['tax'] ?? 0);
$total = (float)($input['total'] ?? 0);
$paymentMethod = $input['payment_method'] ?? '';

if ($storePk <= 0 || empty($cart) || empty($customer['email']) || $total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required order data']);
    exit;
}

// Verify store exists and get vendor user_id
$stmt = $conn->prepare("SELECT user_id FROM stores WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $storePk);
$stmt->execute();
$storeRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$storeRow) {
    echo json_encode(['success' => false, 'message' => 'Store not found or inactive']);
    exit;
}
$vendorUserId = $storeRow['user_id'];

// Generate unique references
$orderRef = 'ORD_' . time() . '_' . bin2hex(random_bytes(4));
$transactionRef = 'TXN_' . time() . '_' . bin2hex(random_bytes(8));

// Build full address string
$fullAddress = trim($customer['address'] . ', ' . $customer['city'] . ', ' . $customer['state'] . ', ' . $customer['country']);

// Insert into orders table
$sql = "INSERT INTO orders 
        (store_id, order_ref, transaction_ref, user_name, user_email, user_phone, user_address, total_amount, payment_method, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("issssssds", 
    $storePk, 
    $orderRef, 
    $transactionRef, 
    $customer['fullName'], 
    $customer['email'], 
    $customer['phone'], 
    $fullAddress, 
    $total, 
    $paymentMethod
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Order insert failed: ' . $stmt->error]);
    exit;
}
$orderId = $stmt->insert_id;
$stmt->close();

// Insert order items
$itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, store_id, product_name, price, quantity, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
if ($itemStmt) {
    foreach ($cart as $item) {
        $productId = $item['id'];
        $productName = $item['name'];
        $price = $item['price'];
        $qty = $item['quantity'];
        $image = $item['image'] ?? '';
        $itemStmt->bind_param("iiisdis", $orderId, $productId, $storePk, $productName, $price, $qty, $image);
        $itemStmt->execute();
    }
    $itemStmt->close();
}

// ========== CREATE NOTIFICATIONS ==========
$customerName = htmlspecialchars($customer['fullName'] ?? 'Customer');
$orderTotal = number_format($total, 2);

// 1. Notify the store owner (vendor)
$title = "New Order #$orderId";
$message = "Order #$orderId from $customerName – Total: ₦$orderTotal";
$link = "orders.php?view=$orderId";
createNotification($vendorUserId, 'order', $title, $message, $link);

// 2. Notify all store admins and editors (team members)
$teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = ? AND role IN ('admin', 'editor')");
$teamQuery->bind_param("i", $storePk);
$teamQuery->execute();
$teamResult = $teamQuery->get_result();
while ($team = $teamResult->fetch_assoc()) {
    // Avoid duplicate notification to the owner if they are already an admin
    if ($team['user_id'] != $vendorUserId) {
        createNotification($team['user_id'], 'order', $title, $message, $link);
    }
}
$teamQuery->close();

// ========== RESPONSE ==========
echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'transaction_ref' => $transactionRef,
    'order_ref' => $orderRef
]);
exit;
?>