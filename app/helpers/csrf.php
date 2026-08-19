<?php
/**
 * Session CSRF tokens for public forms.
 */
if (!function_exists('rdv_csrf_token')) {
    function rdv_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['rdv_csrf']) || !is_string($_SESSION['rdv_csrf'])) {
            $_SESSION['rdv_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['rdv_csrf'];
    }
}

if (!function_exists('rdv_csrf_field')) {
    function rdv_csrf_field() {
        $token = htmlspecialchars(rdv_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('rdv_csrf_verify')) {
    function rdv_csrf_verify($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $expected = $_SESSION['rdv_csrf'] ?? '';
        $given = $token;
        if ($given === null) {
            $given = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        return is_string($expected) && is_string($given) && $expected !== '' && hash_equals($expected, $given);
    }
}
