<?php
session_start();
require_once 'includes/connection.php';

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/adsense_head.php'; ?>
    <title>Your Cart - RD Vendora</title>
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
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
        }
        /* ── TOP STRIP ── */
        .top-strip {
            background: var(--btn-bg-dark);
            color: var(--btn-text);
            font-size: 12px;
            text-align: center;
            padding: 6px 16px;
            letter-spacing: .5px;
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
            font-size: 26px;
            font-weight: 800;
            color: var(--btn-text);
            white-space: nowrap;
            letter-spacing: -1px;
            flex: 0 0 auto;
            text-decoration: none;
            display: flex;
            align-items: center;
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
        }
        .logo span { color: #b8f5d0; }
        .logo i { color: var(--btn-text); }
        .search-bar {
            flex: 0 1 auto;
            margin: 0 auto;
            max-width: 640px;
            width: 100%;
            display: flex;
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.15);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .search-bar:focus-within {
            border-color: rgba(255,255,255,0.6);
            box-shadow: 0 0 0 4px rgba(255,255,255,0.1);
        }
        .search-bar input {
            flex: 1;
            padding: 10px 18px;
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
            padding: 0 20px;
            color: var(--btn-text);
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .search-bar button:hover { background: var(--btn-bg-darker); }

        .header-actions {
            display: flex;
            gap: 18px;
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

        /* ── CART CONTAINER ── */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .cart-container { margin: 2rem 0; display: grid; grid-template-columns: 1fr 320px; gap: 2rem; }
        .cart-items { background: var(--card-bg); border-radius: 1.5rem; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--sidebar-bg);
        }
        .cart-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); }
        .clear-cart {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .store-group {
            margin-bottom: 2rem;
            border: 1px solid var(--sidebar-bg);
            border-radius: 1rem;
            overflow: hidden;
        }
        .store-header {
            background: var(--sidebar-bg);
            padding: 0.8rem 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            color: var(--text-primary);
        }
        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto auto auto;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.2rem;
            border-bottom: 1px solid var(--sidebar-bg);
        }
        .cart-item:last-child { border-bottom: none; }
        .item-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 0.75rem;
            background: var(--body-bg);
        }
        .item-details h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.2rem;
        }
        .item-price {
            color: var(--btn-bg);
            font-weight: 700;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--sidebar-bg);
            padding: 0.3rem 0.6rem;
            border-radius: 2rem;
        }
        .quantity-control button {
            background: none;
            border: none;
            font-weight: 700;
            cursor: pointer;
            padding: 0 0.3rem;
            font-size: 1rem;
            color: var(--text-primary);
        }
        .quantity-control span {
            min-width: 30px;
            text-align: center;
            color: var(--text-primary);
        }
        .remove-item {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 1rem;
        }

        .cart-summary {
            background: var(--card-bg);
            border-radius: 1.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            position: sticky;
            top: 90px;
        }
        .cart-summary h3 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--sidebar-bg);
            color: var(--text-primary);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1rem;
            color: var(--text-primary);
        }
        .summary-total {
            font-size: 1.3rem;
            font-weight: 800;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--sidebar-bg);
        }
        .checkout-btn {
            background: var(--btn-bg);
            color: var(--btn-text);
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
            transition: background 0.2s;
        }
        .checkout-btn:hover { background: var(--btn-bg-dark); }

        .empty-cart {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 1.5rem;
        }
        .empty-cart i { color: var(--sidebar-text); }
        .empty-cart p { color: var(--text-primary); margin: 1rem 0; }
        .continue-shopping {
            display: inline-block;
            margin-top: 1rem;
            background: var(--btn-bg);
            color: var(--btn-text);
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
        }
        .continue-shopping:hover { background: var(--btn-bg-dark); }

        /* ── TOAST ── */
        .toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            z-index: 1000;
            animation: slideUp 0.3s ease forwards;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── FOOTER ── */
        footer {
            background: var(--btn-bg-dark);
            color: rgba(255,255,255,.85);
            padding: 40px 20px 20px;
            margin-top: 30px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 7px;
            transition: color .2s;
        }
        .footer-col a:hover { color: #fff; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,.15);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .social-links { display: flex; gap: 12px; }
        .social-links a {
            color: rgba(255,255,255,.7);
            font-size: 18px;
            transition: color .2s;
            text-decoration: none;
        }
        .social-links a:hover { color: #fff; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .cart-container { grid-template-columns: 1fr; }
            .cart-item {
                grid-template-columns: 60px 1fr;
                gap: 0.8rem;
            }
            .cart-item .quantity-control,
            .cart-item .remove-item {
                grid-column: 2;
                justify-self: start;
                margin-top: 0.5rem;
            }
            .container { padding: 0 1rem; }
            header { flex-wrap: wrap; gap: 10px; }
            .search-bar { order: 3; flex-basis: 100%; max-width: 100%; margin: 0; }
            .header-actions { gap: 12px; }
            .cart-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
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

<!-- CART CONTENT -->
<div class="container">
    <div class="cart-container">
        <div class="cart-items" id="cartItems">
            <div class="empty-cart">
                <i class="fas fa-shopping-cart" style="font-size:3rem;"></i>
                <p>Your cart is empty.</p>
                <a href="marketplace" class="continue-shopping">Continue Shopping</a>
            </div>
        </div>
        <div class="cart-summary" id="cartSummary" style="display:none;">
            <h3>Order Summary</h3>
            <div id="summaryDetails"></div>
            <button class="checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="container">
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
/* ── CART FUNCTIONS ── */
const CART_KEY = "greenshop_cart";

function getCart() {
    const c = localStorage.getItem(CART_KEY);
    return c ? JSON.parse(c) : [];
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartCountDisplay();
    renderCart();
}

function updateCartCountDisplay() {
    const cart = getCart();
    const total = cart.reduce((s, i) => s + i.quantity, 0);
    const span = document.getElementById('cartCount');
    if (span) span.innerText = total;
}

function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.backgroundColor = type === 'success' ? '#10b981' : '#ef4444';
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
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
    const cartItemsDiv = document.getElementById('cartItems');
    const cartSummaryDiv = document.getElementById('cartSummary');

    if (cart.length === 0) {
        cartItemsDiv.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart" style="font-size:3rem;"></i>
                <p>Your cart is empty.</p>
                <a href="marketplace" class="continue-shopping">Continue Shopping</a>
            </div>
        `;
        cartSummaryDiv.style.display = 'none';
        return;
    }
    cartSummaryDiv.style.display = 'block';

    // Group by store
    const grouped = {};
    cart.forEach(item => {
        const storeId = item.store_id || 0;
        if (!grouped[storeId]) {
            grouped[storeId] = {
                store_id: storeId,
                store_name: item.store_name || `Store ${storeId}`,
                items: []
            };
        }
        grouped[storeId].items.push(item);
    });

    let html = `
        <div class="cart-header">
            <h2>Your Cart (${cart.reduce((s,i) => s + i.quantity, 0)} items)</h2>
            <button class="clear-cart" id="clearCartBtn"><i class="fas fa-trash-alt"></i> Clear Cart</button>
        </div>
    `;

    for (const storeId in grouped) {
        const store = grouped[storeId];
        html += `<div class="store-group">
            <div class="store-header"><i class="fas fa-store"></i> ${escapeHtml(store.store_name)}</div>`;
        store.items.forEach(item => {
            html += `
                <div class="cart-item" data-product-id="${item.product_id}" data-store-id="${item.store_id}">
                    <img src="${escapeHtml(item.image)}" class="item-image" onerror="this.src='https://placehold.co/400x400?text=No+Image'">
                    <div class="item-details">
                        <h4>${escapeHtml(item.name)}</h4>
                        <div class="item-price">₦${parseFloat(item.price).toFixed(2)}</div>
                    </div>
                    <div class="quantity-control">
                        <button class="qty-decr">-</button>
                        <span>${item.quantity}</span>
                        <button class="qty-incr">+</button>
                    </div>
                    <button class="remove-item"><i class="fas fa-trash"></i></button>
                </div>
            `;
        });
        html += `</div>`;
    }

    cartItemsDiv.innerHTML = html;

    // Attach event listeners
    document.querySelectorAll('.qty-decr').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ci = this.closest('.cart-item');
            const pid = parseInt(ci.dataset.productId);
            const sid = parseInt(ci.dataset.storeId);
            updateQuantity(sid, pid, -1);
        });
    });

    document.querySelectorAll('.qty-incr').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ci = this.closest('.cart-item');
            const pid = parseInt(ci.dataset.productId);
            const sid = parseInt(ci.dataset.storeId);
            updateQuantity(sid, pid, 1);
        });
    });

    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ci = this.closest('.cart-item');
            const pid = parseInt(ci.dataset.productId);
            const sid = parseInt(ci.dataset.storeId);
            removeItem(sid, pid);
        });
    });

    // Clear cart
    const clearBtn = document.getElementById('clearCartBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (confirm('Clear entire cart?')) {
                localStorage.removeItem(CART_KEY);
                renderCart();
                updateCartCountDisplay();
                showToast('Cart cleared', 'success');
            }
        });
    }

    // Update summary
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    document.getElementById('summaryDetails').innerHTML = `
        <div class="summary-row"><span>Subtotal</span><span>₦${total.toFixed(2)}</span></div>
        <div class="summary-row"><span>Shipping</span><span>Calculated at checkout</span></div>
        <div class="summary-row summary-total"><span>Total</span><span>₦${total.toFixed(2)}</span></div>
    `;
}

function updateQuantity(storeId, productId, delta) {
    let cart = getCart();
    const idx = cart.findIndex(i => i.store_id === storeId && i.product_id === productId);
    if (idx !== -1) {
        const newQty = cart[idx].quantity + delta;
        if (newQty <= 0) {
            cart.splice(idx, 1);
        } else {
            cart[idx].quantity = newQty;
        }
        saveCart(cart);
        renderCart();
    }
}

function removeItem(storeId, productId) {
    let cart = getCart();
    cart = cart.filter(i => !(i.store_id === storeId && i.product_id === productId));
    saveCart(cart);
    renderCart();
    showToast('Item removed', 'success');
}

document.addEventListener('click', function(e) {
    if (e.target.id === 'checkoutBtn') {
        window.location.href='marketplacecheckout';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    renderCart();
    updateCartCountDisplay();
});
</script>
<div id="rdv-cookie-root"></div>
<script src="assets/js/rdv-public.js" defer></script>
</body>
</html>