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
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Design - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── same styles as your original – unchanged ── */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #eef2ff;
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-primary: #e2e8f0;
            --border-secondary: #cbd5e1;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --error: #ef4444;
            --error-light: #fee2e2;
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 260px;
            --sidebar-collapsed: 72px;
            --topbar-height: 64px;
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
        }
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-primary: #334155;
            --border-secondary: #475569;
            --primary-light: rgba(99,102,241,0.2);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.3);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.4), 0 1px 2px -1px rgb(0 0 0 / 0.4);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.4), 0 2px 4px -2px rgb(0 0 0 / 0.4);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.4), 0 4px 6px -4px rgb(0 0 0 / 0.4);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-sans);
            font-size: 0.9375rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            transition: background var(--transition), color var(--transition);
            overflow-x: hidden;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; }
        
        .sidebar {
            position: fixed; left:0; top:0; bottom:0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: 300;
            transition: width var(--transition), transform var(--transition);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.25rem;
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 0.75rem;
            font-weight: 800; font-size: 1.125rem;
            white-space: nowrap;
        }
        .nav-logo-icon {
            width: 32px; height: 32px;
            background: var(--gradient-primary);
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            color: white;
        }
        .sidebar-toggle {
            width: 28px; height: 28px;
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted);
        }
        .sidebar-toggle:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .sidebar-menu {
            flex: 1; overflow-y: auto; padding: 1rem 0.75rem;
        }
        .sidebar-section-title {
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
            color: var(--text-muted); padding: 0.5rem 1rem; letter-spacing: 0.5px;
        }
        .sidebar-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.6rem 1rem; border-radius: var(--radius);
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            transition: var(--transition); margin-bottom: 2px;
            cursor: pointer;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background: var(--primary-light); color: var(--primary);
        }
        .sidebar.collapsed .sidebar-item span,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .nav-logo span {
            opacity: 0; width: 0; overflow: hidden;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition);
            min-height: 100vh;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        
        .dash-navbar {
            position: fixed; top:0; right:0; left: var(--sidebar-width);
            height: var(--topbar-height);
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem;
            z-index: 200;
            transition: left var(--transition);
        }
        [data-theme="dark"] .dash-navbar { background: rgba(15,23,42,0.8); }
        .dash-search {
            display: flex; align-items: center; gap: 0.5rem;
            background: var(--bg-tertiary);
            padding: 0.4rem 1rem;
            border-radius: var(--radius-lg);
            width: 280px;
        }
        .dash-search input {
            background: none; border: none; outline: none;
            font-size: 0.875rem; width: 100%;
        }
        .dash-actions { display: flex; align-items: center; gap: 1rem; }
        .dash-btn {
            width: 38px; height: 38px;
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }
        .dash-user {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.25rem 0.5rem 0.25rem 0.25rem;
            border-radius: var(--radius-lg);
            cursor: pointer;
        }
        .dash-user img {
            width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
        }
        .dash-user-info .name { font-size: 0.875rem; font-weight: 500; }
        .dash-user-info .role { font-size: 0.7rem; color: var(--text-muted); }
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0;
            min-width: 180px; background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            opacity: 0; pointer-events: none; transform: translateY(-8px);
            transition: var(--transition);
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-item {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1rem; font-size: 0.875rem;
            color: var(--text-secondary);
        }
        .dropdown-item:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        
        .page-header {
            padding: 1.5rem 2rem 0.5rem 2rem;
            margin-top: var(--topbar-height);
        }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        
        .design-section {
            padding: 1rem 2rem 2rem 2rem;
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
        .settings-group-body {
            padding: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 0.6rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        .color-input {
            width: 60px;
            height: 40px;
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
        .shipping-grid .form-group {
            margin-bottom: 0;
        }
        .shipping-grid .form-group label {
            font-size: 0.75rem;
            font-weight: 500;
        }
        .shipping-grid .form-group input {
            width: 100%;
            padding: 0.3rem 0.5rem;
            font-size: 0.8rem;
            border-radius: var(--radius);
            border: 1px solid var(--border-primary);
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius);
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        .alert-success {
            background: var(--success-light);
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
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
            background: var(--card-bg-color-demo, #ffffff);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            width: 200px;
            text-align: center;
        }
        .preview-btn {
            background: var(--primary-btn-bg-demo, #2563eb);
            color: var(--primary-btn-text-demo, white);
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
        }
        .hero-preview-text h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .hero-preview img {
            max-width: 150px;
            border-radius: 12px;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .design-section { padding: 1rem; }
            .dash-search { width: 160px; }
            .mobile-sidebar-toggle { display: flex; }
        }
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); z-index:299; display:none; backdrop-filter: blur(4px); }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="nav-logo">
            <div class="nav-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
            <span>RD Vendora</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-section-title">Platform</div>
        <a href="admin.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a href="admin-users.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
        <a href="admin-stores.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span>Stores</span></a>
        <a href="admin-pricing.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pricing Plans</span></a>
        <a href="admin-testimonies.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span>Testimonials</span></a>
        <a href="admin-contacts.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><span>Contact Messages</span></a>
        <a href="admin-about.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>About Page</span></a>
        <a href="admin-chat.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Chat</span></a>
        <a href="admin-receive-order.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>All Orders</span></a>
        <a href="admin-transport.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Transport Orders</span></a>
        <a href="admin-customers.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Customers</span></a>
        <a href="admin-send-email.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Send Email</span></a>
        <a href="admin-marketplace-design.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Marketplace Design</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="../dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Marketplace Design</h1>
        <p class="page-subtitle">Customize the look & feel, hero banner, store visibility, promotional banners, and shipping/tax settings.</p>
    </div>

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
</div>

<script>
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.innerHTML = savedTheme === 'light' ? '🌙' : '☀️';
        themeToggle.addEventListener('click', () => {
            const newTheme = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('RD Vendora-theme', newTheme);
            themeToggle.innerHTML = newTheme === 'light' ? '🌙' : '☀️';
        });
    }

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    function closeMobile() { sidebar.classList.remove('mobile-open'); overlay.style.display = 'none'; document.body.style.overflow = ''; }
    function openMobile() { sidebar.classList.add('mobile-open'); overlay.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) { if (sidebar.classList.contains('mobile-open')) closeMobile(); else openMobile(); }
            else sidebar.classList.toggle('collapsed');
        });
    }
    if (mobileToggle) mobileToggle.addEventListener('click', openMobile);
    overlay.addEventListener('click', closeMobile);
    window.addEventListener('resize', () => { if (window.innerWidth > 768) { closeMobile(); sidebar.classList.remove('collapsed'); } });

    const userDD = document.getElementById('userDropdown');
    if (userDD) {
        const trigger = userDD.querySelector('.dropdown-trigger');
        trigger.addEventListener('click', (e) => { e.stopPropagation(); userDD.classList.toggle('open'); });
        document.addEventListener('click', () => userDD.classList.remove('open'));
    }

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='../logout.php'; }
</script>
</body>
</html>