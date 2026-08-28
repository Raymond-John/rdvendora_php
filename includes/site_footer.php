<?php
if (!function_exists('rdv_newsletter_form')) {
    require_once __DIR__ . '/public_site.php';
}
$year = (int) date('Y');
?>
  <footer class="footer footer-glass" id="footer" data-rdv-chrome="1">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="<?= htmlspecialchars(rdv_url('index'), ENT_QUOTES, 'UTF-8') ?>" class="footer-logo">
            <?= rdv_brand_logo('', 'rdv-brand-logo--footer') ?>
          </a>
          <p class="footer-brand-desc">The complete multi-vendor eCommerce platform. Build, manage, and scale your online business with powerful tools.</p>
        </div>
        <div class="footer-column">
          <h4>Product</h4>
          <div class="footer-links">
            <a href="<?= htmlspecialchars(rdv_url('features'), ENT_QUOTES, 'UTF-8') ?>">Features</a>
            <a href="<?= htmlspecialchars(rdv_url('pricing'), ENT_QUOTES, 'UTF-8') ?>">Pricing</a>
            <a href="<?= htmlspecialchars(rdv_url('marketplace'), ENT_QUOTES, 'UTF-8') ?>">Marketplace</a>
            <a href="<?= htmlspecialchars(rdv_url('blog'), ENT_QUOTES, 'UTF-8') ?>">News</a>
            <a href="<?= htmlspecialchars(rdv_url('faq'), ENT_QUOTES, 'UTF-8') ?>">FAQ</a>
          </div>
        </div>
        <div class="footer-column">
          <h4>Company</h4>
          <div class="footer-links">
            <a href="<?= htmlspecialchars(rdv_url('about'), ENT_QUOTES, 'UTF-8') ?>">About</a>
            <a href="<?= htmlspecialchars(rdv_url('contact'), ENT_QUOTES, 'UTF-8') ?>">Contact</a>
            <a href="<?= htmlspecialchars(rdv_url('sitemap'), ENT_QUOTES, 'UTF-8') ?>">Sitemap</a>
          </div>
        </div>
        <div class="footer-column">
          <h4>Legal</h4>
          <div class="footer-links">
            <a href="<?= htmlspecialchars(rdv_url('privacy'), ENT_QUOTES, 'UTF-8') ?>">Privacy</a>
            <a href="<?= htmlspecialchars(rdv_url('terms'), ENT_QUOTES, 'UTF-8') ?>">Terms</a>
            <a href="<?= htmlspecialchars(rdv_url('cookies'), ENT_QUOTES, 'UTF-8') ?>">Cookies</a>
            <a href="<?= htmlspecialchars(rdv_url('disclaimer'), ENT_QUOTES, 'UTF-8') ?>">Disclaimer</a>
            <a href="<?= htmlspecialchars(rdv_url('community-guidelines'), ENT_QUOTES, 'UTF-8') ?>">Community Guidelines</a>
          </div>
        </div>
        <div class="rdv-footer-newsletter">
          <h4>Newsletter</h4>
          <p>Subscribe to the RD Vendora newsletter to receive updates, useful business resources, platform news, and other relevant information.</p>
          <?= rdv_newsletter_form('footer') ?>
        </div>
      </div>
      <div class="footer-bottom">
        <p class="footer-copyright">&copy; <?= $year ?> RD Vendora. All rights reserved.</p>
        <p class="footer-developer-credit"><?= rdv_developer_credit_html($GLOBALS['conn'] ?? ($GLOBALS['connect'] ?? null)) ?></p>
      </div>
    </div>
  </footer>
