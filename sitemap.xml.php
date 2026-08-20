<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

header('Content-Type: application/xml; charset=UTF-8');

$pages = [
    ['index', '1.0', 'weekly', date('Y-m-d')],
    ['about', '0.8', 'monthly', date('Y-m-d')],
    ['features', '0.8', 'monthly', date('Y-m-d')],
    ['pricing', '0.8', 'monthly', date('Y-m-d')],
    ['contact', '0.7', 'monthly', date('Y-m-d')],
    ['faq', '0.7', 'monthly', date('Y-m-d')],
    ['blog', '0.8', 'daily', date('Y-m-d')],
    ['privacy', '0.4', 'yearly', date('Y-m-d')],
    ['terms', '0.4', 'yearly', date('Y-m-d')],
    ['cookies', '0.4', 'yearly', date('Y-m-d')],
    ['disclaimer', '0.3', 'yearly', date('Y-m-d')],
    ['community-guidelines', '0.5', 'yearly', date('Y-m-d')],
    ['sitemap', '0.3', 'monthly', date('Y-m-d')],
    ['marketplace', '0.9', 'daily', date('Y-m-d')],
];

$conn = $conn ?? $connect ?? null;
if ($conn instanceof mysqli) {
    foreach (rdv_blog_list($conn, ['limit' => 50]) as $post) {
        $mod = date('Y-m-d', strtotime($post['updated_at'] ?: $post['published_at']));
        $pages[] = [rdv_blog_url($post['slug']), '0.6', 'monthly', $mod];
    }
    $storeStmt = $conn->query("SELECT id, store_slug, updated_at, created_at FROM stores WHERE status = 'active' AND active = 1 AND store_slug <> '' ORDER BY id ASC LIMIT 500");
    if ($storeStmt) {
        while ($s = $storeStmt->fetch_assoc()) {
            if (!function_exists('rdv_is_valid_store_slug') || !rdv_is_valid_store_slug($s['store_slug'])) {
                continue;
            }
            $mod = date('Y-m-d', strtotime($s['updated_at'] ?: $s['created_at'] ?: 'now'));
            $pages[] = [rdv_store_url($s), '0.7', 'weekly', $mod];
        }
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) {
    $locRaw = $page[0];
    if (is_string($locRaw) && (str_starts_with($locRaw, 'http://') || str_starts_with($locRaw, 'https://'))) {
        $loc = htmlspecialchars($locRaw, ENT_QUOTES, 'UTF-8');
    } else {
        $loc = htmlspecialchars(rdv_canonical_url($locRaw), ENT_QUOTES, 'UTF-8');
    }
    $lastmod = htmlspecialchars($page[3], ENT_QUOTES, 'UTF-8');
    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>{$page[2]}</changefreq>\n";
    echo "    <priority>{$page[1]}</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
