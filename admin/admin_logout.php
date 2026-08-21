<?php
require_once __DIR__ . '/../includes/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/log_activity.php';
    if (function_exists('logUserActivity')) {
        logUserActivity((int) $_SESSION['user_id'], 'admin_logout', 'admin_logout.php', 'Signed out of the admin panel');
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

$login = function_exists('rdv_url') ? rdv_url('admin/admin_login') : 'admin_login';
header('Location: ' . $login);
exit;
