<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$conn = $conn ?? $connect ?? null;

$svg = [
    'grid' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
    'users' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'store' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'pricing' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'chat' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
    'mail' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    'envelope' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
    'news' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="14" y2="14"/></svg>',
    'cart' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
    'file' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    'clock' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'chart' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
    'gear' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'logout' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
];

$can = static function ($key) use ($conn) {
    if (!function_exists('adminHasPermission') || !$conn) {
        return true;
    }
    return adminHasPermission($key, $conn);
};

$item = static function ($url, $label, $icon) use ($currentPage) {
    $active = $currentPage === $url ? ' active' : '';
    echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="sidebar-item' . $active . '">' . $icon . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
};
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="nav-logo">
            <img class="rdv-brand-logo rdv-brand-logo--sidebar" src="../assets/brand-logo.png" alt="RD Vendora">
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-section-title">Platform</div>
        <?php if ($can('dashboard')) { $item('admin.php', 'Dashboard', $svg['grid']); } ?>
        <?php if ($can('users')) { $item('admin-users.php', 'Users', $svg['users']); } ?>
        <?php if ($can('stores')) { $item('admin-stores.php', 'Stores', $svg['store']); } ?>
        <?php if ($can('pricing')) { $item('admin-pricing.php', 'Pricing Plans', $svg['pricing']); } ?>
        <?php if ($can('testimonials')) { $item('admin-testimonies.php', 'Testimonials', $svg['chat']); } ?>
        <?php if ($can('contacts')) { $item('admin-contacts.php', 'Contact Messages', $svg['mail']); } ?>
        <?php if ($can('newsletter') || $can('contacts')) { $item('admin-newsletter.php', 'Newsletter', $svg['envelope']); } ?>
        <?php if ($can('blog') || $can('about') || $can('newsletter')) { $item('admin-blog.php', 'News', $svg['news']); } ?>
        <?php if ($can('about')) { $item('admin-about.php', 'About Page', $svg['envelope']); } ?>
        <?php if ($can('chat')) { $item('admin-chat.php', 'Chat', $svg['envelope']); } ?>
        <?php if ($can('send_email') || $can('chat')) { $item('admin-messages.php', 'Messages', $svg['envelope']); } ?>
        <?php if ($can('orders')) { $item('admin-receive-order.php', 'All Orders', $svg['cart']); } ?>
        <?php if ($can('transport')) { $item('admin-transport.php', 'Transport Orders', $svg['cart']); } ?>
        <?php if ($can('customers')) { $item('admin-customers.php', 'Customers', $svg['users']); } ?>
        <?php if ($can('send_email')) { $item('admin-send-email.php', 'Send Email', $svg['envelope']); } ?>
        <?php if ($can('marketplace_design')) { $item('admin-marketplace-design.php', 'Marketplace Design', $svg['news']); } ?>
        <?php if ($can('stores') || $can('users')) { $item('admin-documents.php', 'Document Review', $svg['file']); } ?>
        <div class="sidebar-section-title">Analytics</div>
        <?php if ($can('dashboard') || $can('users')) { $item('admin-user-activity.php', 'User Activity', $svg['clock']); } ?>
        <?php if ($can('dashboard')) { $item('admin-analytics.php', 'Analytics', $svg['chart']); } ?>
        <div class="sidebar-section-title">System</div>
        <?php if ($can('pricing')) { $item('admin-subscriptions.php', 'Subscriptions', $svg['pricing']); } ?>
        <?php if ($can('settings')) { $item('adminsettings.php', 'Settings', $svg['gear']); } ?>
        <a href="../dashboard.php" class="sidebar-item"><?= $svg['logout'] ?><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><?= $svg['logout'] ?><span>Logout</span></a>
    </nav>
</div>
