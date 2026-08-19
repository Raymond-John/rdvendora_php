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
$rdvHeaderAds = $rdvHeaderAds ?? $rdvShowAds;
$rdvBodyClass = trim('rdv-public-page ' . ($rdvBodyClass ?? ''));
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
  <link rel="stylesheet" href="assets/css/marketing.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <?= rdv_org_schema() ?>
  <?= rdv_adsense_head_script() ?>
  <?= rdv_analytics_head_script() ?>
  <?= $rdvExtraHead ?>
</head>
<body class="<?= htmlspecialchars($rdvBodyClass, ENT_QUOTES, 'UTF-8') ?>">
  <a class="rdv-skip-link" href="#main-content">Skip to content</a>
  <?php require __DIR__ . '/site_navbar.php'; ?>
  <main id="main-content">
    <?php if (!empty($rdvShowAds) && !empty($rdvHeaderAds)): ?>
      <div class="container rdv-ad-wrap rdv-ad-wrap--header"><?= rdv_render_ad_slot('header') ?></div>
    <?php endif; ?>
