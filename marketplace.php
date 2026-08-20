<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/marketplace_settings.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

rdv_ensure_marketplace_settings_table($conn);
$mpDefaults = rdv_marketplace_defaults();

// ----- Helper to fetch settings -----
function getMarketplaceSetting($key, $default = '') {
    global $conn;
    return rdv_marketplace_setting($conn, $key, $default);
}

function mp_bool($key, $default = true) {
    global $conn;
    return rdv_marketplace_setting_bool($conn, $key, $default);
}

// ----- Hero Settings -----
$hero_enabled = mp_bool('hero_enabled', true);
$hero_image = getMarketplaceSetting('hero_image', $mpDefaults['hero_image']);
$hero_title = getMarketplaceSetting('hero_title', $mpDefaults['hero_title']);
$hero_subtitle = getMarketplaceSetting('hero_subtitle', $mpDefaults['hero_subtitle']);
$hero_btn_text = getMarketplaceSetting('hero_btn_text', $mpDefaults['hero_btn_text']);
$hero_btn_link = getMarketplaceSetting('hero_btn_link', $mpDefaults['hero_btn_link']);
$hero_tag = getMarketplaceSetting('hero_tag', $mpDefaults['hero_tag']);
$hero2_enabled = mp_bool('hero2_enabled', true);
$hero2_image = getMarketplaceSetting('hero2_image', $mpDefaults['hero2_image']);
$hero2_tag = getMarketplaceSetting('hero2_tag', $mpDefaults['hero2_tag']);
$hero2_title = getMarketplaceSetting('hero2_title', $mpDefaults['hero2_title']);
$hero2_subtitle = getMarketplaceSetting('hero2_subtitle', $mpDefaults['hero2_subtitle']);
$hero2_btn_text = getMarketplaceSetting('hero2_btn_text', $mpDefaults['hero2_btn_text']);
$hero2_btn_link = getMarketplaceSetting('hero2_btn_link', $mpDefaults['hero2_btn_link']);
$hero3_enabled = mp_bool('hero3_enabled', true);
$hero3_image = getMarketplaceSetting('hero3_image', $mpDefaults['hero3_image']);
$hero3_tag = getMarketplaceSetting('hero3_tag', $mpDefaults['hero3_tag']);
$hero3_title = getMarketplaceSetting('hero3_title', $mpDefaults['hero3_title']);
$hero3_subtitle = getMarketplaceSetting('hero3_subtitle', $mpDefaults['hero3_subtitle']);
$hero3_btn_text = getMarketplaceSetting('hero3_btn_text', $mpDefaults['hero3_btn_text']);
$hero3_btn_link = getMarketplaceSetting('hero3_btn_link', $mpDefaults['hero3_btn_link']);

// ----- Top strip / sections / footer -----
$top_strip_enabled = mp_bool('top_strip_enabled', true);
$top_strip_text = getMarketplaceSetting('top_strip_text', $mpDefaults['top_strip_text']);
$categories_nav_enabled = mp_bool('categories_nav_enabled', true);
$categories_section_enabled = mp_bool('categories_section_enabled', true);
$categories_section_title = getMarketplaceSetting('categories_section_title', $mpDefaults['categories_section_title']);
$stores_section_enabled = mp_bool('stores_section_enabled', true);
$stores_section_title = getMarketplaceSetting('stores_section_title', $mpDefaults['stores_section_title']);
$flash_banner_enabled = mp_bool('flash_banner_enabled', true);
$flash_banner_title = getMarketplaceSetting('flash_banner_title', $mpDefaults['flash_banner_title']);
$flash_banner_hours = (int) getMarketplaceSetting('flash_banner_hours', $mpDefaults['flash_banner_hours']);
$flash_banner_minutes = (int) getMarketplaceSetting('flash_banner_minutes', $mpDefaults['flash_banner_minutes']);
$products_section_enabled = mp_bool('products_section_enabled', true);
$products_per_store = max(1, min(48, (int) getMarketplaceSetting('products_per_store', $mpDefaults['products_per_store'])));
$footer_enabled = mp_bool('footer_enabled', true);
$footer_copyright = getMarketplaceSetting('footer_copyright', $mpDefaults['footer_copyright']);
$footer_facebook = getMarketplaceSetting('footer_facebook', $mpDefaults['footer_facebook']);
$footer_twitter = getMarketplaceSetting('footer_twitter', $mpDefaults['footer_twitter']);
$footer_instagram = getMarketplaceSetting('footer_instagram', $mpDefaults['footer_instagram']);
$footer_whatsapp = getMarketplaceSetting('footer_whatsapp', $mpDefaults['footer_whatsapp']);
$footer_youtube = getMarketplaceSetting('footer_youtube', $mpDefaults['footer_youtube']);
$footer_cols = [];
for ($i = 1; $i <= 4; $i++) {
    $footer_cols[] = [
        'title' => getMarketplaceSetting("footer_col{$i}_title", $mpDefaults["footer_col{$i}_title"]),
        'links' => rdv_marketplace_parse_footer_links(getMarketplaceSetting("footer_col{$i}_links", $mpDefaults["footer_col{$i}_links"])),
    ];
}

// ----- Promotional Banners -----
$promo1_title = getMarketplaceSetting('promo1_title', 'Up to 50% Off Electronics');
$promo1_subtitle = getMarketplaceSetting('promo1_subtitle', 'Limited time offer on top brands');
$promo1_link = getMarketplaceSetting('promo1_link', '#');
$promo1_btn_text = getMarketplaceSetting('promo1_btn_text', $mpDefaults['promo1_btn_text']);
$promo1_enabled = getMarketplaceSetting('promo1_enabled', '1');
$promo2_title = getMarketplaceSetting('promo2_title', 'New Arrivals in Fashion');
$promo2_subtitle = getMarketplaceSetting('promo2_subtitle', 'Fresh styles every week');
$promo2_link = getMarketplaceSetting('promo2_link', '#');
$promo2_btn_text = getMarketplaceSetting('promo2_btn_text', $mpDefaults['promo2_btn_text']);
$promo2_enabled = getMarketplaceSetting('promo2_enabled', '1');

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

function mp_public_href($url) {
    $url = trim((string) $url);
    if ($url === '' || $url === '#') {
        return '#';
    }
    if (preg_match('#^(https?:)?//|^mailto:|^tel:|^/#i', $url)) {
        return $url;
    }
    return function_exists('rdv_url') ? rdv_url($url) : $url;
}

// ----- Store Visibility -----
$visibleStores = [];
$storesResult = $conn->query("SELECT id FROM stores WHERE status = 'active'");
while ($storeRow = $storesResult->fetch_assoc()) {
    $visible = getMarketplaceSetting("store_visible_{$storeRow['id']}", '1');
    if ($visible == '1') $visibleStores[] = $storeRow['id'];
}

// ----- Get all active stores with an active subscription, ordered by plan -----
$stores = [];
if (!empty($visibleStores)) {
    $placeholders = implode(',', array_fill(0, count($visibleStores), '?'));
    // Robust query: EXISTS to ensure at least one valid subscription
    // Correlated subquery to get the latest active plan for ordering
    $storeSql = "
        SELECT 
            s.id AS store_pk,
            s.store_name,
            s.store_slug,
            s.brand_color,
            s.logo_path,
            (SELECT plan 
             FROM subscriptions 
             WHERE user_id = s.user_id 
               AND status = 'active' 
               AND end_date > NOW() 
             ORDER BY end_date DESC 
             LIMIT 1) AS plan,
            CASE 
                WHEN (SELECT plan 
                      FROM subscriptions 
                      WHERE user_id = s.user_id 
                        AND status = 'active' 
                        AND end_date > NOW() 
                      ORDER BY end_date DESC 
                      LIMIT 1) = 'Empire' THEN 1
                WHEN (SELECT plan 
                      FROM subscriptions 
                      WHERE user_id = s.user_id 
                        AND status = 'active' 
                        AND end_date > NOW() 
                      ORDER BY end_date DESC 
                      LIMIT 1) = 'Scale'  THEN 2
                WHEN (SELECT plan 
                      FROM subscriptions 
                      WHERE user_id = s.user_id 
                        AND status = 'active' 
                        AND end_date > NOW() 
                      ORDER BY end_date DESC 
                      LIMIT 1) = 'Growth' THEN 3
                ELSE 4
            END AS plan_rank
        FROM stores s
        WHERE s.status = 'active'
          AND s.id IN ($placeholders)
          AND EXISTS (
              SELECT 1 
              FROM subscriptions 
              WHERE user_id = s.user_id 
                AND status = 'active' 
                AND end_date > NOW()
          )
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

// ----- Helper to fetch products for a specific store (with optional category filter) -----
function getStoreProducts($storeId, $limit = 10, $category = null) {
    global $conn;
    $sql = "SELECT p.*, s.store_name, s.store_slug, s.logo_path, s.brand_color, s.id AS store_pk
            FROM products p
            INNER JOIN stores s ON p.user_id = s.user_id
            WHERE p.status = 'active' AND s.status = 'active'
              AND s.id = ?";
    $params = [$storeId];
    $types = "i";
    if (!empty($category)) {
        $sql .= " AND p.category = ?";
        $params[] = $category;
        $types .= "s";
    }
    $sql .= " ORDER BY p.created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $products;
}

// ----- Fetch all categories -----
$allCategories = [];
if (!empty($visibleStores)) {
    $placeholders = implode(',', array_fill(0, count($visibleStores), '?'));
    $catSql = "SELECT DISTINCT p.category
               FROM products p
               INNER JOIN stores s ON p.user_id = s.user_id
               WHERE p.status = 'active' AND p.category IS NOT NULL AND p.category != ''
                 AND s.id IN ($placeholders)
               ORDER BY p.category ASC";
    $stmt = $conn->prepare($catSql);
    $stmt->bind_param(str_repeat('i', count($visibleStores)), ...$visibleStores);
    $stmt->execute();
    $catResult = $stmt->get_result();
    while ($catRow = $catResult->fetch_assoc()) {
        $allCategories[] = $catRow['category'];
    }
    $stmt->close();
}

// ----- Fetch promo banners (Empire only) -----
$banners = [];
$bannerSql = "SELECT pb.*, s.store_name, s.brand_color
              FROM promo_banners pb
              INNER JOIN stores s ON pb.user_id = s.user_id
              INNER JOIN subscriptions sub ON sub.user_id = s.user_id
              WHERE pb.status = 'active' AND s.status = 'active'
                AND sub.plan = 'Empire' AND sub.status = 'active' AND sub.end_date > NOW()
              ORDER BY pb.order_position ASC";
$bannerResult = $conn->query($bannerSql);
if ($bannerResult) {
    while ($row = $bannerResult->fetch_assoc()) {
        $banners[] = $row;
    }
}

// ----- Get selected category from URL -----
$selectedCategory = isset($_GET['category']) ? trim($_GET['category']) : '';

// ----- Search handling -----
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchResults = [];
if (!empty($search)) {
    $allProducts = [];
    foreach ($stores as $store) {
        $prods = getStoreProducts($store['store_pk'], 50);
        $allProducts = array_merge($allProducts, $prods);
    }
    $searchResults = array_filter($allProducts, function($p) use ($search) {
        return stripos($p['name'], $search) !== false || stripos($p['description'] ?? '', $search) !== false;
    });
    $searchResults = array_slice($searchResults, 0, 20);
}

// Pre‑fetch products for each store (with category filter if set)
$storeProducts = [];
foreach ($stores as $store) {
    $prods = getStoreProducts($store['store_pk'], $products_per_store, $selectedCategory);
    if (!empty($prods)) {
        $storeProducts[$store['store_pk']] = $prods;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <?php require __DIR__ . '/includes/adsense_head.php'; ?>
  <title>RD Vendora – Premium Marketplace</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
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
      font-family: 'Segoe UI', system-ui, sans-serif;
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
    }
    .logo a { display: flex; align-items: center; gap: 0.5rem; }
    .logo .rdv-brand-name {
      font-size: 1.05rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--btn-text);
    }
    .logo .rdv-brand-logo,
    .rdv-brand-logo--market {
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

    /* ── NAV ── */
    nav {
      background: var(--btn-bg-dark);
      display: flex;
      gap: 0;
      overflow-x: auto;
      scrollbar-width: none;
      padding: 0 10px;
    }
    nav::-webkit-scrollbar { display: none; }
    nav a {
      color: var(--btn-text);
      text-decoration: none;
      padding: 10px 18px;
      font-size: 13px;
      white-space: nowrap;
      transition: background 0.2s, transform 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 500;
    }
    nav a:hover, nav a.active { background: var(--btn-bg); transform: scale(1.02); }

    /* ── HERO CAROUSEL ── */
    .hero-carousel { position: relative; overflow: hidden; height: 360px; user-select: none; }
    .hero-slide {
      position: absolute;
      inset: 0;
      opacity: 0;
      transform: translateX(60px);
      transition: opacity .55s ease, transform .55s ease;
      pointer-events: none;
    }
    .hero-slide.active { opacity: 1; transform: translateX(0); pointer-events: auto; }
    .hero-slide.exit { opacity: 0; transform: translateX(-60px); }
    .hero-slide-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 100%;
      padding: 0 60px;
      gap: 20px;
    }
    .hero-text { color: #fff; max-width: 480px; flex-shrink: 0; }
    .hero-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255,255,255,.18);
      border: 1px solid rgba(255,255,255,.3);
      border-radius: 20px;
      padding: 4px 14px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 14px;
      letter-spacing: .5px;
      backdrop-filter: blur(4px);
    }
    .flash-tag { background: rgba(249,115,22,.25); border-color: rgba(249,115,22,.5); }
    .hero-text h1 {
      font-size: 40px;
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 12px;
      text-shadow: 0 2px 12px rgba(0,0,0,.2);
    }
    .hero-text p { font-size: 15px; opacity: .88; margin-bottom: 22px; line-height: 1.6; }
    .brand-word  { color: #b8f5d0; }
    .brand-word2 { color: #fff; }
    .highlight-orange { color: #fbbf24; }
    .highlight-mint   { color: #86efac; }
    .hero-cta-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
    .hero-btn-primary {
      background: #fff;
      color: var(--btn-bg-dark);
      border: none;
      padding: 11px 26px;
      border-radius: 5px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      display: flex; align-items: center; gap: 8px;
      transition: transform .15s, box-shadow .15s;
      box-shadow: 0 4px 14px rgba(0,0,0,.2);
    }
    .hero-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.25); }
    .hero-btn-primary.orange-btn { background: #f97316; color: #fff; }
    .hero-btn-secondary {
      background: rgba(255,255,255,.15);
      color: #fff;
      border: 2px solid rgba(255,255,255,.5);
      padding: 11px 22px;
      border-radius: 5px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      backdrop-filter: blur(4px);
      transition: background .2s;
    }
    .hero-btn-secondary:hover { background: rgba(255,255,255,.25); }
    .hero-badges { display: flex; gap: 12px; flex-wrap: wrap; }
    .hero-badges span {
      display: flex; align-items: center; gap: 5px;
      font-size: 12px; opacity: .85;
      background: rgba(0,0,0,.15);
      padding: 4px 10px; border-radius: 12px;
    }
    .mini-countdown {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; font-weight: 700; color: #fff; margin-top: 4px;
    }
    .mini-time {
      background: rgba(0,0,0,.3);
      border-radius: 5px;
      padding: 4px 10px;
      font-family: monospace;
      font-size: 18px;
      min-width: 40px;
      text-align: center;
    }
    .mini-sep { font-size: 18px; opacity: .7; }
    .hero-visual { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; height: 100%; }
    .brand-visual { justify-content: flex-end; padding-right: 20px; }
    .brand-logo-big {
      text-align: center;
      background: rgba(255,255,255,.08);
      border: 2px solid rgba(255,255,255,.2);
      border-radius: 20px;
      padding: 28px 36px;
      backdrop-filter: blur(8px);
      position: relative;
      z-index: 2;
      box-shadow: 0 8px 32px rgba(0,0,0,.2);
    }
    .brand-logo-icon {
      font-size: 52px;
      color: #86efac;
      margin-bottom: 8px;
      animation: leafPulse 2.5s ease-in-out infinite;
    }
    @keyframes leafPulse {
      0%, 100% { transform: scale(1) rotate(-5deg); }
      50%       { transform: scale(1.1) rotate(5deg); }
    }
    .brand-logo-text {
      font-size: 36px;
      font-weight: 900;
      color: #fff;
      letter-spacing: -1px;
      line-height: 1;
    }
    .brand-logo-text em { color: #86efac; font-style: normal; }
    .brand-logo-sub {
      font-size: 11px;
      color: rgba(255,255,255,.65);
      margin-top: 6px;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .floating-circle {
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,.07);
      animation: floatCircle 4s ease-in-out infinite;
    }
    .c1 { width: 120px; height: 120px; top: 10%; right: 5%;  animation-delay: 0s; }
    .c2 { width: 70px;  height: 70px;  bottom: 15%; right: 30%; animation-delay: 1s; }
    .c3 { width: 50px;  height: 50px;  top: 30%; right: 50%; animation-delay: 2s; }
    @keyframes floatCircle {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-16px); }
    }
    .deals-visual { flex-direction: column; gap: 16px; }
    .deal-tag-float {
      font-size: 72px;
      font-weight: 900;
      color: #fbbf24;
      text-shadow: 0 4px 20px rgba(0,0,0,.3);
      line-height: 1;
      animation: pricePop 1.5s ease-in-out infinite alternate;
    }
    @keyframes pricePop {
      from { transform: scale(1) rotate(-3deg); }
      to   { transform: scale(1.08) rotate(3deg); }
    }
    .deal-icons-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .deal-icon-box {
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 12px;
      width: 64px; height: 64px;
      display: flex; align-items: center; justify-content: center;
      font-size: 26px;
      color: #fff;
      backdrop-filter: blur(4px);
      transition: transform .2s;
    }
    .deal-icon-box:hover { transform: scale(1.08); }
    .delivery-truck-wrap {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
    }
    .delivery-truck-icon {
      font-size: 100px;
      color: #fff;
      opacity: .9;
      animation: truckDrive 3s ease-in-out infinite;
      filter: drop-shadow(0 8px 20px rgba(0,0,0,.3));
    }
    @keyframes truckDrive {
      0%, 100% { transform: translateX(0) rotate(0deg); }
      25%       { transform: translateX(10px) rotate(1deg); }
      75%       { transform: translateX(-10px) rotate(-1deg); }
    }
    .delivery-road {
      width: 180px; height: 6px;
      background: rgba(255,255,255,.25);
      border-radius: 3px;
      position: relative;
      overflow: hidden;
    }
    .delivery-road::after {
      content: '';
      position: absolute;
      top: 0; left: -100%;
      width: 60%; height: 100%;
      background: rgba(255,255,255,.6);
      border-radius: 3px;
      animation: roadLine 2s linear infinite;
    }
    @keyframes roadLine {
      to { left: 150%; }
    }
    .delivery-location {
      font-size: 30px;
      color: #fbbf24;
      animation: locBounce 1.2s ease-in-out infinite;
    }
    @keyframes locBounce {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-8px); }
    }
    .carousel-arrow {
      position: absolute;
      top: 50%; transform: translateY(-50%);
      background: rgba(255,255,255,.18);
      border: 2px solid rgba(255,255,255,.35);
      color: #fff;
      width: 42px; height: 42px;
      border-radius: 50%;
      font-size: 16px;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(6px);
      transition: background .2s, transform .2s;
      z-index: 10;
    }
    .carousel-arrow:hover { background: rgba(255,255,255,.35); transform: translateY(-50%) scale(1.1); }
    .prev-arrow { left: 16px; }
    .next-arrow { right: 16px; }
    .carousel-dots {
      position: absolute;
      bottom: 16px; left: 50%;
      transform: translateX(-50%);
      display: flex; gap: 8px; z-index: 10;
    }
    .cdot {
      width: 10px; height: 10px;
      border-radius: 50%;
      background: rgba(255,255,255,.4);
      cursor: pointer;
      transition: background .25s, width .25s;
      border: none;
    }
    .cdot.active { background: #fff; width: 28px; border-radius: 5px; }

    /* ── SECTION HEADERS ── */
    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 20px 10px;
    }
    .section-header h2 {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-primary);
      border-left: 4px solid var(--btn-bg);
      padding-left: 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .section-header h2 img { width: 24px; height: 24px; border-radius: 4px; object-fit: cover; }
    .section-header a {
      color: var(--btn-bg);
      font-size: 13px;
      text-decoration: none;
      font-weight: 600;
      cursor: pointer;
    }
    .section-header a:hover { text-decoration: underline; }

    /* ── FLASH BANNER ── */
    .flash-banner {
      background: linear-gradient(90deg, var(--btn-bg-dark), var(--btn-bg));
      color: var(--btn-text);
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 600;
    }
    .flash-banner i { font-size: 18px; color: #ffd700; }
    .countdown { display: flex; gap: 6px; }
    .countdown span {
      background: rgba(255,255,255,.2);
      border-radius: 4px;
      padding: 2px 8px;
      font-size: 13px;
      font-weight: 700;
      font-family: monospace;
    }

    /* ── PRODUCT GRID ── */
    .products-wrapper {
      padding: 0 20px 10px;
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: var(--btn-bg) transparent;
    }
    .products-row {
      display: flex;
      gap: 12px;
      width: max-content;
    }
    .product-card {
      background: var(--card-bg);
      border-radius: 6px;
      width: 180px;
      overflow: hidden;
      box-shadow: var(--shadow);
      cursor: pointer;
      transition: transform .2s, box-shadow .2s;
      flex-shrink: 0;
      position: relative;
    }
    .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.13); }
    .product-img {
      width: 100%;
      height: 150px;
      background: var(--body-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }
    .product-img a { display: block; width: 100%; height: 100%; }
    .product-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s;
    }
    .product-card:hover .product-img img { transform: scale(1.03); }

    .badge-sale {
      position: absolute;
      top: 8px; left: 8px;
      background: var(--orange);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 3px;
      z-index: 1;
    }
    .badge-new {
      position: absolute;
      top: 8px; left: 8px;
      background: var(--btn-bg);
      color: var(--btn-text);
      font-size: 10px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 3px;
      z-index: 1;
    }
    .wishlist-btn {
      position: absolute;
      top: 8px; right: 8px;
      background: #fff;
      border: none;
      border-radius: 50%;
      width: 28px; height: 28px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      box-shadow: 0 1px 4px rgba(0,0,0,.15);
      color: #bbb;
      font-size: 13px;
      transition: color .2s;
      z-index: 1;
    }
    .wishlist-btn:hover { color: #e74c3c; }
    .wishlist-btn.active { color: #e74c3c; }
    .product-info { padding: 10px; }
    .product-name {
      font-size: 12px;
      color: var(--sidebar-text);
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .product-name a {
      color: inherit;
      text-decoration: none;
    }
    .product-price {
      font-size: 15px;
      font-weight: 700;
      color: var(--text-primary);
    }
    .product-old-price {
      font-size: 11px;
      color: #aaa;
      text-decoration: line-through;
      margin-left: 4px;
    }
    .product-discount {
      font-size: 11px;
      color: var(--orange);
      font-weight: 700;
    }
    .stars { color: #f4c430; font-size: 11px; margin-top: 4px; }
    .stars span { color: var(--sidebar-text); font-size: 10px; margin-left: 2px; }
    .add-cart-btn {
      width: calc(100% - 20px);
      margin: 0 10px 10px;
      padding: 7px;
      background: var(--btn-bg);
      color: var(--btn-text);
      border: none;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: background .2s;
    }
    .add-cart-btn:hover { background: var(--btn-bg-dark); }

    /* ── CATEGORIES ── */
    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 10px;
      padding: 0 20px 20px;
    }
    .category-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 16px 10px;
      text-align: center;
      cursor: pointer;
      box-shadow: var(--shadow);
      transition: transform .2s, box-shadow .2s;
      border: 2px solid transparent;
    }
    .category-card:hover {
      transform: translateY(-3px);
      border-color: var(--btn-bg);
      box-shadow: 0 6px 20px rgba(0,0,0,.1);
    }
    .category-card i { font-size: 28px; color: var(--btn-bg); display: block; margin-bottom: 8px; }
    .category-card span { font-size: 11px; font-weight: 600; color: var(--text-primary); }
    .category-card.active {
      border-color: var(--btn-bg);
      background: var(--btn-bg);
      color: var(--btn-text);
    }
    .category-card.active i { color: var(--btn-text); }
    .category-card.active span { color: var(--btn-text); }

    /* ── ALL STORES – HORIZONTAL SCROLL ── */
    .stores-wrapper {
      padding: 0 20px 20px;
      overflow-x: auto;
      overflow-y: hidden;
      scrollbar-width: thin;
      scrollbar-color: var(--btn-bg) transparent;
      -webkit-overflow-scrolling: touch;
    }
    .stores-wrapper::-webkit-scrollbar {
      height: 6px;
    }
    .stores-wrapper::-webkit-scrollbar-track {
      background: transparent;
    }
    .stores-wrapper::-webkit-scrollbar-thumb {
      background: var(--btn-bg);
      border-radius: 10px;
    }
    .stores-row {
      display: flex;
      gap: 16px;
      width: max-content;
      padding-bottom: 4px;
    }
    .store-card {
      background: var(--card-bg);
      border-radius: 10px;
      padding: 16px 14px;
      text-align: center;
      box-shadow: var(--shadow);
      transition: transform 0.2s, box-shadow 0.2s;
      border: 2px solid transparent;
      cursor: pointer;
      flex-shrink: 0;
      width: 150px;
    }
    .store-card:hover {
      transform: translateY(-5px);
      border-color: var(--btn-bg);
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    }
    .store-card img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 8px;
      border: 2px solid var(--btn-bg);
      padding: 2px;
    }
    .store-card .store-name {
      font-weight: 700;
      font-size: 13px;
      color: var(--text-primary);
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .store-card .store-plan {
      font-size: 10px;
      color: var(--sidebar-text);
      opacity: 0.7;
      margin-bottom: 6px;
    }
    .store-card .visit-btn {
      display: inline-block;
      padding: 4px 14px;
      background: var(--btn-bg);
      color: var(--btn-text);
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.2s;
    }
    .store-card .visit-btn:hover {
      background: var(--btn-bg-dark);
    }

    /* ── MODAL (All Stores) ── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 9999;
      justify-content: center;
      align-items: center;
      padding: 20px;
      backdrop-filter: blur(4px);
    }
    .modal-overlay.active {
      display: flex;
    }
    .modal-container {
      background: var(--card-bg);
      border-radius: 16px;
      max-width: 900px;
      width: 100%;
      max-height: 80vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
    }
    .modal-header {
      padding: 16px 24px;
      background: var(--btn-bg);
      color: var(--btn-text);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-header h3 {
      font-size: 20px;
      font-weight: 700;
    }
    .modal-close {
      background: none;
      border: none;
      color: var(--btn-text);
      font-size: 28px;
      cursor: pointer;
      transition: transform 0.2s;
    }
    .modal-close:hover {
      transform: rotate(90deg);
    }
    .modal-body {
      padding: 24px;
      overflow-y: auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 16px;
    }
    .modal-store-item {
      background: var(--body-bg);
      border-radius: 10px;
      padding: 16px 10px;
      text-align: center;
      transition: transform 0.2s, box-shadow 0.2s;
      cursor: pointer;
    }
    .modal-store-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    .modal-store-item img {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 6px;
      border: 2px solid var(--btn-bg);
      padding: 2px;
    }
    .modal-store-item .s-name {
      font-weight: 600;
      font-size: 13px;
      color: var(--text-primary);
    }
    .modal-store-item .s-plan {
      font-size: 10px;
      color: var(--sidebar-text);
      opacity: 0.7;
    }

    /* ── PROMO BANNERS ── */
    .promo-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      padding: 0 20px 20px;
    }
    .promo-card {
      border-radius: 8px;
      padding: 24px 20px;
      color: #fff;
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }
    .promo-card:nth-child(1) { background: linear-gradient(135deg, var(--btn-bg-dark), var(--btn-bg)); }
    .promo-card:nth-child(2) { background: linear-gradient(135deg, var(--btn-bg-darker), var(--btn-bg-dark)); }
    .promo-card h3 { font-size: 16px; margin-bottom: 6px; }
    .promo-card p { font-size: 12px; opacity: .85; margin-bottom: 14px; }
    .promo-card a {
      background: #fff;
      color: var(--btn-bg-dark);
      padding: 6px 16px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
    }
    .promo-card .promo-icon {
      position: absolute;
      right: -10px; bottom: -10px;
      font-size: 80px;
      opacity: .12;
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

    /* ── CART SIDEBAR ── */
    .cart-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 200;
      display: none;
    }
    .cart-overlay.open { display: block; }
    .cart-sidebar {
      position: fixed;
      top: 0; right: -380px;
      width: 360px;
      height: 100%;
      background: var(--card-bg);
      z-index: 201;
      transition: right .3s;
      display: flex;
      flex-direction: column;
      box-shadow: -4px 0 20px rgba(0,0,0,.15);
    }
    .cart-sidebar.open { right: 0; }
    .cart-header {
      background: var(--btn-bg);
      color: var(--btn-text);
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 700;
      font-size: 16px;
    }
    .cart-header button {
      background: none; border: none; color: var(--btn-text); font-size: 20px; cursor: pointer;
    }
    .cart-items { flex: 1; overflow-y: auto; padding: 16px; }
    .cart-item {
      display: flex;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--grey-light);
    }
    .cart-item-img {
      width: 60px; height: 60px;
      background: var(--body-bg);
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      overflow: hidden;
      padding: 0;
    }
    .cart-item-info { flex: 1; }
    .cart-item-info p { font-size: 13px; margin-bottom: 4px; }
    .cart-item-info strong { color: var(--btn-bg); }
    .cart-item-remove {
      background: none; border: none;
      color: #e74c3c; cursor: pointer; font-size: 16px;
      align-self: center;
    }
    .cart-footer {
      padding: 16px 20px;
      border-top: 1px solid var(--grey-light);
    }
    .cart-total {
      display: flex; justify-content: space-between;
      font-size: 16px; font-weight: 700;
      margin-bottom: 14px;
    }
    .checkout-btn {
      width: 100%;
      padding: 13px;
      background: var(--btn-bg);
      color: var(--btn-text);
      border: none;
      border-radius: 5px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: background .2s;
    }
    .checkout-btn:hover { background: var(--btn-bg-dark); }
    .empty-cart {
      text-align: center;
      padding: 40px 20px;
      color: #aaa;
    }
    .empty-cart i { font-size: 60px; margin-bottom: 16px; color: var(--btn-bg); }

    /* ── TOAST ── */
    .toast {
      position: fixed;
      bottom: 24px; left: 50%;
      transform: translateX(-50%) translateY(80px);
      background: var(--btn-bg);
      color: var(--btn-text);
      padding: 10px 24px;
      border-radius: 30px;
      font-size: 13px;
      font-weight: 600;
      z-index: 300;
      transition: transform .3s;
      box-shadow: 0 4px 16px rgba(0,0,0,.2);
    }
    .toast.show { transform: translateX(-50%) translateY(0); }

    /* ── RESPONSIVE ── */
    @media(max-width: 768px) {
      .hero-carousel { height: auto; min-height: 320px; }
      .hero-slide-inner { flex-direction: column; padding: 28px 20px 56px; text-align: center; justify-content: center; position: relative; z-index: 1; }
      .hero-text h1 { font-size: 26px; }
      .hero-text p { font-size: 13px; }
      .hero-visual { display: none; }
      .hero-slide.has-mobile-bg {
        background-color: var(--btn-bg-dark) !important;
        background-image:
          linear-gradient(160deg, color-mix(in srgb, var(--btn-bg-darker) 82%, transparent) 0%, color-mix(in srgb, var(--btn-bg-dark) 72%, transparent) 45%, color-mix(in srgb, var(--btn-bg) 68%, transparent) 100%),
          var(--hero-mobile-img) !important;
        background-size: cover !important;
        background-position: center center !important;
        background-repeat: no-repeat !important;
      }
      .hero-cta-row { justify-content: center; }
      .hero-badges { justify-content: center; }
      .promo-grid { grid-template-columns: 1fr; }
      .header-actions { gap: 12px; }
      header { flex-wrap: wrap; }
      .search-bar { order: 3; flex-basis: 100%; max-width: 100%; margin: 0; }
      .store-card { width: 130px; padding: 12px; }
      .modal-body { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
      .modal-container { max-width: 100%; max-height: 90vh; }
    }

    /* Fallback for browsers without color-mix */
    @supports not (background: color-mix(in srgb, #000 50%, transparent)) {
      @media(max-width: 768px) {
        .hero-slide.has-mobile-bg {
          background-image:
            linear-gradient(160deg, rgba(10, 46, 24, 0.82) 0%, rgba(22, 101, 52, 0.74) 50%, rgba(39, 168, 90, 0.68) 100%),
            var(--hero-mobile-img) !important;
        }
      }
    }
  </style>
</head>
<body>

<?php if ($top_strip_enabled && $top_strip_text !== ''): ?>
<!-- TOP STRIP -->
<div class="top-strip"><?php
  $stripParts = array_map('trim', explode('|', $top_strip_text));
  $stripParts = array_values(array_filter($stripParts, static fn($p) => $p !== ''));
  echo htmlspecialchars(implode('  |  ', $stripParts));
?></div>
<?php endif; ?>

<!-- HEADER -->
<header>
  <div class="logo"><a href="marketplace"><img class="rdv-brand-logo rdv-brand-logo--market" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span></a></div>
  <div class="search-bar">
    <form method="get" action="" style="display:flex; flex:1; width:100%;">
      <input type="text" name="q" id="searchInput" placeholder="Search products, brands and categories…" value="<?= htmlspecialchars($search) ?>" />
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

<?php if ($categories_nav_enabled): ?>
<!-- NAVIGATION -->
<nav>
  <a href="marketplace" class="<?= empty($selectedCategory) ? 'active' : '' ?>"><i class="fas fa-th"></i> All Categories</a>
  <?php foreach ($allCategories as $cat): ?>
    <a href="?category=<?= urlencode($cat) ?>" class="<?= $selectedCategory === $cat ? 'active' : '' ?>"><i class="fas fa-tag"></i> <?= htmlspecialchars($cat) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<!-- HERO CAROUSEL -->
<?php
$slides = [];
if ($hero_enabled) {
  $adminHeroSlides = [
    [
      'enabled' => true,
      'image' => $hero_image,
      'tag' => $hero_tag,
      'title' => $hero_title,
      'subtitle' => $hero_subtitle,
      'btn_text' => $hero_btn_text,
      'btn_link' => $hero_btn_link,
      'icon' => 'fa-leaf',
      'btn_class' => 'hero-btn-primary',
      'btn_icon' => 'fa-arrow-right',
      'badges' => ['<i class="fas fa-truck"></i> Free Delivery', '<i class="fas fa-shield-alt"></i> 100% Genuine', '<i class="fas fa-undo"></i> Easy Returns'],
      'fallback_visual' => '
        <div class="brand-logo-big">
          <img class="rdv-brand-logo" src="assets/brand-logo.png" alt="RD Vendora" style="height:88px;width:auto;max-width:260px;object-fit:contain;background:#fff;border-radius:12px;padding:8px 12px;margin:0 auto;">
          <div class="brand-logo-sub">Premium Marketplace</div>
        </div>
        <div class="floating-circle c1"></div>
        <div class="floating-circle c2"></div>
        <div class="floating-circle c3"></div>
      ',
    ],
    [
      'enabled' => $hero2_enabled,
      'image' => $hero2_image,
      'tag' => $hero2_tag,
      'title' => $hero2_title,
      'subtitle' => $hero2_subtitle,
      'btn_text' => $hero2_btn_text,
      'btn_link' => $hero2_btn_link,
      'icon' => 'fa-bolt',
      'btn_class' => 'hero-btn-primary orange-btn',
      'btn_icon' => 'fa-fire',
      'badges' => [],
      'fallback_visual' => '
        <div class="deal-tag-float">-60%</div>
        <div class="deal-icons-grid">
          <div class="deal-icon-box"><i class="fas fa-mobile-alt"></i></div>
          <div class="deal-icon-box"><i class="fas fa-laptop"></i></div>
          <div class="deal-icon-box"><i class="fas fa-tv"></i></div>
          <div class="deal-icon-box"><i class="fas fa-headphones"></i></div>
        </div>
      ',
    ],
    [
      'enabled' => $hero3_enabled,
      'image' => $hero3_image,
      'tag' => $hero3_tag,
      'title' => $hero3_title,
      'subtitle' => $hero3_subtitle,
      'btn_text' => $hero3_btn_text,
      'btn_link' => $hero3_btn_link,
      'icon' => 'fa-truck',
      'btn_class' => 'hero-btn-primary',
      'btn_icon' => 'fa-map-marker-alt',
      'badges' => ['<i class="fas fa-clock"></i> Same-Day Lagos', '<i class="fas fa-map-marker-alt"></i> 36 States'],
      'fallback_visual' => '
        <div class="delivery-truck-wrap">
          <i class="fas fa-truck delivery-truck-icon"></i>
          <div class="delivery-road"></div>
          <div class="delivery-location"><i class="fas fa-map-marker-alt"></i></div>
        </div>
      ',
    ],
  ];

  foreach ($adminHeroSlides as $hs) {
    if (empty($hs['enabled'])) {
      continue;
    }
    $btnHref = htmlspecialchars(mp_public_href($hs['btn_link']), ENT_QUOTES, 'UTF-8');
    $visual = $hs['fallback_visual'];
    if (!empty($hs['image'])) {
      $imgSrc = htmlspecialchars(function_exists('rdv_asset') ? rdv_asset($hs['image']) : $hs['image'], ENT_QUOTES, 'UTF-8');
      $visual = '<img src="'.$imgSrc.'" alt="'.htmlspecialchars($hs['title'], ENT_QUOTES, 'UTF-8').'" style="max-height:220px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.3);object-fit:cover;" />';
    }
    $slides[] = [
      'bg' => 'linear-gradient(120deg, var(--btn-bg-darker) 0%, var(--btn-bg-dark) 45%, var(--btn-bg) 100%)',
      'bg_image' => !empty($hs['image']) ? (function_exists('rdv_asset') ? rdv_asset($hs['image']) : $hs['image']) : '',
      'tag' => '<i class="fas '.$hs['icon'].'"></i> ' . htmlspecialchars($hs['tag']),
      'title' => htmlspecialchars($hs['title']),
      'desc' => nl2br(htmlspecialchars($hs['subtitle'])),
      'btns' => [
        ['text' => htmlspecialchars($hs['btn_text']), 'icon' => $hs['btn_icon'], 'class' => $hs['btn_class'], 'onclick' => "window.location.href='{$btnHref}'"],
      ],
      'badges' => $hs['badges'],
      'visual' => $visual,
    ];
  }

  // Empire store promo banners still append after admin slides
  if (!empty($banners)) {
    foreach ($banners as $banner) {
      $slides[] = [
        'bg' => 'linear-gradient(120deg, var(--btn-bg-darker) 0%, var(--btn-bg-dark) 50%, var(--btn-bg) 100%)',
        'bg_image' => !empty($banner['image']) ? $banner['image'] : '',
        'tag' => '<i class="fas fa-crown"></i> ' . htmlspecialchars($banner['store_name']),
        'title' => htmlspecialchars($banner['title'] ?? 'Special Offer'),
        'desc' => htmlspecialchars($banner['description'] ?? ''),
        'btns' => [
          ['text' => 'Shop Now', 'icon' => 'fa-arrow-right', 'class' => 'hero-btn-primary orange-btn', 'onclick' => "window.location.href='".htmlspecialchars($banner['link'] ?? '#')."'"]
        ],
        'badges' => [],
        'visual' => '<img src="'.htmlspecialchars($banner['image']).'" style="max-height:200px; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,.3);" />'
      ];
    }
  }
}
?>
<?php if (!empty($slides)): ?>
<div class="hero-carousel" id="heroCarousel">
  <?php foreach ($slides as $index => $slide):
    $slideStyle = 'background: ' . $slide['bg'] . ';';
    $hasMobileBg = !empty($slide['bg_image']);
    if ($hasMobileBg) {
      $slideStyle .= '--hero-mobile-img: url(' . htmlspecialchars(json_encode($slide['bg_image'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . ');';
    }
  ?>
    <div class="hero-slide <?= $index === 0 ? 'active' : '' ?><?= $hasMobileBg ? ' has-mobile-bg' : '' ?>" style="<?= $slideStyle ?>">
      <div class="hero-slide-inner">
        <div class="hero-text">
          <div class="hero-tag <?= strpos($slide['tag'], 'Flash') !== false ? 'flash-tag' : '' ?>"><?= $slide['tag'] ?></div>
          <h1><?= $slide['title'] ?></h1>
          <p><?= $slide['desc'] ?></p>
          <div class="hero-cta-row">
            <?php foreach ($slide['btns'] as $btn): ?>
              <button class="<?= $btn['class'] ?>" onclick="<?= $btn['onclick'] ?>">
                <?= $btn['text'] ?> <?php if ($btn['icon']): ?><i class="fas <?= $btn['icon'] ?>"></i><?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($slide['badges'])): ?>
            <div class="hero-badges">
              <?php foreach ($slide['badges'] as $badge): ?>
                <span><?= $badge ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="hero-visual <?= strpos($slide['visual'], 'brand-logo-big') !== false ? 'brand-visual' : '' ?> <?= strpos($slide['visual'], 'deal-tag-float') !== false ? 'deals-visual' : '' ?> <?= strpos($slide['visual'], 'delivery-truck-wrap') !== false ? 'delivery-visual' : '' ?>">
          <?= $slide['visual'] ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (count($slides) > 1): ?>
    <button class="carousel-arrow prev-arrow" onclick="carouselMove(-1)"><i class="fas fa-chevron-left"></i></button>
    <button class="carousel-arrow next-arrow" onclick="carouselMove(1)"><i class="fas fa-chevron-right"></i></button>
    <div class="carousel-dots" id="carouselDots">
      <?php foreach ($slides as $index => $slide): ?>
        <span class="cdot <?= $index === 0 ? 'active' : '' ?>" onclick="carouselGoTo(<?= $index ?>)"></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($categories_section_enabled): ?>
<!-- SHOP BY CATEGORY -->
<div class="section-header">
  <h2><?= htmlspecialchars($categories_section_title) ?></h2>
  <a href="marketplace">View all <i class="fas fa-chevron-right"></i></a>
</div>
<div class="categories-grid">
  <?php foreach ($allCategories as $cat): ?>
    <div class="category-card <?= $selectedCategory === $cat ? 'active' : '' ?>" onclick="window.location.href='?category=<?= urlencode($cat) ?>'">
      <i class="fas fa-tag"></i>
      <span><?= htmlspecialchars($cat) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (empty($allCategories)): ?>
    <div class="category-card"><i class="fas fa-store"></i><span>No categories</span></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── ALL STORES – HORIZONTAL SCROLL ── -->
<?php if ($stores_section_enabled && !empty($stores) && empty($search)): ?>
<div class="section-header">
  <h2><i class="fas fa-store-alt" style="color:var(--btn-bg);"></i> <?= htmlspecialchars($stores_section_title) ?></h2>
  <a onclick="openStoreModal()">See all <i class="fas fa-chevron-right"></i></a>
</div>
<div class="stores-wrapper">
  <div class="stores-row">
    <?php foreach ($stores as $store): ?>
      <div class="store-card" onclick="window.location.href='<?= htmlspecialchars(rdv_store_url(['id' => (int)$store['store_pk'], 'store_slug' => (string)($store['store_slug'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>'">
        <?php if (!empty($store['logo_path'])): ?>
          <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?>" />
        <?php else: ?>
          <div style="width:60px;height:60px;border-radius:50%;background:var(--btn-bg);margin:0 auto 8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;">
            <i class="fas fa-store"></i>
          </div>
        <?php endif; ?>
        <div class="store-name"><?= htmlspecialchars($store['store_name']) ?></div>
        <div class="store-plan"><?= htmlspecialchars($store['plan']) ?> Plan</div>
        <a href="<?= htmlspecialchars(rdv_store_url(['id' => (int)$store['store_pk'], 'store_slug' => (string)($store['store_slug'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>" class="visit-btn">Visit Store</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($flash_banner_enabled): ?>
<!-- FLASH DEAL COUNTDOWN BANNER -->
<div class="flash-banner">
  <i class="fas fa-bolt"></i>
  <strong><?= htmlspecialchars($flash_banner_title) ?></strong>
  &mdash; Ends in:
  <div class="countdown">
    <span id="hours"><?= str_pad((string) $flash_banner_hours, 2, '0', STR_PAD_LEFT) ?></span>
    <span>:</span>
    <span id="minutes"><?= str_pad((string) $flash_banner_minutes, 2, '0', STR_PAD_LEFT) ?></span>
    <span>:</span>
    <span id="seconds">00</span>
  </div>
</div>
<?php endif; ?>


<?php if (!empty($search)): ?>
  <!-- SEARCH RESULTS -->
  <div class="section-header">
    <h2><i class="fas fa-search" style="color:var(--btn-bg);"></i> Search Results for "<?= htmlspecialchars($search) ?>"</h2>
    <a href="marketplace">Clear search</a>
  </div>
  <div class="products-wrapper">
    <div class="products-row">
      <?php if (empty($searchResults)): ?>
        <div style="padding:20px;text-align:center;color:var(--sidebar-text);">No products found for your search.</div>
      <?php else: ?>
        <?php foreach ($searchResults as $p):
          $discount = (isset($p['old_price']) && $p['old_price'] > 0) ? round((1 - $p['price'] / $p['old_price']) * 100) : 0;
          $store_pk = $p['store_pk'] ?? 0;
          $store_name = $p['store_name'] ?? 'Unknown Store';
        ?>
          <div class="product-card" data-id="<?= $p['id'] ?>" data-store-id="<?= $store_pk ?>" data-store-name="<?= htmlspecialchars($store_name) ?>" data-price="<?= $p['price'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-image="<?= htmlspecialchars($p['image'] ?? '') ?>">
            <div class="product-img">
              <?php if ($discount > 0): ?><span class="badge-sale">-<?= $discount ?>%</span><?php endif; ?>
              <button class="wishlist-btn" onclick="toggleWish(<?= $p['id'] ?>, event)"><i class="far fa-heart"></i></button>
              <a href="marketplaceviewproduct?id=<?= $p['id'] ?>">
                <img src="<?= htmlspecialchars($p['image'] ?? 'https://placehold.co/300x300?text=No+Image') ?>" alt="<?= htmlspecialchars($p['name']) ?>" />
              </a>
            </div>
            <div class="product-info">
              <div class="product-name"><a href="marketplaceviewproduct?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></div>
              <div>
                <span class="product-price">₦<?= number_format($p['price'], 0) ?></span>
                <?php if (isset($p['old_price']) && $p['old_price'] > 0): ?>
                  <span class="product-old-price">₦<?= number_format($p['old_price'], 0) ?></span>
                <?php endif; ?>
              </div>
              <?php if (isset($p['old_price']) && $p['old_price'] > 0): ?>
                <div class="product-discount">Save ₦<?= number_format($p['old_price'] - $p['price'], 0) ?></div>
              <?php endif; ?>
              <div class="stars">★★★★★ <span>(<?= rand(10, 200) ?>)</span></div>
            </div>
            <button class="add-cart-btn" onclick="addToCart(<?= $p['id'] ?>, <?= $store_pk ?>, '<?= htmlspecialchars($store_name) ?>', '<?= htmlspecialchars($p['name']) ?>', <?= $p['price'] ?>, '<?= htmlspecialchars($p['image'] ?? '') ?>', event)">
              <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php elseif ($products_section_enabled): ?>
  <!-- ── STORE PRODUCT SECTIONS (filtered by category if selected) ── -->
  <?php if (!empty($selectedCategory)): ?>
    <div class="section-header" style="margin-top:20px">
      <h2><i class="fas fa-filter" style="color:var(--btn-bg);"></i> Products in "<?= htmlspecialchars($selectedCategory) ?>"</h2>
      <a href="marketplace">Clear filter <i class="fas fa-times"></i></a>
    </div>
  <?php endif; ?>

  <?php
  $hasProducts = false;
  foreach ($stores as $store):
    $prods = isset($storeProducts[$store['store_pk']]) ? $storeProducts[$store['store_pk']] : [];
    if (empty($prods)) continue;
    $hasProducts = true;
  ?>
    <div class="section-header" style="margin-top:20px">
      <h2>
        <?php if (!empty($store['logo_path'])): ?>
          <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?>" />
        <?php else: ?>
          <i class="fas fa-store" style="color:var(--btn-bg);"></i>
        <?php endif; ?>
        <?= htmlspecialchars($store['store_name']) ?>
        <?php if (!empty($selectedCategory)): ?>
          <span style="font-size:12px;font-weight:400;color:var(--sidebar-text);">(<?= htmlspecialchars($selectedCategory) ?>)</span>
        <?php endif; ?>
      </h2>
      <a href="<?= htmlspecialchars(rdv_store_url(['id' => (int)$store['store_pk'], 'store_slug' => (string)($store['store_slug'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>">See all <i class="fas fa-chevron-right"></i></a>
    </div>
    <div class="products-wrapper">
      <div class="products-row">
        <?php foreach ($prods as $p):
          $discount = (isset($p['old_price']) && $p['old_price'] > 0) ? round((1 - $p['price'] / $p['old_price']) * 100) : 0;
        ?>
          <div class="product-card" data-id="<?= $p['id'] ?>" data-store-id="<?= $store['store_pk'] ?>" data-store-name="<?= htmlspecialchars($store['store_name']) ?>" data-price="<?= $p['price'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-image="<?= htmlspecialchars($p['image'] ?? '') ?>">
            <div class="product-img">
              <?php if ($discount > 0): ?><span class="badge-sale">-<?= $discount ?>%</span><?php endif; ?>
              <button class="wishlist-btn" onclick="toggleWish(<?= $p['id'] ?>, event)"><i class="far fa-heart"></i></button>
              <a href="marketplaceviewproduct?id=<?= $p['id'] ?>">
                <img src="<?= htmlspecialchars($p['image'] ?? 'https://placehold.co/300x300?text=No+Image') ?>" alt="<?= htmlspecialchars($p['name']) ?>" />
              </a>
            </div>
            <div class="product-info">
              <div class="product-name"><a href="marketplaceviewproduct?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></div>
              <div>
                <span class="product-price">₦<?= number_format($p['price'], 0) ?></span>
                <?php if (isset($p['old_price']) && $p['old_price'] > 0): ?>
                  <span class="product-old-price">₦<?= number_format($p['old_price'], 0) ?></span>
                <?php endif; ?>
              </div>
              <?php if (isset($p['old_price']) && $p['old_price'] > 0): ?>
                <div class="product-discount">Save ₦<?= number_format($p['old_price'] - $p['price'], 0) ?></div>
              <?php endif; ?>
              <div class="stars">★★★★★ <span>(<?= rand(10, 200) ?>)</span></div>
            </div>
            <button class="add-cart-btn" onclick="addToCart(<?= $p['id'] ?>, <?= $store['store_pk'] ?>, '<?= htmlspecialchars($store['store_name']) ?>', '<?= htmlspecialchars($p['name']) ?>', <?= $p['price'] ?>, '<?= htmlspecialchars($p['image'] ?? '') ?>', event)">
              <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$hasProducts && !empty($selectedCategory)): ?>
    <div style="padding:40px;text-align:center;color:var(--sidebar-text);">
      <i class="fas fa-box-open" style="font-size:48px;color:var(--btn-bg);"></i>
      <p style="margin-top:12px;">No products found in category "<strong><?= htmlspecialchars($selectedCategory) ?></strong>".</p>
      <a href="marketplace" style="color:var(--btn-bg);text-decoration:underline;">Clear filter</a>
    </div>
  <?php endif; ?>

  <?php if (empty($stores) && empty($selectedCategory)): ?>
    <div style="padding:40px;text-align:center;color:var(--sidebar-text);">
      <i class="fas fa-store-slash" style="font-size:48px;color:var(--btn-bg);"></i>
      <p style="margin-top:12px;">No active stores available at the moment.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- ── PROMO BANNERS (NOW DYNAMIC & CONDITIONAL) ── -->
<?php if ($promo1_enabled == '1' || $promo2_enabled == '1'): ?>
<div class="promo-grid">
    <?php if ($promo1_enabled == '1'): ?>
    <div class="promo-card">
        <h3><?= htmlspecialchars($promo1_title) ?></h3>
        <p><?= htmlspecialchars($promo1_subtitle) ?></p>
        <a href="<?= htmlspecialchars($promo1_link) ?>"><?= htmlspecialchars($promo1_btn_text) ?></a>
        <div class="promo-icon"><i class="fas fa-laptop"></i></div>
    </div>
    <?php endif; ?>
    <?php if ($promo2_enabled == '1'): ?>
    <div class="promo-card">
        <h3><?= htmlspecialchars($promo2_title) ?></h3>
        <p><?= htmlspecialchars($promo2_subtitle) ?></p>
        <a href="<?= htmlspecialchars($promo2_link) ?>"><?= htmlspecialchars($promo2_btn_text) ?></a>
        <div class="promo-icon"><i class="fas fa-tshirt"></i></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── MODAL (All Stores) ── -->
<div class="modal-overlay" id="storeModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3><i class="fas fa-store-alt"></i> <?= htmlspecialchars($stores_section_title) ?></h3>
      <button class="modal-close" onclick="closeStoreModal()">&times;</button>
    </div>
    <div class="modal-body" id="modalBody">
      <?php foreach ($stores as $store): ?>
        <div class="modal-store-item" onclick="window.location.href='<?= htmlspecialchars(rdv_store_url(['id' => (int)$store['store_pk'], 'store_slug' => (string)($store['store_slug'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>'">
          <?php if (!empty($store['logo_path'])): ?>
            <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?>" />
          <?php else: ?>
            <div style="width:56px;height:56px;border-radius:50%;background:var(--btn-bg);margin:0 auto 6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;">
              <i class="fas fa-store"></i>
            </div>
          <?php endif; ?>
          <div class="s-name"><?= htmlspecialchars($store['store_name']) ?></div>
          <div class="s-plan"><?= htmlspecialchars($store['plan']) ?> Plan</div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- FOOTER -->
<?php if ($footer_enabled): ?>
<footer>
  <div class="footer-grid">
    <?php foreach ($footer_cols as $col): ?>
    <div class="footer-col">
      <h4><?= htmlspecialchars($col['title']) ?></h4>
      <?php foreach ($col['links'] as $link): ?>
      <a href="<?= htmlspecialchars(mp_public_href($link['url'])) ?>"><?= htmlspecialchars($link['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="footer-bottom">
    <span><?= htmlspecialchars(str_replace('{year}', date('Y'), $footer_copyright)) ?></span>
    <div class="social-links">
      <a href="<?= htmlspecialchars(mp_public_href($footer_facebook)) ?>"><i class="fab fa-facebook-f"></i></a>
      <a href="<?= htmlspecialchars(mp_public_href($footer_twitter)) ?>"><i class="fab fa-twitter"></i></a>
      <a href="<?= htmlspecialchars(mp_public_href($footer_instagram)) ?>"><i class="fab fa-instagram"></i></a>
      <a href="<?= htmlspecialchars(mp_public_href($footer_whatsapp)) ?>"><i class="fab fa-whatsapp"></i></a>
      <a href="<?= htmlspecialchars(mp_public_href($footer_youtube)) ?>"><i class="fab fa-youtube"></i></a>
    </div>
  </div>
</footer>
<?php endif; ?>

<!-- CART SIDEBAR OVERLAY -->
<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
<div class="cart-sidebar" id="cartSidebar">
  <div class="cart-header">
    <span><i class="fas fa-shopping-cart"></i> My Cart</span>
    <button onclick="toggleCart()"><i class="fas fa-times"></i></button>
  </div>
  <div class="cart-items" id="cartItems"></div>
  <div class="cart-footer" id="cartFooter" style="display:none">
    <div class="cart-total">
      <span>Total</span>
      <span id="cartTotal">₦0</span>
    </div>
    <button class="checkout-btn" onclick="showToast('Proceeding to checkout…'); toggleCart()">
      <i class="fas fa-lock"></i> Checkout
    </button>
  </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
/* ── CART FUNCTIONS ── */
const CART_KEY = "greenshop_cart";

function getCart() {
    const c = localStorage.getItem(CART_KEY);
    return c ? JSON.parse(c) : [];
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartUI();
}

function updateCartUI() {
    const cart = getCart();
    const total = cart.reduce((s, item) => s + (item.price * item.quantity), 0);
    const count = cart.reduce((s, item) => s + item.quantity, 0);
    document.getElementById('cartCount').textContent = count;

    // Update sidebar
    const sidebarItems = document.getElementById('cartItems');
    const sidebarFooter = document.getElementById('cartFooter');
    if (sidebarItems) {
        if (cart.length === 0) {
            sidebarItems.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-cart"></i><p>Your cart is empty</p></div>`;
            if (sidebarFooter) sidebarFooter.style.display = 'none';
        } else {
            let html = '';
            cart.forEach(item => {
                html += `<div class="cart-item"><div class="cart-item-img"><img src="${item.image || 'https://placehold.co/60x60'}" alt="${item.name}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;"></div><div class="cart-item-info"><p>${item.name}</p><strong>₦${item.price.toLocaleString()}</strong> × ${item.quantity}</div><button class="cart-item-remove" onclick="removeFromCart(${item.product_id})"><i class="fas fa-trash"></i></button></div>`;
            });
            sidebarItems.innerHTML = html;
            if (sidebarFooter) {
                document.getElementById('cartTotal').textContent = '₦' + total.toLocaleString();
                sidebarFooter.style.display = 'block';
            }
        }
    }
}

function addToCart(product_id, store_id, store_name, name, price, image, event) {
    if (event) event.stopPropagation();
    let cart = getCart();
    store_id = parseInt(store_id);
    const existing = cart.find(item => item.product_id === product_id && item.store_id === store_id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            product_id: product_id,
            store_id: store_id,
            store_name: store_name || 'Unknown Store',
            name: name,
            price: parseFloat(price),
            image: image || 'https://placehold.co/400x400?text=No+Image',
            quantity: 1
        });
    }
    saveCart(cart);
    showToast(`✅ ${name} added to cart`);
}

function removeFromCart(product_id) {
    let cart = getCart();
    cart = cart.filter(item => item.product_id !== product_id);
    saveCart(cart);
    updateCartUI();
    showToast('Item removed', 'success');
}

function toggleWish(id, event) {
    if (event) event.stopPropagation();
    const btn = document.querySelector(`.product-card[data-id="${id}"] .wishlist-btn`);
    if (!btn) return;
    const wished = new Set();
    // We need to maintain wishlist state – using a simple toggle per product
    // But we also need to persist wishes. For now, we use a Set in memory.
    // Better to use localStorage, but we'll keep it simple.
    // Actually we should use the global `wished` set defined below.
    // We'll move the Set outside.
}

// We'll use a global Set for wishlist
const wished = new Set();

// Override toggleWish to use the global Set
function toggleWish(id, event) {
    if (event) event.stopPropagation();
    const btn = document.querySelector(`.product-card[data-id="${id}"] .wishlist-btn`);
    if (!btn) return;
    if (wished.has(id)) {
        wished.delete(id);
        btn.classList.remove('active');
        btn.innerHTML = '<i class="far fa-heart"></i>';
        showToast('Removed from wishlist');
    } else {
        wished.add(id);
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-heart"></i>';
        showToast('❤️ Added to wishlist');
    }
}

function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.backgroundColor = type === 'success' ? '#10b981' : '#ef4444';
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2800);
}

function toggleCart() {
    document.getElementById('cartSidebar').classList.toggle('open');
    document.getElementById('cartOverlay').classList.toggle('open');
}

// ── COUNTDOWN ──
<?php if ($flash_banner_enabled): ?>
let totalSeconds = <?= (int) $flash_banner_hours ?> * 3600 + <?= (int) $flash_banner_minutes ?> * 60;
setInterval(() => {
    if (totalSeconds <= 0) return;
    totalSeconds--;
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');
    if (!hoursEl || !minutesEl || !secondsEl) return;
    hoursEl.textContent   = String(h).padStart(2,'0');
    minutesEl.textContent = String(m).padStart(2,'0');
    secondsEl.textContent = String(s).padStart(2,'0');
}, 1000);
<?php endif; ?>

// ── CAROUSEL ──
<?php if (count($slides) > 1): ?>
const slides = document.querySelectorAll('.hero-slide');
const cdots  = document.querySelectorAll('.cdot');
let currentSlide = 0;
let carouselTimer;

function carouselGoTo(index) {
    slides[currentSlide].classList.remove('active');
    slides[currentSlide].classList.add('exit');
    setTimeout(() => slides[currentSlide].classList.remove('exit'), 600);
    cdots[currentSlide].classList.remove('active');
    currentSlide = (index + slides.length) % slides.length;
    slides[currentSlide].classList.add('active');
    cdots[currentSlide].classList.add('active');
    setTimeout(() => {
        document.querySelectorAll('.hero-slide.exit').forEach(s => s.classList.remove('exit'));
    }, 600);
}

function carouselMove(dir) {
    clearInterval(carouselTimer);
    carouselGoTo(currentSlide + dir);
    startCarouselAuto();
}

function startCarouselAuto() {
    carouselTimer = setInterval(() => carouselGoTo(currentSlide + 1), 4500);
}

startCarouselAuto();
<?php endif; ?>

// ── MODAL ──
function openStoreModal() {
    document.getElementById('storeModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeStoreModal() {
    document.getElementById('storeModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('storeModal').addEventListener('click', function(e) {
    if (e.target === this) closeStoreModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeStoreModal();
});

// ── INIT ──
document.addEventListener('DOMContentLoaded', function() {
    updateCartUI();
});
</script>
<div id="rdv-cookie-root"></div>
<script src="assets/js/rdv-public.js" defer></script>
</body>
</html>