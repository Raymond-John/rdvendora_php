<?php
/**
 * Fallback autoload used when Composer has not been installed on the server.
 * Running `composer install` replaces this file with Composer's autoloader.
 */
$composerReal = __DIR__ . '/composer/autoload_real.php';
if (file_exists($composerReal)) {
    require_once $composerReal;
    foreach (get_declared_classes() as $class) {
        if (strpos($class, 'ComposerAutoloaderInit') === 0 && method_exists($class, 'getLoader')) {
            return $class::getLoader();
        }
    }
}

spl_autoload_register(static function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $file = __DIR__ . '/phpmailer/phpmailer/src/' . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
