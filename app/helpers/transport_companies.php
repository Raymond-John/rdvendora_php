<?php

if (!function_exists('rdv_transport_companies_defaults')) {
    function rdv_transport_companies_defaults() {
        return [
            'Fast Delivery Express',
            'Logistics Plus',
            'Speed Cargo',
            'Local Courier Service',
        ];
    }
}

if (!function_exists('rdv_transport_companies')) {
    function rdv_transport_companies($conn = null) {
        if (!$conn instanceof mysqli) {
            $conn = $GLOBALS['conn'] ?? ($GLOBALS['connect'] ?? null);
        }
        if (!$conn instanceof mysqli) {
            return rdv_transport_companies_defaults();
        }
        if (!function_exists('rdv_site_setting')) {
            require_once APP_PATH . '/helpers/public_site.php';
        }
        $raw = rdv_site_setting($conn, 'transport_companies');
        if ($raw === '') {
            return rdv_transport_companies_defaults();
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $list = array_values(array_filter(array_map('trim', $decoded), static function ($name) {
                return $name !== '';
            }));
            return $list ?: rdv_transport_companies_defaults();
        }
        $list = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)), static function ($name) {
            return $name !== '';
        }));
        return $list ?: rdv_transport_companies_defaults();
    }
}

if (!function_exists('rdv_transport_companies_text')) {
    function rdv_transport_companies_text($conn = null) {
        return implode("\n", rdv_transport_companies($conn));
    }
}

if (!function_exists('rdv_save_transport_companies')) {
    function rdv_save_transport_companies(mysqli $conn, $textOrArray) {
        if (is_string($textOrArray)) {
            $companies = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $textOrArray)), static function ($name) {
                return $name !== '';
            }));
        } else {
            $companies = array_values(array_filter(array_map('trim', (array) $textOrArray), static function ($name) {
                return $name !== '';
            }));
        }
        $json = json_encode($companies, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES ('transport_companies', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $json);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $companies : false;
    }
}

if (!function_exists('rdv_transport_company_is_valid')) {
    function rdv_transport_company_is_valid($conn, $company) {
        $company = trim((string) $company);
        if ($company === '') {
            return false;
        }
        return in_array($company, rdv_transport_companies($conn), true);
    }
}
