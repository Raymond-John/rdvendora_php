<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/email_functions.php';
require_once 'includes/notification_helper.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$order_id) die('Invalid order');

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, s.user_id as vendor_user_id, s.id as store_id, u.email, u.full_name 
    FROM orders o 
    LEFT JOIN stores s ON o.store_id = s.id 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) die('Order not found');

// ========== CREATE NOTIFICATIONS ==========
$vendor_id = $order['vendor_user_id'] ?? 0;
$customerName = $order['full_name'] ?? $order['user_name'] ?? 'Customer';
$orderRef = $order['order_ref'] ?? '#' . $order_id;
$total = number_format($order['total_amount'] ?? 0, 2);

$title = "New Order $orderRef";
$message = "Order $orderRef from $customerName – Total: ₦$total";
$link = "orders.php?view=$order_id";

// 1. Notify the vendor (store owner)
if ($vendor_id) {
    createNotification($vendor_id, 'order', $title, $message, $link);
}

// 2. Notify all admins and editors of the store (team members)
$store_id = $order['store_id'] ?? 0;
if ($store_id) {
    $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = ? AND role IN ('admin', 'editor')");
    $teamQuery->bind_param("i", $store_id);
    $teamQuery->execute();
    $teamResult = $teamQuery->get_result();
    while ($team = $teamResult->fetch_assoc()) {
        // Avoid duplicate to vendor
        if ($team['user_id'] != $vendor_id) {
            createNotification($team['user_id'], 'order', $title, $message, $link);
        }
    }
    $teamQuery->close();
}
// ============================================

// Fetch order items
$items_stmt = $conn->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$orderItems = [];
while ($item = $items_result->fetch_assoc()) {
    $orderItems[] = [
        'name'  => $item['product_name'],
        'qty'   => $item['quantity'],
        'price' => $item['price']
    ];
}
$items_stmt->close();

// Prepare order data for email
$orderData = [
    'order_id'     => $order['id'],
    'created_at'   => $order['created_at'],
    'total_amount' => $order['total_amount'],
    'items'        => $orderItems
];

$customerEmail = $order['email'] ?? ($order['user_email'] ?? $order['customer_email'] ?? '');
$customerName  = $order['full_name'] ?? ($order['customer_name'] ?? 'Valued Customer');

$emailSent = false;
if (!empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $emailSent = sendOrderConfirmation($customerEmail, $customerName, $orderData);
    if (!$emailSent) {
        error_log("Order confirmation email FAILED for order #$order_id to $customerEmail");
    }
} else {
    error_log("Order confirmation email not sent: invalid email for order #$order_id");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Successful</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; text-align: center; padding: 4rem 2rem; }
        .card { background: white; max-width: 500px; margin: 0 auto; padding: 2rem; border-radius: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .checkmark { font-size: 4rem; color: #10b981; margin-bottom: 1rem; }
        h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .order-details { background: #f9fafb; padding: 1rem; border-radius: 0.75rem; margin: 1.5rem 0; text-align: left; }
        .order-details div { padding: 0.25rem 0; }
        .btn { display: inline-block; background: #1a56db; color: white; padding: 0.75rem 1.5rem; border-radius: 2rem; text-decoration: none; margin-top: 1rem; }
        .email-status { font-size: 0.8rem; margin-top: 1rem; }
        .email-status.success { color: #059669; }
        .email-status.error { color: #dc2626; }
    </style>
</head>
<body>
<div class="card">
    <div class="checkmark">✅</div>
    <h1>Thank you for your order!</h1>
    <p>Your order <?= htmlspecialchars($orderRef) ?> has been received.</p>
    <div class="order-details">
        <div><strong>Total paid:</strong> ₦<?= number_format($order['total_amount'], 2) ?></div>
        <div><strong>Payment method:</strong> <?= ucfirst($order['payment_method']) ?></div>
        <div><strong>Confirmation sent to:</strong> <?= htmlspecialchars($customerEmail) ?></div>
    </div>
    <!-- FIXED: Uses store_id (primary key) instead of vendor_user_id -->
    <a href="storefront.php?store=<?= (int)$order['store_id'] ?>" class="btn">Continue Shopping</a>
    <div class="email-status <?= $emailSent ? 'success' : 'error' ?>">
        <?= $emailSent ? '✉️ A confirmation email has been sent to your email address.' : '⚠️ Could not send confirmation email. Please contact support.' ?>
    </div>
</div>

<script>
    // FIXED: Clears the global cart (greenshop_cart) instead of per-store carts
    const CART_KEY = 'greenshop_cart';
    localStorage.removeItem(CART_KEY);
    console.log('Cart cleared');
</script>
</body>
</html>