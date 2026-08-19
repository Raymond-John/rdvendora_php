<?php
if (!defined('RDV_BOOTSTRAPPED')) {
    require_once __DIR__ . '/connection.php';
}
require_once __DIR__ . '/public_site.php';

$rdvPageTitle = $rdvPageTitle ?? 'RD Vendora';
$rdvPageDescription = $rdvPageDescription ?? 'RD Vendora is a multi-vendor eCommerce platform for building and running online stores.';
$rdvPagePath = $rdvPagePath ?? basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$rdvActiveNav = $rdvActiveNav ?? $rdvPagePath;
$rdvOgType = $rdvOgType ?? 'website';
$rdvExtraHead = $rdvExtraHead ?? '';
$rdvShowAds = $rdvShowAds ?? true;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= rdv_seo_tags($rdvPageTitle, $rdvPageDescription, $rdvPagePath, $rdvOgType) ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/animations.css">
  <link rel="stylesheet" href="assets/css/public-extras.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?= rdv_org_schema() ?>
  <?= rdv_adsense_head_script() ?>
  <?= rdv_analytics_head_script() ?>
  <?= $rdvExtraHead ?>
</head>
<body class="rdv-public-page">
  <a class="rdv-skip-link" href="#main-content">Skip to content</a>
  <header class="navbar glass" id="navbar">
    <div class="navbar-inner">
      <a href="index.php" class="navbar-brand">
        <div class="navbar-brand-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </div>
        RD Vendora
      </a>
      <nav class="navbar-nav" id="navbar-nav" aria-label="Primary">
        <?php foreach (rdv_public_nav_items() as $href => $label): ?>
          <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="nav-link<?= $rdvActiveNav === $href ? ' active' : '' ?>"<?= $rdvActiveNav === $href ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="navbar-actions">
        <a href="login.php" class="btn btn-ghost btn-sm">Log in</a>
        <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
        <button type="button" class="btn-icon mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Open menu" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>
  <div class="mobile-overlay" id="rdv-mobile-overlay" hidden>
    <nav class="mobile-nav-links" aria-label="Mobile">
      <?php foreach (rdv_public_nav_items() as $href => $label): ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
      <a href="login.php" class="mobile-nav-link">Log in</a>
      <a href="register.php" class="mobile-nav-link">Get Started</a>
    </nav>
  </div>
  <main id="main-content">
    <?php if (!empty($rdvShowAds)): ?>
      <div class="container rdv-ad-wrap rdv-ad-wrap--header"><?= rdv_render_ad_slot('header') ?></div>
    <?php endif; ?>
