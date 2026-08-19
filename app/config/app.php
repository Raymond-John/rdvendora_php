<?php
/**
 * Application constants. Override via .env on Hostinger.
 */
if (!defined('APP_NAME')) {
    define('APP_NAME', rdv_env('APP_NAME', 'RD Vendora'));
}

$appUrl = trim((string) rdv_env('APP_URL', ''));
$host = function_exists('rdv_request_host') ? rdv_request_host() : strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
if ($appUrl !== '' && function_exists('rdv_uri_is_local') && function_exists('rdv_host_is_local') && rdv_uri_is_local($appUrl) && !rdv_host_is_local($host)) {
    $appUrl = '';
}
if ($appUrl === '' && ($host === 'rdvendora.com' || $host === 'www.rdvendora.com' || str_ends_with((string) $host, '.rdvendora.com'))) {
    $appUrl = 'https://rdvendora.com';
}
if ($appUrl === '' && $host !== '') {
    $isLocal = function_exists('rdv_host_is_local') && rdv_host_is_local($host);
    $https = (function_exists('rdv_request_is_https') && rdv_request_is_https()) || !$isLocal;
    $scheme = $https ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if (substr($scriptDir, -9) === '/includes') {
        $scriptDir = dirname($scriptDir);
    }
    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = dirname($scriptDir);
    }
    if (!$isLocal) {
        $scriptDir = '';
    }
    $appUrl = $scheme . '://' . $host . rtrim($scriptDir, '/');
}
if (!defined('APP_URL')) {
    define('APP_URL', rtrim((string) $appUrl, '/'));
}

if (!defined('GOOGLE_PRODUCTION_REDIRECT_URI')) {
    define('GOOGLE_PRODUCTION_REDIRECT_URI', 'https://rdvendora.com/oauth2callback.php');
}

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', trim((string) rdv_env('GOOGLE_CLIENT_ID', '')));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', trim((string) rdv_env('GOOGLE_CLIENT_SECRET', '')));
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    $host = function_exists('rdv_request_host') ? rdv_request_host() : '';
    if ($host === 'rdvendora.com' || $host === 'www.rdvendora.com' || str_ends_with((string) $host, '.rdvendora.com')) {
        $redirect = GOOGLE_PRODUCTION_REDIRECT_URI;
    } else {
        $redirect = trim((string) rdv_env('GOOGLE_REDIRECT_URI', ''));
        if ($redirect !== '' && function_exists('rdv_uri_is_local') && rdv_uri_is_local($redirect) && !rdv_host_is_local($host)) {
            $redirect = '';
        }
        if ($redirect === '' || (stripos($redirect, 'http://') === 0 && !rdv_uri_is_local($redirect))) {
            $redirect = rtrim(APP_URL, '/') . '/oauth2callback.php';
        }
        if ($host === '' || (!function_exists('rdv_host_is_local') || !rdv_host_is_local($host))) {
            if (stripos($redirect, 'https://') !== 0) {
                $redirect = GOOGLE_PRODUCTION_REDIRECT_URI;
            }
        }
    }
    define('GOOGLE_REDIRECT_URI', $redirect);
}
