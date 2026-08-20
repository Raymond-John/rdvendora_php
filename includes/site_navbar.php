<?php
if (!function_exists('rdv_public_nav_items')) {
    require_once __DIR__ . '/public_site.php';
}
$rdvActiveNav = $rdvActiveNav ?? basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$rdvActiveNavClean = preg_replace('/\.php$/i', '', (string) $rdvActiveNav);
if ($rdvActiveNavClean === 'index') {
    $rdvActiveNavClean = 'index';
}
?>
  <header class="navbar glass" id="navbar" data-rdv-chrome="1">
    <div class="navbar-inner">
      <a href="<?= htmlspecialchars(rdv_url('index'), ENT_QUOTES, 'UTF-8') ?>" class="navbar-brand">
        <?= rdv_brand_logo() ?>
      </a>
      <nav class="navbar-nav" id="navbar-nav" aria-label="Primary">
        <?php foreach (rdv_public_nav_items() as $href => $label): ?>
          <?php
            $isActive = ($rdvActiveNavClean === $href) || ($href === 'index' && ($rdvActiveNavClean === '' || $rdvActiveNavClean === 'index'));
          ?>
          <a href="<?= htmlspecialchars(rdv_url($href), ENT_QUOTES, 'UTF-8') ?>" class="nav-link<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="navbar-actions">
        <a href="<?= htmlspecialchars(rdv_url('login'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-ghost btn-sm">Log in</a>
        <a href="<?= htmlspecialchars(rdv_url('register'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm">Get Started</a>
        <button type="button" class="btn-icon mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Open menu" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>
