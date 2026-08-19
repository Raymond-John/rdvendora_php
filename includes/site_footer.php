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
          <a href="index.php" class="footer-logo">
            <div class="navbar-brand-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
            RD Vendora
          </a>
          <p class="footer-brand-desc">The complete multi-vendor eCommerce platform. Build, manage, and scale your online business with powerful tools.</p>
        </div>
        <div class="footer-column">
          <h4>Product</h4>
          <div class="footer-links">
            <a href="features.php">Features</a>
            <a href="pricing.php">Pricing</a>
            <a href="marketplace.php">Marketplace</a>
            <a href="blog.php">News</a>
            <a href="faq.php">FAQ</a>
          </div>
        </div>
        <div class="footer-column">
          <h4>Company</h4>
          <div class="footer-links">
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="sitemap.php">Sitemap</a>
          </div>
        </div>
        <div class="footer-column">
          <h4>Legal</h4>
          <div class="footer-links">
            <a href="privacy.php">Privacy</a>
            <a href="terms.php">Terms</a>
            <a href="cookies.php">Cookies</a>
            <a href="disclaimer.php">Disclaimer</a>
            <a href="community-guidelines.php">Community Guidelines</a>
          </div>
        </div>
      </div>
      <div class="rdv-footer-newsletter">
        <h4>Newsletter</h4>
        <p>Subscribe to the RD Vendora newsletter to receive updates, useful business resources, platform news, and other relevant information.</p>
        <?= rdv_newsletter_form('footer') ?>
      </div>
      <div class="footer-bottom">
        <p class="footer-copyright">&copy; <?= $year ?> RD Vendora. All rights reserved. Designed By RD NEXA TECH</p>
      </div>
    </div>
  </footer>
  <div id="rdv-cookie-root"></div>
