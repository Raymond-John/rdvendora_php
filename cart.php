<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ----- Helper to fetch settings (same as checkout) -----
function getMarketplaceSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

// ----- Get store ID from either 'store' or 'store_id' parameter -----
$storeId = isset($_GET['store']) ? (int)$_GET['store'] : 0;
if ($storeId == 0 && isset($_GET['store_id'])) {
    $storeId = (int)$_GET['store_id'];
}

// If not provided but user logged in, attempt to get their own store
if ($storeId == 0 && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $storeId = $row['id'];
    $stmt->close();
}

$store = null;
if ($storeId > 0) {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("i", $storeId);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$store) {
    die('<div style="text-align:center; padding:3rem;"><h1>Store Not Found</h1><p>The store you are looking for does not exist.</p><a href="marketplace.php">← Back to Marketplace</a></div>');
}

// ----- Ensure missing colour columns exist (run once) -----
$schemaChecked = STORAGE_PATH . '/cache/.cart_schema_checked';
if (!file_exists($schemaChecked)) {
    $missingCols = [];
    $checkNav = $conn->query("SHOW COLUMNS FROM stores LIKE 'nav_color'");
    if ($checkNav->num_rows == 0) $missingCols[] = "ADD COLUMN nav_color VARCHAR(7) DEFAULT '#ffffff' AFTER brand_color";
    $checkBody = $conn->query("SHOW COLUMNS FROM stores LIKE 'body_bg_color'");
    if ($checkBody->num_rows == 0) $missingCols[] = "ADD COLUMN body_bg_color VARCHAR(7) DEFAULT '#f9fafb' AFTER nav_color";
    $checkFooter = $conn->query("SHOW COLUMNS FROM stores LIKE 'footer_bg_color'");
    if ($checkFooter->num_rows == 0) $missingCols[] = "ADD COLUMN footer_bg_color VARCHAR(7) DEFAULT '#111827' AFTER body_bg_color";
    $checkCardBg = $conn->query("SHOW COLUMNS FROM stores LIKE 'card_bg_color'");
    if ($checkCardBg->num_rows == 0) $missingCols[] = "ADD COLUMN card_bg_color VARCHAR(7) DEFAULT '#ffffff' AFTER footer_bg_color";
    $checkCardBorder = $conn->query("SHOW COLUMNS FROM stores LIKE 'card_border_color'");
    if ($checkCardBorder->num_rows == 0) $missingCols[] = "ADD COLUMN card_border_color VARCHAR(7) DEFAULT '#e5e7eb' AFTER card_bg_color";
    $checkButtonBg = $conn->query("SHOW COLUMNS FROM stores LIKE 'button_bg_color'");
    if ($checkButtonBg->num_rows == 0) $missingCols[] = "ADD COLUMN button_bg_color VARCHAR(7) DEFAULT '#6366f1' AFTER card_border_color";
    $checkButtonText = $conn->query("SHOW COLUMNS FROM stores LIKE 'button_text_color'");
    if ($checkButtonText->num_rows == 0) $missingCols[] = "ADD COLUMN button_text_color VARCHAR(7) DEFAULT '#ffffff' AFTER button_bg_color";
    $checkDivBg = $conn->query("SHOW COLUMNS FROM stores LIKE 'div_bg_color'");
    if ($checkDivBg->num_rows == 0) $missingCols[] = "ADD COLUMN div_bg_color VARCHAR(7) DEFAULT '#f3f4f6' AFTER button_text_color";
    $checkDivBorder = $conn->query("SHOW COLUMNS FROM stores LIKE 'div_border_color'");
    if ($checkDivBorder->num_rows == 0) $missingCols[] = "ADD COLUMN div_border_color VARCHAR(7) DEFAULT '#e5e7eb' AFTER div_bg_color";

    if (!empty($missingCols)) {
        $alterSQL = "ALTER TABLE stores " . implode(", ", $missingCols);
        $conn->query($alterSQL);
    }
    file_put_contents($schemaChecked, '1');
    // Refresh store data after altering
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("i", $storeId);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ----- Dynamic colors from store settings -----
$brandColor       = $store['brand_color']       ?? '#1a56db';
$navColor         = $store['nav_color']         ?? '#ffffff';
$bodyBgColor      = $store['body_bg_color']     ?? '#f9fafb';
$footerBgColor    = $store['footer_bg_color']   ?? '#111827';
$cardBgColor      = $store['card_bg_color']     ?? '#ffffff';
$cardBorderColor  = $store['card_border_color'] ?? '#e5e7eb';
$buttonBgColor    = $store['button_bg_color']   ?? '#6366f1';
$buttonTextColor  = $store['button_text_color'] ?? '#ffffff';
$divBgColor       = $store['div_bg_color']      ?? '#f3f4f6';
$divBorderColor   = $store['div_border_color']  ?? '#e5e7eb';

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

$brandColorDark  = adjustBrightness($brandColor, -20);
$brandColorLight = adjustBrightness($brandColor, 60);
$gradientPrimary = "linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%)";

function getTextColor($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
    return $luminance > 128 ? '#1f2937' : '#ffffff';
}

$navTextColor = getTextColor($navColor);
$footerTextColor = getTextColor($footerBgColor);

// ========== FETCH TAX SETTINGS (shipping not shown on cart) ==========
$tax_rate = floatval(getMarketplaceSetting('tax_rate', '5'));

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Cart - <?= htmlspecialchars($store['store_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ====== (All styles remain exactly as before) ====== */
        * { margin:0; padding:0; box-sizing:border-box; }
        
        :root {
            --primary: <?= $brandColor ?>;
            --primary-dark: <?= $brandColorDark ?>;
            --primary-light: <?= $brandColorLight ?>;
            --gradient-primary: <?= $gradientPrimary ?>;
            --nav-bg: <?= $navColor ?>;
            --nav-text: <?= $navTextColor ?>;
            --body-bg: <?= $bodyBgColor ?>;
            --footer-bg: <?= $footerBgColor ?>;
            --footer-text: <?= $footerTextColor ?>;
            --card-bg: <?= $cardBgColor ?>;
            --card-border: <?= $cardBorderColor ?>;
            --button-bg: <?= $buttonBgColor ?>;
            --button-text: <?= $buttonTextColor ?>;
            --div-bg: <?= $divBgColor ?>;
            --div-border: <?= $divBorderColor ?>;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--body-bg);
            color: var(--gray-900);
            line-height: 1.5;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; transition: var(--transition); }
        img { max-width: 100%; display: block; }

        /* Navbar */
        .navbar {
            background: var(--nav-bg);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--gray-200);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
            gap: 1.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 800;
            white-space: nowrap;
            color: var(--nav-text);
        }

        .logo h4 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--gradient-primary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .logo img { 
            height: 45px; 
            width: auto; 
            object-fit: contain;
        }

        .store-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--nav-text);
        }

        .btn-outline:hover {
            background: rgba(0, 0, 0, 0.05);
            border-color: var(--gray-400);
        }

        /* Cart Page */
        .cart-page {
            max-width: 1280px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .cart-header {
            margin-bottom: 2rem;
        }

        .cart-header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .cart-grid {
                grid-template-columns: 1fr;
            }
            .nav-container {
                padding: 0 1rem;
            }
            .logo h4 {
                font-size: 1rem;
            }
        }

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            transition: var(--transition);
        }

        .cart-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .cart-item img {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-lg);
            object-fit: cover;
            background: var(--gray-100);
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .cart-item-cat {
            font-size: 0.7rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .cart-item-price {
            font-weight: 800;
            font-size: 1.125rem;
            color: var(--primary);
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--div-bg);
            padding: 0.25rem;
            border-radius: var(--radius-lg);
        }

        .qty-control button {
            width: 34px;
            height: 34px;
            border-radius: var(--radius);
            background: var(--card-bg);
            border: 1px solid var(--div-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            color: var(--gray-700);
        }

        .qty-control button:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .qty-control span {
            min-width: 32px;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .remove-btn {
            width: 38px;
            height: 38px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.2rem;
        }

        .remove-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            transform: scale(1.1);
        }

        /* Cart Summary */
        .cart-summary {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius-xl);
            padding: 1.75rem;
            height: fit-content;
            position: sticky;
            top: 90px;
            box-shadow: var(--shadow);
        }

        .cart-summary h3 {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            color: var(--gray-900);
            position: relative;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .summary-row.total {
            border-top: 2px solid var(--card-border);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-size: 1.125rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        .summary-row.total span:last-child {
            color: var(--primary);
            font-size: 1.25rem;
        }

        .checkout-btn {
            background: var(--gradient-primary);
            color: white;
            width: 100%;
            padding: 0.875rem;
            border-radius: var(--radius-lg);
            text-align: center;
            display: block;
            font-weight: 700;
            margin-top: 1.5rem;
            transition: var(--transition);
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--card-border);
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--gray-700);
        }

        .empty-cart p {
            color: var(--gray-500);
            margin-bottom: 1.5rem;
        }

        .empty-cart .continue-shopping {
            display: inline-block;
            background: var(--gradient-primary);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            transition: var(--transition);
        }

        .empty-cart .continue-shopping:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Footer */
        .footer {
            background: var(--footer-bg);
            color: var(--footer-text);
            padding: 2rem 1.5rem;
            margin-top: 4rem;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer p {
            opacity: 0.8;
            font-size: 0.85rem;
        }

        /* Toast Animation */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .toast {
            animation: slideIn 0.3s ease-out;
        }
        .shipping-note {
            font-size: 0.7rem;
            color: var(--gray-500);
            text-align: right;
            margin-top: -0.25rem;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="storefront.php?store=<?= $store['id'] ?>" class="logo">
            <?php if (!empty($store['logo_path'])): ?>
                <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?>">
            <?php else: ?>
                <div class="logo-icon">🛍️</div>
            <?php endif; ?>
            <h4><?= htmlspecialchars($store['store_name']) ?></h4>
        </a>
        <div class="store-actions">
            <a href="storefront.php?store=<?= $store['id'] ?>" class="btn btn-outline">
                ← Continue Shopping
            </a>
        </div>
    </div>
</nav>

<div class="cart-page">
    <div class="cart-header">
        <h1>Shopping Cart</h1>
    </div>
    <div id="cartContent" class="cart-grid">
        <!-- Dynamic content will be injected here -->
    </div>
</div>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>. All rights reserved | Developed by RD Nexa Tech</p>
</footer>

<script>
    // ========== DYNAMIC SETTINGS FROM PHP ==========
    const TAX_RATE = <?= json_encode($tax_rate) ?>;   // e.g., 5

    // ========== CART MANAGEMENT ==========
    const STORE_ID = <?= $store['id'] ?>;
    const CART_KEY = `cart_${STORE_ID}`;

    function getCart() {
        const cart = localStorage.getItem(CART_KEY);
        return cart ? JSON.parse(cart) : [];
    }

    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        renderCart();
    }

    function updateQuantity(id, delta) {
        let cart = getCart();
        const index = cart.findIndex(item => item.id == id);
        if (index !== -1) {
            const newQty = cart[index].quantity + delta;
            if (newQty <= 0) {
                cart.splice(index, 1);
                showToast('Item removed from cart', '#3b82f6');
            } else {
                cart[index].quantity = newQty;
                showToast('Cart updated', '#10b981');
            }
            saveCart(cart);
        }
    }

    function removeFromCart(id) {
        let cart = getCart();
        cart = cart.filter(item => item.id != id);
        saveCart(cart);
        showToast('Item removed', '#3b82f6');
    }

    function showToast(message, bgColor) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${bgColor};
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            z-index: 10000;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function renderCart() {
        const cart = getCart();
        const container = document.getElementById('cartContent');
        if (!container) return;

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="empty-cart" style="grid-column:1/-1;">
                    <h3>🛒 Your cart is empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="storefront.php?store=${STORE_ID}" class="continue-shopping">Continue Shopping</a>
                </div>
            `;
            return;
        }

        let itemsHtml = '';
        let subtotal = 0;
        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            itemsHtml += `
                <div class="cart-item">
                    <img src="${escapeHtml(item.image) || 'https://placehold.co/100x100'}" alt="${escapeHtml(item.name)}">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${escapeHtml(item.name)}</div>
                        <div class="cart-item-cat">₦${Number(item.price).toLocaleString()} each</div>
                        <div class="cart-item-price">₦${itemTotal.toLocaleString()}</div>
                    </div>
                    <div class="qty-control">
                        <button onclick="updateQuantity(${item.id}, -1)">−</button>
                        <span>${item.quantity}</span>
                        <button onclick="updateQuantity(${item.id}, 1)">+</button>
                    </div>
                    <div class="remove-btn" onclick="removeFromCart(${item.id})" title="Remove item">🗑️</div>
                </div>
            `;
        });

        // Calculate tax (shipping not included on cart page)
        const tax = subtotal * (TAX_RATE / 100);
        const total = subtotal + tax;   // shipping will be added at checkout

        const summaryHtml = `
            <div class="cart-summary">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₦${subtotal.toLocaleString()}</span>
                </div>
                <div class="summary-row" style="flex-direction:column; align-items:flex-start; gap:0.25rem; padding-bottom:0.25rem;">
                    <div style="display:flex; justify-content:space-between; width:100%;">
                        <span>Shipping</span>
                        <span style="font-weight:500; color:var(--gray-700);">Select state at checkout</span>
                    </div>
                    <div class="shipping-note">Shipping cost will be calculated based on your location</div>
                </div>
                <div class="summary-row">
                    <span>Tax (${TAX_RATE}%)</span>
                    <span>₦${tax.toLocaleString()}</span>
                </div>
                <div class="summary-row total">
                    <span>Total (excl. shipping)</span>
                    <span>₦${total.toLocaleString()}</span>
                </div>
                <a href="checkout.php?store=${STORE_ID}" class="checkout-btn">Proceed to Checkout →</a>
            </div>
        `;

        container.innerHTML = `
            <div class="cart-items">${itemsHtml}</div>
            ${summaryHtml}
        `;
    }

    // Initial render
    renderCart();
</script>
</body>
</html>