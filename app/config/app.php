<?php
/**
 * Application constants. Override via .env on Hostinger.
 */
if (!defined('APP_NAME')) {
    define('APP_NAME', rdv_env('APP_NAME', 'RD Vendora'));
}

$appUrl = rdv_env('APP_URL', '');
if ($appUrl === '' && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (substr($scriptDir, -9) === '/includes') {
        $scriptDir = dirname($scriptDir);
    }
    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = dirname($scriptDir);
    }
    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim($scriptDir, '/');
}
if (!defined('APP_URL')) {
    define('APP_URL', rtrim((string) $appUrl, '/'));
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', rdv_env('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com'));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', rdv_env('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'));
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    $redirect = rdv_env('GOOGLE_REDIRECT_URI', '');
    if ($redirect === '') {
        $redirect = APP_URL . '/oauth2callback.php';
    }
    define('GOOGLE_REDIRECT_URI', $redirect);
}
