<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once APP_PATH . '/helpers/csrf.php';
require_once APP_PATH . '/helpers/ads.php';
require_once APP_PATH . '/helpers/analytics.php';
require_once APP_PATH . '/helpers/newsletter.php';
require_once APP_PATH . '/helpers/blog.php';

if (!function_exists('rdv_canonical_url')) {
    function rdv_canonical_url($path = null) {
        if ($path === null) {
            $path = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? 'index.php', PHP_URL_PATH) ?: 'index.php');
        }
        $path = ltrim((string) $path, '/');
        // Prefer clean URLs in SEO tags
        if (function_exists('rdv_url')) {
            // Absolute http(s) already (e.g. store URLs)
            if (preg_match('#^https?://#i', $path)) {
                return $path;
            }
            return rdv_url($path);
        }
        if (preg_match('/\.php$/i', $path)) {
            $path = substr($path, 0, -4);
        }
        if ($path === '' || $path === 'index') {
            return rtrim(APP_URL, '/') . '/';
        }
        return rtrim(APP_URL, '/') . '/' . $path;
    }
}

if (!function_exists('rdv_site_contact_email')) {
    function rdv_site_contact_email($conn = null) {
        $email = '';
        if ($conn instanceof mysqli) {
            $email = rdv_site_setting($conn, 'site_email');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = trim((string) rdv_env('SMTP_FROM', rdv_env('SMTP_USER', '')));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return $email;
    }
}

if (!function_exists('rdv_site_setting')) {
    function rdv_site_setting($conn, $key) {
        if (!$conn instanceof mysqli) {
            return '';
        }
        try {
            $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
            if (!$stmt) {
                return '';
            }
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return trim((string) ($row['setting_value'] ?? ''));
        } catch (Throwable $e) {
            error_log('rdv_site_setting(' . $key . '): ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('rdv_developer_credit')) {
    function rdv_developer_credit($conn = null) {
        if (!$conn instanceof mysqli) {
            $conn = $GLOBALS['conn'] ?? ($GLOBALS['connect'] ?? null);
        }
        $label = 'RD NEXA TECH';
        $url = '';
        if ($conn instanceof mysqli) {
            $labelSetting = rdv_site_setting($conn, 'developer_credit_label');
            $urlSetting = rdv_site_setting($conn, 'developer_credit_url');
            if ($labelSetting !== '') {
                $label = $labelSetting;
            }
            $url = $urlSetting;
        }
        return ['label' => $label, 'url' => $url];
    }
}

if (!function_exists('rdv_developer_credit_html')) {
    function rdv_developer_credit_html($conn = null) {
        $credit = rdv_developer_credit($conn);
        $label = htmlspecialchars($credit['label'], ENT_QUOTES, 'UTF-8');
        $url = trim((string) $credit['url']);
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return 'Developed by <a href="' . $urlEsc . '" target="_blank" rel="noopener noreferrer" class="footer-developer-link">' . $label . '</a>';
        }
        return 'Developed by ' . $label;
    }
}

if (!function_exists('rdv_brand_logo')) {
    function rdv_brand_logo($prefix = '', $extraClass = '', $showName = true) {
        $src = htmlspecialchars(rdv_asset('assets/brand-logo.png', $prefix), ENT_QUOTES, 'UTF-8');
        $class = trim('rdv-brand-logo ' . $extraClass);
        $html = '<img class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" src="' . $src . '" alt="">';
        if ($showName) {
            $html .= '<span class="rdv-brand-name">RD Vendora</span>';
        }
        return $html;
    }
}

if (!function_exists('rdv_favicon_tags')) {
    function rdv_favicon_tags($prefix = '') {
        $logo = htmlspecialchars(rdv_asset('assets/brand-logo.png', $prefix), ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars(rdv_asset('assets/favicon.svg', $prefix), ENT_QUOTES, 'UTF-8');
        return '  <link rel="icon" href="' . $logo . '" type="image/png">' . "\n"
            . '  <link rel="icon" href="' . $icon . '" type="image/svg+xml">' . "\n"
            . '  <link rel="apple-touch-icon" href="' . $logo . '">' . "\n";
    }
}

if (!function_exists('rdv_seo_tags')) {
    function rdv_seo_tags($title, $description, $path = null, $type = 'website', $image = '') {
        $canonical = rdv_canonical_url($path);
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $canonicalEsc = htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8');
        $ogImage = '';
        if ($image === '') {
            $image = rtrim(APP_URL, '/') . '/assets/brand-logo.png';
        }
        if ($image !== '') {
            $ogImage = '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        $verify = trim((string) rdv_env('GOOGLE_SITE_VERIFICATION', ''));
        $verifyTag = '';
        if ($verify !== '' && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $verify)) {
            $verifyTag = '<meta name="google-site-verification" content="' . htmlspecialchars($verify, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        return <<<HTML
  <title>{$title}</title>
  <meta name="description" content="{$description}">
  <link rel="canonical" href="{$canonicalEsc}">
  <meta property="og:type" content="{$type}">
  <meta property="og:site_name" content="RD Vendora">
  <meta property="og:title" content="{$title}">
  <meta property="og:description" content="{$description}">
  <meta property="og:url" content="{$canonicalEsc}">
  {$ogImage}  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="{$title}">
  <meta name="twitter:description" content="{$description}">
  {$verifyTag}
HTML;
    }
}

if (!function_exists('rdv_org_schema')) {
    function rdv_org_schema() {
        $data = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => rtrim(APP_URL, '/') . '/#organization',
                    'name' => 'RD Vendora',
                    'url' => rtrim(APP_URL, '/'),
                    'description' => 'RD Vendora is a multi-vendor eCommerce platform for creating and managing online stores.',
                    'email' => rdv_site_contact_email($GLOBALS['conn'] ?? $GLOBALS['connect'] ?? null) ?: null,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => rtrim(APP_URL, '/') . '/#website',
                    'url' => rtrim(APP_URL, '/'),
                    'name' => 'RD Vendora',
                    'publisher' => ['@id' => rtrim(APP_URL, '/') . '/#organization'],
                ],
            ],
        ];
        if (empty($data['@graph'][0]['email'])) {
            unset($data['@graph'][0]['email']);
        }
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }
}

if (!function_exists('rdv_newsletter_form')) {
    function rdv_newsletter_form($context = 'footer') {
        $csrf = rdv_csrf_field();
        $id = 'rdv-newsletter-' . preg_replace('/[^a-z0-9-]/', '', $context);
        $action = htmlspecialchars(function_exists('rdv_url') ? rdv_url('newsletter-subscribe') : 'newsletter-subscribe', ENT_QUOTES, 'UTF-8');
        return <<<HTML
<form class="rdv-newsletter-form" id="{$id}" method="post" action="{$action}" novalidate>
  {$csrf}
  <input type="text" name="website" class="rdv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <label class="rdv-sr-only" for="{$id}-email">Email address</label>
  <input type="email" id="{$id}-email" name="email" required maxlength="190" placeholder="Email address" autocomplete="email">
  <label class="rdv-consent">
    <input type="checkbox" name="consent" value="1" required>
    <span>I want to subscribe to the RD Vendora newsletter for platform news, useful business resources, and product updates. I can unsubscribe at any time.</span>
  </label>
  <button type="submit" class="btn btn-primary">Subscribe</button>
  <p class="rdv-newsletter-status" role="status" aria-live="polite"></p>
</form>
HTML;
    }
}

if (!function_exists('rdv_public_nav_items')) {
    function rdv_public_nav_items() {
        return [
            'index' => 'Home',
            'features' => 'Features',
            'pricing' => 'Pricing',
            'blog' => 'News',
            'about' => 'About',
            'contact' => 'Contact',
        ];
    }
}
