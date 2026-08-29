<?php
/**
 * Vendor topbar/sidebar user avatar markup.
 * Requires session user_id and optional $conn in scope.
 */
if (!function_exists('rdv_user_avatar_url')) {
    require_once dirname(__DIR__) . '/app/helpers/user_avatar.php';
}

$rdvAvatarUserId = (int) ($_SESSION['user_id'] ?? 0);
$rdvAvatarConn = $conn ?? ($connect ?? null);
$rdvAvatarUrl = '';
if ($rdvAvatarUserId > 0 && !empty($_SESSION['user_avatar']) && is_string($_SESSION['user_avatar'])) {
    $rdvAvatarUrl = $_SESSION['user_avatar'];
}
if ($rdvAvatarUrl === '' && $rdvAvatarUserId > 0 && $rdvAvatarConn instanceof mysqli) {
    $rdvAvatarUrl = rdv_user_avatar_url($rdvAvatarConn, $rdvAvatarUserId);
    if ($rdvAvatarUrl !== '') {
        $_SESSION['user_avatar'] = $rdvAvatarUrl;
    }
}
$rdvAvatarInitials = rdv_user_avatar_initials($_SESSION['fullname'] ?? 'User');
$rdvAvatarClass = isset($rdvAvatarClass) && is_string($rdvAvatarClass) ? $rdvAvatarClass : 'topbar-user-avatar';

if ($rdvAvatarUrl !== '') {
    echo '<img src="' . htmlspecialchars($rdvAvatarUrl, ENT_QUOTES, 'UTF-8') . '" alt="User" class="' . htmlspecialchars($rdvAvatarClass, ENT_QUOTES, 'UTF-8') . '">';
} else {
    echo '<div class="' . htmlspecialchars($rdvAvatarClass, ENT_QUOTES, 'UTF-8') . '" style="display:flex;align-items:center;justify-content:center;background:var(--primary-light);color:var(--primary);font-weight:700;font-size:12px;flex-shrink:0;">'
        . htmlspecialchars($rdvAvatarInitials, ENT_QUOTES, 'UTF-8')
        . '</div>';
}
