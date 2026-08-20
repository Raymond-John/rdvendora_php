<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!function_exists('rdv_marketplace_url')) {
    require_once APP_PATH . '/helpers/marketplace_urls.php';
}

$mpActive = $mpActive ?? 'home'; // home | cart | checkout | product
$mpSearch = $mpSearch ?? '';
$mpCategories = $mpCategories ?? [];
$mpSelectedCategory = $mpSelectedCategory ?? '';
$mpShowCategories = $mpShowCategories ?? true;
$mpPageTitle = $mpPageTitle ?? 'Marketplace';

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$url = static function ($path = '', $query = []) {
    return function_exists('rdv_marketplace_url') ? rdv_marketplace_url($path, $query) : ('marketplace/' . ltrim($path, '/'));
};
$asset = static function ($path) {
    return function_exists('rdv_asset') ? rdv_asset($path) : $path;
};
$site = static function ($path) {
    return function_exists('rdv_url') ? rdv_url($path) : $path;
};
?>
<div class="mp-strip" role="note">
  <span>Free delivery on orders above ₦10,000</span>
  <span class="mp-strip-dot" aria-hidden="true">·</span>
  <span>Genuine products from verified sellers</span>
  <span class="mp-strip-dot" aria-hidden="true">·</span>
  <span>Secure guest checkout</span>
</div>

<header class="mp-header" id="mpHeader">
  <div class="mp-header-inner">
    <a class="mp-logo" href="<?= $h($url()) ?>" aria-label="RD Vendora Marketplace home">
      <img class="mp-logo-img" src="<?= $h($asset('assets/brand-logo.png')) ?>" alt="RD Vendora">
      <span class="mp-logo-text">
        <strong>RD Vendora</strong>
        <em>Marketplace</em>
      </span>
    </a>

    <nav class="mp-nav-desktop" aria-label="Marketplace">
      <a href="<?= $h($url()) ?>" class="<?= $mpActive === 'home' ? 'is-active' : '' ?>">Marketplace</a>
      <a href="#mp-categories" class="mp-nav-cats-link">Categories</a>
    </nav>

    <form class="mp-search mp-search-desktop" method="get" action="<?= $h($url()) ?>" role="search">
      <label class="mp-sr-only" for="mpSearchDesktop">Search products</label>
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" id="mpSearchDesktop" name="q" value="<?= $h($mpSearch) ?>" placeholder="Search products, brands, categories…" autocomplete="off">
      <button type="submit">Search</button>
    </form>

    <div class="mp-header-actions">
      <button type="button" class="mp-icon-btn mp-search-toggle" id="mpSearchToggle" aria-label="Open search" aria-expanded="false" aria-controls="mpMobileSearch">
        <i class="fas fa-search" aria-hidden="true"></i>
      </button>
      <a class="mp-cart-btn" href="<?= $h($url('cart')) ?>" aria-label="Shopping cart">
        <i class="fas fa-shopping-bag" aria-hidden="true"></i>
        <span class="mp-cart-count" id="cartCount" data-mp-cart-count>0</span>
      </a>
      <button type="button" class="mp-icon-btn mp-menu-toggle" id="mpMenuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mpMobileMenu">
        <i class="fas fa-bars" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  <div class="mp-mobile-search" id="mpMobileSearch" hidden>
    <form method="get" action="<?= $h($url()) ?>" role="search">
      <label class="mp-sr-only" for="mpSearchMobile">Search products</label>
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" id="mpSearchMobile" name="q" value="<?= $h($mpSearch) ?>" placeholder="Search the marketplace…" autocomplete="off">
      <button type="submit">Go</button>
    </form>
  </div>
</header>

<div class="mp-drawer-overlay" id="mpDrawerOverlay" hidden></div>
<aside class="mp-drawer" id="mpMobileMenu" hidden aria-label="Marketplace menu">
  <div class="mp-drawer-head">
    <strong>Browse</strong>
    <button type="button" class="mp-icon-btn" id="mpMenuClose" aria-label="Close menu"><i class="fas fa-times" aria-hidden="true"></i></button>
  </div>
  <nav class="mp-drawer-nav">
    <a href="<?= $h($url()) ?>">Marketplace home</a>
    <a href="<?= $h($url('cart')) ?>">Cart</a>
    <a href="<?= $h($site('about')) ?>">About RD Vendora</a>
    <a href="<?= $h($site('contact')) ?>">Contact</a>
    <a href="<?= $h($site('faq')) ?>">Help &amp; FAQ</a>
  </nav>
  <?php if ($mpShowCategories && !empty($mpCategories)): ?>
  <div class="mp-drawer-section">
    <p class="mp-drawer-label">Categories</p>
    <div class="mp-drawer-cats">
      <a href="<?= $h($url()) ?>" class="<?= $mpSelectedCategory === '' ? 'is-active' : '' ?>">All</a>
      <?php foreach ($mpCategories as $cat): ?>
        <a href="<?= $h($url('', ['category' => $cat])) ?>" class="<?= $mpSelectedCategory === $cat ? 'is-active' : '' ?>"><?= $h($cat) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</aside>

<?php if ($mpShowCategories): ?>
<nav class="mp-cats" id="mp-categories" aria-label="Product categories">
  <div class="mp-cats-track">
    <a href="<?= $h($url()) ?>" class="mp-cat-pill <?= $mpSelectedCategory === '' ? 'is-active' : '' ?>">All</a>
    <?php foreach ($mpCategories as $cat): ?>
      <a href="<?= $h($url('', ['category' => $cat])) ?>" class="mp-cat-pill <?= $mpSelectedCategory === $cat ? 'is-active' : '' ?>"><?= $h($cat) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
