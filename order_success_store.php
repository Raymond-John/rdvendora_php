<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/email_functions.php';
require_once 'includes/notification_helper.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ----- Helper to fetch settings -----
function getMarketplaceSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

// ----- Color Settings -----
$body_bg_color = getMarketplaceSetting('body_bg_color', '#f7faf8');
$text_primary_color = getMarketplaceSetting('text_primary_color', '#1a1a1a');
$primary_btn_bg = getMarketplaceSetting('primary_btn_bg', '#27a85a');
$primary_btn_text = getMarketplaceSetting('primary_btn_text', '#ffffff');
$card_bg_color = getMarketplaceSetting('card_bg_color', '#ffffff');
$sidebar_bg_color = getMarketplaceSetting('sidebar_bg_color', '#ffffff');
$sidebar_text_color = getMarketplaceSetting('sidebar_text_color', '#555');

function darkenHex($hex, $factor = 0.7) {
    if (preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
        $r = hexdec($m[1][0].$m[1][1]) * $factor;
        $g = hexdec($m[1][2].$m[1][3]) * $factor;
        $b = hexdec($m[1][4].$m[1][5]) * $factor;
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    return $hex;
}
$btn_bg_dark = darkenHex($primary_btn_bg, 0.7);
$btn_bg_darker = darkenHex($primary_btn_bg, 0.5);

$orderRef = $_GET['ref'] ?? '';
$error = null;
$orders = [];
$allItems = [];
$customerEmail = '';

if (empty($orderRef)) {
    $error = "No order reference provided.";
} else {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_ref = ?");
    $stmt->bind_param("s", $orderRef);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $error = "Order not found. Please check your order reference.";
    } elseif ($order['status'] !== 'completed') {
        $error = "Order status is '{$order['status']}'. Payment not completed yet.";
    } else {
        $transactionRef = $order['transaction_ref'];
        $stmt = $conn->prepare("SELECT * FROM orders WHERE transaction_ref = ?");
        $stmt->bind_param("s", $transactionRef);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();

        foreach ($orders as $ord) {
            $stmt = $conn->prepare("SELECT oi.*, s.store_name, s.user_id as vendor_user_id
                                    FROM order_items oi
                                    LEFT JOIN stores s ON oi.store_id = s.id
                                    WHERE oi.order_id = ?");
            $stmt->bind_param("i", $ord['id']);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($item = $res->fetch_assoc()) {
                $allItems[] = $item;
            }
            $stmt->close();
        }

        // Notifications
        $customerName = $order['user_name'] ?? $order['customer_name'] ?? 'Customer';
        $orderRefDisplay = $order['order_ref'] ?? '#' . $order['id'];

        $storeGroups = [];
        foreach ($allItems as $item) {
            $storeKey = $item['store_id'];
            if (!isset($storeGroups[$storeKey])) {
                $storeGroups[$storeKey] = [
                    'store_name' => $item['store_name'] ?? 'Store',
                    'vendor_user_id' => $item['vendor_user_id'] ?? 0,
                    'items' => [],
                    'total' => 0
                ];
            }
            $storeGroups[$storeKey]['items'][] = $item;
            $storeGroups[$storeKey]['total'] += $item['price'] * $item['quantity'];
        }

        foreach ($storeGroups as $storeId => $storeData) {
            $vendor_id = $storeData['vendor_user_id'];
            $storeTotal = number_format($storeData['total'], 2);
            $title = "New Order $orderRefDisplay";
            $message = "Order $orderRefDisplay from $customerName – Total: ₦$storeTotal (Store: {$storeData['store_name']})";
            $link = "orders.php?view=" . ($orders[0]['id'] ?? 0);

            if ($vendor_id) {
                createNotification($vendor_id, 'order', $title, $message, $link);
            }

            if ($storeId) {
                $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = ? AND role IN ('admin', 'editor')");
                $teamQuery->bind_param("i", $storeId);
                $teamQuery->execute();
                $teamResult = $teamQuery->get_result();
                while ($team = $teamResult->fetch_assoc()) {
                    if ($team['user_id'] != $vendor_id) {
                        createNotification($team['user_id'], 'order', $title, $message, $link);
                    }
                }
                $teamQuery->close();
            }
        }

        // Email
        $customerEmail = $order['user_email'] ?? ($order['customer_email'] ?? '');
        if (!empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $user_id = $order['user_id'] ?? 0;
            $customerName = 'Valued Customer';
            if ($user_id) {
                $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
                $userStmt->bind_param("i", $user_id);
                $userStmt->execute();
                $userRes = $userStmt->get_result();
                if ($user = $userRes->fetch_assoc()) {
                    $customerName = $user['full_name'];
                }
                $userStmt->close();
            }

            $orderItemsForEmail = [];
            foreach ($allItems as $item) {
                $orderItemsForEmail[] = [
                    'name'  => $item['product_name'],
                    'qty'   => $item['quantity'],
                    'price' => $item['price']
                ];
            }

            $orderData = [
                'order_id'     => $order['id'],
                'created_at'   => $order['created_at'],
                'total_amount' => array_sum(array_column($orders, 'total_amount')),
                'items'        => $orderItemsForEmail
            ];

            sendOrderConfirmation($customerEmail, $customerName, $orderData);
        } else {
            error_log("Order confirmation email not sent: missing customer email for order_ref $orderRef");
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Order Success - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ── DYNAMIC COLORS ── */
        :root {
            --body-bg: <?= htmlspecialchars($body_bg_color) ?>;
            --text-primary: <?= htmlspecialchars($text_primary_color) ?>;
            --btn-bg: <?= htmlspecialchars($primary_btn_bg) ?>;
            --btn-text: <?= htmlspecialchars($primary_btn_text) ?>;
            --card-bg: <?= htmlspecialchars($card_bg_color) ?>;
            --sidebar-bg: <?= htmlspecialchars($sidebar_bg_color) ?>;
            --sidebar-text: <?= htmlspecialchars($sidebar_text_color) ?>;
            --btn-bg-dark: <?= $btn_bg_dark ?>;
            --btn-bg-darker: <?= $btn_bg_darker ?>;
            --orange: #f97316;
        }

        /* ── BASE ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ── TOP STRIP ── */
        .top-strip {
            background: var(--btn-bg-dark);
            color: var(--btn-text);
            font-size: 12px;
            text-align: center;
            padding: 6px 16px;
            letter-spacing: .4px;
            font-weight: 500;
        }

        /* ── HEADER ── */
        header {
            background: var(--btn-bg);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            backdrop-filter: blur(2px);
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--btn-text);
            white-space: nowrap;
            letter-spacing: -0.5px;
            flex: 0 0 auto;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logo .rdv-brand-logo {
            height: 44px;
            width: auto;
            max-width: 170px;
            object-fit: contain;
            background: #fff;
            border-radius: 8px;
            padding: 2px 6px;
            display: block;
        }
        .logo span { color: #b8f5d0; }
        .logo i { color: var(--btn-text); font-size: 22px; }
        .search-bar {
            flex: 0 1 auto;
            margin: 0 auto;
            max-width: 560px;
            width: 100%;
            display: flex;
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.15);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .search-bar:focus-within {
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 4px rgba(255,255,255,0.08);
        }
        .search-bar input {
            flex: 1;
            padding: 10px 16px;
            border: none;
            font-size: 14px;
            outline: none;
            background: transparent;
            color: var(--btn-text);
            min-width: 0;
        }
        .search-bar input::placeholder { color: rgba(255,255,255,0.7); }
        .search-bar button {
            background: var(--btn-bg-dark);
            border: none;
            padding: 0 18px;
            color: var(--btn-text);
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .search-bar button:hover { background: var(--btn-bg-darker); }

        .header-actions {
            display: flex;
            gap: 16px;
            align-items: center;
            color: var(--btn-text);
            font-size: 13px;
            flex: 0 0 auto;
            margin-left: auto;
        }
        .header-actions a {
            color: var(--btn-text);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            cursor: pointer;
            transition: transform 0.2s, opacity 0.2s;
            position: relative;
        }
        .header-actions a:hover { transform: translateY(-2px); opacity: 0.85; }
        .header-actions a i { font-size: 22px; }
        .header-actions a span {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .cart-badge { position: relative; }
        .cart-badge .badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: var(--orange);
            color: #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(249,115,22,0.4);
        }

        /* ── MAIN CONTAINER ── */
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 20px; }

        /* ── SUCCESS / ERROR CARD ── */
        .success-card {
            background: var(--card-bg);
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.04);
            text-align: center;
            border: 1px solid rgba(0,0,0,0.03);
        }
        .error-card {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .error-card h1 { color: #dc2626; }
        .success-icon {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .order-ref {
            background: var(--sidebar-bg);
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            display: inline-block;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
            word-break: break-all;
            max-width: 100%;
            color: var(--text-primary);
        }

        /* ── ORDER DETAILS ── */
        .order-details {
            text-align: left;
            margin-top: 2rem;
            border-top: 2px solid var(--sidebar-bg);
            padding-top: 1.5rem;
        }
        .order-details h3 {
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .store-group {
            margin-bottom: 1.2rem;
            background: var(--body-bg);
            border-radius: 0.8rem;
            padding: 0.8rem 1rem;
            border: 1px solid var(--sidebar-bg);
        }
        .store-title {
            font-weight: 700;
            color: var(--btn-bg);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--sidebar-bg);
            margin-left: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .order-item:last-child { border-bottom: none; }
        .order-item span:first-child { flex: 1; word-break: break-word; }
        .order-item span:last-child { font-weight: 600; white-space: nowrap; color: var(--text-primary); }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--sidebar-bg);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .total-row .amount {
            color: var(--btn-bg);
        }

        .email-note {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--sidebar-text);
        }

        /* ── BUTTON ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--btn-bg);
            color: var(--btn-text);
            padding: 0.75rem 1.8rem;
            border-radius: 2rem;
            text-decoration: none;
            margin-top: 1.5rem;
            font-weight: 700;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(39,168,90,0.2);
        }
        .btn:hover {
            background: var(--btn-bg-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(39,168,90,0.3);
        }
        .btn-secondary {
            background: var(--sidebar-bg);
            color: var(--text-primary);
            box-shadow: none;
        }
        .btn-secondary:hover {
            background: var(--sidebar-text);
            color: var(--btn-text);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* ── FOOTER ── */
        footer {
            background: var(--btn-bg-dark);
            color: rgba(255,255,255,.85);
            padding: 40px 20px 20px;
            margin-top: 3rem;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        .footer-col h4 {
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
            border-bottom: 2px solid var(--btn-bg);
            padding-bottom: 6px;
        }
        .footer-col a {
            display: block;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 6px;
            transition: color .2s;
        }
        .footer-col a:hover { color: #fff; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,.1);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .social-links { display: flex; gap: 14px; }
        .social-links a {
            color: rgba(255,255,255,.6);
            font-size: 18px;
            transition: color .2s;
            text-decoration: none;
        }
        .social-links a:hover { color: #fff; }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .container { padding: 1rem 16px; }
            .success-card { padding: 1.5rem 1rem; }
            h1 { font-size: 1.5rem; }
            .success-icon, .error-icon { font-size: 3rem; }
            .order-ref { font-size: 0.75rem; }
            .order-details h3 { font-size: 1.1rem; }
            .store-title { font-size: 0.9rem; }
            .order-item { margin-left: 0.5rem; gap: 0.5rem; flex-direction: column; align-items: flex-start; }
            .order-item span:last-child { white-space: normal; font-size: 0.9rem; }
            .total-row { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .btn { width: 100%; justify-content: center; }
            header { flex-wrap: wrap; gap: 10px; }
            .search-bar { order: 3; flex-basis: 100%; max-width: 100%; margin: 0; }
            .header-actions { gap: 12px; }
        }
        @media (max-width: 480px) {
            .logo { font-size: 20px; }
            .store-group { padding: 0.5rem; }
            .order-item { padding: 0.4rem 0; }
        }
    </style>
</head>
<body>

<!-- TOP STRIP -->
<div class="top-strip">🚚 Free delivery on orders above ₦10,000 &nbsp;|&nbsp; ✅ 100% Genuine Products &nbsp;|&nbsp; 🔄 Easy Returns</div>

<!-- HEADER -->
<header>
    <a href="marketplace" class="logo"><img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span></a>
    <div class="search-bar">
        <form method="get" action="marketplace" style="display:flex; flex:1; width:100%;">
            <input type="text" name="q" placeholder="Search products, brands and categories…" />
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="header-actions">
        <a href="marketplaceaddtocart">
            <div class="cart-badge">
                <i class="fas fa-shopping-cart"></i>
                <span class="badge" id="cartCount">0</span>
            </div>
            <span>Cart</span>
        </a>
    </div>
</header>

<div class="container">
    <?php if ($error): ?>
        <!-- ERROR CARD -->
        <div class="success-card error-card">
            <i class="fas fa-exclamation-triangle error-icon"></i>
            <h1>Order Error</h1>
            <p><?= htmlspecialchars($error) ?></p>
            <a href="marketplace" class="btn"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
        </div>
    <?php else: ?>
        <!-- SUCCESS CARD -->
        <div class="success-card">
            <i class="fas fa-check-circle success-icon"></i>
            <h1>Payment Successful! 🎉</h1>
            <p>Thank you for your order. Your transaction has been completed.</p>
            <div class="order-ref">Transaction Reference: <?= htmlspecialchars($order['transaction_ref'] ?? $orderRef) ?></div>

            <div class="order-details">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                <?php
                $grouped = [];
                foreach ($allItems as $item) {
                    $storeKey = $item['store_id'];
                    if (!isset($grouped[$storeKey])) {
                        $grouped[$storeKey] = [
                            'store_name' => $item['store_name'] ?? 'Store',
                            'items' => []
                        ];
                    }
                    $grouped[$storeKey]['items'][] = $item;
                }
                foreach ($grouped as $storeId => $storeData): ?>
                    <div class="store-group">
                        <div class="store-title"><i class="fas fa-store"></i> <?= htmlspecialchars($storeData['store_name']) ?></div>
                        <?php foreach ($storeData['items'] as $item): ?>
                            <div class="order-item">
                                <span><?= htmlspecialchars($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                                <span>₦<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <div class="total-row">
                    <span>Total Paid</span>
                    <span class="amount">₦<?= number_format(array_sum(array_column($orders, 'total_amount')), 2) ?></span>
                </div>
                <?php if (!empty($customerEmail)): ?>
                    <p class="email-note">A confirmation email has been sent to <?= htmlspecialchars($customerEmail) ?></p>
                <?php endif; ?>
            </div>

            <!-- Only Continue Shopping button -->
            <a href="marketplace" class="btn"><i class="fas fa-shopping-cart"></i> Continue Shopping</a>
        </div>
    <?php endif; ?>
</div>

<!-- FOOTER -->
<footer>
    <div class="container" style="max-width:1200px;">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>RD Vendora</h4>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="#">Press</a>
                <a href="#">Contact Us</a>
                <a href="#">Affiliates</a>
            </div>
            <div class="footer-col">
                <h4>Help</h4>
                <a href="#">FAQ</a>
                <a href="#">Track Order</a>
                <a href="#">Returns</a>
                <a href="#">Report a Product</a>
            </div>
            <div class="footer-col">
                <h4>Sell on RD Vendora</h4>
                <a href="#">Become a Seller</a>
                <a href="#">Seller Center</a>
                <a href="#">Flash Sales</a>
                <a href="#">Advertise</a>
            </div>
            <div class="footer-col">
                <h4>Payment</h4>
                <a href="#">RD Pay</a>
                <a href="#">Cards Accepted</a>
                <a href="#">Bank Transfer</a>
                <a href="#">Pay on Delivery</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> RD Vendora. All rights reserved.</span>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-whatsapp"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
</footer>

<script>
// Clear cart after successful order (only if no error)
<?php if (!$error): ?>
    const CART_KEY = 'greenshop_cart';
    localStorage.removeItem(CART_KEY);
    // Update badge to 0
    document.getElementById('cartCount').innerText = '0';
<?php endif; ?>
</script>

</body>
</html>