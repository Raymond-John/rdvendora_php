<?php
/**
 * Shared marketplace_settings helpers.
 */
if (!function_exists('rdv_ensure_marketplace_settings_table')) {
    function rdv_ensure_marketplace_settings_table($conn) {
        if (!$conn) {
            return;
        }
        $conn->query("CREATE TABLE IF NOT EXISTS `marketplace_settings` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `setting_key` VARCHAR(100) NOT NULL,
            `setting_value` TEXT,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('rdv_marketplace_setting')) {
    function rdv_marketplace_setting($conn, $key, $default = '', $forceSet = false) {
        if (!$conn) {
            return $default;
        }
        static $cache = [];
        $ck = spl_object_hash($conn) . ':' . $key;
        if ($forceSet) {
            $cache[$ck] = $default;
            return $cache[$ck];
        }
        if (array_key_exists($ck, $cache)) {
            return $cache[$ck];
        }
        $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
        if (!$stmt) {
            return $default;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        $cache[$ck] = ($row && array_key_exists('setting_value', $row) && $row['setting_value'] !== null)
            ? $row['setting_value']
            : $default;
        return $cache[$ck];
    }
}

if (!function_exists('rdv_marketplace_setting_set')) {
    function rdv_marketplace_setting_set($conn, $key, $value) {
        if (!$conn) {
            return false;
        }
        $stmt = $conn->prepare(
            "INSERT INTO marketplace_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        if (!$stmt) {
            return false;
        }
        $val = (string) $value;
        $stmt->bind_param('ss', $key, $val);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            // Keep request-local cache in sync after writes (admin save + refresh).
            rdv_marketplace_setting_cache_put($conn, $key, $val);
        }
        return $ok;
    }
}

if (!function_exists('rdv_marketplace_setting_cache_put')) {
    function rdv_marketplace_setting_cache_put($conn, $key, $value) {
        // Shared static with rdv_marketplace_setting via a dedicated cache helper.
        rdv_marketplace_setting($conn, $key, $value, true);
    }
}

if (!function_exists('rdv_marketplace_setting_bool')) {
    function rdv_marketplace_setting_bool($conn, $key, $default = true) {
        $v = rdv_marketplace_setting($conn, $key, $default ? '1' : '0');
        return $v === '1' || $v === 1 || $v === true || $v === 'true';
    }
}

if (!function_exists('rdv_marketplace_parse_footer_links')) {
    /**
     * Parse lines of "Label|url" into [['label'=>,'url'=>], ...]
     */
    function rdv_marketplace_parse_footer_links($raw) {
        $out = [];
        $raw = (string) $raw;
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, '|')) {
                [$label, $url] = array_map('trim', explode('|', $line, 2));
            } else {
                $label = $line;
                $url = '#';
            }
            if ($label !== '') {
                $out[] = ['label' => $label, 'url' => $url !== '' ? $url : '#'];
            }
        }
        return $out;
    }
}

if (!function_exists('rdv_marketplace_defaults')) {
    function rdv_marketplace_defaults() {
        return [
            'top_strip_enabled' => '1',
            'top_strip_text' => 'Free delivery on orders above ₦10,000 | 100% Genuine Products | Easy Returns',
            'hero_enabled' => '1',
            'hero_image' => '',
            'hero_title' => 'Up to 50% OFF on everything',
            'hero_subtitle' => 'Shop the biggest sale of the year. Limited time offer.',
            'hero_btn_text' => 'Shop Now',
            'hero_btn_link' => '#',
            'hero_tag' => "Nigeria's Marketplace",
            'hero2_enabled' => '1',
            'hero2_image' => '',
            'hero2_tag' => 'Limited Time Only',
            'hero2_title' => 'Flash Deals — Up to 60% Off',
            'hero2_subtitle' => 'Grab the best discounts before they\'re gone. New deals every 4 hours!',
            'hero2_btn_text' => 'See Deals',
            'hero2_btn_link' => '#',
            'hero3_enabled' => '1',
            'hero3_image' => '',
            'hero3_tag' => 'Nationwide Delivery',
            'hero3_title' => 'Free Delivery on Orders Above ₦10k',
            'hero3_subtitle' => 'We deliver to every state in Nigeria. Same-day delivery in Lagos available.',
            'hero3_btn_text' => 'Learn More',
            'hero3_btn_link' => '#',
            'categories_nav_enabled' => '1',
            'categories_section_enabled' => '1',
            'categories_section_title' => 'Shop by Category',
            'stores_section_enabled' => '1',
            'stores_section_title' => 'All Stores',
            'flash_banner_enabled' => '1',
            'flash_banner_title' => 'Flash Deals',
            'flash_banner_hours' => '4',
            'flash_banner_minutes' => '37',
            'products_per_store' => '10',
            'products_section_enabled' => '1',
            'promo1_btn_text' => 'Shop Now',
            'promo2_btn_text' => 'Explore',
            'footer_enabled' => '1',
            'footer_col1_title' => 'RD Vendora',
            'footer_col1_links' => "About Us|about\nCareers|#\nPress|#\nContact Us|contact\nAffiliates|#",
            'footer_col2_title' => 'Help',
            'footer_col2_links' => "FAQ|faq\nTrack Order|#\nReturns|#\nReport a Product|#",
            'footer_col3_title' => 'Sell on RD Vendora',
            'footer_col3_links' => "Become a Seller|register\nSeller Center|login\nFlash Sales|#\nAdvertise|#",
            'footer_col4_title' => 'Payment',
            'footer_col4_links' => "RD Pay|#\nCards Accepted|#\nBank Transfer|#\nPay on Delivery|#",
            'footer_copyright' => '© {year} RD Vendora. All rights reserved.',
            'footer_facebook' => '#',
            'footer_twitter' => '#',
            'footer_instagram' => '#',
            'footer_whatsapp' => '#',
            'footer_youtube' => '#',
        ];
    }
}
