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

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: marketplace.php');
    exit;
}

// Fetch product details, store info, and store subscription (for Empire badge)
// IMPORTANT: added s.id AS store_id so we can link to the correct storefront
$sql = "SELECT p.*, s.id AS store_id, s.store_name, s.store_slug, s.logo_path, s.brand_color, s.description as store_description,
               (SELECT plan FROM subscriptions WHERE user_id = s.user_id AND status = 'active' AND end_date > NOW() LIMIT 1) as store_plan
        FROM products p
        INNER JOIN stores s ON p.user_id = s.user_id
        WHERE p.id = ? AND p.status = 'active' AND s.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: marketplace.php?error=Product+not+found');
    exit;
}

// Get related products (same category or same store, limit 4)
$related = [];
$relatedSql = "SELECT p.*, s.store_name, s.store_slug
               FROM products p
               INNER JOIN stores s ON p.user_id = s.user_id
               WHERE p.status = 'active' AND s.status = 'active'
                 AND p.id != ? AND (p.category = ? OR p.user_id = ?)
               LIMIT 4";
$stmt = $conn->prepare($relatedSql);
$stmt->bind_param("isi", $product_id, $product['category'], $product['user_id']);
$stmt->execute();
$relatedResult = $stmt->get_result();
while ($row = $relatedResult->fetch_assoc()) {
    $related[] = $row;
}
$stmt->close();
$conn->close();

// Calculate discount for display (if any)
$originalPrice = $product['price'] * 1.3;
$discount = round((1 - $product['price'] / $originalPrice) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/adsense_head.php'; ?>
    <title><?= htmlspecialchars($product['name']) ?> - RD Vendora</title>
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
            gap: 20px;
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
            gap: 6px;
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

        /* ── BACK LINK ── */
        .back-link {
            margin: 1rem 20px 0;
        }
        .back-link a {
            color: var(--btn-bg);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .back-link a:hover { text-decoration: underline; }

        /* ── PRODUCT DETAIL CONTAINER ── */
        .product-detail-container {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 20px;
        }
        .product-detail-grid {
            display: flex;
            gap: 2.5rem;
            background: var(--card-bg);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.03);
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .product-gallery {
            flex: 1;
            min-width: 280px;
        }
        .product-gallery img {
            width: 100%;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            object-fit: cover;
            aspect-ratio: 1/1;
            background: var(--body-bg);
        }
        .product-info-details {
            flex: 1;
            min-width: 280px;
            display: flex;
            flex-direction: column;
        }
        .store-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: var(--sidebar-bg);
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            margin-bottom: 0.8rem;
            color: var(--text-primary);
            font-size: 0.8rem;
            align-self: flex-start;
        }
        .empire-badge {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 0.3rem;
        }
        .store-name-large {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .store-name-large img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--btn-bg);
            padding: 2px;
        }
        .store-name-large a {
            font-weight: 600;
            color: var(--btn-bg);
            text-decoration: none;
        }
        .store-name-large a:hover { color: var(--btn-bg-dark); }
        .product-title {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0.5rem 0;
            color: var(--text-primary);
        }
        .price-block {
            margin: 0.8rem 0;
        }
        .current-price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--btn-bg);
        }
        .old-price {
            font-size: 1rem;
            color: #94a3b8;
            text-decoration: line-through;
            margin-left: 0.8rem;
        }
        .discount-badge {
            background: #fee2e2;
            color: #dc2626;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            margin-left: 0.8rem;
        }
        .rating {
            display: flex;
            align-items: center;
            gap: 0.2rem;
            margin: 0.5rem 0;
            color: #fbbf24;
        }
        .rating span {
            color: var(--sidebar-text);
            margin-left: 0.3rem;
        }
        .description {
            margin: 1rem 0;
            color: var(--text-primary);
            line-height: 1.7;
            flex: 1;
        }
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .quantity-btn {
            background: var(--sidebar-bg);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            color: var(--text-primary);
        }
        .quantity-btn:hover {
            background: var(--btn-bg);
            color: var(--btn-text);
        }
        .quantity-input {
            width: 60px;
            text-align: center;
            font-size: 1rem;
            padding: 0.4rem;
            border: 1px solid var(--sidebar-bg);
            border-radius: 12px;
            background: var(--body-bg);
            color: var(--text-primary);
        }
        .add-to-cart-btn {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(39,168,90,0.2);
            align-self: flex-start;
        }
        .add-to-cart-btn:hover {
            background: var(--btn-bg-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(39,168,90,0.3);
        }

        /* ── RELATED PRODUCTS ── */
        .related-section {
            margin-top: 2rem;
        }
        .related-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .related-card {
            background: var(--card-bg);
            border-radius: 1rem;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.03);
        }
        .related-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .related-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            background: var(--body-bg);
        }
        .related-info {
            padding: 0.8rem;
        }
        .related-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .related-price {
            font-weight: 700;
            color: var(--btn-bg);
        }
        .related-card a {
            text-decoration: none;
            color: inherit;
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
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 20px;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px 20px 0;
        }
        .social-links { display: flex; gap: 14px; }
        .social-links a {
            color: rgba(255,255,255,.6);
            font-size: 18px;
            transition: color .2s;
            text-decoration: none;
        }
        .social-links a:hover { color: #fff; }

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
            font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            animation: slideUp 0.3s ease;
        }
        .toast.error { background: #ef4444; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            header { flex-wrap: wrap; gap: 10px; }
            .search-bar { order: 3; flex-basis: 100%; max-width: 100%; margin: 0; }
            .header-actions { gap: 12px; }
            .product-detail-container { padding: 0 16px; }
            .product-detail-grid { padding: 1.2rem; flex-direction: column; }
            .product-title { font-size: 1.4rem; }
            .current-price { font-size: 1.6rem; }
            .related-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .back-link { margin: 0.8rem 16px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .related-grid { gap: 0.75rem; }
            .related-info { padding: 0.5rem; }
            .related-name { font-size: 0.75rem; }
            .related-price { font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<!-- TOP STRIP -->
<div class="top-strip">🚚 Free delivery on orders above ₦10,000 &nbsp;|&nbsp; ✅ 100% Genuine Products &nbsp;|&nbsp; 🔄 Easy Returns</div>

<!-- HEADER -->
<header>
    <a href="marketplace.php" class="logo"><i class="fas fa-store"></i> RD<span>Vendora</span></a>
    <div class="search-bar">
        <form method="get" action="marketplace.php" style="display:flex; flex:1; width:100%;">
            <input type="text" name="q" placeholder="Search products, brands and categories…" />
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="header-actions">
        <a href="marketplaceaddtocart.php">
            <div class="cart-badge">
                <i class="fas fa-shopping-cart"></i>
                <span class="badge" id="cartCount">0</span>
            </div>
            <span>Cart</span>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php">
                <i class="fas fa-user-circle"></i>
                <span>Account</span>
            </a>
        <?php else: ?>
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="back-link">
    <a href="marketplace.php"><i class="fas fa-arrow-left"></i> Back to Marketplace</a>
</div>

<div class="product-detail-container">
    <div class="product-detail-grid">
        <!-- Image Gallery -->
        <div class="product-gallery">
            <img src="<?= htmlspecialchars($product['image'] ?? 'https://placehold.co/600x600?text=No+Image') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        </div>

        <!-- Product Info -->
        <div class="product-info-details">
            <div class="store-badge">
                <i class="fas fa-store"></i> Official Store
                <?php if ($product['store_plan'] === 'Empire'): ?>
                    <span class="empire-badge"><i class="fas fa-crown"></i> Empire</span>
                <?php endif; ?>
            </div>
            <div class="store-name-large">
                <?php if (!empty($product['logo_path'])): ?>
                    <img src="<?= htmlspecialchars($product['logo_path']) ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-store" style="font-size:1.2rem; color:var(--btn-bg);"></i>
                <?php endif; ?>
                <!-- FIXED: Now uses store_id (primary key) instead of user_id -->
                <a href="storefront.php?store=<?= $product['store_id'] ?>"><?= htmlspecialchars($product['store_name']) ?></a>
            </div>
            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
            <div class="rating">
                <?php for ($i=1; $i<=5; $i++): ?>
                    <?php if ($i <= 4.5): ?><i class="fas fa-star"></i><?php elseif ($i-4.5 < 0.6): ?><i class="fas fa-star-half-alt"></i><?php else: ?><i class="far fa-star"></i><?php endif; ?>
                <?php endfor; ?>
                <span>(<?= rand(10, 200) ?> reviews)</span>
            </div>
            <div class="price-block">
                <span class="current-price">₦ <?= number_format($product['price'], 2) ?></span>
                <?php if ($originalPrice > $product['price']): ?>
                    <span class="old-price">₦ <?= number_format($originalPrice, 2) ?></span>
                    <span class="discount-badge">-<?= $discount ?>%</span>
                <?php endif; ?>
            </div>
            <div class="description">
                <p><?= nl2br(htmlspecialchars($product['description'] ?? 'No description available.')) ?></p>
            </div>

            <!-- Quantity and Add to Cart -->
            <div class="quantity-selector">
                <button class="quantity-btn" id="decQty">−</button>
                <input type="number" id="qtyInput" class="quantity-input" value="1" min="1" max="<?= $product['stock'] ?? 99 ?>">
                <button class="quantity-btn" id="incQty">+</button>
            </div>
            <button class="add-to-cart-btn" id="addToCartBtn">
                <i class="fas fa-shopping-cart"></i> Add to Cart
            </button>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <div class="related-section">
        <h2 class="related-title">You May Also Like</h2>
        <div class="related-grid">
            <?php foreach ($related as $rel): ?>
                <div class="related-card">
                    <a href="marketplaceviewproduct.php?id=<?= $rel['id'] ?>">
                        <img src="<?= htmlspecialchars($rel['image'] ?? 'https://placehold.co/400x400?text=No+Image') ?>" alt="<?= htmlspecialchars($rel['name']) ?>">
                        <div class="related-info">
                            <div class="related-name"><?= htmlspecialchars($rel['name']) ?></div>
                            <div class="related-price">₦ <?= number_format($rel['price'], 2) ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- FOOTER -->
<footer>
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
</footer>

<script>
    // ── CART FUNCTIONS (using same key and structure as marketplace) ──
    const CART_KEY = "greenshop_cart";

    function getCart() {
        const c = localStorage.getItem(CART_KEY);
        return c ? JSON.parse(c) : [];
    }

    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        updateCartCount();
    }

    function updateCartCount() {
        const cart = getCart();
        const total = cart.reduce((s, i) => s + i.quantity, 0);
        const el = document.getElementById('cartCount');
        if (el) el.innerText = total;
    }

    function showToast(msg, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast' + (type === 'error' ? ' error' : '');
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${msg}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // ── QUANTITY CONTROLS ──
    const qtyInput = document.getElementById('qtyInput');
    const decBtn = document.getElementById('decQty');
    const incBtn = document.getElementById('incQty');
    const addBtn = document.getElementById('addToCartBtn');

    decBtn.addEventListener('click', () => {
        let val = parseInt(qtyInput.value);
        if (val > 1) qtyInput.value = val - 1;
    });
    incBtn.addEventListener('click', () => {
        let val = parseInt(qtyInput.value);
        let max = parseInt(qtyInput.getAttribute('max')) || 99;
        if (val < max) qtyInput.value = val + 1;
    });

    // ── ADD TO CART ──
    addBtn.addEventListener('click', () => {
        const quantity = parseInt(qtyInput.value);
        const productId = <?= $product['id'] ?>;
        const storeId = <?= $product['store_id'] ?>;
        const storeName = <?= json_encode($product['store_name']) ?>;
        const name = <?= json_encode($product['name']) ?>;
        const price = <?= $product['price'] ?>;
        const image = <?= json_encode($product['image'] ?? 'https://placehold.co/400x400') ?>;

        let cart = getCart();
        const existing = cart.find(item => item.product_id === productId && item.store_id === storeId);
        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({
                product_id: productId,
                store_id: storeId,
                store_name: storeName,
                name: name,
                price: parseFloat(price),
                image: image,
                quantity: quantity
            });
        }
        saveCart(cart);
        showToast(`✅ ${quantity} × ${name} added to cart`);
    });

    // ── INIT ──
    document.addEventListener('DOMContentLoaded', updateCartCount);
</script>
</body>
</html>