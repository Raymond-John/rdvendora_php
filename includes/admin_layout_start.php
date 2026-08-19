<?php
if (!function_exists('rdv_asset')) {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
}
$currentPage = basename($_SERVER['PHP_SELF']);
$adminPageTitle = $adminPageTitle ?? 'Admin - RD Vendora';
$adminSearchPlaceholder = $adminSearchPlaceholder ?? 'Search platform...';
$adminPageSubtitle = $adminPageSubtitle ?? '';
$adminShowHeader = $adminShowHeader ?? true;
$adminHeadExtra = $adminHeadExtra ?? '';
$adminPageStyles = $adminPageStyles ?? '';
$adminName = htmlspecialchars((string) ($_SESSION['fullname'] ?? $_SESSION['email'] ?? 'Platform Admin'), ENT_QUOTES, 'UTF-8');
$adminRoleLabel = (string) ($_SESSION['role_name'] ?? 'Admin');
if ($adminRoleLabel === 'super_admin') {
    $adminRoleLabel = 'Super Admin';
}
$adminRoleLabel = htmlspecialchars($adminRoleLabel, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="<?= htmlspecialchars(rdv_asset('assets/brand-logo.png', '../'), ENT_QUOTES, 'UTF-8') ?>" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/admin.css', '../'), ENT_QUOTES, 'UTF-8') ?>">
    <?= $adminHeadExtra ?>
    <?php if ($adminPageStyles !== ''): ?>
    <style><?= $adminPageStyles ?></style>
    <?php endif; ?>
</head>
<body class="admin-app">
<?php require __DIR__ . '/admin_sidebar.php'; ?>
<div class="main-content">
    <header class="dash-navbar">
        <button type="button" class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Open menu"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="adminSearchInput" placeholder="<?= htmlspecialchars($adminSearchPlaceholder, ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="dash-actions">
            <button type="button" class="theme-toggle dash-btn" id="themeToggle" aria-label="Toggle theme"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger">
                    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="">
                    <div class="dash-user-info">
                        <div class="name"><?= $adminName ?></div>
                        <div class="role"><?= $adminRoleLabel ?></div>
                    </div>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>
<?php if ($adminShowHeader): ?>
    <div class="page-header">
        <h1 class="page-title"><?= htmlspecialchars($adminPageHeading ?? $adminPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($adminPageSubtitle !== ''): ?>
            <p class="page-subtitle"><?= htmlspecialchars($adminPageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
