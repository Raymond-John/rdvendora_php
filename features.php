<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

$rdvPageTitle = 'Features — RD Vendora';
$rdvPageDescription = 'Storefronts, catalogue, orders, marketplace, and Paystack or Flutterwave checkout on RD Vendora.';
$rdvPagePath = 'features.php';
$rdvActiveNav = 'features.php';
$rdvBodyClass = 'mk-marketing';
$rdvHeaderAds = false;
require __DIR__ . '/includes/public_layout_start.php';

$check = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
$features = [
  ['purple', '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>', 'Public storefront', 'A shop page for your products and brand that you can share with customers.', ['Product listings', 'Mobile-friendly layout', 'Your store link']],
  ['green', '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>', 'Catalogue & stock', 'Add products, prices, and inventory so buyers see what is actually available.', ['Variants where you need them', 'Low-stock visibility', 'Seller dashboard controls']],
  ['amber', '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>', 'Checkout', 'Payments go through Paystack and Flutterwave — not a homemade card form.', ['Cards and local methods they support', 'Paid order records', 'No card data stored on RD Vendora']],
  ['blue', '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>', 'Orders & reports', 'See incoming orders and basic sales activity from your dashboard.', ['Order status', 'Customer details from checkout', 'Export where available']],
  ['purple', '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>', 'Messaging', 'Keep buyer and seller conversations on the platform instead of lost chats.', ['In-platform chat', 'Order context', 'Support from the team when needed']],
  ['green', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'Marketplace', 'Independent stores can be discovered together, with platform tools for operators.', ['Multi-vendor listings', 'Store pages', 'Admin moderation tools']],
];
?>

<section class="mk-hero mk-hero--compact mk-page-hero">
  <div class="container">
    <div class="mk-kicker">Features</div>
    <h1>The tools a working shop needs</h1>
    <p class="lead">RD Vendora is built around storefronts, products, orders, and supported checkout — not a wall of features we do not ship.</p>
    </div>
  </section>

<section class="mk-section">
    <div class="container">
    <div class="feature-grid">
      <?php foreach ($features as $f): ?>
      <article class="feature-card reveal" style="padding:1.75rem;">
        <div class="feature-icon <?= $f[0] ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $f[1] ?></svg></div>
        <h3 class="feature-title"><?= $f[2] ?></h3>
        <p class="feature-description"><?= $f[3] ?></p>
        <ul class="mk-feature-list">
          <?php foreach ($f[4] as $item): ?>
            <li><?= $check ?> <?= htmlspecialchars($item) ?></li>
          <?php endforeach; ?>
              </ul>
      </article>
      <?php endforeach; ?>
      </div>
    </div>
  </section>

<div class="container">
  <div class="mk-cta-band">
    <h2>See it on your own store</h2>
    <p>Create an account and add a product. If you already sell, compare plans next.</p>
    <div class="mk-actions" style="justify-content:center;">
      <a href="register.php" class="btn btn-white btn-lg" style="background:#fff;color:#12305f;">Get started</a>
      <a href="pricing.php" class="btn btn-outline-white btn-lg">View pricing</a>
    </div>
    </div>
  </div>

<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
