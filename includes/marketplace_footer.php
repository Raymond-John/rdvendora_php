<?php
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$site = static function ($path) {
    return function_exists('rdv_url') ? rdv_url($path) : $path;
};
$url = static function ($path = '') {
    return function_exists('rdv_marketplace_url') ? rdv_marketplace_url($path) : ('marketplace/' . ltrim($path, '/'));
};
?>
<footer class="mp-footer">
  <div class="mp-footer-inner">
    <div class="mp-footer-brand">
      <strong>RD Vendora Marketplace</strong>
      <p>A premium public shopping floor for independent Nigerian businesses — browse freely, checkout as a guest.</p>
    </div>
    <div class="mp-footer-cols">
      <div>
        <h4>Shop</h4>
        <a href="<?= $h($url()) ?>">Marketplace</a>
        <a href="<?= $h($url('cart')) ?>">Cart</a>
        <a href="<?= $h($url('checkout')) ?>">Checkout</a>
      </div>
      <div>
        <h4>Company</h4>
        <a href="<?= $h($site('about')) ?>">About</a>
        <a href="<?= $h($site('contact')) ?>">Contact</a>
        <a href="<?= $h($site('faq')) ?>">FAQ</a>
      </div>
      <div>
        <h4>Policies</h4>
        <a href="<?= $h($site('privacy')) ?>">Privacy</a>
        <a href="<?= $h($site('terms')) ?>">Terms</a>
        <a href="<?= $h($site('cookies')) ?>">Cookies</a>
      </div>
    </div>
  </div>
  <div class="mp-footer-bottom">
    <span>© <?= date('Y') ?> RD Vendora. All rights reserved.</span>
    <span class="mp-footer-note">Guest shopping enabled</span>
  </div>
</footer>

<div class="mp-toast-host" id="mpToastHost" aria-live="polite"></div>
<script>
window.MP_URLS = {
  home: <?= json_encode(function_exists('rdv_marketplace_url') ? rdv_marketplace_url() : 'marketplace') ?>,
  cart: <?= json_encode(function_exists('rdv_marketplace_url') ? rdv_marketplace_url('cart') : 'marketplace/cart') ?>,
  checkout: <?= json_encode(function_exists('rdv_marketplace_url') ? rdv_marketplace_url('checkout') : 'marketplace/checkout') ?>
};
</script>
<script src="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/js/marketplace-cart.js') : 'assets/js/marketplace-cart.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
<div id="rdv-cookie-root"></div>
<script src="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/js/rdv-public.js') : 'assets/js/rdv-public.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
