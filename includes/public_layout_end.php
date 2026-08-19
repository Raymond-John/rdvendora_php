<?php
if (!function_exists('rdv_newsletter_form')) {
    require_once __DIR__ . '/public_site.php';
}
$rdvShowAds = $rdvShowAds ?? true;
?>
  </main>
  <?php if (!empty($rdvShowAds)): ?>
    <div class="container rdv-ad-wrap rdv-ad-wrap--footer"><?= rdv_render_ad_slot('footer') ?></div>
  <?php endif; ?>
<?php require __DIR__ . '/site_footer.php'; ?>
  <script src="assets/js/rdv-public.js" defer></script>
  <?= $rdvFooterExtra ?? '' ?>
</body>
</html>
