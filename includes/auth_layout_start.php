<?php
if (!function_exists('rdv_public_nav_items')) {
    require_once __DIR__ . '/public_site.php';
}
$authPageTitle = $authPageTitle ?? 'RD Vendora';
$authVisualTitle = $authVisualTitle ?? 'Welcome to RD Vendora';
$authVisualText = $authVisualText ?? 'Create a store, list products, and take orders with Paystack or Flutterwave.';
$authVisualFeatures = $authVisualFeatures ?? [
    'Free trial on eligible plans',
    'Store dashboard for products and orders',
    'Checkout through supported providers',
];
$year = (int) date('Y');
$checkIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($authPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <?= rdv_favicon_tags() ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/auth.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/animations.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/public-extras.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/responsive.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="auth-page">
  <?php require __DIR__ . '/site_navbar.php'; ?>
  <div class="auth-layout">
    <div class="auth-visual">
      <div class="auth-visual-bg"></div>
      <div class="auth-visual-content">
        <a href="index.php" class="auth-visual-brand">
          <?= rdv_brand_logo('', 'rdv-brand-logo--auth') ?>
        </a>
      </div>
      <div class="auth-visual-body">
        <h2 class="auth-visual-title"><?= htmlspecialchars($authVisualTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="auth-visual-text"><?= htmlspecialchars($authVisualText, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="auth-visual-features">
          <?php foreach ($authVisualFeatures as $feat): ?>
            <div class="auth-visual-feature"><?= $checkIcon ?><?= htmlspecialchars($feat, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="auth-visual-footer">
        <span>&copy; <?= $year ?> RD Vendora</span>
      </div>
    </div>
    <div class="auth-form-side">
      <div class="auth-form-container anim-fade-in-up">
