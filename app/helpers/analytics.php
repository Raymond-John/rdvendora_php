<?php
/**
 * Google Analytics 4 (gtag.js).
 * Loads on the live site when a valid G- measurement ID is set in .env
 * or Admin → Settings. Page hits start after the visitor opts in to analytics.
 */
if (!function_exists('rdv_env')) {
    require_once dirname(__DIR__) . '/config/env.php';
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', dirname(__DIR__, 2));
    }
    rdv_load_env(BASE_PATH . '/.env');
}

if (!function_exists('rdv_ga_measurement_id')) {
    function rdv_ga_measurement_id() {
        $id = trim((string) rdv_env('GA_MEASUREMENT_ID', rdv_env('GOOGLE_ANALYTICS_ID', 'G-2CMDRDJNSM')));
        if ($id === '') {
            $conn = $GLOBALS['conn'] ?? $GLOBALS['connect'] ?? null;
            if ($conn instanceof mysqli) {
                $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'google_analytics_id' LIMIT 1");
                if ($stmt) {
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $fromDb = trim((string) ($row['setting_value'] ?? ''));
                    if ($fromDb !== '') {
                        $id = $fromDb;
                    }
                }
            }
        }
        if ($id === '') {
            $id = 'G-2CMDRDJNSM';
        }
        $id = strtoupper(preg_replace('/\s+/', '', $id));
        if ($id === '' || stripos($id, 'XXXX') !== false) {
            return '';
        }
        if (!preg_match('/^G-[A-Z0-9]{6,}$/', $id)) {
            return '';
        }
        return $id;
    }
}

if (!function_exists('rdv_ga_enabled')) {
    function rdv_ga_enabled() {
        if (rdv_ga_measurement_id() === '') {
            return false;
        }
        $flag = rdv_env('GA_ENABLED', null);
        if ($flag === true) {
            return true;
        }
        if (function_exists('rdv_adsense_is_live_host')) {
            return rdv_adsense_is_live_host();
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        return $host === 'rdvendora.com';
    }
}

if (!function_exists('rdv_analytics_head_script')) {
    function rdv_analytics_head_script() {
        if (!rdv_ga_enabled()) {
            return '';
        }
        $id = rdv_ga_measurement_id();
        $idJson = json_encode($id);
        $idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $optedIn = isset($_COOKIE['rdv_ga_consent']) && $_COOKIE['rdv_ga_consent'] === '1';
        $html = '<script>window.rdvGaId=' . $idJson . ';</script>' . "\n";
        if (!$optedIn) {
            return $html;
        }
        $html .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $idEsc . '"></script>' . "\n";
        $html .= '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . $idJson . ',{anonymize_ip:true});</script>' . "\n";
        return $html;
    }
}
