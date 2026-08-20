<?php
/**
 * Marketplace URL helpers (clean paths).
 */
if (!function_exists('rdv_marketplace_url')) {
    function rdv_marketplace_url($path = '', $query = []) {
        $path = trim((string) $path, '/');
        $base = 'marketplace';
        if ($path === '' || $path === 'home') {
            $route = $base;
        } elseif ($path === 'cart') {
            $route = $base . '/cart';
        } elseif ($path === 'checkout') {
            $route = $base . '/checkout';
        } elseif (preg_match('#^product/(\d+)#', $path, $m)) {
            $route = $base . '/product/' . $m[1];
        } else {
            $route = $base . '/' . $path;
        }
        if (function_exists('rdv_url')) {
            return rdv_url($route, is_array($query) ? $query : []);
        }
        $url = '/' . $route;
        if (is_array($query) && $query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }
}
