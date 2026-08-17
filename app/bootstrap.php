<?php
/**
 * RD Vendora application bootstrap.
 * Loaded by public pages via includes/connection.php (URL-compatible wrapper).
 */
if (defined('RDV_BOOTSTRAPPED')) {
    return;
}
define('RDV_BOOTSTRAPPED', true);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH);
define('VENDOR_PATH', BASE_PATH . '/vendor');

require_once APP_PATH . '/config/env.php';
rdv_load_env(BASE_PATH . '/.env');

$appEnv = (string) rdv_env('APP_ENV', 'local');
$appDebug = (bool) rdv_env('APP_DEBUG', $appEnv !== 'production');

ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');
ini_set('display_errors', $appDebug ? '1' : '0');
error_reporting($appDebug ? E_ALL : E_ALL & ~E_NOTICE & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookiePath = '/';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if (substr($dir, -9) === '/includes') {
            $dir = dirname($dir);
        }
        if ($dir !== '/' && $dir !== '.' && $dir !== '') {
            $cookiePath = $dir;
        }
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once APP_PATH . '/config/app.php';
require_once APP_PATH . '/config/database.php';

if (!function_exists('rdv_load_phpmailer')) {
    function rdv_load_phpmailer() {
        static $loaded = false;
        if ($loaded) {
            return class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
        }

        $composerAutoload = VENDOR_PATH . '/composer/autoload_real.php';
        if (file_exists($composerAutoload) && file_exists(VENDOR_PATH . '/autoload.php')) {
            require_once VENDOR_PATH . '/autoload.php';
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                $loaded = true;
                return true;
            }
        }

        $src = VENDOR_PATH . '/phpmailer/phpmailer/src';
        if (file_exists($src . '/PHPMailer.php')) {
            require_once $src . '/Exception.php';
            require_once $src . '/PHPMailer.php';
            require_once $src . '/SMTP.php';
            $loaded = true;
            return true;
        }

        return false;
    }
}
