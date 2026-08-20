<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/marketplace_settings.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

if (!adminHasPermission('marketplace_design', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage marketplace design.</p><a href="admin">Go to Dashboard</a></div>');
}

rdv_ensure_marketplace_settings_table($conn);
$mpDefaults = rdv_marketplace_defaults();

function getSetting($key, $default = '') {
    global $conn;
    return rdv_marketplace_setting($conn, $key, $default);
}

function updateSetting($key, $value) {
    global $conn;
    return rdv_marketplace_setting_set($conn, $key, $value);
}

function mp_load_all_settings() {
    global $mpDefaults;
    $out = [];
    foreach ($mpDefaults as $key => $default) {
        $out[$key] = getSetting($key, $default);
    }
    // Existing keys not only in defaults map
    $extra = [
        'body_bg_color' => '#f5f5f5',
        'text_primary_color' => '#1f2937',
        'primary_btn_bg' => '#2563eb',
        'primary_btn_text' => '#ffffff',
        'card_bg_color' => '#ffffff',
        'sidebar_bg_color' => '#ffffff',
        'sidebar_text_color' => '#4a5568',
        'promo1_title' => 'Up to 50% Off Electronics',
        'promo1_subtitle' => 'Limited time offer on top brands',
        'promo1_link' => '#',
        'promo1_enabled' => '1',
        'promo2_title' => 'New Arrivals in Fashion',
        'promo2_subtitle' => 'Fresh styles every week',
        'promo2_link' => '#',
        'promo2_enabled' => '1',
        'tax_rate' => '0',
        'shipping_default' => '0',
        'shipping_states' => '',
    ];
    foreach ($extra as $key => $default) {
        $out[$key] = getSetting($key, $default);
    }
    return $out;
}

$S = mp_load_all_settings();
extract($S, EXTR_OVERWRITE);
$hero_image = $S['hero_image'];
$hero_title = $S['hero_title'];
$hero_subtitle = $S['hero_subtitle'];
$hero_btn_text = $S['hero_btn_text'];
$hero_btn_link = $S['hero_btn_link'];
$body_bg_color = $S['body_bg_color'];
$text_primary_color = $S['text_primary_color'];
$primary_btn_bg = $S['primary_btn_bg'];
$primary_btn_text = $S['primary_btn_text'];
$card_bg_color = $S['card_bg_color'];
$sidebar_bg_color = $S['sidebar_bg_color'];
$sidebar_text_color = $S['sidebar_text_color'];
$promo1_title = $S['promo1_title'];
$promo1_subtitle = $S['promo1_subtitle'];
$promo1_link = $S['promo1_link'];
$promo1_enabled = $S['promo1_enabled'];
$promo2_title = $S['promo2_title'];
$promo2_subtitle = $S['promo2_subtitle'];
$promo2_link = $S['promo2_link'];
$promo2_enabled = $S['promo2_enabled'];
$tax_rate = $S['tax_rate'];
$shipping_default = $S['shipping_default'];
$shipping_states_raw = $S['shipping_states'];
$shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];
if (!is_array($shipping_states)) $shipping_states = [];

// List of Nigerian states for the admin UI
$nigeria_states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
    'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
    'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
    'Yobe', 'Zamfara'
];

$stores = [];
$storeQuery = $conn->query("SELECT id, store_name, user_id FROM stores WHERE status = 'active' ORDER BY store_name ASC");
while ($row = $storeQuery->fetch_assoc()) {
    $row['visible'] = getSetting("store_visible_{$row['id']}", '1');
    $stores[] = $row;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_hero'])) {
        updateSetting('hero_enabled', isset($_POST['hero_enabled']) ? '1' : '0');
        updateSetting('hero_tag', $_POST['hero_tag'] ?? '');
        updateSetting('hero_title', $_POST['hero_title'] ?? '');
        updateSetting('hero_subtitle', $_POST['hero_subtitle'] ?? '');
        updateSetting('hero_btn_text', $_POST['hero_btn_text'] ?? '');
        updateSetting('hero_btn_link', $_POST['hero_btn_link'] ?? '');

        updateSetting('hero2_enabled', isset($_POST['hero2_enabled']) ? '1' : '0');
        updateSetting('hero2_tag', $_POST['hero2_tag'] ?? '');
        updateSetting('hero2_title', $_POST['hero2_title'] ?? '');
        updateSetting('hero2_subtitle', $_POST['hero2_subtitle'] ?? '');
        updateSetting('hero2_btn_text', $_POST['hero2_btn_text'] ?? '');
        updateSetting('hero2_btn_link', $_POST['hero2_btn_link'] ?? '');

        updateSetting('hero3_enabled', isset($_POST['hero3_enabled']) ? '1' : '0');
        updateSetting('hero3_tag', $_POST['hero3_tag'] ?? '');
        updateSetting('hero3_title', $_POST['hero3_title'] ?? '');
        updateSetting('hero3_subtitle', $_POST['hero3_subtitle'] ?? '');
        updateSetting('hero3_btn_text', $_POST['hero3_btn_text'] ?? '');
        updateSetting('hero3_btn_link', $_POST['hero3_btn_link'] ?? '');

        $uploadDir = dirname(__DIR__) . '/uploads/hero/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $allowed = ['jpg','jpeg','png','gif','webp'];
        foreach (['hero_image' => 'hero_banner_', 'hero2_image' => 'hero2_banner_', 'hero3_image' => 'hero3_banner_'] as $fileKey => $prefix) {
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true) || $_FILES[$fileKey]['size'] >= 2 * 1024 * 1024) {
                continue;
            }
            $filename = $prefix . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destination)) {
                updateSetting($fileKey, 'uploads/hero/' . $filename);
            }
        }
        $message = "Hero carousel updated.";
        $messageType = "success";
    }

    if (isset($_POST['save_top_strip'])) {
        updateSetting('top_strip_enabled', isset($_POST['top_strip_enabled']) ? '1' : '0');
        updateSetting('top_strip_text', $_POST['top_strip_text'] ?? '');
        $message = "Top strip updated.";
        $messageType = "success";
    }

    if (isset($_POST['save_sections'])) {
        $keys = [
            'categories_nav_enabled', 'categories_section_enabled', 'stores_section_enabled',
            'flash_banner_enabled', 'products_section_enabled', 'footer_enabled',
        ];
        foreach ($keys as $k) {
            updateSetting($k, isset($_POST[$k]) ? '1' : '0');
        }
        updateSetting('categories_section_title', $_POST['categories_section_title'] ?? '');
        updateSetting('stores_section_title', $_POST['stores_section_title'] ?? '');
        updateSetting('flash_banner_title', $_POST['flash_banner_title'] ?? '');
        updateSetting('flash_banner_hours', preg_replace('/\D/', '', $_POST['flash_banner_hours'] ?? '4') ?: '0');
        updateSetting('flash_banner_minutes', preg_replace('/\D/', '', $_POST['flash_banner_minutes'] ?? '37') ?: '0');
        $pps = max(1, min(48, (int) ($_POST['products_per_store'] ?? 10)));
        updateSetting('products_per_store', (string) $pps);
        $message = "Marketplace sections updated.";
        $messageType = "success";
    }

    if (isset($_POST['save_footer'])) {
        updateSetting('footer_enabled', isset($_POST['footer_enabled']) ? '1' : '0');
        foreach (['footer_col1_title','footer_col1_links','footer_col2_title','footer_col2_links','footer_col3_title','footer_col3_links','footer_col4_title','footer_col4_links','footer_copyright','footer_facebook','footer_twitter','footer_instagram','footer_whatsapp','footer_youtube'] as $k) {
            updateSetting($k, $_POST[$k] ?? '');
        }
        $message = "Footer updated.";
        $messageType = "success";
    }
    
    if (isset($_POST['save_colors'])) {
        updateSetting('body_bg_color', $_POST['body_bg_color']);
        updateSetting('text_primary_color', $_POST['text_primary_color']);
        updateSetting('primary_btn_bg', $_POST['primary_btn_bg']);
        updateSetting('primary_btn_text', $_POST['primary_btn_text']);
        updateSetting('card_bg_color', $_POST['card_bg_color']);
        updateSetting('sidebar_bg_color', $_POST['sidebar_bg_color']);
        updateSetting('sidebar_text_color', $_POST['sidebar_text_color']);
        $message = "Color settings saved.";
        $messageType = "success";
    }
    
    if (isset($_POST['save_store_visibility'])) {
        foreach ($stores as $store) {
            $visible = isset($_POST["store_{$store['id']}"]) ? '1' : '0';
            updateSetting("store_visible_{$store['id']}", $visible);
        }
        $message = "Store visibility updated.";
        $messageType = "success";
    }
    
    if (isset($_POST['save_promos'])) {
        updateSetting('promo1_title', $_POST['promo1_title']);
        updateSetting('promo1_subtitle', $_POST['promo1_subtitle']);
        updateSetting('promo1_link', $_POST['promo1_link']);
        updateSetting('promo1_btn_text', $_POST['promo1_btn_text'] ?? 'Shop Now');
        updateSetting('promo1_enabled', isset($_POST['promo1_enabled']) ? '1' : '0');
        updateSetting('promo2_title', $_POST['promo2_title']);
        updateSetting('promo2_subtitle', $_POST['promo2_subtitle']);
        updateSetting('promo2_link', $_POST['promo2_link']);
        updateSetting('promo2_btn_text', $_POST['promo2_btn_text'] ?? 'Explore');
        updateSetting('promo2_enabled', isset($_POST['promo2_enabled']) ? '1' : '0');
        $message = "Promotional banners updated.";
        $messageType = "success";
    }
    
    if (isset($_POST['save_shipping'])) {
        updateSetting('tax_rate', $_POST['tax_rate']);
        updateSetting('shipping_default', $_POST['shipping_default']);
        $states_data = [];
        foreach ($nigeria_states as $state) {
            $key = 'shipping_' . str_replace(' ', '_', $state);
            if (isset($_POST[$key]) && is_numeric($_POST[$key])) {
                $states_data[$state] = floatval($_POST[$key]);
            }
        }
        updateSetting('shipping_states', json_encode($states_data));
        $message = "Shipping & Tax settings updated.";
        $messageType = "success";
    }
    
    // Refresh all settings after update
    $S = mp_load_all_settings();
    extract($S, EXTR_OVERWRITE);
    $hero_image = $S['hero_image'];
    $hero_title = $S['hero_title'];
    $hero_subtitle = $S['hero_subtitle'];
    $hero_btn_text = $S['hero_btn_text'];
    $hero_btn_link = $S['hero_btn_link'];
    $body_bg_color = $S['body_bg_color'];
    $text_primary_color = $S['text_primary_color'];
    $primary_btn_bg = $S['primary_btn_bg'];
    $primary_btn_text = $S['primary_btn_text'];
    $card_bg_color = $S['card_bg_color'];
    $sidebar_bg_color = $S['sidebar_bg_color'];
    $sidebar_text_color = $S['sidebar_text_color'];
    $promo1_title = $S['promo1_title'];
    $promo1_subtitle = $S['promo1_subtitle'];
    $promo1_link = $S['promo1_link'];
    $promo1_enabled = $S['promo1_enabled'];
    $promo2_title = $S['promo2_title'];
    $promo2_subtitle = $S['promo2_subtitle'];
    $promo2_link = $S['promo2_link'];
    $promo2_enabled = $S['promo2_enabled'];
    $tax_rate = $S['tax_rate'];
    $shipping_default = $S['shipping_default'];
    $shipping_states_raw = $S['shipping_states'];
    $shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];
    if (!is_array($shipping_states)) $shipping_states = [];
    $stores = [];
    $storeQuery = $conn->query("SELECT id, store_name, user_id FROM stores WHERE status = 'active' ORDER BY store_name ASC");
    while ($row = $storeQuery->fetch_assoc()) {
        $row['visible'] = getSetting("store_visible_{$row['id']}", '1');
        $stores[] = $row;
    }
}

$adminPageTitle = 'Marketplace Design - Admin';
$adminPageHeading = 'Marketplace Design';
$adminPageSubtitle = 'Control every marketplace section: strip, hero, categories, stores, flash deals, products, promos, colors, shipping, and footer';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
$adminPageStyles = <<<'CSS'
.design-section { padding: 1rem 2rem 2rem; }
.admin-app .design-section h4 label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin: 0;
    font-weight: 400;
    font-size: 0.9rem;
    color: var(--text-primary);
}
.settings-group {
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    overflow: hidden;
}
.settings-group-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-primary);
    background: var(--bg-tertiary);
}
.settings-group-title {
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}
.settings-group-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
}
.settings-group-body { padding: 1.5rem; }
.design-section .form-group { margin-bottom: 1.25rem; }
.design-section .form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-primary);
}
.design-section .form-input,
.design-section .form-select {
    width: 100%;
    padding: 0.6rem 1rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius);
    font-size: 0.875rem;
    color: var(--text-primary);
}
.design-section input[type="checkbox"],
.design-section input[type="radio"],
.design-section input[type="file"] {
    width: auto;
    padding: 0;
    border: none;
    background: none;
}
.design-section .color-input,
.design-section input[type="color"] {
    width: 60px;
    height: 40px;
    padding: 2px;
    border: 1px solid var(--border-primary);
    border-radius: var(--radius);
    cursor: pointer;
    background: var(--bg-secondary);
}
.store-checkbox-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    max-height: 300px;
    overflow-y: auto;
    padding: 0.5rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius);
}
.store-checkbox-list label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0;
    color: var(--text-primary);
    cursor: pointer;
}
.admin-app .store-checkbox-list input[type="checkbox"] {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}
.shipping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
    max-height: 400px;
    overflow-y: auto;
    padding: 0.5rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius);
}
.shipping-grid .form-group { margin-bottom: 0; }
.shipping-grid .form-group label {
    font-size: 0.75rem;
    font-weight: 500;
}
.design-section .btn-primary,
.design-section button[type="submit"].btn-primary {
    background: var(--gradient-primary);
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: var(--radius);
    font-weight: 600;
    border: none;
    cursor: pointer;
    width: auto;
    display: inline-flex;
}
.design-section .alert {
    padding: 1rem;
    border-radius: var(--radius);
    margin: 0 0 1rem;
}
.design-section .alert-success {
    background: var(--success-light);
    color: #047857;
    border: 1px solid #a7f3d0;
}
.design-section .alert-error {
    background: var(--error-light);
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.color-preview-grid {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 0.5rem;
}
.preview-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem;
    width: 200px;
    text-align: center;
}
.preview-btn {
    padding: 0.5rem 1rem;
    border-radius: 30px;
    display: inline-block;
    font-size: 0.8rem;
}
.hero-preview {
    background: linear-gradient(135deg, #1e2a3e, #0f172a);
    border-radius: 20px;
    padding: 1.5rem;
    margin-top: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    color: white;
    gap: 1rem;
}
.hero-preview-text h2 {
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #fff;
}
.hero-preview-text p { color: #cbd5e1; }
.hero-preview img {
    max-width: 150px;
    border-radius: 12px;
}
@media (max-width: 768px) {
    .design-section { padding: 1rem; }
}
CSS;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <div class="design-section">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Top trust strip -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">Top announcement strip</div>
                <div class="settings-group-desc">The thin bar above the marketplace header (delivery / trust message).</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                        <input type="checkbox" name="top_strip_enabled" value="1" <?= ($top_strip_enabled ?? '1') == '1' ? 'checked' : '' ?>> Show top strip
                    </label>
                    <div class="form-group">
                        <label class="form-label">Strip text (use | to separate phrases)</label>
                        <input type="text" name="top_strip_text" class="form-input" value="<?= htmlspecialchars($top_strip_text ?? '') ?>">
                    </div>
                    <button type="submit" name="save_top_strip" class="btn-primary">Save Top Strip</button>
                </form>
            </div>
        </div>

        <!-- Hero Banner Settings -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">Hero carousel (3 slides)</div>
                <div class="settings-group-desc">All marketplace hero slides are controlled here. Empire store promo banners (if any) still append after these slides.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST" enctype="multipart/form-data">
                    <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;">
                        <input type="checkbox" name="hero_enabled" value="1" <?= ($hero_enabled ?? '1') == '1' ? 'checked' : '' ?>> Show hero carousel
                    </label>

                    <h4 style="margin:0 0 0.75rem;font-weight:600;">Slide 1 — Main</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Image (optional)</label>
                            <input type="file" name="hero_image" accept="image/*" class="form-input">
                            <?php if (!empty($hero_image)): ?>
                                <div style="margin-top:10px;"><img src="<?= htmlspecialchars(rdv_admin_src($hero_image)) ?>" style="max-width:150px;border-radius:8px;"></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tag / kicker</label>
                            <input type="text" name="hero_tag" class="form-input" value="<?= htmlspecialchars($hero_tag ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Button text</label>
                            <input type="text" name="hero_btn_text" class="form-input" value="<?= htmlspecialchars($hero_btn_text) ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Title</label>
                            <input type="text" name="hero_title" class="form-input" value="<?= htmlspecialchars($hero_title) ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="hero_subtitle" class="form-input" value="<?= htmlspecialchars($hero_subtitle) ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Button link</label>
                            <input type="text" name="hero_btn_link" class="form-input" value="<?= htmlspecialchars($hero_btn_link) ?>">
                        </div>
                    </div>

                    <hr style="margin:0 0 1.25rem;border-color:var(--border-primary);">
                    <h4 style="margin:0 0 0.75rem;font-weight:600;display:flex;align-items:center;gap:0.75rem;">
                        <span>Slide 2</span>
                        <label style="font-weight:400;font-size:0.9rem;"><input type="checkbox" name="hero2_enabled" value="1" <?= ($hero2_enabled ?? '1') == '1' ? 'checked' : '' ?>> Show</label>
                    </h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Image (optional)</label>
                            <input type="file" name="hero2_image" accept="image/*" class="form-input">
                            <?php if (!empty($hero2_image)): ?>
                                <div style="margin-top:10px;"><img src="<?= htmlspecialchars(rdv_admin_src($hero2_image)) ?>" style="max-width:150px;border-radius:8px;"></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tag / kicker</label>
                            <input type="text" name="hero2_tag" class="form-input" value="<?= htmlspecialchars($hero2_tag ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Button text</label>
                            <input type="text" name="hero2_btn_text" class="form-input" value="<?= htmlspecialchars($hero2_btn_text ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Title</label>
                            <input type="text" name="hero2_title" class="form-input" value="<?= htmlspecialchars($hero2_title ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="hero2_subtitle" class="form-input" value="<?= htmlspecialchars($hero2_subtitle ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Button link</label>
                            <input type="text" name="hero2_btn_link" class="form-input" value="<?= htmlspecialchars($hero2_btn_link ?? '') ?>">
                        </div>
                    </div>

                    <hr style="margin:0 0 1.25rem;border-color:var(--border-primary);">
                    <h4 style="margin:0 0 0.75rem;font-weight:600;display:flex;align-items:center;gap:0.75rem;">
                        <span>Slide 3</span>
                        <label style="font-weight:400;font-size:0.9rem;"><input type="checkbox" name="hero3_enabled" value="1" <?= ($hero3_enabled ?? '1') == '1' ? 'checked' : '' ?>> Show</label>
                    </h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Image (optional)</label>
                            <input type="file" name="hero3_image" accept="image/*" class="form-input">
                            <?php if (!empty($hero3_image)): ?>
                                <div style="margin-top:10px;"><img src="<?= htmlspecialchars(rdv_admin_src($hero3_image)) ?>" style="max-width:150px;border-radius:8px;"></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tag / kicker</label>
                            <input type="text" name="hero3_tag" class="form-input" value="<?= htmlspecialchars($hero3_tag ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Button text</label>
                            <input type="text" name="hero3_btn_text" class="form-input" value="<?= htmlspecialchars($hero3_btn_text ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Title</label>
                            <input type="text" name="hero3_title" class="form-input" value="<?= htmlspecialchars($hero3_title ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="hero3_subtitle" class="form-input" value="<?= htmlspecialchars($hero3_subtitle ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:span 2;">
                            <label class="form-label">Button link</label>
                            <input type="text" name="hero3_btn_link" class="form-input" value="<?= htmlspecialchars($hero3_btn_link ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" name="save_hero" class="btn-primary">Save Hero Carousel</button>
                </form>
            </div>
        </div>

        <!-- Sections layout -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">Marketplace sections</div>
                <div class="settings-group-desc">Show/hide major marketplace areas and edit their titles.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;margin-bottom:1.25rem;">
                        <?php
                        $toggles = [
                            'categories_nav_enabled' => 'Category navigation bar',
                            'categories_section_enabled' => 'Shop by Category grid',
                            'stores_section_enabled' => 'All Stores row',
                            'flash_banner_enabled' => 'Flash deals banner',
                            'products_section_enabled' => 'Product listings',
                            'footer_enabled' => 'Footer',
                        ];
                        foreach ($toggles as $key => $label):
                            $val = $$key ?? '1';
                        ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;background:var(--bg-primary);padding:0.75rem;border-radius:8px;border:1px solid var(--border-primary);">
                            <input type="checkbox" name="<?= $key ?>" value="1" <?= $val == '1' ? 'checked' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Categories section title</label>
                            <input type="text" name="categories_section_title" class="form-input" value="<?= htmlspecialchars($categories_section_title ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stores section title</label>
                            <input type="text" name="stores_section_title" class="form-input" value="<?= htmlspecialchars($stores_section_title ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Flash banner title</label>
                            <input type="text" name="flash_banner_title" class="form-input" value="<?= htmlspecialchars($flash_banner_title ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Products per store</label>
                            <input type="number" name="products_per_store" class="form-input" min="1" max="48" value="<?= htmlspecialchars($products_per_store ?? '10') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Flash countdown hours</label>
                            <input type="number" name="flash_banner_hours" class="form-input" min="0" max="72" value="<?= htmlspecialchars($flash_banner_hours ?? '4') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Flash countdown minutes</label>
                            <input type="number" name="flash_banner_minutes" class="form-input" min="0" max="59" value="<?= htmlspecialchars($flash_banner_minutes ?? '37') ?>">
                        </div>
                    </div>
                    <button type="submit" name="save_sections" class="btn-primary" style="margin-top:1rem;">Save Sections</button>
                </form>
            </div>
        </div>

        <!-- Color & Typography Settings -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">🎨 Marketplace Colors</div>
                <div class="settings-group-desc">Global colors for the marketplace front-end.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Body Background</label>
                            <input type="color" name="body_bg_color" class="color-input" value="<?= htmlspecialchars($body_bg_color) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Primary Text Color</label>
                            <input type="color" name="text_primary_color" class="color-input" value="<?= htmlspecialchars($text_primary_color) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Primary Button Background</label>
                            <input type="color" name="primary_btn_bg" class="color-input" value="<?= htmlspecialchars($primary_btn_bg) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Primary Button Text</label>
                            <input type="color" name="primary_btn_text" class="color-input" value="<?= htmlspecialchars($primary_btn_text) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Card Background</label>
                            <input type="color" name="card_bg_color" class="color-input" value="<?= htmlspecialchars($card_bg_color) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sidebar Background</label>
                            <input type="color" name="sidebar_bg_color" class="color-input" value="<?= htmlspecialchars($sidebar_bg_color) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sidebar Text Color</label>
                            <input type="color" name="sidebar_text_color" class="color-input" value="<?= htmlspecialchars($sidebar_text_color) ?>">
                        </div>
                    </div>
                    <div class="color-preview-grid">
                        <div class="preview-card" style="background: <?= $card_bg_color ?>;">
                            <p style="color: <?= $text_primary_color ?>;">Product Card Preview</p>
                            <div class="preview-btn" style="background: <?= $primary_btn_bg ?>; color: <?= $primary_btn_text ?>;">Button</div>
                        </div>
                        <div class="preview-card" style="background: <?= $sidebar_bg_color ?>; color: <?= $sidebar_text_color ?>;">
                            Sidebar Preview
                        </div>
                    </div>
                    <button type="submit" name="save_colors" class="btn-primary" style="margin-top: 1rem;">Save Colors</button>
                </form>
            </div>
        </div>

        <!-- Store Visibility -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">🏪 Store Visibility</div>
                <div class="settings-group-desc">Select which stores appear in the marketplace sidebar and product listings.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <div class="store-checkbox-list">
                        <?php foreach ($stores as $store): ?>
                            <label>
                                <input type="checkbox" name="store_<?= $store['id'] ?>" value="1" <?= $store['visible'] == '1' ? 'checked' : '' ?>>
                                <?= htmlspecialchars($store['store_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="save_store_visibility" class="btn-primary" style="margin-top: 1rem;">Update Store Visibility</button>
                </form>
            </div>
        </div>

        <!-- Promotional Banners -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">📢 Promotional Banners</div>
                <div class="settings-group-desc">Edit the two static promo cards that appear on the marketplace.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <h4 style="margin-bottom:0.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                        <span>Banner 1</span>
                        <label style="font-weight:400; font-size:0.9rem;">
                            <input type="checkbox" name="promo1_enabled" value="1" <?= $promo1_enabled == '1' ? 'checked' : '' ?>> Show
                        </label>
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="promo1_title" class="form-input" value="<?= htmlspecialchars($promo1_title) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="promo1_subtitle" class="form-input" value="<?= htmlspecialchars($promo1_subtitle) ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Link (URL)</label>
                            <input type="text" name="promo1_link" class="form-input" value="<?= htmlspecialchars($promo1_link) ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Button text</label>
                            <input type="text" name="promo1_btn_text" class="form-input" value="<?= htmlspecialchars($promo1_btn_text ?? 'Shop Now') ?>">
                        </div>
                    </div>
                    <hr style="margin: 1.5rem 0; border-color: var(--border-primary);">
                    <h4 style="margin-bottom:0.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                        <span>Banner 2</span>
                        <label style="font-weight:400; font-size:0.9rem;">
                            <input type="checkbox" name="promo2_enabled" value="1" <?= $promo2_enabled == '1' ? 'checked' : '' ?>> Show
                        </label>
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="promo2_title" class="form-input" value="<?= htmlspecialchars($promo2_title) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="promo2_subtitle" class="form-input" value="<?= htmlspecialchars($promo2_subtitle) ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Link (URL)</label>
                            <input type="text" name="promo2_link" class="form-input" value="<?= htmlspecialchars($promo2_link) ?>">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Button text</label>
                            <input type="text" name="promo2_btn_text" class="form-input" value="<?= htmlspecialchars($promo2_btn_text ?? 'Explore') ?>">
                        </div>
                    </div>
                    <button type="submit" name="save_promos" class="btn-primary" style="margin-top: 1rem;">Save Promotional Banners</button>
                </form>
            </div>
        </div>

        <!-- NEW: Shipping & Tax Settings -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">🚚 Shipping & Tax</div>
                <div class="settings-group-desc">Set tax rate, default shipping fee, and per‑state shipping fees for Nigeria.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" name="tax_rate" class="form-input" step="0.01" min="0" value="<?= htmlspecialchars($tax_rate) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Default Shipping Fee (₦)</label>
                            <input type="number" name="shipping_default" class="form-input" step="0.01" min="0" value="<?= htmlspecialchars($shipping_default) ?>">
                        </div>
                    </div>

                    <h4 style="margin: 1.5rem 0 0.5rem; font-weight:600;">Per‑State Shipping Fees (₦)</h4>
                    <div class="shipping-grid">
                        <?php foreach ($nigeria_states as $state): 
                            $val = isset($shipping_states[$state]) ? $shipping_states[$state] : '';
                            $key = 'shipping_' . str_replace(' ', '_', $state);
                        ?>
                            <div class="form-group">
                                <label for="<?= $key ?>"><?= htmlspecialchars($state) ?></label>
                                <input type="number" name="<?= $key ?>" id="<?= $key ?>" step="0.01" min="0" value="<?= htmlspecialchars($val) ?>" placeholder="0">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="save_shipping" class="btn-primary" style="margin-top: 1.5rem;">Save Shipping & Tax Settings</button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">Footer</div>
                <div class="settings-group-desc">Edit marketplace footer columns. Enter one link per line as Label|url (example: About Us|about).</div>
            </div>
            <div class="settings-group-body">
                <form method="POST">
                    <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
                        <input type="checkbox" name="footer_enabled" value="1" <?= ($footer_enabled ?? '1') == '1' ? 'checked' : '' ?>> Show footer
                    </label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <?php for ($i = 1; $i <= 4; $i++):
                            $tKey = "footer_col{$i}_title";
                            $lKey = "footer_col{$i}_links";
                        ?>
                        <div class="form-group">
                            <label class="form-label">Column <?= $i ?> title</label>
                            <input type="text" name="<?= $tKey ?>" class="form-input" value="<?= htmlspecialchars($$tKey ?? '') ?>">
                            <label class="form-label" style="margin-top:0.75rem;">Column <?= $i ?> links</label>
                            <textarea name="<?= $lKey ?>" class="form-input" rows="5"><?= htmlspecialchars($$lKey ?? '') ?></textarea>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="form-group" style="margin-top:1rem;">
                        <label class="form-label">Copyright line (use {year} for current year)</label>
                        <input type="text" name="footer_copyright" class="form-input" value="<?= htmlspecialchars($footer_copyright ?? '') ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                        <?php foreach (['footer_facebook'=>'Facebook','footer_twitter'=>'Twitter / X','footer_instagram'=>'Instagram','footer_whatsapp'=>'WhatsApp','footer_youtube'=>'YouTube'] as $k=>$lab): ?>
                        <div class="form-group">
                            <label class="form-label"><?= $lab ?> URL</label>
                            <input type="text" name="<?= $k ?>" class="form-input" value="<?= htmlspecialchars($$k ?? '#') ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="save_footer" class="btn-primary" style="margin-top:1rem;">Save Footer</button>
                </form>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
