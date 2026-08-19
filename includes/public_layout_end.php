<?php
if (!function_exists('rdv_newsletter_form')) {
    require_once __DIR__ . '/public_site.php';
}
$rdvShowAds = $rdvShowAds ?? true;
$year = date('Y');
?>
  </main>
  <?php if (!empty($rdvShowAds)): ?>
    <div class="container rdv-ad-wrap rdv-ad-wrap--footer"><?= rdv_render_ad_slot('footer') ?></div>
  <?php endif; ?>
  <footer class="footer footer-glass" id="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.php" class="navbar-brand" style="margin-bottom:16px;">
            <div class="navbar-brand-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
            RD Vendora
          </a>
          <p class="footer-brand-desc">RD Vendora is a multi-vendor eCommerce platform that helps people create an online store, manage products and orders, and sell with Paystack or Flutterwave.</p>
          <div class="rdv-footer-newsletter">
            <h2 class="rdv-footer-heading">Newsletter</h2>
            <p>Subscribe to the RD Vendora newsletter to receive updates, useful business resources, platform news, and other relevant information.</p>
            <?= rdv_newsletter_form('footer') ?>
          </div>
        </div>
        <div class="footer-column">
          <h2 class="rdv-footer-heading">Product</h2>
          <div class="footer-links">
            <a href="features.php">Features</a>
            <a href="pricing.php">Pricing</a>
            <a href="marketplace.php">Marketplace</a>
            <a href="blog.php">News</a>
            <a href="faq.php">FAQ</a>
          </div>
        </div>
        <div class="footer-column">
          <h2 class="rdv-footer-heading">Company</h2>
          <div class="footer-links">
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="sitemap.php">Sitemap</a>
          </div>
        </div>
        <div class="footer-column">
          <h2 class="rdv-footer-heading">Legal</h2>
          <div class="footer-links">
            <a href="privacy.php">Privacy Policy</a>
            <a href="terms.php">Terms and Conditions</a>
            <a href="cookies.php">Cookie Policy</a>
            <a href="disclaimer.php">Disclaimer</a>
            <a href="community-guidelines.php">Community Guidelines</a>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <p class="footer-copyright">&copy; <?= (int) $year ?> RD Vendora. All rights reserved. Designed by RD NEXA TECH.</p>
      </div>
    </div>
  </footer>
  <div id="rdv-cookie-root"></div>
  <script src="assets/js/rdv-public.js" defer></script>
</body>
</html>
