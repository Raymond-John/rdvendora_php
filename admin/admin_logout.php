<?php
require_once __DIR__ . '/../includes/connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/log_activity.php';
    $userId = (int) $_SESSION['user_id'];
    $userEmail = trim((string) ($_SESSION['email'] ?? ''));
    $userName = trim((string) ($_SESSION['fullname'] ?? ''));
    $isAdmin = !empty($_SESSION['is_admin']);

    if (function_exists('logUserActivity')) {
        logUserActivity($userId, $isAdmin ? 'admin_logout' : 'logout', 'admin_logout.php', $isAdmin ? 'Signed out of the admin panel' : 'Signed out');
    }

    if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/../includes/email_functions.php';
        if (function_exists('sendLogoutNotification')) {
            try {
                sendLogoutNotification(
                    $userEmail,
                    $userName !== '' ? $userName : $userEmail,
                    $isAdmin ? 'admin' : 'account'
                );
            } catch (Throwable $e) {
                error_log('Admin logout email failed: ' . $e->getMessage());
            }
        }
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
