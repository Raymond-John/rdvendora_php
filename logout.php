<?php
session_start();

require_once __DIR__ . '/includes/email_functions.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userEmail = trim((string) ($_SESSION['user_email'] ?? $_SESSION['email'] ?? ''));
$userName = trim((string) ($_SESSION['user_name'] ?? $_SESSION['fullname'] ?? ''));

if ($userId > 0) {
    require_once __DIR__ . '/includes/log_activity.php';
    if (function_exists('logUserActivity')) {
        logUserActivity($userId, 'logout', 'logout.php', 'Signed out');
    }
}

if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL) && function_exists('sendLogoutNotification')) {
    try {
        sendLogoutNotification($userEmail, $userName !== '' ? $userName : $userEmail);
    } catch (Throwable $e) {
        error_log('Logout email failed: ' . $e->getMessage());
    }
}

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header('Location: login');
exit;
