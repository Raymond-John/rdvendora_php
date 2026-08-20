<?php
session_start();
require_once 'includes/connection.php';
require_once __DIR__ . '/app/helpers/marketplace_urls.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

function getMarketplaceSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

$body_bg_color = getMarketplaceSetting('body_bg_color', '#f3f5f9');
$text_primary_color = getMarketplaceSetting('text_primary_color', '#0f172a');
$primary_btn_bg = getMarketplaceSetting('primary_btn_bg', '#0A3D91');
$primary_btn_text = getMarketplaceSetting('primary_btn_text', '#ffffff');
$card_bg_color = getMarketplaceSetting('card_bg_color', '#ffffff');
$sidebar_bg_color = getMarketplaceSetting('sidebar_bg_color', '#eef2f8');
$sidebar_text_color = getMarketplaceSetting('sidebar_text_color', '#5b6578');

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

$allCategories = [];
try {
    $catResult = $conn->query("SELECT DISTINCT category FROM products WHERE status='active' AND category IS NOT NULL AND category != '' ORDER BY category ASC LIMIT 40");
    if ($catResult) {
        while ($row = $catResult->fetch_assoc()) {
            $allCategories[] = $row['category'];
        }
    }
} catch (Throwable $e) {}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/adsense_head.php'; ?>
  <title>Your Cart — RD Vendora Marketplace</title>
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
$mpActive = 'cart';
$mpSearch = '';
$mpCategories = $allCategories;
$mpSelectedCategory = '';
$mpShowCategories = false;
require __DIR__ . '/includes/marketplace_header.php';
?>

<div class="mp-page-title">
  <h1>Shopping cart</h1>
</div>

<div class="mp-cart-layout">
  <div class="mp-panel" id="cartItems">
    <div class="mp-empty-cart">
      <i class="fas fa-shopping-bag" style="font-size:2.4rem;color:var(--mp-navy)"></i>
      <h2>Your cart is empty</h2>
      <p>Discover something you’ll love from businesses on RD Vendora.</p>
      <a class="mp-btn mp-btn-primary" href="<?= htmlspecialchars(rdv_marketplace_url(), ENT_QUOTES, 'UTF-8') ?>">Start Shopping</a>
    </div>
  </div>
  <aside class="mp-panel" id="cartSummary" hidden>
    <h2 style="margin:0 0 0.75rem;font-size:1.1rem;">Order summary</h2>
    <div id="summaryDetails"></div>
    <a class="mp-btn mp-btn-primary mp-btn-block" id="checkoutBtnDesktop" href="<?= htmlspecialchars(rdv_marketplace_url('checkout'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:1rem;">Proceed to Checkout</a>
  </aside>
</div>

<div class="mp-sticky-checkout" id="mpStickyCheckout">
  <div class="mp-summary-row mp-summary-total" style="margin:0 0 0.55rem;border:0;padding:0;">
    <span>Total</span>
    <strong id="stickyTotal">₦0.00</strong>
  </div>
  <a class="mp-btn mp-btn-primary mp-btn-block" href="<?= htmlspecialchars(rdv_marketplace_url('checkout'), ENT_QUOTES, 'UTF-8') ?>">Proceed to Checkout</a>
</div>

<?php require __DIR__ . '/includes/marketplace_footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var MP = window.MPCart;
  function renderCart() {
    var cart = MP.getCart();
    var cartItemsDiv = document.getElementById('cartItems');
    var cartSummaryDiv = document.getElementById('cartSummary');
    var sticky = document.getElementById('mpStickyCheckout');

    if (!cart.length) {
      cartItemsDiv.innerHTML = `
        <div class="mp-empty-cart">
          <i class="fas fa-shopping-bag" style="font-size:2.4rem;color:var(--mp-navy)"></i>
          <h2>Your cart is empty</h2>
          <p>Discover something you’ll love from businesses on RD Vendora.</p>
          <a class="mp-btn mp-btn-primary" href="${(window.MP_URLS && window.MP_URLS.home) || 'marketplace'}">Start Shopping</a>
        </div>`;
      cartSummaryDiv.hidden = true;
      sticky.classList.remove('is-visible');
      return;
    }

    cartSummaryDiv.hidden = false;
    sticky.classList.add('is-visible');

    var grouped = {};
    cart.forEach(function (item) {
      var sid = item.store_id || 0;
      if (!grouped[sid]) grouped[sid] = { store_name: item.store_name || ('Store ' + sid), items: [] };
      grouped[sid].items.push(item);
    });

    var html = '<div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;margin-bottom:0.5rem;flex-wrap:wrap">'
      + '<strong>' + cart.reduce(function(s,i){return s+(i.quantity||0);},0) + ' items</strong>'
      + '<button type="button" class="mp-remove" id="clearCartBtn">Clear cart</button></div>';

    Object.keys(grouped).forEach(function (sid) {
      var store = grouped[sid];
      html += '<div style="margin-bottom:0.5rem"><div class="mp-cart-meta" style="font-weight:750;margin:0.4rem 0">' + MP.escapeHtml(store.store_name) + '</div>';
      store.items.forEach(function (item) {
        var line = (parseFloat(item.price) || 0) * (parseInt(item.quantity, 10) || 0);
        html += '<div class="mp-cart-item" data-product-id="' + item.product_id + '" data-store-id="' + item.store_id + '">'
          + '<img src="' + MP.escapeHtml(item.image || '') + '" alt="" onerror="this.src=\'assets/brand-logo.png\'">'
          + '<div><h3>' + MP.escapeHtml(item.name) + '</h3>'
          + '<div class="mp-cart-meta">' + MP.escapeHtml(item.store_name || '') + ' · ₦' + (parseFloat(item.price)||0).toFixed(2) + ' each</div>'
          + '<div class="mp-cart-row">'
          + '<div class="mp-qty"><button type="button" class="qty-decr" aria-label="Decrease quantity">−</button><span>' + item.quantity + '</span><button type="button" class="qty-incr" aria-label="Increase quantity">+</button></div>'
          + '<strong>₦' + line.toFixed(2) + '</strong>'
          + '<button type="button" class="mp-remove remove-item">Remove</button>'
          + '</div></div></div>';
      });
      html += '</div>';
    });
    cartItemsDiv.innerHTML = html;

    cartItemsDiv.querySelectorAll('.qty-decr').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ci = btn.closest('.mp-cart-item');
        MP.updateQuantity(ci.dataset.storeId, ci.dataset.productId, -1);
        renderCart();
      });
    });
    cartItemsDiv.querySelectorAll('.qty-incr').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ci = btn.closest('.mp-cart-item');
        MP.updateQuantity(ci.dataset.storeId, ci.dataset.productId, 1);
        renderCart();
      });
    });
    cartItemsDiv.querySelectorAll('.remove-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ci = btn.closest('.mp-cart-item');
        MP.removeItem(ci.dataset.storeId, ci.dataset.productId);
        renderCart();
      });
    });
    var clearBtn = document.getElementById('clearCartBtn');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (confirm('Clear entire cart?')) {
          MP.clearCart();
          renderCart();
          MP.showToast('Cart cleared');
        }
      });
    }

    var total = cart.reduce(function (sum, item) {
      return sum + (parseFloat(item.price) || 0) * (parseInt(item.quantity, 10) || 0);
    }, 0);
    document.getElementById('summaryDetails').innerHTML =
      '<div class="mp-summary-row"><span>Subtotal</span><span>₦' + total.toFixed(2) + '</span></div>'
      + '<div class="mp-summary-row"><span>Discount</span><span>₦0.00</span></div>'
      + '<div class="mp-summary-row"><span>Shipping</span><span>Calculated at checkout</span></div>'
      + '<div class="mp-summary-row mp-summary-total"><span>Total</span><strong>₦' + total.toFixed(2) + '</strong></div>';
    document.getElementById('stickyTotal').textContent = '₦' + total.toFixed(2);
  }

  document.addEventListener('mp:cart-updated', renderCart);
  renderCart();
});
</script>
</body>
</html>
