<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$resolved = rdv_resolve_public_store($conn, true);
$store = $resolved['store'];
$onSubdomain = !empty($resolved['on_subdomain']);

// Owner preview: inactive/pending store via ?store= id on main domain (seller logged in)
if (!$store && !empty($_GET['store']) && !empty($_SESSION['user_id'])) {
    $preview = rdv_fetch_store_by_id($conn, (int) $_GET['store'], false);
    if ($preview && (int) $preview['user_id'] === (int) $_SESSION['user_id']) {
        $store = $preview;
        $resolved['via'] = 'owner_preview';
    }
}

if (!$store) {
    rdv_store_not_found_page();
}

$storeId = (int) $store['id'];

// Legacy / dashboard links on main domain → store subdomain when enabled
if (!$onSubdomain && rdv_store_subdomains_enabled() && in_array($resolved['via'], ['id', 'slug', 'session', 'owner_preview'], true)) {
    $status = strtolower((string) ($store['status'] ?? ''));
    $active = (int) ($store['active'] ?? 0);
    if ($status === 'active' && $active === 1 && rdv_is_valid_store_slug($store['store_slug'] ?? '')) {
        rdv_redirect_legacy_storefront($store);
    }
}

$storeCanonical = rdv_store_url($store);

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

// ----- Check if 'featured' column exists -----
$hasFeatured = false;
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'featured'");
if ($result && $result->num_rows > 0) $hasFeatured = true;

// ----- Get products for this store (only active) -----
$orderBy = $hasFeatured ? "featured DESC, created_at DESC" : "created_at DESC";
$stmt = $conn->prepare("SELECT * FROM products WHERE user_id = ? AND status = 'active' ORDER BY $orderBy");
$stmt->bind_param("i", $store['user_id']);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ----- Get active banners -----
$banners = [];
$stmt = $conn->prepare("SELECT * FROM promo_banners WHERE user_id = ? AND status = 'active' ORDER BY order_position ASC");
$stmt->bind_param("i", $store['user_id']);
$stmt->execute();
$banners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ----- Custom typography CSS -----
$customCSS = '';
if (!empty($store['typography'])) {
    $typo = json_decode($store['typography'], true);
    if ($typo) {
        $customCSS = '<style>';
        foreach (['h1','h2','h3','h4','h5','h6','p'] as $tag) {
            if (isset($typo[$tag])) {
                $size = $typo[$tag]['size'];
                $color = $typo[$tag]['color'];
                $customCSS .= "$tag { font-size: {$size}px; color: {$color}; } ";
            }
        }
        $customCSS .= '</style>';
    }
}

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

// ----- Filters: category & search (preserve both) -----
$currentCategory = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$filteredProducts = $products;
if ($currentCategory !== 'all') {
    $filteredProducts = array_filter($filteredProducts, fn($p) => $p['category'] === $currentCategory);
}
if (!empty($search)) {
    $filteredProducts = array_filter($filteredProducts, fn($p) => stripos($p['name'], $search) !== false || stripos($p['category'], $search) !== false);
}
$filteredProducts = array_values($filteredProducts);

$categories = array_unique(array_column($products, 'category'));
$categoryIcons = [
    'electronics' => '📱', 'fashion' => '👕', 'beauty' => '💄', 'home' => '🏠',
    'sports' => '⚽', 'powerbanks' => '🔋', 'earbuds' => '🎧', 'chargers' => '⚡',
    'cases' => '📲', 'headphones' => '🎧', 'smartwatches' => '⌚', 'accessories' => '🎒'
];

// Helper to build filter URL keeping current search
function filterUrl($cat, $storeId, $search) {
    global $store;
    if (function_exists('rdv_store_filter_url') && is_array($store)) {
        return rdv_store_filter_url($store, $cat, $search);
    }
    $url = "?store=$storeId&cat=" . urlencode($cat);
    if (!empty($search)) $url .= "&q=" . urlencode($search);
    return $url;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/adsense_head.php'; ?>
    <title><?= htmlspecialchars($store['store_name']) ?> – Official Store</title>
    <link rel="canonical" href="<?= htmlspecialchars($storeCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($storeCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($store['store_name'] . ' – Official Store', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($store['store_name'] . ' – Official Store', ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== DYNAMIC STYLES ========== */
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
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db;
            --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151;
            --gray-800: #1f2937; --gray-900: #111827; --white: #ffffff;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius: 0.5rem; --radius-lg: 0.75rem; --radius-xl: 1rem;
            --transition: all 0.3s ease;
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--body-bg); color: var(--gray-900); line-height: 1.5; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; transition: var(--transition); }
        img { max-width: 100%; display: block; }

        /* Navbar */
        .navbar { background: var(--nav-bg); box-shadow: var(--shadow); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid var(--gray-200); }
        .nav-container { max-width: 1400px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; height: 70px; gap: 1.5rem; }
        .logo { display: flex; align-items: center; gap: 0.5rem; font-size: 1.4rem; font-weight: 800; white-space: nowrap; }
        .logo-icon { width: 32px; height: 32px; background: var(--gradient-primary); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; }
        .logo img { height: 40px; width: auto; }
        .search-container { flex: 1; max-width: 500px; }
        .search-form { display: flex; width: 100%; }
        .search-input { flex: 1; padding: 0.6rem 1rem; border: 1px solid var(--gray-200); border-radius: var(--radius-lg) 0 0 var(--radius-lg); font-size: 0.9rem; outline: none; background: white; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-light); }
        .search-btn { background: var(--primary); border: none; padding: 0 1rem; border-radius: 0 var(--radius-lg) var(--radius-lg) 0; color: white; cursor: pointer; transition: var(--transition); }
        .search-btn:hover { background: var(--primary-dark); }
        .hamburger { display: none; flex-direction: column; cursor: pointer; gap: 5px; z-index: 1001; }
        .hamburger span { width: 25px; height: 3px; background: var(--nav-text); border-radius: 3px; transition: var(--transition); }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(7px, -7px); }
        
        .cart-link {
            position: relative; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;
            background: rgba(0,0,0,0.05); padding: 0.5rem 1rem; border-radius: var(--radius-lg);
            transition: var(--transition); color: var(--nav-text);
        }
        .cart-link:hover { background: rgba(0,0,0,0.1); }
        .cart-count {
            position: absolute; top: -8px; right: -8px; background: var(--primary); color: white;
            font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 999px;
            min-width: 20px; text-align: center;
        }

        /* Main layout */
        .main-wrapper { max-width: 1400px; margin: 1.5rem auto 0; padding: 0 1.5rem; display: flex; gap: 1.5rem; position: relative; }
        .content-area { flex: 1; min-width: 0; }
        .side-navbar { background: var(--div-bg); border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 1rem 0; border: 1px solid var(--div-border); width: 100%; }
        .side-navbar-title { font-weight: 700; padding: 0 1rem 0.75rem; border-bottom: 1px solid var(--div-border); color: var(--gray-800); }
        .side-navbar-list { list-style: none; margin-top: 0.5rem; }
        .side-navbar-list li a { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem; font-size: 0.9rem; color: var(--gray-700); }
        .side-navbar-list li a:hover { background: var(--primary-light); color: var(--primary); padding-left: 1.25rem; }
        .active-filter { background: var(--primary-light); color: var(--primary); font-weight: bold; }
        @media (min-width: 769px) { .side-navbar { position: sticky; top: 90px; width: 240px; flex-shrink: 0; align-self: flex-start; } .hamburger { display: none; } }
        @media (max-width: 768px) {
            .nav-container { flex-wrap: wrap; height: auto; padding: 0.75rem 1rem; gap: 0.8rem; }
            .search-container { max-width: 100%; width: 100%; }
            .hamburger { display: flex; }
            .main-wrapper { flex-direction: column; gap: 1rem; }
            .side-navbar { position: fixed; top: 0; left: -280px; width: 260px; height: 100%; z-index: 1002; background: white; box-shadow: var(--shadow-lg); border-radius: 0; transition: left 0.3s ease; overflow-y: auto; padding-top: 4rem; }
            .side-navbar.open { left: 0; }
            .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; display: none; }
            .overlay.active { display: block; }
        }

        /* Promo Carousel */
        .promo-carousel { margin-bottom: 2rem; position: relative; }
        .carousel-container { position: relative; overflow: hidden; border-radius: var(--radius-xl); }
        .carousel-slide { display: none; }
        .carousel-slide.active { display: block; }
        .carousel-prev, .carousel-next {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.5); color: white; border: none;
            padding: 0.5rem 1rem; cursor: pointer; border-radius: var(--radius);
            font-size: 1.5rem; z-index: 10;
        }
        .carousel-prev { left: 10px; }
        .carousel-next { right: 10px; }
        .carousel-dots { text-align: center; margin-top: 10px; }
        .dot { display: inline-block; width: 10px; height: 10px; background: #ccc; border-radius: 50%; margin: 0 4px; cursor: pointer; }
        .dot.active { background: var(--primary); }
        
        .promo-banner { background: var(--gradient-primary); border-radius: var(--radius-xl); color: white; overflow: hidden; }
        .promo-banner .container { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; padding: 2rem 1.5rem; }
        .promo-content { flex: 1; min-width: 200px; }
        .promo-label { background: rgba(255,255,255,0.2); display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; margin-bottom: 0.75rem; }
        .promo-title { font-size: 1.75rem; font-weight: 800; margin-bottom: 0.5rem; }
        .promo-description { opacity: 0.9; margin-bottom: 1rem; font-size: 1rem; line-height: 1.5; }
        .promo-button { background: white; color: var(--primary-dark); padding: 0.75rem 2rem; border-radius: var(--radius-lg); font-weight: 700; white-space: nowrap; display: inline-block; transition: var(--transition); }
        .promo-button:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .promo-image { flex: 0 0 200px; max-width: 200px; }
        .promo-image img { width: 100%; height: auto; border-radius: var(--radius); box-shadow: var(--shadow-md); }
        @media (max-width: 640px) {
            .promo-banner .container { flex-direction: column; text-align: center; }
            .promo-image { max-width: 150px; margin-top: 1rem; }
        }

        /* Category Grid */
        .section { margin-bottom: 2rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem; }
        .section-title { font-size: 1.5rem; font-weight: 700; color: var(--gray-800); }
        .view-all { color: var(--primary); font-weight: 600; }
        .category-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; }
        @media (min-width:480px) { .category-grid { grid-template-columns: repeat(4,1fr); } }
        @media (min-width:768px) { .category-grid { grid-template-columns: repeat(6,1fr); } }
        .category-card {
            background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-lg);
            padding: 1rem 0.5rem; text-align: center; box-shadow: var(--shadow); cursor: pointer;
            transition: var(--transition);
        }
        .category-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .category-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .category-name { font-weight: 500; }

        /* Product Grid */
        .product-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 1rem; }
        @media (min-width:640px) { .product-grid { grid-template-columns: repeat(3,1fr); gap: 1.5rem; } }
        @media (min-width:1024px) { .product-grid { grid-template-columns: repeat(4,1fr); } }
        .product-card {
            background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-xl);
            overflow: hidden; box-shadow: var(--shadow); transition: var(--transition);
            display: flex; flex-direction: column; height: 100%;
        }
        .product-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .product-image { position: relative; aspect-ratio: 3/4; overflow: hidden; background: var(--gray-100); }
        .product-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .product-card:hover .product-image img { transform: scale(1.03); }
        .product-badges { position: absolute; top: 0.75rem; left: 0.75rem; display: flex; flex-direction: column; gap: 0.4rem; z-index: 2; }
        .badge { padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-featured { background: var(--primary); color: white; }
        .badge-stock { background: #059669; color: white; }
        .product-info { padding: 0.75rem; flex: 1; display: flex; flex-direction: column; }
        .product-brand { font-size: 0.75rem; color: var(--primary); font-weight: 700; text-transform: uppercase; }
        .product-name {
            font-size: 0.9rem; font-weight: 700; margin: 0.25rem 0 0.5rem;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            color: #000000 !important;
        }
        .product-specs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
        .spec-tag { font-size: 0.65rem; background: var(--div-bg); color: var(--gray-600); padding: 0.2rem 0.5rem; border-radius: var(--radius); }
        .product-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; flex-wrap: wrap; gap: 0.5rem; }
        .product-price { font-size: 1rem; font-weight: 800; color: var(--gray-900); }
        .add-to-cart-btn {
            background: var(--button-bg); color: var(--button-text); border: none;
            padding: 0.4rem 0.9rem; border-radius: var(--radius); font-size: 0.8rem;
            font-weight: 600; cursor: pointer; transition: var(--transition);
        }
        .add-to-cart-btn:hover { filter: brightness(0.9); transform: scale(1.02); }

        .no-results { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: var(--radius-lg); padding: 2rem; text-align: center; }

        /* Footer */
        .footer { background: var(--footer-bg); color: var(--footer-text); padding: 2.5rem 1.5rem 1.5rem; margin-top: 3rem; }
        .footer a { color: var(--footer-text); opacity: 0.8; }
        .footer a:hover { opacity: 1; }
        .footer-content { max-width: 1400px; margin: 0 auto; display: grid; gap: 2rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .footer-brand { font-size: 1.4rem; font-weight: 800; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.5rem; }
        .footer-bottom { text-align: center; padding-top: 1.5rem; margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.8rem; }

        .cookie-banner {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: var(--card-bg); border-top: 1px solid var(--card-border);
            padding: 1rem; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem;
            transform: translateY(100%); transition: transform 0.3s ease; z-index: 1000;
        }
        .cookie-banner.show { transform: translateY(0); }
        .cookie-btn { padding: 0.5rem 1rem; border-radius: var(--radius); border: none; cursor: pointer; }
        .cookie-accept { background: var(--button-bg); color: var(--button-text); }

        <?= $customCSS ?>
    </style>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
        <a href="<?= htmlspecialchars($storeCanonical, ENT_QUOTES, 'UTF-8') ?>" class="logo">
            <?php if (!empty($store['logo_path'])): ?>
                <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?> logo">
            <?php else: ?>
                <div class="logo-icon">🏪</div>
            <?php endif; ?>
            <h4><?= htmlspecialchars($store['store_name']) ?></h4>
        </a>
        <div class="search-container">
            <form class="search-form" method="get" action="<?= htmlspecialchars($onSubdomain ? '/' : 'storefront.php', ENT_QUOTES, 'UTF-8') ?>">
                <?php if (!$onSubdomain): ?>
                <input type="hidden" name="store" value="<?= (int) $store['id'] ?>">
                <?php endif; ?>
                <?php if ($currentCategory !== 'all'): ?>
                <input type="hidden" name="cat" value="<?= htmlspecialchars($currentCategory, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <input class="search-input" type="text" name="q" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                <button class="search-btn" type="submit">🔍</button>
            </form>
        </div>
        <a href="cart.php?store_id=<?= $store['id'] ?>" class="cart-link">
            🛒 <span id="cartCount" class="cart-count">0</span>
        </a>
    </div>
</nav>

<div class="overlay" id="overlay"></div>

<div class="main-wrapper">
    <aside class="side-navbar" id="sideNavbar">
        <div class="side-navbar-title">📌 Shop by department</div>
        <ul class="side-navbar-list">
            <li><a href="<?= filterUrl('all', $store['id'], $search) ?>" class="<?= $currentCategory=='all' ? 'active-filter' : '' ?>">🏠 All Products</a></li>
            <?php foreach ($categories as $cat): ?>
                <?php $icon = $categoryIcons[$cat] ?? '📦'; ?>
                <li><a href="<?= filterUrl($cat, $store['id'], $search) ?>" class="<?= $currentCategory==$cat ? 'active-filter' : '' ?>"><?= $icon ?> <?= ucfirst($cat) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <div class="content-area">
        <!-- Promo Carousel -->
        <?php if (!empty($banners)): ?>
        <div class="promo-carousel">
            <div class="carousel-container">
                <?php foreach ($banners as $index => $banner): ?>
                <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                    <div class="promo-banner">
                        <div class="container">
                            <div class="promo-content">
                                <div class="promo-label">🔥 Limited Offer</div>
                                <div class="promo-title"><?= htmlspecialchars($banner['title'] ?? 'Exclusive Deal!') ?></div>
                                <div class="promo-description">
                                    <?= nl2br(htmlspecialchars($banner['description'] ?? 'Shop now and save big with our exclusive offers!')) ?>
                                </div>
                                <?php if (!empty($banner['link'])): ?>
                                    <a href="<?= htmlspecialchars($banner['link']) ?>" class="promo-button">Shop Now →</a>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($banner['image'])): ?>
                            <div class="promo-image">
                                <img src="<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title'] ?? 'Promotion') ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($banners) > 1): ?>
            <button class="carousel-prev">❮</button>
            <button class="carousel-next">❯</button>
            <div class="carousel-dots">
                <?php foreach ($banners as $index => $banner): ?>
                <span class="dot <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>"></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Category Grid -->
        <section class="section">
            <div class="section-header">
                <h2 class="section-title">📂 Shop by Category</h2>
                <a href="<?= filterUrl('all', $store['id'], $search) ?>" class="view-all">All Categories →</a>
            </div>
            <div class="category-grid">
                <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                    <?php $icon = $categoryIcons[$cat] ?? '📦'; ?>
                    <a href="<?= filterUrl($cat, $store['id'], $search) ?>" class="category-card">
                        <div class="category-icon"><?= $icon ?></div>
                        <div class="category-name"><?= ucfirst($cat) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Products Section -->
        <?php if ($currentCategory !== 'all' || !empty($search)): ?>
            <h2 class="section-title" style="margin-bottom:1rem;">
                <?= empty($search) ? ucfirst($currentCategory) . ' Products' : "Search results for “" . htmlspecialchars($search) . "”" ?>
            </h2>
        <?php endif; ?>

        <?php if (count($filteredProducts) > 0): ?>
            <div class="product-grid">
                <?php foreach ($filteredProducts as $product): 
                    // Mock rating (replace with actual reviews later)
                    $rating = 4 + round(rand(0, 10) / 10, 1);
                ?>
                    <div class="product-card" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>" data-image="<?= htmlspecialchars($product['image'] ?? 'https://placehold.co/400x400') ?>">
                        <a href="<?= htmlspecialchars(rdv_store_product_url($store, $product['id'], $product['name']), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="product-image">
                                <div class="product-badges">
                                    <span class="badge badge-stock">In Stock</span>
                                    <?php if ($hasFeatured && ($product['featured'] ?? false)): ?>
                                        <span class="badge badge-featured">Featured</span>
                                    <?php endif; ?>
                                </div>
                                <img src="<?= htmlspecialchars($product['image'] ?? 'https://placehold.co/400x400?text=No+Image') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            </div>
                        </a>
                        <div class="product-info">
                            <div class="product-brand"><?= htmlspecialchars($product['category']) ?></div>
                            <a href="<?= htmlspecialchars(rdv_store_product_url($store, $product['id'], $product['name']), ENT_QUOTES, 'UTF-8') ?>">
                                <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                            </a>
                            <div class="product-specs">
                                <span class="spec-tag">⭐ <?= number_format($rating, 1) ?></span>
                                <?php if ($product['stock'] > 0): ?><span class="spec-tag">📦 Stock: <?= $product['stock'] ?></span><?php endif; ?>
                            </div>
                            <div class="product-footer">
                                <div class="product-price">₦ <?= number_format($product['price']) ?></div>
                                <button class="add-to-cart-btn" onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', <?= $product['price'] ?>, '<?= addslashes($product['image'] ?? '') ?>')">Add to Cart</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">😞 No products found. Try a different category or search term.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Cookie Banner -->
<div id="cookieBanner" class="cookie-banner">
    <div class="cookie-text">🍪 We use cookies to improve your experience. By using our site, you accept our <a href="#">cookie policy</a>.</div>
    <div class="cookie-buttons">
        <button id="acceptCookies" class="cookie-btn cookie-accept">Accept</button>
        <button id="declineCookies" class="cookie-btn">Decline</button>
    </div>
</div>

<footer class="footer">
    <div class="footer-content">
        <div>
            <h4 class="footer-brand"><?= htmlspecialchars($store['store_name']) ?></h4>
            <p class="footer-desc"><?= htmlspecialchars($store['description'] ?? 'Your trusted equipment store.') ?></p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="<?= htmlspecialchars($storeCanonical, ENT_QUOTES, 'UTF-8') ?>">Home</a></li>
                <li><a href="<?= htmlspecialchars(rdv_store_filter_url($store, 'all', ''), ENT_QUOTES, 'UTF-8') ?>">All Products</a></li>
                <li><a href="<?= htmlspecialchars(rtrim(APP_URL, '/') . '/marketplace.php', ENT_QUOTES, 'UTF-8') ?>">← Back to Marketplace</a></li>
            </ul>
        </div>
        <div>
            <h4>Contact</h4>
            <ul class="footer-links">
                <li>WhatsApp: +234 810 893 0194</li>
                <li>Email: rdvendoracompany@gmail.com</li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>. All rights reserved | Developed by RD Nexa Tech</p>
    </div>
</footer>

<script>
    const STORE_ID = <?= $store['id'] ?>;
    const CART_KEY = `cart_${STORE_ID}`;

    function getCart() { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
    function saveCart(cart) { localStorage.setItem(CART_KEY, JSON.stringify(cart)); updateCartUI(); }
    
    function addToCart(id, name, price, image) {
        let cart = getCart();
        let existing = cart.find(item => item.id == id);
        if (existing) existing.quantity += 1;
        else cart.push({ id, name, price, image, quantity: 1 });
        saveCart(cart);
        showToast('✓ Item added to cart', '#10b981');
    }
    
    function updateCartUI() {
        const cart = getCart();
        const total = cart.reduce((sum, i) => sum + i.quantity, 0);
        const cartCountSpan = document.getElementById('cartCount');
        if (cartCountSpan) cartCountSpan.innerText = total;
    }
    
    function showToast(msg, bg) {
        let toast = document.createElement('div');
        toast.style.cssText = `position:fixed; bottom:20px; right:20px; background:${bg}; color:white; padding:12px 20px; border-radius:40px; z-index:10000; font-size:0.9rem; box-shadow:0 2px 8px rgba(0,0,0,0.2);`;
        toast.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    }

    // Mobile menu
    const hamburger = document.getElementById('hamburger');
    const sideNavbar = document.getElementById('sideNavbar');
    const overlay = document.getElementById('overlay');
    function closeMenu() {
        sideNavbar.classList.remove('open');
        overlay.classList.remove('active');
        hamburger.classList.remove('active');
        document.body.style.overflow = '';
    }
    function openMenu() {
        sideNavbar.classList.add('open');
        overlay.classList.add('active');
        hamburger.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    if (hamburger) {
        hamburger.addEventListener('click', () => sideNavbar.classList.contains('open') ? closeMenu() : openMenu());
    }
    if (overlay) overlay.addEventListener('click', closeMenu);
    document.querySelectorAll('.side-navbar-list a').forEach(link => link.addEventListener('click', () => { if (window.innerWidth <= 768) closeMenu(); }));

    // Carousel (FIXED SYNTAX)
    let currentSlide = 0;
    const slides = document.querySelectorAll('.carousel-slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.querySelector('.carousel-prev');
    const nextBtn = document.querySelector('.carousel-next');
    
    function showSlide(index) {
        if (!slides.length) return;
        slides.forEach((s, i) => s.classList.toggle('active', i === index));
        dots.forEach((d, i) => d.classList.toggle('active', i === index));
        currentSlide = index;
    }
    
    if (prevBtn && nextBtn && slides.length > 1) {
        prevBtn.addEventListener('click', () => showSlide((currentSlide - 1 + slides.length) % slides.length));
        nextBtn.addEventListener('click', () => showSlide((currentSlide + 1) % slides.length));
        dots.forEach(dot => dot.addEventListener('click', (e) => showSlide(parseInt(e.target.getAttribute('data-index')))));
    }

    // Cookie consent
    const cookieBanner = document.getElementById('cookieBanner');
    if (!localStorage.getItem('cookieConsent')) setTimeout(() => cookieBanner.classList.add('show'), 500);
    document.getElementById('acceptCookies')?.addEventListener('click', () => { localStorage.setItem('cookieConsent', 'accepted'); cookieBanner.classList.remove('show'); });
    document.getElementById('declineCookies')?.addEventListener('click', () => { localStorage.setItem('cookieConsent', 'declined'); cookieBanner.classList.remove('show'); });

    updateCartUI();
</script>
<div id="rdv-cookie-root"></div>
<script src="assets/js/rdv-public.js" defer></script>
</body>
</html>