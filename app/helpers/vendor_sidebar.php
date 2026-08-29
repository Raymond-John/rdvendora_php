<?php
/**
 * Shared vendor dashboard sidebar.
 * Expects bootstrap + $conn (optional). Uses session store/user fields when present.
 *
 * Optional vars before include:
 * - $vendorSidebarActive (string page key, e.g. 'dashboard', 'orders')
 * - $isSuspended, $storeRestricted (bool)
 */
$conn = $conn ?? $connect ?? null;
$userId = (int) ($_SESSION['user_id'] ?? 0);

$currentPage = isset($vendorSidebarActive) && $vendorSidebarActive !== ''
    ? preg_replace('/\.php$/i', '', (string) $vendorSidebarActive)
    : preg_replace('/\.php$/i', '', basename($_SERVER['PHP_SELF'] ?? 'dashboard'));

if ($currentPage === 'vendor-messages') {
    $currentPage = 'vendor-chat';
}

$isSuspended = !empty($isSuspended) || !empty($isExpired);
$storeRestricted = !empty($storeRestricted);
$navLocked = $isSuspended || $storeRestricted;

$unreadNotifications = 0;
$unreadChat = 0;
$pendingOrders = 0;

if ($conn && $userId > 0) {
    if (!function_exists('getUnreadNotificationCount')) {
        $helper = APP_PATH . '/helpers/notifications_helper.php';
        if (is_file($helper)) {
            require_once $helper;
        }
    }
    if (function_exists('getUnreadNotificationCount')) {
        try {
            $unreadNotifications = (int) getUnreadNotificationCount($conn, $userId);
        } catch (Throwable $e) {
            $unreadNotifications = 0;
        }
    }

    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM chat_messages
             WHERE vendor_id = ? AND sender_type = 'admin' AND is_read = 0
               AND message NOT LIKE '\\_\\_%'"
        );
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $unreadChat = (int) ($row['cnt'] ?? 0);
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        $unreadChat = 0;
    }

    $storeId = (int) ($_SESSION['store_id'] ?? 0);
    if ($storeId > 0) {
        try {
            $orderCol = 'store_id';
            $cols = @$conn->query("SHOW COLUMNS FROM orders");
            if ($cols) {
                $names = [];
                while ($c = $cols->fetch_assoc()) {
                    $names[strtolower($c['Field'])] = true;
                }
                if (!isset($names['store_id']) && isset($names['vendor_id'])) {
                    $orderCol = 'vendor_id';
                }
            }
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM orders
                 WHERE {$orderCol} = ? AND status IN ('pending','processing')"
            );
            if ($stmt) {
                $stmt->bind_param('i', $storeId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $pendingOrders = (int) ($row['cnt'] ?? 0);
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $pendingOrders = 0;
        }
    }
}

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$url = static function ($path) {
    return function_exists('rdv_url') ? rdv_url($path) : $path;
};

$asset = static function ($path) {
    return function_exists('rdv_asset') ? rdv_asset($path) : $path;
};

$storefrontHref = 'storefront';
$storefrontTarget = '';
if (!empty($_SESSION['store_slug']) && function_exists('rdv_store_url') && !$navLocked) {
    $storefrontHref = rdv_store_url([
        'id' => (int) ($_SESSION['store_id'] ?? 0),
        'store_slug' => (string) $_SESSION['store_slug'],
    ]);
    $storefrontTarget = ' target="_blank" rel="noopener"';
}

$linkClass = static function ($key, $locked = false) use ($currentPage) {
    $classes = 'sidebar-link';
    if ($currentPage === $key) {
        $classes .= ' active';
    }
    if ($locked) {
        $classes .= ' disabled';
    }
    return $classes;
};

$badge = static function ($count) use ($h) {
    $count = (int) $count;
    if ($count < 1) {
        return '';
    }
    $label = $count > 99 ? '99+' : (string) $count;
    return '<span class="sidebar-link-badge" aria-label="' . $h($label) . ' unread">' . $h($label) . '</span>';
};

$logoutHref = $h($url('logout'));
$userName = $h($_SESSION['fullname'] ?? 'Vendor');
$userAvatarUrl = '';
$userInitials = 'V';
try {
    if (!function_exists('rdv_user_avatar_initials')) {
        require_once APP_PATH . '/helpers/user_avatar.php';
    }
    $userInitials = $h(rdv_user_avatar_initials($_SESSION['fullname'] ?? 'Vendor'));
    if ($conn instanceof mysqli && $userId > 0) {
        $userAvatarUrl = rdv_user_avatar_url($conn, $userId);
    }
} catch (Throwable $e) {
    error_log('vendor_sidebar avatar: ' . $e->getMessage());
    $userInitials = $h(rdv_user_avatar_initials($_SESSION['fullname'] ?? 'Vendor'));
}
$storeName = trim((string) ($_SESSION['store_name'] ?? ''));
$createStoreHref = $h($url('create-store'));
$homeHref = $h($url('index'));
$logoSrc = $h($asset('assets/brand-logo.png'));
?>
<style id="vendor-sidebar-shared-css">
.sidebar-link { position: relative; }
.sidebar-link-badge {
    margin-left: auto;
    font-size: 10px;
    line-height: 1.2;
    min-width: 18px;
    text-align: center;
    padding: 2px 6px;
    background: var(--error, #ef4444);
    color: #fff;
    border-radius: 999px;
    font-weight: 700;
    flex-shrink: 0;
}
.sidebar.collapsed .sidebar-link-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 8px;
    height: 8px;
    min-width: 0;
    padding: 0;
    font-size: 0;
    overflow: hidden;
}
.sidebar-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.suspended-badge {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 6px;
    font-size: 10px;
    font-weight: 600;
    border-radius: 999px;
    background: rgba(239, 68, 68, 0.15);
    color: var(--error, #ef4444);
}
</style>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= $homeHref ?>" class="sidebar-brand">
            <img class="rdv-brand-logo" src="<?= $logoSrc ?>" alt=""><span class="rdv-brand-name">RD Vendora</span>
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg>
        </button>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section-title">Main</div>
        <a href="<?= $h($url('dashboard')) ?>" class="<?= $linkClass('dashboard') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /></svg>
            <span class="sidebar-link-text">Dashboard</span>
        </a>
        <a href="<?= $h($url('analytics')) ?>" class="<?= $linkClass('analytics', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" /></svg>
            <span class="sidebar-link-text">Analytics</span>
        </a>
        <a href="<?= $h($url('products')) ?>" class="<?= $linkClass('products', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <span class="sidebar-link-text">Products</span>
        </a>
        <a href="<?= $h($url('orders')) ?>" class="<?= $linkClass('orders', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" /></svg>
            <span class="sidebar-link-text">Orders</span>
            <?= $badge($pendingOrders) ?>
        </a>
        <a href="<?= $h($url('customers')) ?>" class="<?= $linkClass('customers', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
            <span class="sidebar-link-text">Customers</span>
        </a>

        <div class="sidebar-section-title">Store</div>
        <a href="<?= $h($storefrontHref) ?>" class="<?= $linkClass('storefront', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : $storefrontTarget ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>
            <span class="sidebar-link-text">Storefront</span>
        </a>
        <a href="<?= $h($url('settings')) ?>" class="<?= $linkClass('settings', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
            <span class="sidebar-link-text">Store Settings</span>
        </a>
        <a href="<?= $h($url('subscription')) ?>" class="<?= $linkClass('subscription', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
            <span class="sidebar-link-text">Subscription</span>
        </a>
        <a href="<?= $h($url('vendor-chat')) ?>" class="<?= $linkClass('vendor-chat', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="sidebar-link-text">Chat</span>
            <?= $badge($unreadChat) ?>
        </a>
        <a href="<?= $h($url('vendor-communication')) ?>" class="<?= $linkClass('vendor-communication', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span class="sidebar-link-text">Communication</span>
        </a>
        <a href="<?= $h($url('notifications')) ?>" class="<?= $linkClass('notifications', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="sidebar-link-text">Notifications</span>
            <?= $badge($unreadNotifications) ?>
        </a>

        <div class="sidebar-section-title">AI Tools</div>
        <a href="<?= $h($url('ai-chat')) ?>" class="<?= $linkClass('ai-chat', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2a10 10 0 1 0 10 10 10 10 0 0 0-10-10zM12 6v4M12 16h.01"/><line x1="12" y1="12" x2="12" y2="12"/></svg>
            <span class="sidebar-link-text">AI Chat</span>
        </a>

        <div class="sidebar-section-title">Account</div>
        <a href="<?= $h($url('profile')) ?>" class="<?= $linkClass('profile', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="sidebar-link-text">Profile</span>
        </a>
        <a href="<?= $h($url('company-documents')) ?>" class="<?= $linkClass('company-documents', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span class="sidebar-link-text">Documents</span>
        </a>
        <a href="<?= $h($url('contanctsupport')) ?>" class="<?= $linkClass('contanctsupport', $navLocked) ?>"<?= $navLocked ? ' onclick="return false;"' : '' ?>>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span class="sidebar-link-text">Support</span>
        </a>
        <a href="<?= $logoutHref ?>" class="sidebar-link" onclick="if (typeof handleLogout === 'function') { handleLogout(); return false; } return confirm('Logout?');">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" /></svg>
            <span class="sidebar-link-text">Logout</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <?php if ($userAvatarUrl !== ''): ?>
                <img src="<?= $h($userAvatarUrl) ?>" alt="" class="sidebar-user-avatar">
            <?php else: ?>
                <div class="sidebar-user-avatar" style="display:flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--primary);font-weight:700;font-size:0.85rem;"><?= $userInitials ?></div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">
                    <?= $userName ?>
                    <?php if ($isSuspended): ?>
                        <span class="suspended-badge">Expired</span>
                    <?php endif; ?>
                </div>
                <div class="sidebar-user-role">
                    <?php if ($storeName !== ''): ?>
                        <?= $h($storeName) ?>
                    <?php else: ?>
                        <a href="<?= $createStoreHref ?>" style="color: var(--primary);">Create Store</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</aside>
