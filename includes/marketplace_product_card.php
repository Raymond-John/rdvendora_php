<?php
/**
 * Expects $p product row and optional $storeName, $storePk overrides.
 */
$storePk = (int) ($storePk ?? $p['store_pk'] ?? 0);
$storeName = (string) ($storeName ?? $p['store_name'] ?? 'Store');
$pid = (int) ($p['id'] ?? 0);
$name = (string) ($p['name'] ?? 'Product');
$price = (float) ($p['price'] ?? 0);
$image = (string) ($p['image'] ?? '');
$old = isset($p['old_price']) ? (float) $p['old_price'] : 0;
$discount = ($old > 0 && $old > $price) ? (int) round((1 - $price / $old) * 100) : 0;
$productHref = function_exists('rdv_marketplace_url')
    ? rdv_marketplace_url('product/' . $pid)
    : ('marketplaceviewproduct?id=' . $pid);
$imgSrc = $image !== '' ? $image : 'assets/brand-logo.png';
$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<article class="mp-card"
  data-id="<?= $pid ?>"
  data-store-id="<?= $storePk ?>"
  data-store-name="<?= $h($storeName) ?>"
  data-price="<?= $h($price) ?>"
  data-name="<?= $h($name) ?>"
  data-image="<?= $h($image) ?>">
  <div class="mp-card-media">
    <?php if ($discount > 0): ?><span class="mp-badge-sale">-<?= $discount ?>%</span><?php endif; ?>
    <button type="button" class="mp-wish" aria-label="Save for later" data-wish="<?= $pid ?>">
      <i class="far fa-heart" aria-hidden="true"></i>
    </button>
    <a href="<?= $h($productHref) ?>">
      <img src="<?= $h($imgSrc) ?>" alt="<?= $h($name) ?>" loading="lazy" width="400" height="400">
    </a>
  </div>
  <div class="mp-card-body">
    <div class="mp-card-store"><?= $h($storeName) ?></div>
    <a class="mp-card-title" href="<?= $h($productHref) ?>"><?= $h($name) ?></a>
    <div class="mp-card-price-row">
      <span class="mp-price">₦<?= number_format($price, 0) ?></span>
      <?php if ($discount > 0): ?>
        <span class="mp-price-old">₦<?= number_format($old, 0) ?></span>
        <span class="mp-save">Save ₦<?= number_format($old - $price, 0) ?></span>
      <?php endif; ?>
    </div>
    <button
      type="button"
      class="mp-btn mp-btn-primary"
      onclick="addToCart(<?= $pid ?>, <?= $storePk ?>, <?= json_encode($storeName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($price) ?>, <?= json_encode($image, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, event)">
      Add to Cart
    </button>
  </div>
</article>
