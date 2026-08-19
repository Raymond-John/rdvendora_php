<?php
/**
 * Google AdSense for the public site.
 * Live site (rdvendora.com) prints Google's official head snippet so crawlers
 * and Auto ads can see it. Localhost stays off unless ADSENSE_ENABLED=true.
 */
if (!function_exists('rdv_env')) {
    require_once dirname(__DIR__) . '/config/env.php';
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', dirname(__DIR__, 2));
    }
    rdv_load_env(BASE_PATH . '/.env');
}

if (!function_exists('rdv_adsense_publisher_id')) {
    function rdv_adsense_publisher_id() {
        $id = trim((string) rdv_env('ADSENSE_PUBLISHER_ID', 'pub-7091872948951204'));
        if (stripos($id, 'ca-pub-') === 0) {
            $id = substr($id, 3);
        }
        $id = preg_replace('/^ca-/', '', $id);
        if ($id === '' || stripos($id, 'XXXXXXXX') !== false) {
            return 'pub-7091872948951204';
        }
        if (!preg_match('/^pub-[0-9]{8,}$/', $id)) {
            return 'pub-7091872948951204';
        }
        return $id;
    }
}

if (!function_exists('rdv_adsense_client_id')) {
    function rdv_adsense_client_id() {
        return 'ca-' . rdv_adsense_publisher_id();
    }
}

if (!function_exists('rdv_adsense_is_live_host')) {
    function rdv_adsense_is_live_host() {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        return $host === 'rdvendora.com'
            || (
                (string) rdv_env('APP_ENV', '') === 'production'
                && stripos((string) rdv_env('APP_URL', ''), 'rdvendora.com') !== false
            );
    }
}

if (!function_exists('rdv_ads_enabled')) {
    function rdv_ads_enabled() {
        if (rdv_adsense_publisher_id() === '') {
            return false;
        }
        $flag = rdv_env('ADSENSE_ENABLED', null);
        if ($flag === false) {
            return rdv_adsense_is_live_host();
        }
        if ($flag === true) {
            return true;
        }
        return rdv_adsense_is_live_host();
    }
}

if (!function_exists('rdv_ad_unit_id')) {
    function rdv_ad_unit_id($slot) {
        $map = [
            'header' => 'ADSENSE_SLOT_HEADER',
            'content' => 'ADSENSE_SLOT_CONTENT',
            'sidebar' => 'ADSENSE_SLOT_SIDEBAR',
            'article' => 'ADSENSE_SLOT_ARTICLE',
            'footer' => 'ADSENSE_SLOT_FOOTER',
        ];
        $key = $map[$slot] ?? '';
        if ($key === '') {
            return '';
        }
        $unit = trim((string) rdv_env($key, ''));
        if ($unit === '' || !preg_match('/^[0-9]{6,}$/', $unit)) {
            return '';
        }
        return $unit;
    }
}

if (!function_exists('rdv_render_ad_slot')) {
    function rdv_render_ad_slot($placement, $label = 'Advertisement') {
        $placement = preg_replace('/[^a-z]/', '', strtolower((string) $placement));
        if (!in_array($placement, ['header', 'content', 'sidebar', 'article', 'footer'], true)) {
            $placement = 'content';
        }
        $pub = rdv_adsense_publisher_id();
        $unit = rdv_ad_unit_id($placement);
        $live = rdv_ads_enabled() && $pub !== '';

        if ($live && $unit === '') {
            return '';
        }

        ob_start();
        ?>
        <aside class="rdv-ad-slot rdv-ad-slot--<?= htmlspecialchars($placement, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" data-ad-placement="<?= htmlspecialchars($placement, ENT_QUOTES, 'UTF-8') ?>">
            <p class="rdv-ad-slot__label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($live && $unit !== ''): ?>
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="<?= htmlspecialchars(rdv_adsense_client_id(), ENT_QUOTES, 'UTF-8') ?>"
                     data-ad-slot="<?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?>"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
            <?php else: ?>
                <div class="rdv-ad-slot__placeholder">Ad placement reserved. Live ads load on rdvendora.com after Google AdSense approval.</div>
            <?php endif; ?>
        </aside>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('rdv_adsense_head_script')) {
    function rdv_adsense_head_script() {
        if (!rdv_ads_enabled()) {
            return '';
        }
        $client = rdv_adsense_client_id();
        $clientEsc = htmlspecialchars($client, ENT_QUOTES, 'UTF-8');
        return '<script>window.rdvAdsenseClient=' . json_encode($client) . ';</script>' . "\n"
            . '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='
            . $clientEsc . '" crossorigin="anonymous"></script>' . "\n";
    }
}
