<?php
/**
 * Store subdomain URLs, slug generation, and host detection.
 * Reuses existing stores.store_slug (unique, indexed).
 */

if (!function_exists('rdv_store_base_domain')) {
    function rdv_store_base_domain() {
        $configured = strtolower(trim((string) rdv_env('STORE_BASE_DOMAIN', '')));
        if ($configured !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $configured)) {
            return $configured;
        }
        $appHost = parse_url((string) (defined('APP_URL') ? APP_URL : ''), PHP_URL_HOST);
        $appHost = strtolower((string) $appHost);
        if ($appHost === 'www.rdvendora.com' || $appHost === 'rdvendora.com' || str_ends_with($appHost, '.rdvendora.com')) {
            return 'rdvendora.com';
        }
        if ($appHost !== '' && !rdv_host_is_local($appHost)) {
            return preg_replace('/^www\./', '', $appHost);
        }
        return 'rdvendora.com';
    }
}

if (!function_exists('rdv_store_subdomains_enabled')) {
    function rdv_store_subdomains_enabled() {
        $flag = strtolower(trim((string) rdv_env('STORE_SUBDOMAINS', '')));
        if ($flag === '1' || $flag === 'true' || $flag === 'on' || $flag === 'yes') {
            return true;
        }
        if ($flag === '0' || $flag === 'false' || $flag === 'off' || $flag === 'no') {
            return false;
        }
        $env = strtolower((string) rdv_env('APP_ENV', 'local'));
        if ($env !== 'production') {
            return false;
        }
        $host = function_exists('rdv_request_host') ? rdv_request_host() : '';
        $base = rdv_store_base_domain();
        return $host === $base
            || $host === 'www.' . $base
            || str_ends_with($host, '.' . $base);
    }
}

if (!function_exists('rdv_reserved_store_subdomains')) {
    function rdv_reserved_store_subdomains() {
        return [
            'www', 'admin', 'api', 'mail', 'ftp', 'cpanel', 'webmail', 'support', 'help',
            'blog', 'dashboard', 'app', 'cdn', 'static', 'assets', 'uploads', 'storage',
            'vendor', 'marketplace', 'login', 'register', 'oauth', 'oauth2callback',
            'pay', 'payment', 'payments', 'checkout', 'cart', 'billing', 'status',
            'statuspage', 'ns1', 'ns2', 'mx', 'smtp', 'imap', 'pop', 'dev', 'test',
            'staging', 'beta', 'demo', 'docs', 'doc', 'shop', 'store', 'stores',
            'seller', 'sellers', 'customer', 'customers', 'account', 'accounts',
            'secure', 'ssl', 'root', 'system', 'platform', 'rdvendora', 'www2',
            'm', 'mobile', 'img', 'images', 'media', 'files', 'download', 'downloads',
            'newsletter', 'email', 'emails', 'contact', 'about', 'pricing', 'features',
            'faq', 'terms', 'privacy', 'cookies', 'sitemap', 'robots', 'ads', 'ad',
        ];
    }
}

if (!function_exists('rdv_is_reserved_store_slug')) {
    function rdv_is_reserved_store_slug($slug) {
        $slug = strtolower(trim((string) $slug));
        return $slug === '' || in_array($slug, rdv_reserved_store_subdomains(), true);
    }
}

if (!function_exists('rdv_slugify_store_name')) {
    function rdv_slugify_store_name($name) {
        $string = strtolower(trim((string) $name));
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        $string = trim((string) $string, '-');
        if ($string === '') {
            $string = 'store';
        }
        if (strlen($string) > 80) {
            $string = rtrim(substr($string, 0, 80), '-');
        }
        return $string;
    }
}

if (!function_exists('rdv_is_valid_store_slug')) {
    function rdv_is_valid_store_slug($slug) {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '' || strlen($slug) > 100) {
            return false;
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return false;
        }
        return !rdv_is_reserved_store_slug($slug);
    }
}

if (!function_exists('rdv_unique_store_slug')) {
    function rdv_unique_store_slug(mysqli $conn, $baseSlug, $ignoreStoreId = 0) {
        $base = rdv_slugify_store_name($baseSlug);
        if (rdv_is_reserved_store_slug($base)) {
            $base = 'store-' . $base;
            if (rdv_is_reserved_store_slug($base)) {
                $base = 'shop-' . substr(md5((string) microtime(true)), 0, 6);
            }
        }
        $slug = $base;
        $counter = 2;
        while (true) {
            if (!rdv_is_reserved_store_slug($slug)) {
                $stmt = $conn->prepare('SELECT id FROM stores WHERE store_slug = ? LIMIT 1');
                if (!$stmt) {
                    return $slug . '-' . $counter;
                }
                $stmt->bind_param('s', $slug);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$row || ((int) $row['id'] === (int) $ignoreStoreId)) {
                    return $slug;
                }
            }
            $slug = $base . '-' . $counter;
            $counter++;
            if ($counter > 5000) {
                return $base . '-' . bin2hex(random_bytes(3));
            }
        }
    }
}

if (!function_exists('rdv_parse_store_host')) {
    /**
     * @return array{type:string,host:string,slug:?string,base:string}
     * type: main|www|store|reserved|unknown|local
     */
    function rdv_parse_store_host($host = null) {
        $host = strtolower(trim((string) ($host ?? (function_exists('rdv_request_host') ? rdv_request_host() : ''))));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = rtrim((string) $host, '.');
        $base = rdv_store_base_domain();

        if ($host === '' || rdv_host_is_local($host) || preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host)) {
            return ['type' => 'local', 'host' => $host, 'slug' => null, 'base' => $base];
        }

        if ($host === $base) {
            return ['type' => 'main', 'host' => $host, 'slug' => null, 'base' => $base];
        }
        if ($host === 'www.' . $base) {
            return ['type' => 'www', 'host' => $host, 'slug' => null, 'base' => $base];
        }

        $suffix = '.' . $base;
        if (!str_ends_with($host, $suffix)) {
            return ['type' => 'unknown', 'host' => $host, 'slug' => null, 'base' => $base];
        }

        $sub = substr($host, 0, -strlen($suffix));
        if ($sub === '' || str_contains($sub, '.')) {
            // e.g. a.b.rdvendora.com — not a single store label
            return ['type' => 'unknown', 'host' => $host, 'slug' => null, 'base' => $base];
        }
        $sub = strtolower($sub);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $sub)) {
            return ['type' => 'unknown', 'host' => $host, 'slug' => null, 'base' => $base];
        }
        if (rdv_is_reserved_store_slug($sub)) {
            return ['type' => 'reserved', 'host' => $host, 'slug' => $sub, 'base' => $base];
        }
        return ['type' => 'store', 'host' => $host, 'slug' => $sub, 'base' => $base];
    }
}

if (!function_exists('rdv_redirect_reserved_subdomain')) {
    function rdv_redirect_reserved_subdomain() {
        $home = rtrim((string) (defined('APP_URL') ? APP_URL : ('https://' . rdv_store_base_domain())), '/');
        if (!headers_sent()) {
            header('Location: ' . $home . '/', true, 302);
            exit;
        }
    }
}

if (!function_exists('rdv_store_url')) {
    /**
     * Public customer-facing store URL (subdomain in production; query URL locally).
     *
     * @param array|string $storeOrSlug Store row or slug string
     * @param string $path Optional path on the store host (e.g. product/12-name)
     * @param array $query Optional query params when not using subdomains
     */
    function rdv_store_url($storeOrSlug, $path = '', $query = []) {
        $slug = '';
        $id = 0;
        if (is_array($storeOrSlug)) {
            $slug = (string) ($storeOrSlug['store_slug'] ?? '');
            $id = (int) ($storeOrSlug['id'] ?? 0);
        } else {
            $slug = (string) $storeOrSlug;
        }
        $slug = strtolower(trim($slug));
        $path = ltrim((string) $path, '/');
        $scheme = (function_exists('rdv_request_is_https') && rdv_request_is_https()) || !rdv_host_is_local(rdv_request_host()) ? 'https' : 'http';
        if (defined('APP_URL') && stripos(APP_URL, 'https://') === 0) {
            $scheme = 'https';
        }

        if (rdv_store_subdomains_enabled() && $slug !== '' && rdv_is_valid_store_slug($slug)) {
            $url = $scheme . '://' . $slug . '.' . rdv_store_base_domain();
            if ($path !== '') {
                $url .= '/' . $path;
            }
            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }
            return $url;
        }

        $base = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
        if ($base === '') {
            $base = $scheme . '://' . (rdv_request_host() ?: 'localhost');
        }
        if ($path !== '' && preg_match('#^product/#', $path)) {
            // Local: map product path to product-details.php
            if (preg_match('#^product/([0-9]+)#', $path, $m)) {
                $q = array_merge(['id' => (int) $m[1], 'store' => $id], $query);
                return $base . '/product-details.php?' . http_build_query($q);
            }
        }
        if ($path !== '' && preg_match('#^category/(.+)$#', $path, $m)) {
            $q = array_merge(['store' => $id, 'cat' => rawurldecode($m[1])], $query);
            return $base . '/storefront.php?' . http_build_query($q);
        }
        $q = $query;
        if ($id > 0) {
            $q['store'] = $id;
        } elseif ($slug !== '') {
            $q['slug'] = $slug;
        }
        $url = $base . '/storefront.php';
        if (!empty($q)) {
            $url .= '?' . http_build_query($q);
        }
        return $url;
    }
}

if (!function_exists('getStoreUrl')) {
    function getStoreUrl($storeSlug) {
        return rdv_store_url($storeSlug);
    }
}

if (!function_exists('rdv_store_product_path')) {
    function rdv_store_product_path($productId, $productName = '') {
        $id = (int) $productId;
        $slug = rdv_slugify_store_name($productName);
        if ($slug === '' || $slug === 'store') {
            return 'product/' . $id;
        }
        return 'product/' . $id . '-' . $slug;
    }
}

if (!function_exists('rdv_store_product_url')) {
    function rdv_store_product_url($store, $productId, $productName = '') {
        $id = (int) $productId;
        if (!rdv_store_subdomains_enabled()) {
            $storeId = is_array($store) ? (int) ($store['id'] ?? 0) : 0;
            $base = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
            return $base . '/product-details.php?' . http_build_query(['id' => $id, 'store' => $storeId]);
        }
        return rdv_store_url($store, rdv_store_product_path($id, $productName));
    }
}

if (!function_exists('rdv_store_category_url')) {
    function rdv_store_category_url($store, $category) {
        $cat = trim((string) $category);
        if ($cat === '' || $cat === 'all') {
            return rdv_store_url($store);
        }
        if (!rdv_store_subdomains_enabled()) {
            $storeId = is_array($store) ? (int) ($store['id'] ?? 0) : 0;
            $base = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
            return $base . '/storefront.php?' . http_build_query(['store' => $storeId, 'cat' => $cat]);
        }
        return rdv_store_url($store, 'category/' . rawurlencode($cat));
    }
}

if (!function_exists('rdv_store_filter_url')) {
    function rdv_store_filter_url($store, $category = 'all', $search = '') {
        $cat = (string) $category;
        $search = trim((string) $search);
        if (rdv_store_subdomains_enabled()) {
            $path = ($cat !== '' && $cat !== 'all') ? 'category/' . rawurlencode($cat) : '';
            $query = $search !== '' ? ['q' => $search] : [];
            return rdv_store_url($store, $path, $query);
        }
        $storeId = is_array($store) ? (int) ($store['id'] ?? 0) : 0;
        $q = ['store' => $storeId];
        if ($cat !== '' && $cat !== 'all') {
            $q['cat'] = $cat;
        }
        if ($search !== '') {
            $q['q'] = $search;
        }
        $base = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
        return $base . '/storefront.php?' . http_build_query($q);
    }
}

if (!function_exists('rdv_fetch_store_by_slug')) {
    function rdv_fetch_store_by_slug(mysqli $conn, $slug, $requirePublic = true) {
        $slug = strtolower(trim((string) $slug));
        if (!rdv_is_valid_store_slug($slug)) {
            return null;
        }
        $sql = 'SELECT * FROM stores WHERE store_slug = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $store = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$store) {
            return null;
        }
        if ($requirePublic) {
            $status = strtolower((string) ($store['status'] ?? ''));
            $active = (int) ($store['active'] ?? 0);
            if ($status !== 'active' || $active !== 1) {
                return null;
            }
        }
        return $store;
    }
}

if (!function_exists('rdv_fetch_store_by_id')) {
    function rdv_fetch_store_by_id(mysqli $conn, $id, $requirePublic = false) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $store = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$store) {
            return null;
        }
        if ($requirePublic) {
            $status = strtolower((string) ($store['status'] ?? ''));
            $active = (int) ($store['active'] ?? 0);
            if ($status !== 'active' || $active !== 1) {
                return null;
            }
        }
        return $store;
    }
}

if (!function_exists('rdv_store_not_found_page')) {
    function rdv_store_not_found_page($message = null) {
        if (!headers_sent()) {
            http_response_code(404);
        }
        $page = (defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__, 2)) . '/store-not-found.php';
        if (is_readable($page)) {
            $rdvStoreNotFoundMessage = $message;
            include $page;
            exit;
        }
        $home = htmlspecialchars(rtrim((string) (defined('APP_URL') ? APP_URL : 'https://rdvendora.com'), '/'), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Store Not Found | RD Vendora</title></head><body style="font-family:system-ui,sans-serif;text-align:center;padding:4rem 1rem"><h1>Store Not Found</h1><p>This store could not be found or may no longer be available.</p><p><a href="' . $home . '/">Go to RD Vendora</a></p></body></html>';
        exit;
    }
}

if (!function_exists('rdv_redirect_legacy_storefront')) {
    function rdv_redirect_legacy_storefront(array $store) {
        if (!rdv_store_subdomains_enabled()) {
            return;
        }
        $parsed = rdv_parse_store_host();
        if ($parsed['type'] === 'store') {
            return; // already on store host
        }
        $slug = (string) ($store['store_slug'] ?? '');
        if ($slug === '' || !rdv_is_valid_store_slug($slug)) {
            return;
        }
        $target = rdv_store_url($store);
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $qs = [];
        if ($uri !== '' && str_contains($uri, '?')) {
            parse_str(parse_url($uri, PHP_URL_QUERY) ?: '', $qs);
            unset($qs['store'], $qs['slug']);
        }
        if (!empty($_GET['cat'])) {
            $target = rdv_store_category_url($store, (string) $_GET['cat']);
            if (!empty($_GET['q'])) {
                $target .= (str_contains($target, '?') ? '&' : '?') . 'q=' . rawurlencode((string) $_GET['q']);
            }
        } elseif (!empty($_GET['q'])) {
            $target .= (str_contains($target, '?') ? '&' : '?') . 'q=' . rawurlencode((string) $_GET['q']);
        }
        if (!headers_sent()) {
            header('Location: ' . $target, true, 301);
            exit;
        }
    }
}

if (!function_exists('rdv_resolve_public_store')) {
    /**
     * Resolve the store for public storefront/product pages from host or query.
     * @return array{store:?array,via:string,on_subdomain:bool}
     */
    function rdv_resolve_public_store(mysqli $conn, $requirePublic = true) {
        $parsed = rdv_parse_store_host();
        if ($parsed['type'] === 'reserved') {
            rdv_redirect_reserved_subdomain();
        }
        if ($parsed['type'] === 'store' && $parsed['slug']) {
            $store = rdv_fetch_store_by_slug($conn, $parsed['slug'], $requirePublic);
            if (!$store) {
                rdv_store_not_found_page();
            }
            return ['store' => $store, 'via' => 'subdomain', 'on_subdomain' => true];
        }

        $storeId = isset($_GET['store']) ? (int) $_GET['store'] : 0;
        $slug = isset($_GET['slug']) ? strtolower(trim((string) $_GET['slug'])) : '';
        $store = null;
        $via = 'none';

        if ($storeId > 0) {
            $store = rdv_fetch_store_by_id($conn, $storeId, $requirePublic);
            $via = 'id';
        } elseif ($slug !== '') {
            $store = rdv_fetch_store_by_slug($conn, $slug, $requirePublic);
            $via = 'slug';
        } elseif (!empty($_SESSION['user_id'])) {
            $uid = (int) $_SESSION['user_id'];
            $stmt = $conn->prepare('SELECT * FROM stores WHERE user_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $store = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
                $via = 'session';
                if ($store && $requirePublic) {
                    $status = strtolower((string) ($store['status'] ?? ''));
                    $active = (int) ($store['active'] ?? 0);
                    // Sellers may preview their own pending store via session fallback
                    $isOwner = ((int) ($store['user_id'] ?? 0) === $uid);
                    if (!$isOwner && ($status !== 'active' || $active !== 1)) {
                        $store = null;
                    }
                }
            }
        }

        return ['store' => $store, 'via' => $via, 'on_subdomain' => false];
    }
}
