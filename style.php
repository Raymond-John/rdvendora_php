<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ----- Helper to fetch settings from marketplace_settings table -----
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

// ----- Hero Banner Settings -----
$hero_image = getMarketplaceSetting('hero_image', '');
$hero_title = getMarketplaceSetting('hero_title', 'Up to 50% OFF on everything');
$hero_subtitle = getMarketplaceSetting('hero_subtitle', 'Shop the biggest sale of the year. Limited time offer.');
$hero_btn_text = getMarketplaceSetting('hero_btn_text', 'Shop Now');
$hero_btn_link = getMarketplaceSetting('hero_btn_link', '#');

// ----- Color Settings (with fallbacks) -----
$body_bg_color = getMarketplaceSetting('body_bg_color', '#f5f5f5');
$text_primary_color = getMarketplaceSetting('text_primary_color', '#1f2937');
$primary_btn_bg = getMarketplaceSetting('primary_btn_bg', '#2563eb');
$primary_btn_text = getMarketplaceSetting('primary_btn_text', '#ffffff');
$card_bg_color = getMarketplaceSetting('card_bg_color', '#ffffff');
$sidebar_bg_color = getMarketplaceSetting('sidebar_bg_color', '#ffffff');
$sidebar_text_color = getMarketplaceSetting('sidebar_text_color', '#4a5568');

// ----- Store Visibility (get list of visible store IDs) -----
$visibleStores = [];
$storesResult = $conn->query("SELECT id FROM stores WHERE status = 'active'");
while ($storeRow = $storesResult->fetch_assoc()) {
    $visible = getMarketplaceSetting("store_visible_{$storeRow['id']}", '1');
    if ($visible == '1') {
        $visibleStores[] = $storeRow['id'];
    }
}

// ----- Get filter parameters -----
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$storeFilter = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';

// ----- Build products query (only active stores & active products, and only visible stores) -----
$sql = "SELECT p.*, 
               s.id AS store_pk,
               s.store_name, 
               COALESCE(s.brand_color, '#2563eb') as brand_color, 
               s.logo_path 
        FROM products p 
        INNER JOIN stores s ON p.user_id = s.user_id 
        WHERE p.status = 'active' 
          AND s.status = 'active'";

// Add visible stores filter
if (!empty($visibleStores)) {
    $placeholders = implode(',', array_fill(0, count($visibleStores), '?'));
    $sql .= " AND s.id IN ($placeholders)";
    $params = $visibleStores;
    $types = str_repeat('i', count($visibleStores));
} else {
    $params = [];
    $types = "";
}

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
if ($storeFilter > 0) {
    $sql .= " AND s.id = ?";
    $params[] = $storeFilter;
    $types .= "i";
}
if (!empty($categoryFilter)) {
    $sql .= " AND p.category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ----- Get ONLY ACTIVE stores for sidebar, filtered by visibility, sorted by subscription priority -----
$stores = [];
if (!empty($visibleStores)) {
    $placeholders = implode(',', array_fill(0, count($visibleStores), '?'));
    $storeSql = "
        SELECT 
            s.id AS store_pk,
            s.store_name,
            s.brand_color,
            s.logo_path,
            COALESCE(sub.plan, 'Launch') AS plan,
            CASE 
                WHEN sub.plan = 'Empire' THEN 1
                WHEN sub.plan = 'Scale'  THEN 2
                WHEN sub.plan = 'Growth' THEN 3
                ELSE 4
            END AS plan_rank
        FROM stores s
        LEFT JOIN (
            SELECT user_id, plan
            FROM subscriptions
            WHERE (user_id, end_date) IN (
                SELECT user_id, MAX(end_date)
                FROM subscriptions
                WHERE status = 'active' AND end_date > NOW()
                GROUP BY user_id
            )
        ) sub ON s.user_id = sub.user_id
        WHERE s.status = 'active'
          AND s.id IN ($placeholders)
        ORDER BY plan_rank ASC, s.store_name ASC
    ";
    $stmt = $conn->prepare($storeSql);
    $stmt->bind_param(str_repeat('i', count($visibleStores)), ...$visibleStores);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }
    $stmt->close();
}

// ----- Get all categories (from active products, but only from visible stores) -----
$allCategories = [];
if (!empty($visibleStores)) {
    $placeholders = implode(',', array_fill(0, count($visibleStores), '?'));
    $catSql = "SELECT DISTINCT p.category 
               FROM products p 
               INNER JOIN stores s ON p.user_id = s.user_id 
               WHERE p.status = 'active' 
                 AND p.category IS NOT NULL 
                 AND p.category != ''
                 AND s.id IN ($placeholders)";
    $stmt = $conn->prepare($catSql);
    $stmt->bind_param(str_repeat('i', count($visibleStores)), ...$visibleStores);
    $stmt->execute();
    $catResult = $stmt->get_result();
    while ($catRow = $catResult->fetch_assoc()) {
        $allCategories[] = $catRow['category'];
    }
    $stmt->close();
}

// ----- PROMOTIONAL BANNERS – only for Empire subscribers (used in carousel) -----
$banners = [];
$bannerSql = "SELECT pb.*, s.store_name, s.brand_color 
              FROM promo_banners pb
              INNER JOIN stores s ON pb.user_id = s.user_id
              INNER JOIN subscriptions sub ON sub.user_id = s.user_id
              WHERE pb.status = 'active' 
                AND s.status = 'active'
                AND sub.plan = 'Empire'
                AND sub.status = 'active'
                AND sub.end_date > NOW()
              ORDER BY pb.order_position ASC";
$bannerResult = $conn->query($bannerSql);
if ($bannerResult) {
    while ($row = $bannerResult->fetch_assoc()) {
        $banners[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>RD Vendora – Multi‑Vendor Marketplace | Shop Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== DYNAMIC COLORS FROM SETTINGS ========== */
        :root {
            --body-bg: <?= htmlspecialchars($body_bg_color) ?>;
            --text-primary: <?= htmlspecialchars($text_primary_color) ?>;
            --btn-bg: <?= htmlspecialchars($primary_btn_bg) ?>;
            --btn-text: <?= htmlspecialchars($primary_btn_text) ?>;
            --card-bg: <?= htmlspecialchars($card_bg_color) ?>;
            --sidebar-bg: <?= htmlspecialchars($sidebar_bg_color) ?>;
            --sidebar-text: <?= htmlspecialchars($sidebar_text_color) ?>;
        }
        /* Keep brand blue for interactive elements */
        .store-name, .promo-store, .sidebar-title, .current-price, .cart-link, .hamburger, .logo i {
            color: #2563eb !important;
        }
        .btn-add-cart {
            background: var(--btn-bg) !important;
            color: var(--btn-text) !important;
        }
        .sidebar {
            background: var(--sidebar-bg) !important;
        }
        .sidebar a {
            color: var(--sidebar-text) !important;
        }
        .product-card {
            background: var(--card-bg) !important;
        }
        
        /* ========== BASE STYLES ========== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            line-height: 1.5;
            overflow-x: hidden;
        }
        .store-name a, .product-name, .price-section, .rating, .rating span {
            color: inherit;
        }

        /* ========== HEADER / NAVBAR (COMPACT & RESPONSIVE) ========== */
        .top-header {
            background: #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0.6rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        .nav-left {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .hamburger {
            background: none;
            border: none;
            font-size: 1.4rem;
            color: #2563eb;
            cursor: pointer;
            padding: 0.2rem 0.4rem;
            border-radius: 6px;
            display: none;
        }
        .hamburger:hover { background: #eef2ff; }
        .logo a {
            font-size: 1.4rem;
            font-weight: 800;
            text-decoration: none;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }
        .logo i { font-size: 1.4rem; color: #2563eb; }
        .search-wrapper {
            flex: 1;
            max-width: 480px;
        }
        .search-wrapper form {
            display: flex;
            width: 100%;
        }
        .search-input {
            flex: 1;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 30px 0 0 30px;
            font-size: 0.85rem;
            outline: none;
            background: #fff;
        }
        .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
        }
        .search-btn {
            background: #2563eb;
            border: none;
            padding: 0 1.2rem;
            border-radius: 0 30px 30px 0;
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }
        .search-btn:hover { background: #1d4ed8; }
        .header-icons { display: flex; align-items: center; }
        .cart-link {
            position: relative;
            text-decoration: none;
            font-size: 1.3rem;
            color: #333;
            display: flex;
            align-items: center;
        }
        .cart-count {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #2563eb;
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 20px;
            min-width: 18px;
            text-align: center;
        }

        /* ========== DYNAMIC HERO BANNER ========== */
        .hero {
            margin: 1rem 2rem 2rem 2rem;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #1e2a3e 0%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            position: relative;
        }
        .hero-content {
            flex: 1;
            padding: 2rem 2rem 2rem 3rem;
            color: white;
            z-index: 2;
        }
        .hero-content h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin-bottom: 1rem;
        }
        .hero-content .discount {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 0.2rem 1rem;
            border-radius: 30px;
            backdrop-filter: blur(4px);
        }
        .hero-content p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
            max-width: 400px;
        }
        .hero-btn {
            background: #2563eb;
            color: white;
            padding: 0.7rem 1.8rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .hero-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        .hero-image {
            flex: 0 0 35%;
            max-width: 35%;
            text-align: center;
            padding: 1rem;
        }
        .hero-image img {
            width: 100%;
            max-width: 300px;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 20px 30px rgba(0,0,0,0.2));
        }
        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
                margin: 1rem;
            }
            .hero-content {
                padding: 2rem 1.5rem;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
            .hero-image {
                flex: 0 0 auto;
                max-width: 80%;
                padding-bottom: 1.5rem;
            }
            .hero-content p {
                margin-left: auto;
                margin-right: auto;
            }
        }
        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 1.8rem;
            }
            .hero-image {
                max-width: 70%;
            }
        }

        /* ========== PROMO CAROUSEL (image fills container) ========== */
        .promo-section {
            margin: 0 2rem 1.5rem 2rem;
        }
        .promo-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .promo-title {
            font-size: 1.3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .promo-badge {
            font-size: 0.75rem;
            padding: 0.2rem 0.9rem;
            background: rgba(37,99,235,0.15);
            border-radius: 40px;
            font-weight: 600;
            color: #2563eb;
        }
        .carousel-container {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .carousel-track {
            display: flex;
            transition: transform 0.5s ease;
        }
        .carousel-slide {
            flex: 0 0 100%;
            background: #fff;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: row;
            align-items: stretch;
        }
        .carousel-slide .promo-img-side {
            flex: 0 0 35%;
            position: relative;
            overflow: hidden;
        }
        .carousel-slide .promo-img-side img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            display: block;
        }
        .exclusive-tag {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0,0,0,0.7);
            color: #ffd966;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            z-index: 2;
        }
        .carousel-slide .promo-info-side {
            flex: 1;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .promo-store {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #2563eb;
            margin-bottom: 0.3rem;
        }
        .promo-title-text {
            font-weight: 800;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        .promo-desc {
            font-size: 0.85rem;
            color: #4b5563;
            margin-bottom: 1rem;
        }
        .promo-btn {
            background: linear-gradient(100deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 0.4rem 1.2rem;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
            width: fit-content;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.5);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .carousel-btn.prev { left: 15px; }
        .carousel-btn.next { right: 15px; }
        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ccc;
            cursor: pointer;
        }
        .dot.active {
            background: #2563eb;
            width: 24px;
            border-radius: 6px;
        }

        /* ========== SIDEBAR – COMPACT, NO STRETCHING ========== */
        .sidebar {
            width: 240px;
            flex-shrink: 0;
            background: var(--sidebar-bg);
            border-radius: 14px;
            padding: 0.75rem 0.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            position: sticky;
            top: 90px;
            align-self: flex-start;
            height: auto;
        }
        .sidebar-section {
            margin-bottom: 1rem;
        }
        .sidebar-title {
            font-size: 0.85rem;
            font-weight: 700;
            padding-bottom: 0.3rem;
            border-bottom: 2px solid #2563eb;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        .category-list, .store-list {
            list-style: none;
        }
        .category-list li, .store-list li {
            margin-bottom: 0.2rem;
        }
        .category-list a, .store-list a {
            text-decoration: none;
            color: var(--sidebar-text);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.15s;
        }
        .category-list a:hover, .store-list a:hover,
        .category-list a.active, .store-list a.active {
            background: #eff6ff;
            color: #2563eb;
        }
        .store-badge img {
            width: 18px;
            height: 18px;
            border-radius: 3px;
        }

        /* ========== MAIN LAYOUT ========== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        .main-grid {
            display: flex;
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .products-area {
            flex: 1;
        }

        /* ========== PRODUCTS AREA (COMPACT TEXT) ========== */
        .products-header {
            margin-bottom: 1.5rem;
        }
        .products-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .products-header p {
            color: var(--text-primary);
            opacity: 0.7;
            font-size: 0.9rem;
        }
        .product-grid {
            display: grid;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .product-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (min-width: 768px) and (max-width: 1023px) {
            .product-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 767px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            .product-info {
                padding: 0.5rem;
            }
            .current-price {
                font-size: 0.75rem;
            }
            .btn-add-cart {
                font-size: 0.55rem;
                padding: 0.3rem 0.4rem;
            }
        }
        .product-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.15);
        }
        .product-image {
            aspect-ratio: 1 / 1;
            background: #f8f8f8;
            overflow: hidden;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .product-card:hover .product-image img {
            transform: scale(1.03);
        }
        .product-info {
            padding: 0.8rem;
        }
        .store-name {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #2563eb;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        .product-name {
            font-weight: 700;
            font-size: 0.8rem;
            margin: 0.2rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
            color: inherit;
        }
        .price-section {
            margin: 0.3rem 0;
        }
        .current-price {
            font-weight: 800;
            font-size: 0.85rem;
            color: #2563eb;
        }
        .old-price, .discount-badge {
            display: none;
        }
        .rating {
            display: flex;
            align-items: center;
            gap: 0.15rem;
            margin: 0.2rem 0;
            font-size: 0.6rem;
            color: #fbbf24;
        }
        .rating span {
            color: var(--text-primary);
            opacity: 0.6;
            margin-left: 0.2rem;
            font-size: 0.6rem;
        }
        .btn-add-cart {
            background: var(--btn-bg);
            color: var(--btn-text);
            border: none;
            padding: 0.35rem 0.5rem;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 600;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            cursor: pointer;
        }
        .btn-add-cart:hover {
            filter: brightness(0.9);
        }
        .no-results {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 16px;
        }
        footer {
            background: #1e293b;
            color: #94a3b8;
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
            font-size: 0.8rem;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 0.8rem;
            z-index: 1100;
        }

        /* ========== MOBILE MENU (OFF-CANVAS) ========== */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
        }
        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: -260px;
            width: 260px;
            height: 100%;
            background: white;
            box-shadow: 2px 0 12px rgba(0,0,0,0.1);
            z-index: 2001;
            padding: 1rem;
            overflow-y: auto;
            transition: left 0.3s ease;
        }
        .mobile-sidebar.open {
            left: 0;
        }
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .close-menu {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .mobile-sidebar .sidebar-section {
            margin-bottom: 1rem;
        }
        .mobile-sidebar .category-list a,
        .mobile-sidebar .store-list a {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        /* ========== RESPONSIVE SEARCH, SIDEBAR & CAROUSEL ========== */
        @media (max-width: 992px) {
            .main-grid {
                flex-direction: column;
            }
            .sidebar {
                display: none;
            }
            .container {
                padding: 0 1rem;
            }
            .hero, .promo-section {
                margin: 1rem;
            }
        }
        @media (max-width: 768px) {
            .header-container {
                padding: 0.5rem 1rem;
                gap: 0.5rem;
            }
            .hamburger {
                display: block;
            }
            .logo a {
                font-size: 1.2rem;
            }
            .search-wrapper {
                order: 3;
                width: 100%;
                max-width: 100%;
                margin-top: 0.2rem;
            }
            .search-wrapper form {
                flex-direction: row !important;
            }
            .search-input {
                font-size: 0.8rem;
                padding: 0.5rem 0.8rem;
            }
            .search-btn {
                padding: 0 0.8rem;
                font-size: 0.8rem;
            }
            /* Mobile: carousel vertical, image fills container */
            .carousel-slide {
                flex-direction: column !important;
            }
            .carousel-slide .promo-img-side {
                flex: 0 0 auto;
                width: 100%;
                aspect-ratio: 16 / 9;
                overflow: hidden;
            }
            .carousel-slide .promo-img-side img {
                height: 100%;
                width: 100%;
                object-fit: cover;
            }
            .carousel-slide .promo-info-side {
                padding: 1rem;
            }
            .promo-title-text {
                font-size: 1rem;
            }
            .promo-desc {
                font-size: 0.75rem;
            }
            .promo-btn {
                padding: 0.2rem 0.8rem;
                font-size: 0.7rem;
            }
            .exclusive-tag {
                top: 8px;
                left: 8px;
                font-size: 0.6rem;
            }
        }
        @media (max-width: 480px) {
            .carousel-slide .promo-img-side {
                aspect-ratio: 4 / 3;
            }
            .carousel-slide .promo-info-side {
                padding: 0.8rem;
            }
            .promo-title-text {
                font-size: 0.9rem;
                margin-bottom: 0.2rem;
            }
            .promo-desc {
                font-size: 0.65rem;
                margin-bottom: 0.5rem;
            }
            .promo-btn {
                padding: 0.2rem 0.6rem;
                font-size: 0.65rem;
            }
            .search-input {
                font-size: 0.75rem;
                padding: 0.4rem 0.7rem;
            }
            .search-btn {
                padding: 0 0.6rem;
                font-size: 0.75rem;
            }
            .search-btn span {
                display: none;
            }
            .search-btn i {
                margin: 0;
            }
        }
    </style>
</head>
<body>

<!-- HEADER (COMPACT, RESPONSIVE SEARCH) -->
<div class="top-header">
    <div class="header-container">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <div class="logo">
                <a href="marketplace.php">
                    <i class="fas fa-store"></i> <span>RD Vendora</span>
                </a>
            </div>
        </div>
        <div class="search-wrapper">
            <form method="get" action="">
                <input type="text" name="q" class="search-input" placeholder="Search products, brands, stores..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> <span>Search</span></button>
            </form>
        </div>
        <div class="header-icons">
            <a href="marketplaceaddtocart.php" class="cart-link">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cartCount">0</span>
            </a>
        </div>
    </div>
</div>

<!-- DYNAMIC HERO BANNER (from settings) -->
<div class="hero">
    <div class="hero-content">
        <span class="discount">🔥 Black Friday Exclusive</span>
        <h1><?= htmlspecialchars($hero_title) ?></h1>
        <p><?= htmlspecialchars($hero_subtitle) ?></p>
        <a href="<?= htmlspecialchars($hero_btn_link) ?>" class="hero-btn"><?= htmlspecialchars($hero_btn_text) ?> <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if (!empty($hero_image)): ?>
        <div class="hero-image">
            <img src="<?= htmlspecialchars($hero_image) ?>" alt="Black Friday Sale">
        </div>
    <?php endif; ?>
</div>

<!-- PROMO CAROUSEL (Empire only) -->
<?php if (!empty($banners)): ?>
<div class="promo-section">
    <div class="promo-header">
        <div class="promo-title"><i class="fas fa-crown"></i> Empire Exclusives</div>
        <div class="promo-badge">🔥 Limited Time Offers</div>
    </div>
    <div class="carousel-container" id="promoCarousel">
        <div class="carousel-track" id="carouselTrack">
            <?php foreach ($banners as $banner): ?>
                <a href="<?= htmlspecialchars($banner['link'] ?? '#') ?>" class="carousel-slide" target="_blank">
                    <div class="promo-img-side">
                        <img src="<?= htmlspecialchars($banner['image']) ?>" alt="<?= htmlspecialchars($banner['title'] ?? 'Promotion') ?>">
                        <div class="exclusive-tag"><i class="fas fa-crown"></i> Empire Only</div>
                    </div>
                    <div class="promo-info-side">
                        <div class="promo-store"><?= htmlspecialchars($banner['store_name']) ?></div>
                        <div class="promo-title-text"><?= htmlspecialchars($banner['title'] ?? 'Special Offer') ?></div>
                        <?php if (!empty($banner['description'])): ?>
                            <div class="promo-desc"><?= htmlspecialchars($banner['description']) ?></div>
                        <?php endif; ?>
                        <div class="promo-btn">Shop Now <i class="fas fa-arrow-right"></i></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($banners) > 1): ?>
            <button class="carousel-btn prev" id="carouselPrev"><i class="fas fa-chevron-left"></i></button>
            <button class="carousel-btn next" id="carouselNext"><i class="fas fa-chevron-right"></i></button>
            <div class="carousel-dots" id="carouselDots"></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="container">
    <div class="main-grid">
        <!-- DESKTOP SIDEBAR (now with dynamic background/text color) -->
        <aside class="sidebar">
            <div class="sidebar-section">
                <div class="sidebar-title"><i class="fas fa-list"></i> Categories</div>
                <ul class="category-list">
                    <li><a href="marketplace.php" class="<?= empty($categoryFilter) ? 'active' : '' ?>"><i class="fas fa-th-large"></i> All Categories</a></li>
                    <?php foreach ($allCategories as $cat): ?>
                        <li><a href="?category=<?= urlencode($cat) ?><?= $storeFilter ? '&store_id='.$storeFilter : '' ?><?= !empty($search) ? '&q='.urlencode($search) : '' ?>" class="<?= $categoryFilter == $cat ? 'active' : '' ?>"><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($cat)) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-title"><i class="fas fa-store"></i> Stores</div>
                <ul class="store-list">
                    <li><a href="marketplace.php" class="<?= $storeFilter == 0 ? 'active' : '' ?>"><i class="fas fa-store"></i> All Stores</a></li>
                    <?php foreach ($stores as $store): ?>
                        <li><a href="storefront.php?store=<?= $store['store_pk'] ?>" class="<?= $storeFilter == $store['store_pk'] ? 'active' : '' ?>">
                            <span class="store-badge">
                                <?php if (!empty($store['logo_path'])): ?>
                                    <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-shop"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($store['store_name']) ?>
                            </span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <!-- PRODUCTS AREA -->
        <div class="products-area">
            <div class="products-header">
                <h1><i class="fas fa-gem"></i> Discover Amazing Products</h1>
                <p><?= count($products) ?> product<?= count($products) != 1 ? 's' : '' ?> found</p>
            </div>
            <?php if (empty($products)): ?>
                <div class="no-results"><i class="fas fa-frown" style="font-size: 3rem; color: #94a3b8;"></i><p style="margin-top: 1rem;">No products match your criteria.</p><a href="marketplace.php" class="btn-add-cart" style="display: inline-block; width: auto; padding: 0.5rem 1rem;">Clear filters</a></div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product):
                        $rating = 4.5;
                    ?>
                        <div class="product-card" data-product-id="<?= $product['id'] ?>" data-store-id="<?= $product['store_pk'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>" data-image="<?= htmlspecialchars($product['image'] ?? 'https://placehold.co/400x400') ?>">
                            <a href="marketplaceviewproduct.php?id=<?= $product['id'] ?>&store=<?= $product['store_pk'] ?>" style="text-decoration: none; color: inherit;"><div class="product-image"><img src="<?= htmlspecialchars($product['image'] ?? 'https://placehold.co/400x400?text=No+Image') ?>" alt="<?= htmlspecialchars($product['name']) ?>"></div></a>
                            <div class="product-info">
                                <div class="store-name">
                                    <?php if (!empty($product['logo_path'])): ?><img src="<?= htmlspecialchars($product['logo_path']) ?>" style="width: 14px; height: 14px; border-radius: 2px;"><?php else: ?><i class="fas fa-store"></i><?php endif; ?>
                                    <a href="storefront.php?store=<?= $product['store_pk'] ?>" style="color: inherit;"><?= htmlspecialchars($product['store_name']) ?></a>
                                </div>
                                <a href="product-details.php?id=<?= $product['id'] ?>&store=<?= $product['store_pk'] ?>" style="text-decoration: none; color: inherit;"><h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3></a>
                                <div class="price-section"><span class="current-price">₦ <?= number_format($product['price'], 2) ?></span></div>
                                <div class="rating"><?php for ($i = 1; $i <= 5; $i++): ?><?php if ($i <= floor($rating)): ?><i class="fas fa-star"></i><?php elseif ($i - $rating < 0.6): ?><i class="fas fa-star-half-alt"></i><?php else: ?><i class="far fa-star"></i><?php endif; ?><?php endfor; ?><span>(<?= rand(10, 200) ?>)</span></div>
                                <button class="btn-add-cart add-to-cart-btn"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MOBILE OFF-CANVAS MENU -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
<div class="mobile-sidebar" id="mobileSidebar">
    <div class="mobile-menu-header">
        <h3><i class="fas fa-filter"></i> Filters</h3>
        <button class="close-menu" id="closeMenuBtn"><i class="fas fa-times"></i></button>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title"><i class="fas fa-list"></i> Categories</div>
        <ul class="category-list">
            <li><a href="marketplace.php" class="<?= empty($categoryFilter) ? 'active' : '' ?>"><i class="fas fa-th-large"></i> All Categories</a></li>
            <?php foreach ($allCategories as $cat): ?>
                <li><a href="?category=<?= urlencode($cat) ?><?= $storeFilter ? '&store_id='.$storeFilter : '' ?><?= !empty($search) ? '&q='.urlencode($search) : '' ?>" class="<?= $categoryFilter == $cat ? 'active' : '' ?>"><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($cat)) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="sidebar-section">
        <div class="sidebar-title"><i class="fas fa-store"></i> Stores</div>
        <ul class="store-list">
            <li><a href="marketplace.php" class="<?= $storeFilter == 0 ? 'active' : '' ?>"><i class="fas fa-store"></i> All Stores</a></li>
            <?php foreach ($stores as $store): ?>
                <li><a href="storefront.php?store=<?= $store['store_pk'] ?>" class="<?= $storeFilter == $store['store_pk'] ? 'active' : '' ?>">
                    <span class="store-badge">
                        <?php if (!empty($store['logo_path'])): ?>
                            <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-shop"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($store['store_name']) ?>
                    </span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<footer>
    <p><i class="fas fa-copyright"></i> <?= date('Y') ?> RD Vendora – Multi‑Vendor Marketplace. All rights reserved.</p>
</footer>

<script>
    // All JavaScript unchanged (cart, carousel, menu)
    const CART_KEY = 'marketplace_cart';
    function getCart() { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
    function saveCart(cart) { localStorage.setItem(CART_KEY, JSON.stringify(cart)); updateCartCount(); }
    function updateCartCount() { const cart = getCart(); const total = cart.reduce((s,i)=>s+i.quantity,0); const el = document.getElementById('cartCount'); if (el) el.innerText = total; }
    function addToCart(storeId, productId, name, price, image) { let cart = getCart(); const idx = cart.findIndex(i=>i.store_id==storeId && i.product_id==productId); if (idx>-1) cart[idx].quantity++; else cart.push({ store_id:storeId, product_id:productId, name, price:parseFloat(price), image, quantity:1 }); saveCart(cart); showToast('✓ Added to cart', '#10b981'); }
    function showToast(msg, bg) { const toast = document.createElement('div'); toast.className='toast'; toast.innerHTML=`<i class="fas fa-check-circle"></i> ${msg}`; toast.style.backgroundColor=bg; document.body.appendChild(toast); setTimeout(()=>toast.remove(),2500); }
    function handleAddToCart(e) { const card = e.target.closest('.product-card'); if (!card) return; addToCart(card.dataset.storeId, card.dataset.productId, card.dataset.name, card.dataset.price, card.dataset.image); }
    document.querySelectorAll('.add-to-cart-btn').forEach(btn=>btn.addEventListener('click', handleAddToCart));
    document.addEventListener('DOMContentLoaded', updateCartCount);

    <?php if (!empty($banners) && count($banners) > 1): ?>
    const track = document.getElementById('carouselTrack');
    const slides = Array.from(track.children);
    const prevBtn = document.getElementById('carouselPrev');
    const nextBtn = document.getElementById('carouselNext');
    const dotsContainer = document.getElementById('carouselDots');
    let currentIndex = 0, autoSlideInterval;
    slides.forEach((_, idx) => {
        const dot = document.createElement('div');
        dot.classList.add('dot');
        if (idx === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(idx));
        dotsContainer.appendChild(dot);
    });
    const dots = Array.from(dotsContainer.children);
    function updateCarousel() { const slideWidth = slides[0].getBoundingClientRect().width; track.style.transform = `translateX(-${currentIndex * slideWidth}px)`; dots.forEach((dot, idx) => dot.classList.toggle('active', idx === currentIndex)); }
    function goToSlide(index) { if (index < 0) index = slides.length - 1; if (index >= slides.length) index = 0; currentIndex = index; updateCarousel(); resetAutoSlide(); }
    function nextSlide() { goToSlide(currentIndex + 1); }
    function prevSlide() { goToSlide(currentIndex - 1); }
    function resetAutoSlide() { if (autoSlideInterval) clearInterval(autoSlideInterval); autoSlideInterval = setInterval(() => nextSlide(), 5000); }
    nextBtn.addEventListener('click', nextSlide); prevBtn.addEventListener('click', prevSlide);
    window.addEventListener('resize', updateCarousel); updateCarousel(); resetAutoSlide();
    <?php endif; ?>

    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('mobileMenuOverlay');
    const closeMenuBtn = document.getElementById('closeMenuBtn');

    function openMenu() { mobileSidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeMenu() { mobileSidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }
    if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMenu);
    if (closeMenuBtn) closeMenuBtn.addEventListener('click', closeMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
    document.querySelectorAll('.mobile-sidebar a').forEach(link => { link.addEventListener('click', closeMenu); });
</script>
</body>
</html>