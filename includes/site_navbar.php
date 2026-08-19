<?php
if (!function_exists('rdv_public_nav_items')) {
    require_once __DIR__ . '/public_site.php';
}
$rdvActiveNav = $rdvActiveNav ?? basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
?>
  <header class="navbar glass" id="navbar" data-rdv-chrome="1">
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
