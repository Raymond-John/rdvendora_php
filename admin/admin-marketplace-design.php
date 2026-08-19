<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
    }
}

if (!adminHasPermission('marketplace_design', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage marketplace design.</p><a href="admin.php">Go to Dashboard</a></div>');
}

$conn->query("CREATE TABLE IF NOT EXISTS `marketplace_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function getSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

function updateSetting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO marketplace_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Fetch current settings
$hero_image = getSetting('hero_image', '');
$hero_title = getSetting('hero_title', 'Up to 50% OFF on everything');
$hero_subtitle = getSetting('hero_subtitle', 'Shop the biggest sale of the year. Limited time offer.');
$hero_btn_text = getSetting('hero_btn_text', 'Shop Now');
$hero_btn_link = getSetting('hero_btn_link', '#');
$body_bg_color = getSetting('body_bg_color', '#f5f5f5');
$text_primary_color = getSetting('text_primary_color', '#1f2937');
$primary_btn_bg = getSetting('primary_btn_bg', '#2563eb');
$primary_btn_text = getSetting('primary_btn_text', '#ffffff');
$card_bg_color = getSetting('card_bg_color', '#ffffff');
$sidebar_bg_color = getSetting('sidebar_bg_color', '#ffffff');
$sidebar_text_color = getSetting('sidebar_text_color', '#4a5568');

// Promotional Banners
$promo1_title = getSetting('promo1_title', 'Up to 50% Off Electronics');
$promo1_subtitle = getSetting('promo1_subtitle', 'Limited time offer on top brands');
$promo1_link = getSetting('promo1_link', '#');
$promo1_enabled = getSetting('promo1_enabled', '1');
$promo2_title = getSetting('promo2_title', 'New Arrivals in Fashion');
$promo2_subtitle = getSetting('promo2_subtitle', 'Fresh styles every week');
$promo2_link = getSetting('promo2_link', '#');
$promo2_enabled = getSetting('promo2_enabled', '1');

// ----- NEW: Shipping & Tax Settings -----
$tax_rate = getSetting('tax_rate', '0');
$shipping_default = getSetting('shipping_default', '0');
$shipping_states_raw = getSetting('shipping_states', '');
$shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];

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
        updateSetting('hero_title', $_POST['hero_title']);
        updateSetting('hero_subtitle', $_POST['hero_subtitle']);
        updateSetting('hero_btn_text', $_POST['hero_btn_text']);
        updateSetting('hero_btn_link', $_POST['hero_btn_link']);
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/hero/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed) && $_FILES['hero_image']['size'] < 2*1024*1024) {
                $filename = 'hero_banner_' . time() . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $destination)) {
                    updateSetting('hero_image', 'uploads/hero/' . $filename);
                }
            }
        }
        $message = "Hero banner updated.";
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
        updateSetting('promo1_enabled', isset($_POST['promo1_enabled']) ? '1' : '0');
        updateSetting('promo2_title', $_POST['promo2_title']);
        updateSetting('promo2_subtitle', $_POST['promo2_subtitle']);
        updateSetting('promo2_link', $_POST['promo2_link']);
        updateSetting('promo2_enabled', isset($_POST['promo2_enabled']) ? '1' : '0');
        $message = "Promotional banners updated.";
        $messageType = "success";
    }
    
    // NEW: Save Shipping & Tax Settings
    if (isset($_POST['save_shipping'])) {
        updateSetting('tax_rate', $_POST['tax_rate']);
        updateSetting('shipping_default', $_POST['shipping_default']);
        // Build states array from POST
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
    $hero_image = getSetting('hero_image', '');
    $hero_title = getSetting('hero_title', 'Up to 50% OFF on everything');
    $hero_subtitle = getSetting('hero_subtitle', 'Shop the biggest sale of the year. Limited time offer.');
    $hero_btn_text = getSetting('hero_btn_text', 'Shop Now');
    $hero_btn_link = getSetting('hero_btn_link', '#');
    $body_bg_color = getSetting('body_bg_color', '#f5f5f5');
    $text_primary_color = getSetting('text_primary_color', '#1f2937');
    $primary_btn_bg = getSetting('primary_btn_bg', '#2563eb');
    $primary_btn_text = getSetting('primary_btn_text', '#ffffff');
    $card_bg_color = getSetting('card_bg_color', '#ffffff');
    $sidebar_bg_color = getSetting('sidebar_bg_color', '#ffffff');
    $sidebar_text_color = getSetting('sidebar_text_color', '#4a5568');
    $promo1_title = getSetting('promo1_title', 'Up to 50% Off Electronics');
    $promo1_subtitle = getSetting('promo1_subtitle', 'Limited time offer on top brands');
    $promo1_link = getSetting('promo1_link', '#');
    $promo1_enabled = getSetting('promo1_enabled', '1');
    $promo2_title = getSetting('promo2_title', 'New Arrivals in Fashion');
    $promo2_subtitle = getSetting('promo2_subtitle', 'Fresh styles every week');
    $promo2_link = getSetting('promo2_link', '#');
    $promo2_enabled = getSetting('promo2_enabled', '1');
    $tax_rate = getSetting('tax_rate', '0');
    $shipping_default = getSetting('shipping_default', '0');
    $shipping_states_raw = getSetting('shipping_states', '');
    $shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];
    $stores = [];
    $storeQuery = $conn->query("SELECT id, store_name, user_id FROM stores WHERE status = 'active' ORDER BY store_name ASC");
    while ($row = $storeQuery->fetch_assoc()) {
        $row['visible'] = getSetting("store_visible_{$row['id']}", '1');
        $stores[] = $row;
    }
}

$adminPageTitle = 'Marketplace Design - Admin';
$adminPageHeading = 'Marketplace Design';
$adminPageSubtitle = 'Control marketplace appearance';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
$adminPageStyles = <<<'CSS'
.design-section { padding: 1rem 2rem 2rem; }
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

        <!-- Hero Banner Settings -->
        <div class="settings-group">
            <div class="settings-group-header">
                <div class="settings-group-title">🎯 Black Friday Hero Banner</div>
                <div class="settings-group-desc">Edit the promotional banner shown at the top of the marketplace.</div>
            </div>
            <div class="settings-group-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Banner Image (optional)</label>
                        <input type="file" name="hero_image" accept="image/*" class="form-input">
                        <?php if ($hero_image): ?>
                            <div style="margin-top: 10px;"><img src="<?= htmlspecialchars(rdv_admin_src($hero_image)) ?>" style="max-width: 150px; border-radius: 8px;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Main Title</label>
                        <input type="text" name="hero_title" class="form-input" value="<?= htmlspecialchars($hero_title) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subtitle / Description</label>
                        <input type="text" name="hero_subtitle" class="form-input" value="<?= htmlspecialchars($hero_subtitle) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Button Text</label>
                        <input type="text" name="hero_btn_text" class="form-input" value="<?= htmlspecialchars($hero_btn_text) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Button Link</label>
                        <input type="text" name="hero_btn_link" class="form-input" value="<?= htmlspecialchars($hero_btn_link) ?>">
                    </div>
                    <button type="submit" name="save_hero" class="btn-primary">Save Hero Banner</button>
                </form>
                <div class="hero-preview">
                    <div class="hero-preview-text">
                        <h2><?= htmlspecialchars($hero_title) ?></h2>
                        <p><?= htmlspecialchars($hero_subtitle) ?></p>
                        <a href="#" style="background: #2563eb; color: white; padding: 0.5rem 1rem; border-radius: 30px; text-decoration: none; display: inline-block;"><?= htmlspecialchars($hero_btn_text) ?></a>
                    </div>
                    <?php if ($hero_image): ?>
                        <img src="<?= htmlspecialchars(rdv_admin_src($hero_image)) ?>" alt="Banner preview">
                    <?php else: ?>
                        <div style="width: 100px; height: 100px; background: #ccc; border-radius: 12px; display: flex; align-items: center; justify-content: center;">Image preview</div>
                    <?php endif; ?>
                </div>
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
    </div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
