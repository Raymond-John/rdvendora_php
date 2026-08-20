<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$resolved = rdv_resolve_public_store($conn, true);
$store = $resolved['store'];
$onSubdomain = !empty($resolved['on_subdomain']);
$onPath = !empty($resolved['on_path']);
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$store && !empty($_GET['store']) && !empty($_SESSION['user_id'])) {
    $preview = rdv_fetch_store_by_id($conn, (int) $_GET['store'], false);
    if ($preview && (int) $preview['user_id'] === (int) $_SESSION['user_id']) {
        $store = $preview;
    }
}

if (!$store) {
    rdv_store_not_found_page('Sorry, we couldn\'t find a store with this address.');
}

$storeId = (int) $store['id'];
$storeHome = rdv_store_url($store);

// Redirect legacy product-details.php?id=&store= → /{slug}/product/{id}-{name}
if (!$onPath && !$onSubdomain && rdv_store_url_mode() !== 'query' && $productId > 0) {
    $status = strtolower((string) ($store['status'] ?? ''));
    $active = (int) ($store['active'] ?? 0);
    if ($status === 'active' && $active === 1) {
        $rdvLegacyProductRedirect = true;
    }
}

// ----- Ensure missing colour columns exist (run once) -----
$schemaChecked = STORAGE_PATH . '/cache/.store_schema_checked';
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

// ----- Fetch product (belongs to this store) -----
$product = null;
if ($productId > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ? AND status = 'active'");
    $stmt->bind_param("ii", $productId, $store['user_id']);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$product) {
    die('<div style="text-align:center; padding:3rem;"><h1>Product Not Found</h1><p>The product you are looking for does not exist in this store.</p><a href="' . htmlspecialchars($storeHome, ENT_QUOTES, 'UTF-8') . '">Back to Store</a></div>');
}

if (!empty($rdvLegacyProductRedirect)) {
    $target = rdv_store_product_url($store, $product['id'], $product['name']);
    if (!headers_sent()) {
        header('Location: ' . $target, true, 301);
        exit;
    }
}

$productCanonical = rdv_store_product_url($store, $product['id'], $product['name']);

// ----- Dynamic colors -----
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
$navTextColor   = getTextColor($navColor);
$footerTextColor = getTextColor($footerBgColor);

$conn->close();

// Mock rating (replace with actual reviews later)
$rating = 4.5;
$reviewCount = rand(10, 200);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($product['name']) ?> - <?= htmlspecialchars($store['store_name']) ?></title>
    <link rel="canonical" href="<?= htmlspecialchars($productCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($productCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($product['name'] . ' - ' . $store['store_name'], ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="product">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
            --gray-100: #f9fafb; --gray-200: #e5e7eb; --gray-300: #d1d5db;
            --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563;
            --gray-700: #374151; --gray-800: #1f2937; --gray-900: #111827;
            --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem;
            --transition: all 0.3s ease;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--body-bg);
            color: var(--gray-800);
            line-height: 1.5;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; }

        /* Navbar */
        .navbar { background: var(--nav-bg); box-shadow: var(--shadow); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid var(--gray-200); }
        .nav-container { max-width: 1280px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; height: 70px; gap: 1.5rem; }
        .logo { display: flex; align-items: center; gap: 0.5rem; font-weight: 800; color: var(--nav-text); }
        .logo h4 { font-size: 1.25rem; }
        .logo-icon { width: 32px; height: 32px; background: var(--gradient-primary); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; }
        .logo img { height: 40px; width: auto; }
        .cart-link { position: relative; display: flex; align-items: center; gap: 0.5rem; background: rgba(0,0,0,0.05); padding: 0.5rem 1rem; border-radius: var(--radius-lg); color: var(--nav-text); }
        .cart-count { position: absolute; top: -8px; right: -8px; background: var(--primary); color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 999px; min-width: 20px; text-align: center; }

        /* Breadcrumb */
        .breadcrumb { margin-bottom: 24px; font-size: 0.875rem; color: var(--gray-500); }
        .breadcrumb a { color: var(--primary); }
        .breadcrumb-separator { margin: 0 8px; }

        /* Product Detail Grid */
        .product-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-bottom: 60px; }
        @media (max-width: 768px) { .product-detail-grid { grid-template-columns: 1fr; gap: 32px; } }
        .product-image { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-xl); overflow: hidden; }
        .product-image img { width: 100%; aspect-ratio: 1; object-fit: cover; }
        .product-category { display: inline-block; background: var(--primary-light); color: var(--primary); padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 12px; }
        .product-rating { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .product-rating-stars { display: flex; gap: 2px; color: #f59e0b; }
        .product-price-current { font-size: 2rem; font-weight: 800; color: var(--gray-900); }
        .product-price-original { font-size: 1rem; text-decoration: line-through; color: var(--gray-400); margin-left: 12px; }
        .stock-indicator { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
        .stock-dot { width: 10px; height: 10px; border-radius: 50%; }
        .stock-dot.stock-in { background: #10b981; }
        .stock-dot.stock-low { background: #f59e0b; }
        .stock-dot.stock-out { background: #ef4444; }
        .qty-control { display: flex; align-items: center; border: 1px solid var(--card-border); border-radius: var(--radius); overflow: hidden; }
        .qty-btn { width: 40px; height: 40px; background: var(--div-bg); border: none; font-size: 1.2rem; cursor: pointer; transition: var(--transition); }
        .qty-btn:hover { background: var(--primary-light); color: var(--primary); }
        #pqty { width: 50px; text-align: center; border: none; outline: none; font-size: 1rem; font-weight: 500; background: transparent; }
        .btn-primary { background: var(--button-bg); color: var(--button-text); padding: 0.75rem 1.5rem; border-radius: var(--radius-lg); font-weight: 600; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary:hover { filter: brightness(0.9); transform: translateY(-2px); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-outline-custom { background: transparent; border: 1px solid var(--card-border); color: var(--gray-700); padding: 0.75rem 1.5rem; border-radius: var(--radius-lg); font-weight: 600; transition: var(--transition); display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline-custom:hover { border-color: var(--primary); color: var(--primary); }

        /* Footer */
        .footer { background: var(--footer-bg); color: var(--footer-text); padding: 2.5rem 1.5rem 1.5rem; margin-top: 3rem; text-align: center; }
        .footer p { opacity: 0.8; font-size: 0.85rem; }

        /* Toast */
        .toast { position: fixed; bottom: 20px; right: 20px; background: #059669; color: white; padding: 12px 20px; border-radius: 12px; z-index: 10000; font-size: 0.875rem; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= htmlspecialchars($storeHome, ENT_QUOTES, 'UTF-8') ?>" class="logo">
            <?php if (!empty($store['logo_path'])): ?>
                <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?> logo">
            <?php else: ?>
                <div class="logo-icon">🛍️</div>
            <?php endif; ?>
            <h4><?= htmlspecialchars($store['store_name']) ?></h4>
        </a>
        <div class="store-actions">
            <a href="cart?store_id=<?= $store['id'] ?>" class="cart-link">
                🛒 <span id="cartCount" class="cart-count">0</span>
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= htmlspecialchars($storeHome, ENT_QUOTES, 'UTF-8') ?>">Store</a>
        <span class="breadcrumb-separator">/</span>
        <a href="<?= htmlspecialchars(rdv_store_category_url($store, $product['category']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($product['category']) ?></a>
        <span class="breadcrumb-separator">/</span>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>

    <div class="product-detail-grid">
        <div class="product-image">
            <img src="<?= htmlspecialchars($product['image'] ?? 'https://placehold.co/600x600?text=No+Image') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        </div>
        <div>
            <div class="product-category"><?= htmlspecialchars($product['category']) ?></div>
            <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 12px; color: var(--gray-900);"><?= htmlspecialchars($product['name']) ?></h1>
            <div class="product-rating">
                <div class="product-rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $i <= floor($rating) ? '#f59e0b' : 'none' ?>" stroke="#f59e0b" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?php endfor; ?>
                </div>
                <span style="color: var(--gray-500); font-size: 0.875rem;"><?= $rating ?> (<?= $reviewCount ?> reviews)</span>
            </div>
            <div style="margin-bottom: 20px;">
                <span class="product-price-current">₦ <?= number_format($product['price'], 2) ?></span>
                <?php if (!empty($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                    <span class="product-price-original">₦ <?= number_format($product['compare_price'], 2) ?></span>
                <?php endif; ?>
            </div>
            <p style="color: var(--gray-600); line-height: 1.7; margin-bottom: 24px;"><?= nl2br(htmlspecialchars($product['description'] ?? 'No description available.')) ?></p>
            <div class="stock-indicator">
                <span class="stock-dot <?= $product['stock'] == 0 ? 'stock-out' : ($product['stock'] <= 10 ? 'stock-low' : 'stock-in') ?>"></span>
                <span style="font-weight: 500;">
                    <?php if ($product['stock'] == 0): ?>
                        Out of Stock
                    <?php elseif ($product['stock'] <= 10): ?>
                        Low Stock - <?= $product['stock'] ?> left
                    <?php else: ?>
                        In Stock (<?= $product['stock'] ?> available)
                    <?php endif; ?>
                </span>
            </div>
            <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
                <div class="qty-control">
                    <button class="qty-btn" id="qtyMinus">-</button>
                    <input type="number" id="pqty" value="1" min="1" max="<?= $product['stock'] ?>" style="width: 50px; text-align: center; border: none; outline: none; font-size: 1rem; font-weight: 500; background: transparent;">
                    <button class="qty-btn" id="qtyPlus">+</button>
                </div>
                <button class="btn-primary" id="addToCartBtn" <?= $product['stock'] == 0 ? 'disabled' : '' ?>>
                    🛒 <?= $product['stock'] == 0 ? 'Out of Stock' : 'Add to Cart' ?>
                </button>
            </div>
            <div>
                <button class="btn-outline-custom" id="wishlistBtn">
                    ❤️ Add to Wishlist
                </button>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>. All rights reserved | Developed by RD Nexa Tech</p>
</footer>

<script>
    const STORE_ID = <?= $store['id'] ?>;
    const CART_KEY = `cart_${STORE_ID}`;
    const PRODUCT = {
        id: <?= $product['id'] ?>,
        name: <?= json_encode($product['name']) ?>,
        price: <?= $product['price'] ?>,
        image: <?= json_encode($product['image'] ?? 'https://placehold.co/400x400') ?>,
        stock: <?= $product['stock'] ?>
    };

    function getCart() {
        const cart = localStorage.getItem(CART_KEY);
        return cart ? JSON.parse(cart) : [];
    }

    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        updateCartUI();
    }

    function addToCart(quantity) {
        if (PRODUCT.stock === 0) {
            showToast('Product out of stock', '#dc2626');
            return;
        }
        let cart = getCart();
        const existing = cart.find(item => item.id == PRODUCT.id);
        if (existing) {
            let newQty = existing.quantity + quantity;
            if (newQty > PRODUCT.stock) {
                showToast(`Only ${PRODUCT.stock} items available`, '#dc2626');
                newQty = PRODUCT.stock;
            }
            existing.quantity = newQty;
        } else {
            if (quantity > PRODUCT.stock) {
                showToast(`Only ${PRODUCT.stock} items available`, '#dc2626');
                return;
            }
            cart.push({ ...PRODUCT, quantity: quantity });
        }
        saveCart(cart);
        showToast(`${quantity} × ${PRODUCT.name} added to cart`, '#10b981');
    }

    function updateCartUI() {
        const cart = getCart();
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartCountSpan = document.getElementById('cartCount');
        if (cartCountSpan) cartCountSpan.innerText = totalItems;
    }

    function showToast(message, bgColor) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.backgroundColor = bgColor;
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    }

    // Quantity controls
    const qtyInput = document.getElementById('pqty');
    const qtyMinus = document.getElementById('qtyMinus');
    const qtyPlus = document.getElementById('qtyPlus');
    const addBtn = document.getElementById('addToCartBtn');

    if (qtyMinus) {
        qtyMinus.addEventListener('click', () => {
            let val = parseInt(qtyInput.value);
            if (val > 1) qtyInput.value = val - 1;
        });
    }
    if (qtyPlus) {
        qtyPlus.addEventListener('click', () => {
            let val = parseInt(qtyInput.value);
            if (val < PRODUCT.stock) qtyInput.value = val + 1;
            else showToast(`Maximum ${PRODUCT.stock} items available`, '#dc2626');
        });
    }
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            const qty = parseInt(qtyInput.value);
            if (qty >= 1 && qty <= PRODUCT.stock) addToCart(qty);
            else showToast(`Please select quantity between 1 and ${PRODUCT.stock}`, '#dc2626');
        });
    }

    // Wishlist (simple placeholder)
    document.getElementById('wishlistBtn')?.addEventListener('click', () => {
        showToast('Added to wishlist', '#10b981');
    });

    updateCartUI();
</script>
</body>
</html>