<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    die('<div style="text-align:center; padding:3rem;"><h1>Invalid Order</h1><p>No order ID provided.</p><a href="marketplace.php">Go to Marketplace</a></div>');
}

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('<div style="text-align:center; padding:3rem;"><h1>Order Not Found</h1><p>The order you are looking for does not exist.</p><a href="marketplace.php">Go to Marketplace</a></div>');
}

// Fetch order items with store names
$stmt = $conn->prepare("
    SELECT oi.*, s.store_name, s.brand_color 
    FROM order_items oi 
    LEFT JOIN stores s ON oi.store_id = s.user_id 
    WHERE oi.order_id = ? 
    ORDER BY s.store_name, oi.id
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group items by store for display
$grouped = [];
foreach ($items as $item) {
    $storeId = $item['store_id'];
    if (!isset($grouped[$storeId])) {
        $grouped[$storeId] = [
            'store_name' => $item['store_name'] ?? "Store #$storeId",
            'brand_color' => $item['brand_color'] ?? '#4f46e5',
            'items' => []
        ];
    }
    $grouped[$storeId]['items'][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Order Confirmation - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #1e293b;
            line-height: 1.5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .nav-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
            gap: 1.5rem;
        }
        .logo {
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .logo i {
            color: #4f46e5;
        }
        .cart-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
        }
        .cart-count {
            background: #4f46e5;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            min-width: 22px;
            text-align: center;
        }
        /* Main content */
        .confirmation-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .success-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .success-header i {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        .success-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
        }
        .order-details {
            border-top: 1px solid #eef2ff;
            padding-top: 1.5rem;
            margin-top: 1rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 1rem;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
        }
        .info-value {
            font-weight: 600;
            margin-top: 0.2rem;
        }
        .store-group {
            margin-bottom: 1.5rem;
            border: 1px solid #f0f2f5;
            border-radius: 1rem;
            overflow: hidden;
        }
        .store-header {
            background: #f8fafc;
            padding: 0.8rem 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid #eef2ff;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1.2rem;
            border-bottom: 1px solid #f0f2f5;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .item-details {
            flex: 2;
        }
        .item-name {
            font-weight: 600;
        }
        .item-meta {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.2rem;
        }
        .item-quantity {
            flex: 1;
            text-align: center;
        }
        .item-price {
            flex: 1;
            text-align: right;
            font-weight: 700;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: 800;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #eef2ff;
        }
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-processing { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .continue-btn {
            background: #4f46e5;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 1rem;
        }
        footer {
            text-align: center;
            padding: 2rem;
            background: #0f172a;
            color: #94a3b8;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .info-grid { grid-template-columns: 1fr; }
            .order-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .item-quantity, .item-price { text-align: left; width: 100%; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="container nav-container">
        <a href="marketplace.php" class="logo">
            <i class="fas fa-store"></i> RD Vendora
        </a>
        <a href="cart.php" class="cart-link">
            <i class="fas fa-shopping-cart"></i> Cart <span id="cartCount" class="cart-count">0</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="confirmation-card">
        <div class="success-header">
            <i class="fas fa-check-circle"></i>
            <h1>Thank You for Your Order!</h1>
            <p>Your order has been placed successfully.</p>
        </div>

        <div class="order-details">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Order ID</span>
                    <span class="info-value">#<?= $order['id'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value"><?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value"><span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></span>
                </div>
            </div>

            <h3 style="margin-bottom: 1rem;">Customer Information</h3>
            <div class="info-grid" style="margin-bottom: 1.5rem;">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($order['customer_phone'] ?: 'Not provided') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Delivery Address</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($order['customer_address'])) ?></span>
                </div>
            </div>

            <h3 style="margin-bottom: 1rem;">Order Items</h3>
            <?php foreach ($grouped as $store): ?>
                <div class="store-group">
                    <div class="store-header" style="color: <?= $store['brand_color'] ?>;">
                        <i class="fas fa-store"></i> <?= htmlspecialchars($store['store_name']) ?>
                    </div>
                    <?php foreach ($store['items'] as $item): ?>
                        <div class="order-item">
                            <div class="item-details">
                                <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="item-meta">Price: $<?= number_format($item['price'], 2) ?></div>
                            </div>
                            <div class="item-quantity">Qty: <?= $item['quantity'] ?></div>
                            <div class="item-price">$<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="total-row">
                <span>Total Amount</span>
                <span>$<?= number_format($order['total_amount'], 2) ?></span>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="marketplace.php" class="continue-btn"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
        </div>
    </div>
</div>

<footer>
    <p><i class="fas fa-copyright"></i> <?= date('Y') ?> RD Vendora – Multi‑Vendor Marketplace. All rights reserved.</p>
</footer>

<script>
    // Update cart count from localStorage (optional)
    const CART_KEY = 'marketplace_cart';
    function updateCartCountDisplay() {
        const cart = localStorage.getItem(CART_KEY);
        const items = cart ? JSON.parse(cart) : [];
        const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
        const cartCountSpan = document.getElementById('cartCount');
        if (cartCountSpan) cartCountSpan.innerText = totalItems;
    }
    document.addEventListener('DOMContentLoaded', updateCartCountDisplay);
</script>
</body>
</html>