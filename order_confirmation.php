<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$order_id) die('Order not found.');

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) die('Order not found.');

// Get store details
$store_stmt = $conn->prepare("SELECT * FROM stores WHERE user_id = ?");
$store_stmt->bind_param("i", $order['store_id']);
$store_stmt->execute();
$store = $store_stmt->get_result()->fetch_assoc();
$store_stmt->close();

$brandColor = $store['brand_color'] ?? '#1a56db';
function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    $r = max(0, min(255, $r + $percent));
    $g = max(0, min(255, $g + $percent));
    $b = max(0, min(255, $b + $percent));
    return "#".str_pad(dechex($r),2,'0',STR_PAD_LEFT).str_pad(dechex($g),2,'0',STR_PAD_LEFT).str_pad(dechex($b),2,'0',STR_PAD_LEFT);
}
$brandColorDark = adjustBrightness($brandColor, -20);
$gradientPrimary = "linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%)";

// Decode cart items from JSON
$cart_items = json_decode($order['cart_data'], true);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?= htmlspecialchars($store['store_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            padding: 2rem 1rem;
        }
        .receipt-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .receipt-header {
            background: <?= $gradientPrimary ?>;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .receipt-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .receipt-header p {
            opacity: 0.9;
        }
        .receipt-body {
            padding: 2rem;
        }
        .order-info {
            background: #f9fafb;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1rem;
        }
        .order-info-item {
            flex: 1;
            min-width: 150px;
        }
        .order-info-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 1px;
        }
        .order-info-value {
            font-weight: 600;
            margin-top: 0.25rem;
            color: #1f2937;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        .items-table th {
            text-align: left;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .items-table td {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .totals {
            text-align: right;
            margin-top: 1rem;
        }
        .totals-row {
            display: flex;
            justify-content: flex-end;
            gap: 2rem;
            padding: 0.25rem 0;
        }
        .totals-label {
            font-weight: 500;
            color: #4b5563;
        }
        .totals-value {
            font-weight: 600;
            min-width: 100px;
        }
        .grand-total {
            border-top: 2px solid #e5e7eb;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            font-size: 1.2rem;
            font-weight: 800;
        }
        .footer-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .btn-primary {
            background: <?= $brandColor ?>;
            color: white;
            border: none;
        }
        .btn-secondary {
            background: white;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        .btn-secondary:hover {
            background: #f9fafb;
        }
        .btn-primary:hover {
            filter: brightness(0.95);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .footer-buttons, .no-print {
                display: none;
            }
            .receipt-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
<div class="receipt-container" id="receipt">
    <div class="receipt-header">
        <h1>🎉 Thank You for Your Order!</h1>
        <p>Your order has been confirmed.</p>
    </div>
    <div class="receipt-body">
        <div class="order-info">
            <div class="order-info-item">
                <div class="order-info-label">Order Number</div>
                <div class="order-info-value"><?= htmlspecialchars($order['order_number']) ?></div>
            </div>
            <div class="order-info-item">
                <div class="order-info-label">Order Date</div>
                <div class="order-info-value"><?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></div>
            </div>
            <div class="order-info-item">
                <div class="order-info-label">Payment Status</div>
                <div class="order-info-value" style="color: #059669;"><?= ucfirst($order['payment_status']) ?></div>
            </div>
        </div>

        <h3 style="margin-bottom: 0.5rem;">Customer Details</h3>
        <div class="order-info" style="margin-bottom: 1.5rem;">
            <div class="order-info-item">
                <div class="order-info-label">Name</div>
                <div class="order-info-value"><?= htmlspecialchars($order['customer_name']) ?></div>
            </div>
            <div class="order-info-item">
                <div class="order-info-label">Email</div>
                <div class="order-info-value"><?= htmlspecialchars($order['customer_email']) ?></div>
            </div>
            <div class="order-info-item">
                <div class="order-info-label">Phone</div>
                <div class="order-info-value"><?= htmlspecialchars($order['customer_phone']) ?></div>
            </div>
            <div class="order-info-item">
                <div class="order-info-label">Shipping Address</div>
                <div class="order-info-value"><?= nl2br(htmlspecialchars($order['shipping_address'] . ', ' . $order['city'] . ', ' . $order['state'] . ', ' . $order['country'])) ?></div>
            </div>
        </div>

        <h3 style="margin-bottom: 1rem;">Order Items</h3>
        <table class="items-table">
            <thead>
                <tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= (int)$item['quantity'] ?></td>
                    <td>₦<?= number_format($item['price'], 2) ?></td>
                    <td>₦<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row"><span class="totals-label">Subtotal:</span><span class="totals-value">₦<?= number_format($order['subtotal'], 2) ?></span></div>
            <div class="totals-row"><span class="totals-label">Shipping:</span><span class="totals-value"><?= $order['shipping_cost'] == 0 ? 'Free' : '₦'.number_format($order['shipping_cost'], 2) ?></span></div>
            <div class="totals-row"><span class="totals-label">Tax (5%):</span><span class="totals-value">₦<?= number_format($order['tax'], 2) ?></span></div>
            <div class="totals-row grand-total"><span class="totals-label">Total Paid:</span><span class="totals-value">₦<?= number_format($order['total'], 2) ?></span></div>
        </div>

        <div class="footer-buttons no-print">
            <a href="storefront.php?store=<?= $order['store_id'] ?>" class="btn btn-secondary">← Continue Shopping</a>
            <button onclick="window.print();" class="btn btn-primary">📄 Download Receipt (PDF)</button>
        </div>
        <p style="text-align: center; font-size: 0.75rem; color: #9ca3af; margin-top: 2rem;">
            Thank you for shopping at <?= htmlspecialchars($store['store_name']) ?>.
        </p>
    </div>
</div>
</body>
</html>