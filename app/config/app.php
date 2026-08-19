<?php
/**
 * Application constants. Override via .env on Hostinger.
 */
if (!defined('APP_NAME')) {
    define('APP_NAME', rdv_env('APP_NAME', 'RD Vendora'));
}

$appUrl = trim((string) rdv_env('APP_URL', ''));
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);
if ($appUrl === '' && ($host === 'rdvendora.com' || $host === 'www.rdvendora.com')) {
    $appUrl = 'https://rdvendora.com';
}
if ($appUrl === '' && $host !== '') {
    $scheme = (function_exists('rdv_request_is_https') && rdv_request_is_https()) ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (substr($scriptDir, -9) === '/includes') {
        $scriptDir = dirname($scriptDir);
    }
    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = dirname($scriptDir);
    }
    $appUrl = $scheme . '://' . $host . rtrim($scriptDir, '/');
}
if (!defined('APP_URL')) {
    define('APP_URL', rtrim((string) $appUrl, '/'));
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', trim((string) rdv_env('GOOGLE_CLIENT_ID', '')));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', trim((string) rdv_env('GOOGLE_CLIENT_SECRET', '')));
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    $redirect = trim((string) rdv_env('GOOGLE_REDIRECT_URI', ''));
    if ($redirect === '') {
        $redirect = APP_URL . '/oauth2callback.php';
    }
    define('GOOGLE_REDIRECT_URI', $redirect);
}
