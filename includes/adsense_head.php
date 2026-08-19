<?php
if (!function_exists('rdv_adsense_head_script')) {
    require_once dirname(__DIR__) . '/app/helpers/ads.php';
}
if (!function_exists('rdv_analytics_head_script')) {
    require_once dirname(__DIR__) . '/app/helpers/analytics.php';
}
echo rdv_adsense_head_script();
echo rdv_analytics_head_script();
