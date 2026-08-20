<?php
session_start();
require_once 'includes/connection.php';
require_once __DIR__ . '/app/helpers/marketplace_urls.php';

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

// ----- Hero Settings -----
$hero_image = getMarketplaceSetting('hero_image', '');
$hero_title = getMarketplaceSetting('hero_title', 'Discover products from businesses on RD Vendora');
$hero_subtitle = getMarketplaceSetting('hero_subtitle', 'Browse freely. Add to cart. Checkout as a guest — no account required to shop.');
$hero_btn_text = getMarketplaceSetting('hero_btn_text', 'Start shopping');
$hero_btn_link = getMarketplaceSetting('hero_btn_link', '#');

// ----- Promotional Banners -----
$promo1_title = getMarketplaceSetting('promo1_title', 'Up to 50% Off Electronics');
$promo1_subtitle = getMarketplaceSetting('promo1_subtitle', 'Limited time offer on top brands');
$promo1_link = getMarketplaceSetting('promo1_link', '#');
$promo1_enabled = getMarketplaceSetting('promo1_enabled', '1');
$promo2_title = getMarketplaceSetting('promo2_title', 'New Arrivals in Fashion');
$promo2_subtitle = getMarketplaceSetting('promo2_subtitle', 'Fresh styles every week');
$promo2_link = getMarketplaceSetting('promo2_link', '#');
$promo2_enabled = getMarketplaceSetting('promo2_enabled', '1');

// ----- Color Settings -----
$body_bg_color = getMarketplaceSetting('body_bg_color', '#f3f5f9');
$text_primary_color = getMarketplaceSetting('text_primary_color', '#0f172a');
$primary_btn_bg = getMarketplaceSetting('primary_btn_bg', '#0A3D91');
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
    $prods = getStoreProducts($store['store_pk'], 10, $selectedCategory);
    if (!empty($prods)) {
        $storeProducts[$store['store_pk']] = $prods;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/adsense_head.php'; ?>
  <title>RD Vendora Marketplace</title>
  <meta name="description" content="Shop products from independent businesses on RD Vendora. Browse, add to cart, and checkout as a guest.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/css/marketplace.css') : 'assets/css/marketplace.css', ENT_QUOTES, 'UTF-8') ?>">
  <style>
    :root {
      --body-bg: <?= htmlspecialchars($body_bg_color) ?>;
      --text-primary: <?= htmlspecialchars($text_primary_color) ?>;
      --btn-bg: <?= htmlspecialchars($primary_btn_bg) ?>;
      --btn-text: <?= htmlspecialchars($primary_btn_text) ?>;
      --card-bg: <?= htmlspecialchars($card_bg_color) ?>;
      --sidebar-bg: <?= htmlspecialchars($sidebar_bg_color) ?>;
      --sidebar-text: <?= htmlspecialchars($sidebar_text_color) ?>;
      --btn-bg-dark: <?= htmlspecialchars($btn_bg_dark) ?>;
      --btn-bg-darker: <?= htmlspecialchars($btn_bg_darker) ?>;
    }
  </style>
</head>
<body class="mp-page">
<?php
$mpActive = 'home';
$mpSearch = $search;
$mpCategories = $allCategories;
$mpSelectedCategory = $selectedCategory;
$mpShowCategories = true;
require __DIR__ . '/includes/marketplace_header.php';

$heroBtnHref = $hero_btn_link;
if ($heroBtnHref === '' || $heroBtnHref === '#') {
    $heroBtnHref = '#mp-products';
}
$heroImage = $hero_image;
if ($heroImage === '' && !empty($banners[0]['image'])) {
    $heroImage = $banners[0]['image'];
}
?>

<section class="mp-hero" aria-label="Marketplace highlights">
  <div class="mp-hero-inner">
    <div>
      <div class="mp-hero-kicker">RD Vendora Marketplace</div>
      <h1><?= htmlspecialchars($hero_title) ?></h1>
      <p><?= htmlspecialchars($hero_subtitle) ?></p>
      <div class="mp-hero-actions">
        <a class="mp-btn mp-btn-primary" href="<?= htmlspecialchars($heroBtnHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($hero_btn_text) ?></a>
        <a class="mp-btn mp-btn-ghost" href="#mp-categories">Browse categories</a>
      </div>
    </div>
    <?php if ($heroImage !== ''): ?>
    <div class="mp-hero-visual">
      <img src="<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>" alt="" width="640" height="400">
    </div>
    <?php else: ?>
    <div class="mp-hero-visual" aria-hidden="true">
      <img src="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/brand-logo.png') : 'assets/brand-logo.png', ENT_QUOTES, 'UTF-8') ?>" alt="" style="object-fit:contain;background:#fff;padding:2rem;">
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="mp-flash" role="status">
  <i class="fas fa-bolt" aria-hidden="true"></i>
  <strong>Today’s marketplace picks</strong>
  <span class="mp-countdown" id="mpCountdown">04:37:00</span>
</div>

<?php if (!empty($stores) && $search === ''): ?>
<div class="mp-section">
  <div class="mp-section-head">
    <h2>Featured stores</h2>
    <a href="#mp-products">Shop products</a>
  </div>
  <div class="mp-stores">
    <?php foreach ($stores as $store): ?>
      <a class="mp-store-chip" href="<?= htmlspecialchars(rdv_store_url(['id' => (int)$store['store_pk'], 'store_slug' => (string)($store['store_slug'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($store['logo_path'])): ?>
          <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="">
        <?php else: ?>
          <div class="mp-store-fallback"><i class="fas fa-store"></i></div>
        <?php endif; ?>
        <strong><?= htmlspecialchars($store['store_name']) ?></strong>
        <span><?= htmlspecialchars((string)($store['plan'] ?? 'Store')) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mp-toolbar" id="mp-products">
  <h2>
    <?php if ($search !== ''): ?>
      Results for “<?= htmlspecialchars($search) ?>”
    <?php elseif ($selectedCategory !== ''): ?>
      <?= htmlspecialchars($selectedCategory) ?>
    <?php else: ?>
      Popular products
    <?php endif; ?>
  </h2>
  <div class="mp-toolbar-actions">
    <button type="button" class="mp-chip-btn" data-open="filter"><i class="fas fa-sliders-h" aria-hidden="true"></i> Filter</button>
    <button type="button" class="mp-chip-btn" data-open="sort"><i class="fas fa-arrow-down-wide-short" aria-hidden="true"></i> Sort</button>
    <div class="mp-sort-desktop">
      <label class="mp-sr-only" for="mpSortDesktop">Sort products</label>
      <select id="mpSortDesktop">
        <option value="featured">Featured</option>
        <option value="price-asc">Price: Low to High</option>
        <option value="price-desc">Price: High to Low</option>
        <option value="name-asc">Name: A–Z</option>
      </select>
    </div>
  </div>
</div>

<?php if ($search !== ''): ?>
  <div class="mp-section">
    <div class="mp-product-grid" id="mpProductGrid" data-sortable>
      <?php if (empty($searchResults)): ?>
        <div class="mp-empty" style="grid-column:1/-1">
          <i class="fas fa-search"></i>
          <p>No products matched your search.</p>
          <a class="mp-btn mp-btn-outline" href="<?= htmlspecialchars(rdv_marketplace_url(), ENT_QUOTES, 'UTF-8') ?>">Clear search</a>
        </div>
      <?php else: ?>
        <?php foreach ($searchResults as $p):
          $storePk = (int)($p['store_pk'] ?? 0);
          $storeName = (string)($p['store_name'] ?? 'Store');
          require __DIR__ . '/includes/marketplace_product_card.php';
        endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <?php
  $hasProducts = false;
  foreach ($stores as $store):
    $prods = $storeProducts[$store['store_pk']] ?? [];
    if (empty($prods)) continue;
    $hasProducts = true;
  ?>
    <div class="mp-section">
      <div class="mp-section-head">
        <h2>
          <?php if (!empty($store['logo_path'])): ?>
            <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="">
          <?php endif; ?>
          <?= htmlspecialchars($store['store_name']) ?>
        </h2>
        <a href="<?= htmlspecialchars(rdv_store_url(['id' => (int)$store['store_pk'], 'store_slug' => (string)($store['store_slug'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>">Visit store</a>
      </div>
      <div class="mp-product-grid" data-sortable>
        <?php foreach ($prods as $p):
          $storePk = (int)$store['store_pk'];
          $storeName = (string)$store['store_name'];
          require __DIR__ . '/includes/marketplace_product_card.php';
        endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$hasProducts && $selectedCategory !== ''): ?>
    <div class="mp-empty">
      <i class="fas fa-box-open"></i>
      <p>No products in “<?= htmlspecialchars($selectedCategory) ?>”.</p>
      <a class="mp-btn mp-btn-outline" href="<?= htmlspecialchars(rdv_marketplace_url(), ENT_QUOTES, 'UTF-8') ?>">Clear filter</a>
    </div>
  <?php endif; ?>

  <?php if (empty($stores)): ?>
    <div class="mp-empty">
      <i class="fas fa-store"></i>
      <p>No active stores are available right now. Check back soon.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($promo1_enabled == '1' || $promo2_enabled == '1'): ?>
<div class="mp-promo-grid">
  <?php if ($promo1_enabled == '1'): ?>
  <div class="mp-promo">
    <h3><?= htmlspecialchars($promo1_title) ?></h3>
    <p><?= htmlspecialchars($promo1_subtitle) ?></p>
    <a href="<?= htmlspecialchars($promo1_link) ?>">Shop now</a>
  </div>
  <?php endif; ?>
  <?php if ($promo2_enabled == '1'): ?>
  <div class="mp-promo" style="background:linear-gradient(135deg,#12305f,#0a3d91)">
    <h3><?= htmlspecialchars($promo2_title) ?></h3>
    <p><?= htmlspecialchars($promo2_subtitle) ?></p>
    <a href="<?= htmlspecialchars($promo2_link) ?>">Explore</a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="mp-sheet-overlay" id="mpSheetOverlay" hidden></div>
<div class="mp-sheet" id="mpFilterSheet" hidden>
  <h3>Filter by category</h3>
  <button type="button" class="mp-sheet-option <?= $selectedCategory === '' ? 'is-active' : '' ?>" onclick="location.href='<?= htmlspecialchars(rdv_marketplace_url(), ENT_QUOTES, 'UTF-8') ?>'">All categories</button>
  <?php foreach ($allCategories as $cat): ?>
    <button type="button" class="mp-sheet-option <?= $selectedCategory === $cat ? 'is-active' : '' ?>" onclick="location.href='<?= htmlspecialchars(rdv_marketplace_url('', ['category' => $cat]), ENT_QUOTES, 'UTF-8') ?>'"><?= htmlspecialchars($cat) ?></button>
  <?php endforeach; ?>
</div>
<div class="mp-sheet" id="mpSortSheet" hidden>
  <h3>Sort products</h3>
  <button type="button" class="mp-sheet-option" data-sort="featured">Featured</button>
  <button type="button" class="mp-sheet-option" data-sort="price-asc">Price: Low to High</button>
  <button type="button" class="mp-sheet-option" data-sort="price-desc">Price: High to Low</button>
  <button type="button" class="mp-sheet-option" data-sort="name-asc">Name: A–Z</button>
</div>

<?php require __DIR__ . '/includes/marketplace_footer.php'; ?>
<script>
(function () {
  var end = Date.now() + (4 * 3600 + 37 * 60) * 1000;
  function tick() {
    var left = Math.max(0, end - Date.now());
    var h = Math.floor(left / 3600000);
    var m = Math.floor((left % 3600000) / 60000);
    var s = Math.floor((left % 60000) / 1000);
    var el = document.getElementById('mpCountdown');
    if (el) el.textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  }
  tick();
  setInterval(tick, 1000);

  var wishlist = new Set();
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-wish]');
    if (!btn) return;
    e.preventDefault();
    var id = btn.getAttribute('data-wish');
    if (wishlist.has(id)) wishlist.delete(id); else wishlist.add(id);
    btn.classList.toggle('is-on', wishlist.has(id));
    var icon = btn.querySelector('i');
    if (icon) icon.className = wishlist.has(id) ? 'fas fa-heart' : 'far fa-heart';
  });

  function sortGrids(mode) {
    document.querySelectorAll('[data-sortable]').forEach(function (grid) {
      var cards = Array.prototype.slice.call(grid.querySelectorAll('.mp-card'));
      cards.sort(function (a, b) {
        var pa = parseFloat(a.dataset.price || 0);
        var pb = parseFloat(b.dataset.price || 0);
        var na = (a.dataset.name || '').toLowerCase();
        var nb = (b.dataset.name || '').toLowerCase();
        if (mode === 'price-asc') return pa - pb;
        if (mode === 'price-desc') return pb - pa;
        if (mode === 'name-asc') return na.localeCompare(nb);
        return 0;
      });
      cards.forEach(function (c) { grid.appendChild(c); });
    });
  }

  var overlay = document.getElementById('mpSheetOverlay');
  var filterSheet = document.getElementById('mpFilterSheet');
  var sortSheet = document.getElementById('mpSortSheet');
  function closeSheets() {
    if (overlay) overlay.hidden = true;
    if (filterSheet) filterSheet.hidden = true;
    if (sortSheet) sortSheet.hidden = true;
  }
  document.querySelectorAll('[data-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      closeSheets();
      if (overlay) overlay.hidden = false;
      if (btn.getAttribute('data-open') === 'filter' && filterSheet) filterSheet.hidden = false;
      if (btn.getAttribute('data-open') === 'sort' && sortSheet) sortSheet.hidden = false;
    });
  });
  if (overlay) overlay.addEventListener('click', closeSheets);
  document.querySelectorAll('#mpSortSheet [data-sort]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      sortGrids(btn.getAttribute('data-sort'));
      closeSheets();
    });
  });
  var desk = document.getElementById('mpSortDesktop');
  if (desk) desk.addEventListener('change', function () { sortGrids(desk.value); });
})();
</script>
</body>
</html>
