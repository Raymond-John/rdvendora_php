<?php
/**
 * Public store URLs: https://rdvendora.com/{store-slug}
 * Reuses stores.store_slug (unique). Optional STORE_URL_MODE=subdomain|query for legacy.
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

if (!function_exists('rdv_store_url_mode')) {
    /** @return string path|subdomain|query */
    function rdv_store_url_mode() {
        $mode = strtolower(trim((string) rdv_env('STORE_URL_MODE', '')));
        if (in_array($mode, ['path', 'subdomain', 'query'], true)) {
            return $mode;
        }
        // Default: path URLs on the main domain (Hostinger-friendly).
        // Use STORE_URL_MODE=subdomain only when wildcard DNS + SSL are ready.
        return 'path';
    }
}

if (!function_exists('rdv_store_subdomains_enabled')) {
    function rdv_store_subdomains_enabled() {
        return rdv_store_url_mode() === 'subdomain';
    }
}

if (!function_exists('rdv_store_path_urls_enabled')) {
    function rdv_store_path_urls_enabled() {
        return rdv_store_url_mode() === 'path';
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
            'logout', 'settings', 'profile', 'products', 'orders', 'analytics',
            'subscription', 'notifications', 'create-store', 'forgot-password',
            'reset-password', 'maintenance', 'storefront', 'product', 'product-details',
            'category', 'categories', 'home', 'index', 'includes', 'database', 'config',
            'public', 'css', 'js', 'fonts', 'favicon', 'webhook', 'verify', 'billing',
            'community-guidelines', 'disclaimer', 'sitemap.xml', 'ads.txt', 'robots.txt',
            'store-not-found', 'explore', 'search', 'cart', 'checkout', 'order',
            'orders', 'ai-chat', 'company-documents', 'customers', 'ecommercestore',
            'style', 'contanctsupport', 'accept-invite', 'csrf-token', 'submit-contact',
            'submit-testimonial', 'newsletter-subscribe', 'newsletter-confirm',
            'newsletter-unsubscribe', 'blog-post', 'oauth2callback', 'process_payment',
            'process_order', 'payment_callback', 'verify-paystack', 'verify-flutterwave',
            'verify_payment', 'marketplaceaddtocart', 'marketplacecheckout',
            'marketplaceviewproduct', 'marketplace_process_order', 'marketplace_verify_payment',
            'order_success', 'order_success_store', 'order_confirmation', 'order-confirmation',
            'customer-profile', 'vendor-chat', 'vendor-chat-api', 'vendor-communication',
            'vendor-messages', 'transport_orders', 'update_order_status', 'get_notification_count',
            'chatbot-ajax', 'chat_get_messages', 'chat_mark_read', 'chat_typing',
            'chat_typing_ping', 'chat_update_activity', 'chat_upload_audio', 'chat_get_typing',
            'et_order_details', '403', '404', '500', 'phpmyadmin', 'wp-admin', 'wordpress',
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
        $string = preg_replace("/['’`]/u", '', $string);
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

if (!function_exists('rdv_store_slug_availability')) {
    /**
     * @return array{ok:bool,message:string,slug:string}
     */
    function rdv_store_slug_availability(mysqli $conn, $slug, $ignoreStoreId = 0) {
        $slug = strtolower(trim((string) $slug));
        $slug = rdv_slugify_store_name($slug);
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return ['ok' => false, 'message' => 'Only letters, numbers and hyphens are allowed', 'slug' => $slug];
        }
        if (rdv_is_reserved_store_slug($slug)) {
            return ['ok' => false, 'message' => 'Store URL is reserved by the platform', 'slug' => $slug];
        }
        $stmt = $conn->prepare('SELECT id FROM stores WHERE store_slug = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not validate store URL', 'slug' => $slug];
        }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int) $row['id'] !== (int) $ignoreStoreId) {
            return ['ok' => false, 'message' => 'Store URL is already taken', 'slug' => $slug];
        }
        return ['ok' => true, 'message' => 'Store URL is available', 'slug' => $slug];
    }
}

if (!function_exists('rdv_app_base_url')) {
    function rdv_app_base_url() {
        $base = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
        if ($base !== '') {
            return $base;
        }
        $scheme = (function_exists('rdv_request_is_https') && rdv_request_is_https()) ? 'https' : 'http';
        if (defined('APP_URL') && stripos((string) APP_URL, 'https://') === 0) {
            $scheme = 'https';
        }
        $host = function_exists('rdv_request_host') ? rdv_request_host() : 'localhost';
        if ($host === '' || (function_exists('rdv_host_is_local') && rdv_host_is_local($host))) {
            // Local XAMPP often lives under a folder
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            if (substr($scriptDir, -9) === '/includes') {
                $scriptDir = dirname($scriptDir);
            }
            if (substr($scriptDir, -6) === '/admin') {
                $scriptDir = dirname($scriptDir);
            }
            return $scheme . '://' . ($host ?: 'localhost') . ($scriptDir === '/' ? '' : rtrim($scriptDir, '/'));
        }
        return $scheme . '://' . $host;
    }
}

if (!function_exists('rdv_parse_store_host')) {
    /**
     * @return array{type:string,host:string,slug:?string,base:string}
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
        $home = rdv_app_base_url();
        if (!headers_sent()) {
            header('Location: ' . $home . '/', true, 302);
            exit;
        }
    }
}

if (!function_exists('rdv_is_clean_store_request')) {
    function rdv_is_clean_store_request($slug = null) {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $basePath = parse_url(rdv_app_base_url(), PHP_URL_PATH) ?: '';
        $basePath = rtrim((string) $basePath, '/');
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath)) ?: '/';
        } elseif ($basePath !== '' && $path === $basePath) {
            $path = '/';
        }
        if ($slug) {
            $slug = preg_quote(strtolower((string) $slug), '#');
            return (bool) preg_match('#^/' . $slug . '(?:/|$)#i', $path);
        }
        return (bool) preg_match('#^/[a-z0-9]+(?:-[a-z0-9]+)*(?:/(?:product|category)/|$)#i', $path);
    }
}

if (!function_exists('rdv_store_url')) {
    /**
     * @param array|string $storeOrSlug
     * @param string $path Relative path under the store (product/… or category/…)
     */
    function rdv_store_url($storeOrSlug, $path = '', $query = []) {
        $slug = '';
        $id = 0;
        if (is_array($storeOrSlug)) {
            $slug = (string) ($storeOrSlug['store_slug'] ?? '');
            $id = (int) ($storeOrSlug['id'] ?? ($storeOrSlug['store_pk'] ?? 0));
        } else {
            $slug = (string) $storeOrSlug;
        }
        $slug = strtolower(trim($slug));
        $path = ltrim((string) $path, '/');
        $mode = rdv_store_url_mode();
        $base = rdv_app_base_url();

        if ($mode === 'subdomain' && $slug !== '' && rdv_is_valid_store_slug($slug)) {
            $scheme = (stripos($base, 'https://') === 0) ? 'https' : 'http';
            $url = $scheme . '://' . $slug . '.' . rdv_store_base_domain();
            if ($path !== '') {
                $url .= '/' . $path;
            }
            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }
            return $url;
        }

        if ($mode === 'path' && $slug !== '' && rdv_is_valid_store_slug($slug)) {
            $url = $base . '/' . rawurlencode($slug);
            // Keep hyphens unencoded (rawurlencode encodes them? Actually - is unreserved)
            $url = $base . '/' . $slug;
            if ($path !== '') {
                // category may have spaces encoded
                if (str_starts_with($path, 'category/')) {
                    $cat = substr($path, 9);
                    $url .= '/category/' . str_replace('%2F', '/', rawurlencode(rawurldecode($cat)));
                } else {
                    $url .= '/' . $path;
                }
            }
            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }
            return $url;
        }

        // query fallback (local/debug)
        if ($path !== '' && preg_match('#^product/([0-9]+)#', $path, $m)) {
            $q = array_merge(['id' => (int) $m[1], 'store' => $id], $query);
            if ($slug !== '') {
                $q['slug'] = $slug;
            }
            return $base . '/product-details.php?' . http_build_query($q);
        }
        if ($path !== '' && preg_match('#^category/(.+)$#', $path, $m)) {
            $q = array_merge(['store' => $id, 'cat' => rawurldecode($m[1])], $query);
            if ($slug !== '') {
                $q['slug'] = $slug;
            }
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
        $mode = rdv_store_url_mode();
        if ($mode === 'query') {
            $storeId = is_array($store) ? (int) ($store['id'] ?? 0) : 0;
            return rdv_app_base_url() . '/product-details.php?' . http_build_query(['id' => $id, 'store' => $storeId]);
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
        if (rdv_store_url_mode() === 'query') {
            $storeId = is_array($store) ? (int) ($store['id'] ?? 0) : 0;
            return rdv_app_base_url() . '/storefront.php?' . http_build_query(['store' => $storeId, 'cat' => $cat]);
        }
        return rdv_store_url($store, 'category/' . $cat);
    }
}

if (!function_exists('rdv_store_filter_url')) {
    function rdv_store_filter_url($store, $category = 'all', $search = '') {
        $cat = (string) $category;
        $search = trim((string) $search);
        $query = $search !== '' ? ['q' => $search] : [];
        if (rdv_store_url_mode() === 'query') {
            $storeId = is_array($store) ? (int) ($store['id'] ?? 0) : 0;
            $q = array_merge(['store' => $storeId], $query);
            if ($cat !== '' && $cat !== 'all') {
                $q['cat'] = $cat;
            }
            return rdv_app_base_url() . '/storefront.php?' . http_build_query($q);
        }
        $path = ($cat !== '' && $cat !== 'all') ? 'category/' . $cat : '';
        return rdv_store_url($store, $path, $query);
    }
}

if (!function_exists('rdv_fetch_store_by_slug')) {
    function rdv_fetch_store_by_slug(mysqli $conn, $slug, $requirePublic = true) {
        $slug = strtolower(trim((string) $slug));
        if (!rdv_is_valid_store_slug($slug)) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM stores WHERE store_slug = ? LIMIT 1');
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
        $home = htmlspecialchars(rdv_app_base_url(), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Store not found | RD Vendora</title></head><body style="font-family:system-ui,sans-serif;text-align:center;padding:4rem 1rem"><h1>Store not found</h1><p>Sorry, we couldn\'t find a store with this address.</p><p><a href="' . $home . '/marketplace.php">Explore RD Vendora</a></p></body></html>';
        exit;
    }
}

if (!function_exists('rdv_redirect_legacy_storefront')) {
    function rdv_redirect_legacy_storefront(array $store) {
        $mode = rdv_store_url_mode();
        if ($mode === 'query') {
            return;
        }
        $slug = (string) ($store['store_slug'] ?? '');
        if ($slug === '' || !rdv_is_valid_store_slug($slug)) {
            return;
        }

        // Already on clean path URL
        if ($mode === 'path' && rdv_is_clean_store_request($slug)) {
            return;
        }
        // Already on store subdomain
        if ($mode === 'subdomain') {
            $parsed = rdv_parse_store_host();
            if ($parsed['type'] === 'store' && $parsed['slug'] === $slug) {
                return;
            }
        }

        $target = rdv_store_url($store);
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
     * @return array{store:?array,via:string,on_subdomain:bool,on_path:bool}
     */
    function rdv_resolve_public_store(mysqli $conn, $requirePublic = true) {
        $parsed = rdv_parse_store_host();
        if ($parsed['type'] === 'reserved') {
            rdv_redirect_reserved_subdomain();
        }
        if ($parsed['type'] === 'store' && $parsed['slug']) {
            // Legacy subdomain hit → prefer redirect to path URL after lookup
            $store = rdv_fetch_store_by_slug($conn, $parsed['slug'], $requirePublic);
            if (!$store) {
                rdv_store_not_found_page();
            }
            if (rdv_store_path_urls_enabled() && !headers_sent()) {
                $target = rdv_store_url($store);
                $uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
                if (preg_match('#/product/([0-9]+)#', $uriPath, $m)) {
                    // product handled by product-details; keep subdomain store resolve
                } elseif ($uriPath === '/' || $uriPath === '') {
                    header('Location: ' . $target, true, 301);
                    exit;
                }
            }
            return ['store' => $store, 'via' => 'subdomain', 'on_subdomain' => true, 'on_path' => false];
        }

        $storeId = isset($_GET['store']) ? (int) $_GET['store'] : 0;
        $slug = isset($_GET['slug']) ? strtolower(trim((string) $_GET['slug'])) : '';
        $store = null;
        $via = 'none';
        $onPath = $slug !== '' && rdv_is_clean_store_request($slug);

        if ($slug !== '') {
            if (rdv_is_reserved_store_slug($slug)) {
                rdv_store_not_found_page('Sorry, we couldn\'t find a store with this address.');
            }
            $store = rdv_fetch_store_by_slug($conn, $slug, $requirePublic);
            $via = 'slug';
        } elseif ($storeId > 0) {
            $store = rdv_fetch_store_by_id($conn, $storeId, $requirePublic);
            $via = 'id';
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
                    $isOwner = ((int) ($store['user_id'] ?? 0) === $uid);
                    if (!$isOwner && ($status !== 'active' || $active !== 1)) {
                        $store = null;
                    }
                }
            }
        }

        return ['store' => $store, 'via' => $via, 'on_subdomain' => false, 'on_path' => $onPath];
    }
}
