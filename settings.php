<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$user_id = $_SESSION['user_id'];

// ========== STORE EXISTENCE CHECK ==========
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$stmt->close();

// ========== SUBSCRIPTION CHECK ==========
require_once 'includes/subscription_check.php';
$hasSubscription = hasActiveSubscription($conn, $user_id);

// Fetch the active plan name (if any)
$activePlan = null;
if ($hasSubscription) {
    $stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $planRow = $stmt->get_result()->fetch_assoc();
    $activePlan = $planRow['plan'] ?? null;
    $stmt->close();
}
$isPaidUser = ($activePlan && in_array($activePlan, ['Growth', 'Scale', 'Empire']));
$canCustomizeColors = (bool) $isPaidUser; // Colors require Growth, Scale, or Empire

// ========== INITIALIZE VARIABLES ==========
$message = '';
$messageType = '';
$store = null;
$userName = null;
$storeName = null;
$products = [];
$banners = [];

// ----- Fetch user name (auto‑detect column) -----
$cols = $conn->query("SHOW COLUMNS FROM users");
$existingCols = [];
while ($col = $cols->fetch_assoc()) {
    $existingCols[] = $col['Field'];
}
$nameColumn = 'fullname';
if (!in_array('fullname', $existingCols)) {
    if (in_array('full_name', $existingCols)) $nameColumn = 'full_name';
    elseif (in_array('name', $existingCols)) $nameColumn = 'name';
    else $nameColumn = 'email';
}
$stmt = $conn->prepare("SELECT $nameColumn as name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$userName = $user['name'] ?? 'User';

// ----- Fetch store -----
$stmt = $conn->prepare("SELECT * FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$store) {
    die('Store not found. Please contact administrator.');
}

$storeName = $store['store_name'] ?? 'My Store';

// Set defaults for all possible columns (null coalescing)
$store['store_name']        = $store['store_name'] ?? 'My Store';
$store['description']       = $store['description'] ?? '';
$store['currency']          = $store['currency'] ?? 'USD';
$store['language']          = $store['language'] ?? 'English';
$store['brand_color']       = $store['brand_color'] ?? '#4f46e5';
$store['nav_color']         = $store['nav_color'] ?? '#ffffff';
$store['body_bg_color']     = $store['body_bg_color'] ?? '#f9fafb';
$store['footer_bg_color']   = $store['footer_bg_color'] ?? '#111827';
$store['card_bg_color']     = $store['card_bg_color'] ?? '#ffffff';
$store['card_border_color'] = $store['card_border_color'] ?? '#e5e7eb';
$store['button_bg_color']   = $store['button_bg_color'] ?? '#6366f1';
$store['button_text_color'] = $store['button_text_color'] ?? '#ffffff';
$store['div_bg_color']      = $store['div_bg_color'] ?? '#f3f4f6';
$store['div_border_color']  = $store['div_border_color'] ?? '#e5e7eb';
$store['theme']             = $store['theme'] ?? 'minimal';
$store['logo_path']         = $store['logo_path'] ?? null;
$store['hero_background']   = $store['hero_background'] ?? null;
$store['typography']        = $store['typography'] ?? null;

// ----- Ensure products table exists -----
$conn->query("CREATE TABLE IF NOT EXISTS `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL,
    `stock` INT(11) NOT NULL DEFAULT 0,
    `category` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active','draft') DEFAULT 'active',
    `image` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ----- Ensure banners table exists -----
$conn->query("CREATE TABLE IF NOT EXISTS `promo_banners` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT NULL,
    `image` VARCHAR(255) NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `order_position` INT(11) DEFAULT 0,
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add missing columns to promo_banners
$checkDesc = $conn->query("SHOW COLUMNS FROM promo_banners LIKE 'description'");
if ($checkDesc->num_rows == 0) {
    $conn->query("ALTER TABLE promo_banners ADD COLUMN description TEXT NULL AFTER title");
}

// Add all color columns to stores table if missing
$colorColumns = [
    'nav_color' => "ADD COLUMN nav_color VARCHAR(7) DEFAULT '#ffffff' AFTER brand_color",
    'body_bg_color' => "ADD COLUMN body_bg_color VARCHAR(7) DEFAULT '#f9fafb' AFTER nav_color",
    'footer_bg_color' => "ADD COLUMN footer_bg_color VARCHAR(7) DEFAULT '#111827' AFTER body_bg_color",
    'card_bg_color' => "ADD COLUMN card_bg_color VARCHAR(7) DEFAULT '#ffffff' AFTER footer_bg_color",
    'card_border_color' => "ADD COLUMN card_border_color VARCHAR(7) DEFAULT '#e5e7eb' AFTER card_bg_color",
    'button_bg_color' => "ADD COLUMN button_bg_color VARCHAR(7) DEFAULT '#6366f1' AFTER card_border_color",
    'button_text_color' => "ADD COLUMN button_text_color VARCHAR(7) DEFAULT '#ffffff' AFTER button_bg_color",
    'div_bg_color' => "ADD COLUMN div_bg_color VARCHAR(7) DEFAULT '#f3f4f6' AFTER button_text_color",
    'div_border_color' => "ADD COLUMN div_border_color VARCHAR(7) DEFAULT '#e5e7eb' AFTER div_bg_color"
];
foreach ($colorColumns as $column => $alterSQL) {
    $check = $conn->query("SHOW COLUMNS FROM stores LIKE '{$column}'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE stores {$alterSQL}");
    }
}

// ----- Typography: add column if missing, set defaults -----
$result = $conn->query("SHOW COLUMNS FROM stores LIKE 'typography'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE `stores` ADD COLUMN `typography` JSON DEFAULT NULL AFTER `logo_path`");
}

$defaultTypography = [
    'h1' => ['size' => '36', 'color' => '#111827'],
    'h2' => ['size' => '30', 'color' => '#111827'],
    'h3' => ['size' => '24', 'color' => '#111827'],
    'h4' => ['size' => '20', 'color' => '#111827'],
    'h5' => ['size' => '18', 'color' => '#111827'],
    'h6' => ['size' => '16', 'color' => '#111827'],
    'p'  => ['size' => '16', 'color' => '#4b5563']
];

if (empty($store['typography']) || $store['typography'] === null) {
    $typographyJson = json_encode($defaultTypography);
    $update = $conn->prepare("UPDATE stores SET typography = ? WHERE user_id = ?");
    $update->bind_param("si", $typographyJson, $user_id);
    $update->execute();
    $update->close();
    $store['typography'] = $typographyJson;
}
$typography = json_decode($store['typography'], true);
if (!$typography) {
    $typography = $defaultTypography;
}

// ----- Helper functions (always defined, but will not be called if not allowed) -----
function uploadLogo($file, $userId) {
    $uploadDir = 'uploads/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 2*1024*1024) return null;
    $filename = 'store_'.$userId.'_'.time().'.'.$ext;
    $destination = $uploadDir.$filename;
    return move_uploaded_file($file['tmp_name'], $destination) ? $destination : null;
}

function uploadHeroBackground($file, $userId) {
    $uploadDir = 'uploads/hero/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 5*1024*1024) return null;
    $filename = 'hero_'.$userId.'_'.time().'.'.$ext;
    $destination = $uploadDir.$filename;
    return move_uploaded_file($file['tmp_name'], $destination) ? $destination : null;
}

function uploadProductImage($file, $userId) {
    $uploadDir = 'uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 2*1024*1024) return null;
    $filename = 'product_'.$userId.'_'.time().'_'.rand(1000,9999).'.'.$ext;
    $destination = $uploadDir.$filename;
    return move_uploaded_file($file['tmp_name'], $destination) ? $destination : null;
}

function uploadBannerImage($file, $userId) {
    $uploadDir = 'uploads/banners/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 2*1024*1024) return null;
    $filename = 'banner_'.$userId.'_'.time().'_'.rand(1000,9999).'.'.$ext;
    $destination = $uploadDir.$filename;
    return move_uploaded_file($file['tmp_name'], $destination) ? $destination : null;
}

// ----- Handle POST submissions (based on subscription plan) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Global rule: without a paid subscription, only store information can be updated (if subscription exists at all)
    if (!$hasSubscription) {
        $message = "Your subscription is inactive. You cannot change store settings or manage products/banners.";
        $messageType = "error";
    } else {
        // STORE INFORMATION – allowed for all active subscribers (including free)
        if (isset($_POST['update_store'])) {
            $store_name = trim($_POST['store_name'] ?? $store['store_name']);
            $description = trim($_POST['description'] ?? $store['description']);
            $currency = $_POST['currency'] ?? $store['currency'];
            $language = $_POST['language'] ?? $store['language'];
            $brand_color = $_POST['brand_color'] ?? $store['brand_color'];
            $nav_color = $_POST['nav_color'] ?? $store['nav_color'];
            $body_bg_color = $_POST['body_bg_color'] ?? $store['body_bg_color'];
            $footer_bg_color = $_POST['footer_bg_color'] ?? $store['footer_bg_color'];
            $card_bg_color = $_POST['card_bg_color'] ?? $store['card_bg_color'];
            $card_border_color = $_POST['card_border_color'] ?? $store['card_border_color'];
            $button_bg_color = $_POST['button_bg_color'] ?? $store['button_bg_color'];
            $button_text_color = $_POST['button_text_color'] ?? $store['button_text_color'];
            $div_bg_color = $_POST['div_bg_color'] ?? $store['div_bg_color'];
            $div_border_color = $_POST['div_border_color'] ?? $store['div_border_color'];
            $normalizeHex = static function ($value, $fallback) {
                $value = trim((string) $value);
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    return strtolower($value);
                }
                return $fallback;
            };
            $brand_color = $normalizeHex($brand_color, $store['brand_color']);
            $nav_color = $normalizeHex($nav_color, $store['nav_color']);
            $body_bg_color = $normalizeHex($body_bg_color, $store['body_bg_color']);
            $footer_bg_color = $normalizeHex($footer_bg_color, $store['footer_bg_color']);
            $card_bg_color = $normalizeHex($card_bg_color, $store['card_bg_color']);
            $card_border_color = $normalizeHex($card_border_color, $store['card_border_color']);
            $button_bg_color = $normalizeHex($button_bg_color, $store['button_bg_color']);
            $button_text_color = $normalizeHex($button_text_color, $store['button_text_color']);
            $div_bg_color = $normalizeHex($div_bg_color, $store['div_bg_color']);
            $div_border_color = $normalizeHex($div_border_color, $store['div_border_color']);
            $theme = $_POST['theme'] ?? $store['theme'];
            $logoPath = $store['logo_path'];
            $heroPath = $store['hero_background'];
            $newSlug = isset($_POST['store_slug']) ? strtolower(trim((string) $_POST['store_slug'])) : (string) ($store['store_slug'] ?? '');
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $newLogo = uploadLogo($_FILES['logo'], $user_id);
                if ($newLogo) $logoPath = $newLogo;
            }
            if (isset($_FILES['hero_background']) && $_FILES['hero_background']['error'] === UPLOAD_ERR_OK) {
                $newHero = uploadHeroBackground($_FILES['hero_background'], $user_id);
                if ($newHero) $heroPath = $newHero;
            }

            $slugCheck = rdv_store_slug_availability($conn, $newSlug, (int) ($store['id'] ?? 0));
            if (!$slugCheck['ok']) {
                $message = $slugCheck['message'];
                $messageType = 'error';
            } else {
                $newSlug = $slugCheck['slug'];
                $stmt = $conn->prepare("UPDATE stores SET 
                    store_name=?, store_slug=?, description=?, currency=?, language=?, 
                    brand_color=?, nav_color=?, body_bg_color=?, footer_bg_color=?,
                    card_bg_color=?, card_border_color=?, button_bg_color=?, 
                    button_text_color=?, div_bg_color=?, div_border_color=?, 
                    theme=?, logo_path=?, hero_background=? 
                    WHERE user_id = ?");
                
                $stmt->bind_param("ssssssssssssssssssi", 
                    $store_name, $newSlug, $description, $currency, $language, 
                    $brand_color, $nav_color, $body_bg_color, $footer_bg_color,
                    $card_bg_color, $card_border_color, $button_bg_color, 
                    $button_text_color, $div_bg_color, $div_border_color, 
                    $theme, $logoPath, $heroPath, $user_id
                );
                
                if ($stmt->execute()) {
                    $message = "Store settings updated!";
                    $messageType = "success";
                    
                    $store['store_name'] = $store_name;
                    $store['store_slug'] = $newSlug;
                    $store['description'] = $description;
                    $store['currency'] = $currency;
                    $store['language'] = $language;
                    $store['brand_color'] = $brand_color;
                    $store['nav_color'] = $nav_color;
                    $store['body_bg_color'] = $body_bg_color;
                    $store['footer_bg_color'] = $footer_bg_color;
                    $store['card_bg_color'] = $card_bg_color;
                    $store['card_border_color'] = $card_border_color;
                    $store['button_bg_color'] = $button_bg_color;
                    $store['button_text_color'] = $button_text_color;
                    $store['div_bg_color'] = $div_bg_color;
                    $store['div_border_color'] = $div_border_color;
                    $store['theme'] = $theme;
                    $store['logo_path'] = $logoPath;
                    $store['hero_background'] = $heroPath;
                    $storeName = $store_name;
                    $_SESSION['store_slug'] = $newSlug;
                    $_SESSION['store_name'] = $store_name;
                } else {
                    $message = "Failed to update store. Please try again.";
                    $messageType = "error";
                }
                $stmt->close();
            }
        }
        
        // Color & Appearance updates – only for paid users
        if ($isPaidUser && isset($_POST['update_colors'])) {
            // This form is separate (not used in current design, but we keep for completeness)
            $brand_color = $_POST['brand_color'] ?? $store['brand_color'];
            // ... other colors are already saved via update_store.
            // Since we already save all colors in the same form, we don't need separate logic.
        }
        
        // PRODUCT MANAGEMENT – only for paid users
        if ($isPaidUser) {
            if (isset($_POST['add_product'])) {
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category = $_POST['category'];
                $status = $_POST['status'];
                $description = trim($_POST['description']);
                $imagePath = null;
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $imagePath = uploadProductImage($_FILES['product_image'], $user_id);
                }
                $stmt = $conn->prepare("INSERT INTO products (user_id, name, price, stock, category, status, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdissss", $user_id, $name, $price, $stock, $category, $status, $description, $imagePath);
                if ($stmt->execute()) {
                    $message = "Product added!";
                    $messageType = "success";
                } else {
                    $message = "Failed to add product: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            
            if (isset($_POST['delete_product'])) {
                $product_id = intval($_POST['product_id']);
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $product_id, $user_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Product deleted!";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete product.";
                    $messageType = "error";
                }
                $stmt->close();
            }
            
            if (isset($_POST['get_product'])) {
                $product_id = intval($_POST['product_id']);
                $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $product_id, $user_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                echo json_encode($product);
                exit;
            }
            
            if (isset($_POST['update_product'])) {
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category = $_POST['category'];
                $status = $_POST['status'];
                $description = trim($_POST['description']);
                $existing_image = $_POST['existing_image'] ?? '';
                $imagePath = $existing_image;
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $newImage = uploadProductImage($_FILES['product_image'], $user_id);
                    if ($newImage) $imagePath = $newImage;
                }
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category=?, status=?, description=?, image=? WHERE id=? AND user_id=?");
                $stmt->bind_param("sdissssii", $name, $price, $stock, $category, $status, $description, $imagePath, $product_id, $user_id);
                if ($stmt->execute()) {
                    $message = "Product updated!";
                    $messageType = "success";
                } else {
                    $message = "Failed to update product: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            
            // BANNER MANAGEMENT – only for paid users
            if (isset($_POST['add_banner'])) {
                $title = trim($_POST['banner_title']);
                $description = trim($_POST['banner_description']);
                $link = trim($_POST['banner_link']);
                $status = $_POST['banner_status'];
                $imagePath = null;
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $imagePath = uploadBannerImage($_FILES['banner_image'], $user_id);
                }
                if ($imagePath) {
                    $posResult = $conn->query("SELECT MAX(order_position) as maxpos FROM promo_banners WHERE user_id = $user_id");
                    $maxPos = $posResult->fetch_assoc()['maxpos'] ?? 0;
                    $newPos = $maxPos + 1;
                    $stmt = $conn->prepare("INSERT INTO promo_banners (user_id, title, description, image, link, order_position, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssis", $user_id, $title, $description, $imagePath, $link, $newPos, $status);
                    if ($stmt->execute()) {
                        $message = "Banner added!";
                        $messageType = "success";
                    } else {
                        $message = "Failed to add banner: " . $stmt->error;
                        $messageType = "error";
                    }
                    $stmt->close();
                } else {
                    $message = "Banner image is required.";
                    $messageType = "error";
                }
            }
            
            if (isset($_POST['get_banner'])) {
                $banner_id = intval($_POST['banner_id']);
                $stmt = $conn->prepare("SELECT * FROM promo_banners WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $banner_id, $user_id);
                $stmt->execute();
                $banner = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                echo json_encode($banner);
                exit;
            }
            
            if (isset($_POST['update_banner'])) {
                $banner_id = intval($_POST['banner_id']);
                $title = trim($_POST['banner_title']);
                $description = trim($_POST['banner_description']);
                $link = trim($_POST['banner_link']);
                $status = $_POST['banner_status'];
                $existing_image = $_POST['existing_image'] ?? '';
                $imagePath = $existing_image;
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $newImage = uploadBannerImage($_FILES['banner_image'], $user_id);
                    if ($newImage) $imagePath = $newImage;
                }
                $stmt = $conn->prepare("UPDATE promo_banners SET title=?, description=?, link=?, status=?, image=? WHERE id=? AND user_id=?");
                $stmt->bind_param("sssssii", $title, $description, $link, $status, $imagePath, $banner_id, $user_id);
                if ($stmt->execute()) {
                    $message = "Banner updated!";
                    $messageType = "success";
                } else {
                    $message = "Failed to update banner: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            
            if (isset($_POST['delete_banner'])) {
                $banner_id = intval($_POST['banner_id']);
                $stmt = $conn->prepare("DELETE FROM promo_banners WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $banner_id, $user_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Banner deleted!";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete banner.";
                    $messageType = "error";
                }
                $stmt->close();
            }
            
            if (isset($_POST['toggle_banner_status'])) {
                $banner_id = intval($_POST['banner_id']);
                $newStatus = $_POST['status'];
                $stmt = $conn->prepare("UPDATE promo_banners SET status = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("sii", $newStatus, $banner_id, $user_id);
                if ($stmt->execute()) {
                    $message = "Banner status updated!";
                    $messageType = "success";
                } else {
                    $message = "Failed to update status.";
                    $messageType = "error";
                }
                $stmt->close();
            }
            
            // TYPOGRAPHY – only for paid users
            if (isset($_POST['save_typography'])) {
                $typography = [];
                foreach (['h1','h2','h3','h4','h5','h6','p'] as $tag) {
                    $size = intval($_POST[$tag . '_size']);
                    $color = $_POST[$tag . '_color'];
                    $typography[$tag] = ['size' => $size, 'color' => $color];
                }
                $json = json_encode($typography);
                $stmt = $conn->prepare("UPDATE stores SET typography = ? WHERE user_id = ?");
                $stmt->bind_param("si", $json, $user_id);
                if ($stmt->execute()) {
                    $message = "Typography settings saved! Refresh the storefront to see changes.";
                    $messageType = "success";
                    $store['typography'] = $json;
                    $typography = $typography;
                } else {
                    $message = "Failed to save typography: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
        } else {
            // For free users, if they somehow submit product/banner/typography forms, block them.
            if (isset($_POST['add_product']) || isset($_POST['update_product']) || isset($_POST['delete_product']) ||
                isset($_POST['add_banner']) || isset($_POST['update_banner']) || isset($_POST['delete_banner']) ||
                isset($_POST['save_typography'])) {
                $message = "Your current plan (Launch) does not allow managing products, banners, or typography. Please upgrade to Growth, Scale, or Empire.";
                $messageType = "error";
            }
        }
    }
}

// Fetch products and banners (always – view-only when subscription inactive or free)
$products = [];
$stmt = $conn->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// ---------- Ensure store exists ----------
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$stmt->close();

// ---------- Check if store is active (not disabled by admin) ----------
if (!isStoreActive($conn, $_SESSION['user_id'])) {
    // Show a clear message that the store has been disabled by admin
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Store Disabled</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⛔ Store Disabled</h1>
        <p>Your store has been disabled by the administrator. Please contact support for more information.</p>
        <a href="logout">Logout</a>
    </body>
    </html>
    <?php
    exit();
}

$banners = [];
$stmt = $conn->prepare("SELECT * FROM promo_banners WHERE user_id = ? ORDER BY order_position ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $banners[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Settings - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           RD Vendora - Dashboard Styles (identical to dashboard.php)
           ============================================================ */
        :root {
            --bg-primary: #f8f9fb;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f3f6;
            --bg-elevated: #ffffff;
            --bg-hover: #eef0f4;
            --bg-active: #e4e7ed;
            --surface-primary: #ffffff;
            --surface-secondary: #f8f9fb;
            --surface-tertiary: #f1f3f6;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --text-inverse: #ffffff;
            --border-primary: #e5e7eb;
            --border-secondary: #d1d5db;
            --border-focus: #6366f1;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #4338ca;
            --success: #10b981;
            --success-light: #ecfdf5;
            --success-dark: #047857;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --warning-dark: #b45309;
            --error: #ef4444;
            --error-light: #fef2f2;
            --error-dark: #b91c1c;
            --info: #3b82f6;
            --info-light: #eff6ff;
            --info-dark: #1d4ed8;
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --text-xs: 0.75rem;
            --text-sm: 0.8125rem;
            --text-base: 0.9375rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --text-4xl: 2.25rem;
            --font-normal: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-bold: 700;
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
            --space-16: 4rem;
            --space-20: 5rem;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 28px;
            --radius-full: 9999px;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06),0 2px 4px rgba(0,0,0,0.04);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.08),0 4px 8px rgba(0,0,0,0.04);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.10),0 8px 16px rgba(0,0,0,0.04);
            --transition-fast: 150ms cubic-bezier(0.4,0,0.2,1);
            --transition-base: 250ms cubic-bezier(0.4,0,0.2,1);
            --transition-slow: 350ms cubic-bezier(0.4,0,0.2,1);
            --sidebar-width: 260px;
            --sidebar-collapsed: 72px;
            --topbar-height: 64px;
        }
        
        [data-theme="dark"] {
            --bg-primary: #0c0e14;
            --bg-secondary: #14161f;
            --bg-tertiary: #1a1d28;
            --bg-elevated: #1e2130;
            --bg-hover: #242838;
            --bg-active: #2a2e40;
            --surface-primary: #14161f;
            --surface-secondary: #1a1d28;
            --surface-tertiary: #1e2130;
            --text-primary: #e8eaf0;
            --text-secondary: #9ca3b0;
            --text-muted: #6b7280;
            --border-primary: #2d3139;
            --border-secondary: #3a3f4a;
            --primary-light: rgba(99,102,241,0.15);
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.20);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.25),0 1px 2px rgba(0,0,0,0.20);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.30),0 2px 4px rgba(0,0,0,0.20);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.35),0 4px 8px rgba(0,0,0,0.25);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.40),0 8px 16px rgba(0,0,0,0.30);
        }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-sans);
            font-size: var(--text-base);
            line-height: 1.5;
            color: var(--text-primary);
            background: var(--bg-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background var(--transition-base), color var(--transition-base);
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; }
        ul, ol { list-style: none; }
        img { max-width: 100%; display: block; }

        /* Sidebar (exact copy from dashboard) */
        .sidebar {
            position: fixed; left:0; top:0; bottom:0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: 300;
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
            flex-shrink: 0;
            gap: var(--space-3);
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: var(--space-3);
            font-weight: 700; font-size: var(--text-lg);
            white-space: nowrap;
        }
                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
        .sidebar-brand-icon {
            width: 34px; height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: white;
        }
        .sidebar-brand-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-toggle {
            width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            transition: all var(--transition-fast);
        }
        .sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-nav {
            flex:1; overflow-y: auto; padding: var(--space-3);
        }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px; font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-top: var(--space-2);
        }
        .sidebar-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-size: var(--text-sm);
            font-weight: 500;
            transition: all var(--transition-fast);
            margin-bottom: 1px;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: var(--primary-light);
            color: var(--primary);
        }
        .sidebar-footer {
            padding: var(--space-3);
            border-top: 1px solid var(--border-primary);
        }
        .sidebar-user {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md);
            cursor: pointer;
        }
        .sidebar-user:hover { background: var(--bg-hover); }
        .sidebar-user-avatar { width: 34px; height: 34px; border-radius: var(--radius-full); object-fit: cover; }
        .sidebar-user-info { flex:1; min-width:0; }
        .sidebar-user-name { font-size: var(--text-sm); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: var(--text-xs); color: var(--text-muted); margin-top:2px; }
        .sidebar-overlay {
            position: fixed; inset:0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 299;
            opacity:0; pointer-events:none;
            transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active { opacity:1; pointer-events:all; }

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition-slow); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        /* Topbar (exact copy) */
        .topbar {
            position: sticky; top:0;
            height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 var(--space-6);
            z-index: 200;
            gap: var(--space-4);
            backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar { background: rgba(255,255,255,0.85); }
        [data-theme="dark"] .topbar { background: rgba(20,22,31,0.85); }
        .topbar-left { display: flex; align-items: center; gap: var(--space-3); }
        .mobile-sidebar-toggle {
            display: none;
            width: 38px; height: 38px;
            align-items: center; justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
        }
        .mobile-sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-actions { display: flex; align-items: center; gap: var(--space-2); }
        .theme-toggle {
            width:38px; height:38px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary);
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right:0;
            min-width: 240px;
            background: var(--bg-secondary);
            border:1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            opacity:0; pointer-events:none;
            transform: translateY(-8px);
            transition: all var(--transition-fast);
        }
        .dropdown.open .dropdown-menu { opacity:1; pointer-events:all; transform:translateY(0); }
        .dropdown-item { display: block; padding: 8px 16px; color: var(--text-secondary); }
        .dropdown-item:hover { background: var(--bg-tertiary); }

        /* Page content */
        .page-content { flex:1; padding: var(--space-6); }
        .dashboard-header { margin-bottom: var(--space-6); }
        .dashboard-title { font-size: var(--text-2xl); font-weight: 700; }
        .dashboard-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: 4px; }

        /* Settings groups */
        .settings-group {
            background: var(--bg-secondary);
            border:1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            overflow: hidden;
        }
        .settings-group-header { padding: var(--space-5) var(--space-6); border-bottom:1px solid var(--border-primary); }
        .settings-group-title { font-weight: 600; margin-bottom: 4px; }
        .settings-group-desc { font-size: 0.875rem; color: var(--text-muted); }
        .settings-group-body { padding: var(--space-5) var(--space-6); }
        .form-group { margin-bottom: var(--space-4); }
        .form-label { display: block; font-weight: 500; margin-bottom: 6px; }
        .form-input, .form-select, .form-textarea {
            width:100%; padding: var(--space-3);
            background: var(--bg-tertiary);
            border:1px solid var(--border-primary);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
        }
        .btn {
            display: inline-flex; align-items: center; gap:8px;
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            font-weight: 600;
            transition: all var(--transition-fast);
            cursor: pointer;
            border:none;
        }
        .btn-primary { background: var(--gradient-primary); color:white; }
        .btn-danger { background: var(--error); color:white; }
        .btn-sm { padding: 4px 8px; font-size: 0.75rem; }
        
        .color-picker-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .color-option { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: 0.2s; }
        .color-option.active { border-color: white; box-shadow: 0 0 0 2px var(--primary); }
        .color-option:hover { transform: scale(1.1); }
        
        .color-section {
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .color-section-title {
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
            display: inline-block;
        }
        
        .preview-card {
            background: var(--card-bg-demo, #ffffff);
            border: 1px solid var(--card-border-demo, #e5e7eb);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .preview-button {
            background: var(--button-bg-demo, #6366f1);
            color: var(--button-text-demo, #ffffff);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 1rem;
        }
        
        .preview-div {
            background: var(--div-bg-demo, #f3f4f6);
            border: 1px solid var(--div-border-demo, #e5e7eb);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .logo-preview { max-width: 100px; margin-top: 8px; border-radius: 8px; }
        .product-table { width:100%; border-collapse: collapse; }
        .product-table th, .product-table td { padding: 12px; text-align: left; border-bottom:1px solid var(--border-primary); }
        .product-image-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .banner-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px;
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            margin-bottom: 12px;
            background: var(--bg-secondary);
            flex-wrap: wrap;
        }
        .banner-image-preview { width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-md); }
        .banner-info { flex: 2; min-width: 150px; }
        .banner-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: var(--radius-full); font-size: 0.7rem; font-weight: 600; }
        .badge-success { background: var(--success-light); color: var(--success-dark); }
        .badge-secondary { background: var(--bg-tertiary); color: var(--text-muted); }
        .message { padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: var(--success-light); color: var(--success-dark); }
        .message.error { background: var(--error-light); color: var(--error-dark); }
        .message.info { background: var(--info-light); color: var(--info-dark); }

        /* Typography customizer */
        .typography-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .typography-row label { width: 60px; font-weight: 600; }
        .size-input { width: 80px; }
        .color-input { width: 50px; height: 40px; border: 1px solid var(--border-primary); border-radius: 8px; cursor: pointer; }
        .color-picker-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }
        .custom-color-pick {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border: 1px solid var(--border-primary);
            border-radius: 10px;
            background: var(--bg-secondary);
            font-size: 0.8125rem;
            color: var(--text-secondary);
            cursor: pointer;
        }
        .custom-color-pick input[type="color"] {
            width: 36px;
            height: 36px;
            padding: 0;
            border: none;
            background: transparent;
            cursor: pointer;
        }
        .custom-color-hex {
            font-family: var(--font-mono, ui-monospace, monospace);
            font-size: 0.75rem;
            color: var(--text-muted);
            min-width: 4.5rem;
        }
        .preview-text { flex: 1; font-family: inherit; margin-left: 20px; }
        .live-preview-box { background: var(--bg-tertiary); padding: 20px; border-radius: 12px; margin-top: 20px; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top:0; left:0;
            width:100%; height:100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index:1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            max-width: 600px;
            width:90%;
            padding: 24px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 16px; }
        .modal-title { font-size: 1.25rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .product-table th, .product-table td { padding: 8px; }
            .banner-item { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .topbar { padding: 0 var(--space-3); }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
    
    <?php include __DIR__ . '/includes/vendor_sidebar.php'; ?>


    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">☰</button>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggle">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <?php include __DIR__ . '/includes/vendor_user_avatar.php'; ?>
                        <!-- <span><?= htmlspecialchars($userName) ?></span> -->
                    </div>
                    <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="handleLogout()">Logout</a></div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Store Settings</h1>
                <p class="dashboard-subtitle">Manage your store information, colors, products, banners, and typography.</p>
            </div>

            <?php if ($message): ?>
                <div class="message <?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <!-- SUBSCRIPTION SUSPENSION BANNER (only for completely inactive subscription) -->
            <?php if (!$hasSubscription): ?>
                <div style="background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%); border-left: 4px solid #dc2626; border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="width: 48px; height: 48px; background: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">⚠️</div>
                        <div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #991b1b;">Store Suspended</h3>
                            <p style="color: #7f1d1d; margin-top: 4px;">Your store has been suspended because there is no active subscription. Please choose a plan to reactivate your store.</p>
                        </div>
                    </div>
                    <a href="subscription" class="btn" style="background: #dc2626; color: white; padding: 10px 24px; border-radius: 40px; text-decoration: none; font-weight: 600;">Reactivate Now →</a>
                </div>
            <?php else: ?>
                <!-- Info for free users: show upgrade notice -->
                <?php if ($hasSubscription && !$isPaidUser): ?>
                    <div class="message info" style="background: var(--info-light); color: var(--info-dark);">
                        💡 You are on the <strong>Launch (Free) Plan</strong>. Upgrade to <strong>Growth, Scale, or Empire</strong> to access <strong>Colors & Appearance</strong>, <strong>Products & Banners</strong>, and <strong>Typography</strong>.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($store): ?>
                <!-- Store Information Section (always visible) -->
                <div class="settings-group">
                    <div class="settings-group-header">
                        <div class="settings-group-title">Store Information</div>
                        <div class="settings-group-desc">Basic details about your store.</div>
                    </div>
                    <div class="settings-group-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label class="form-label">Store Name</label>
                                <input type="text" name="store_name" class="form-input" value="<?= htmlspecialchars($store['store_name'] ?? '') ?>" required <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                            </div>
                            <?php
                            $settingsStoreUrl = rdv_store_url($store);
                            $settingsSlug = (string) ($store['store_slug'] ?? '');
                            ?>
                            <div class="form-group" id="my-store-url">
                                <label class="form-label">My Store URL</label>
                                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
                                    <a id="storePublicUrlLink" href="<?= htmlspecialchars($settingsStoreUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;word-break:break-all;"><?= htmlspecialchars($settingsStoreUrl, ENT_QUOTES, 'UTF-8') ?></a>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.75rem;">
                                    <button type="button" class="btn btn-outline btn-sm" id="copyStoreUrlBtn">Copy URL</button>
                                    <a href="<?= htmlspecialchars($settingsStoreUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm" id="openStoreUrlBtn">Open Store</a>
                                    <button type="button" class="btn btn-ghost btn-sm" id="editStoreSlugBtn" <?= (!$hasSubscription) ? 'disabled' : '' ?>>Edit Store URL</button>
                                </div>
                                <div id="storeSlugEditor" style="display:none;margin-top:0.5rem;">
                                    <label class="form-label" for="storeSlugInput">Store URL slug</label>
                                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.35rem;">
                                        <span style="color:var(--text-muted);font-size:0.875rem;"><?= htmlspecialchars(rtrim((string)(defined('APP_URL')?APP_URL:'https://rdvendora.com'), '/') . '/', ENT_QUOTES, 'UTF-8') ?></span>
                                        <input type="text" id="storeSlugInput" class="form-input" value="<?= htmlspecialchars($settingsSlug, ENT_QUOTES, 'UTF-8') ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="100" autocomplete="off" style="max-width:280px;" <?= $hasSubscription ? 'name="store_slug"' : 'disabled' ?>>
                                    </div>
                                    <p id="storeSlugStatus" style="font-size:0.8125rem;margin-top:0.5rem;color:var(--text-muted);">Only letters, numbers and hyphens are allowed.</p>
                                </div>
                                <?php if (!$hasSubscription): ?>
                                <input type="hidden" name="store_slug" value="<?= htmlspecialchars($settingsSlug, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-textarea" rows="2" <?= (!$hasSubscription) ? 'disabled' : '' ?>><?= htmlspecialchars($store['description'] ?? '') ?></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-input" <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                                        <option value="USD" <?= ($store['currency'] ?? 'USD') == 'USD' ? 'selected' : '' ?>>USD</option>
                                        <option value="EUR" <?= ($store['currency'] ?? 'USD') == 'EUR' ? 'selected' : '' ?>>EUR</option>
                                        <option value="GBP" <?= ($store['currency'] ?? 'USD') == 'GBP' ? 'selected' : '' ?>>GBP</option>
                                        <option value="CAD" <?= ($store['currency'] ?? 'USD') == 'CAD' ? 'selected' : '' ?>>CAD</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Language</label>
                                    <select name="language" class="form-input" <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                                        <option value="English" <?= ($store['language'] ?? 'English') == 'English' ? 'selected' : '' ?>>English</option>
                                        <option value="Spanish" <?= ($store['language'] ?? 'English') == 'Spanish' ? 'selected' : '' ?>>Spanish</option>
                                        <option value="French" <?= ($store['language'] ?? 'English') == 'French' ? 'selected' : '' ?>>French</option>
                                        <option value="German" <?= ($store['language'] ?? 'English') == 'German' ? 'selected' : '' ?>>German</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Theme</label>
                                <select name="theme" class="form-input" <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                                    <option value="minimal" <?= ($store['theme'] ?? 'minimal') == 'minimal' ? 'selected' : '' ?>>Minimal</option>
                                    <option value="bold" <?= ($store['theme'] ?? 'minimal') == 'bold' ? 'selected' : '' ?>>Bold</option>
                                    <option value="warm" <?= ($store['theme'] ?? 'minimal') == 'warm' ? 'selected' : '' ?>>Warm</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Store Logo</label>
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp" <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                                <?php if (!empty($store['logo_path'])): ?>
                                    <div><img src="<?= htmlspecialchars($store['logo_path']) ?>" class="logo-preview" alt="Logo"></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Hero Background Image</label>
                                <input type="file" name="hero_background" accept="image/jpeg,image/png,image/gif,image/webp" <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                                <?php if (!empty($store['hero_background'])): ?>
                                    <div style="margin-top:8px;">
                                        <img src="<?= htmlspecialchars($store['hero_background']) ?>" style="max-width:200px; border-radius:8px; border:1px solid var(--border-primary);">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($hasSubscription): ?>
                                <button type="submit" name="update_store" class="btn btn-primary">Save Store Settings</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" disabled style="opacity:0.6; cursor:not-allowed;">Save Store Settings (Reactivate to edit)</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Colors & Appearance – Growth, Scale, or Empire only -->
                <?php if ($canCustomizeColors): ?>
                <div class="settings-group">
                    <div class="settings-group-header">
                        <div class="settings-group-title">Colors & Appearance</div>
                        <div class="settings-group-desc">Pick a preset or choose any custom color for your store.</div>
                    </div>
                    <div class="settings-group-body">
                        <form method="POST" id="colorForm">
                            <!-- Brand Color -->
                            <div class="color-section">
                                <div class="color-section-title">Brand Color</div>
                                <div class="form-group">
                                    <label class="form-label">Primary Brand Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="brandColorPickerGrid">
                                            <?php
                                            $brandColorOptions = ['#6366f1', '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#1a1d23'];
                                            $currentBrandColor = $store['brand_color'] ?? '#6366f1';
                                            foreach ($brandColorOptions as $color):
                                                $active = (strcasecmp($color, $currentBrandColor) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectBrandColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="brandColorCustom" value="<?= htmlspecialchars($currentBrandColor) ?>" oninput="applyCustomColor('brand', this.value)">
                                            <span class="custom-color-hex" id="brandColorHex"><?= htmlspecialchars(strtoupper($currentBrandColor)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="brand_color" id="brandColorInput" value="<?= htmlspecialchars($currentBrandColor) ?>">
                                </div>
                            </div>
                            
                            <!-- Navigation Bar -->
                            <div class="color-section">
                                <div class="color-section-title">Navigation Bar</div>
                                <div class="form-group">
                                    <label class="form-label">Navigation Bar Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="navColorPickerGrid">
                                            <?php
                                            $navColorOptions = ['#ffffff', '#1a1a2e', '#16213e', '#0f3460', '#2c3e50', '#34495e', '#111827', '#1f2937', '#f3f4f6'];
                                            $currentNavColor = $store['nav_color'] ?? '#ffffff';
                                            foreach ($navColorOptions as $color):
                                                $active = (strcasecmp($color, $currentNavColor) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectNavColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="navColorCustom" value="<?= htmlspecialchars($currentNavColor) ?>" oninput="applyCustomColor('nav', this.value)">
                                            <span class="custom-color-hex" id="navColorHex"><?= htmlspecialchars(strtoupper($currentNavColor)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="nav_color" id="navColorInput" value="<?= htmlspecialchars($currentNavColor) ?>">
                                </div>
                            </div>
                            
                            <!-- Page Background -->
                            <div class="color-section">
                                <div class="color-section-title">Page Background</div>
                                <div class="form-group">
                                    <label class="form-label">Body Background Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="bodyBgColorPickerGrid">
                                            <?php
                                            $bodyBgOptions = ['#f9fafb', '#f3f4f6', '#ffffff', '#e5e7eb', '#fef3c7', '#ecfdf5', '#eff6ff', '#fae8ff'];
                                            $currentBodyBg = $store['body_bg_color'] ?? '#f9fafb';
                                            foreach ($bodyBgOptions as $color):
                                                $active = (strcasecmp($color, $currentBodyBg) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectBodyBgColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="bodyBgColorCustom" value="<?= htmlspecialchars($currentBodyBg) ?>" oninput="applyCustomColor('bodyBg', this.value)">
                                            <span class="custom-color-hex" id="bodyBgColorHex"><?= htmlspecialchars(strtoupper($currentBodyBg)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="body_bg_color" id="bodyBgColorInput" value="<?= htmlspecialchars($currentBodyBg) ?>">
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div class="color-section">
                                <div class="color-section-title">Footer</div>
                                <div class="form-group">
                                    <label class="form-label">Footer Background Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="footerBgColorPickerGrid">
                                            <?php
                                            $footerBgOptions = ['#111827', '#1f2937', '#000000', '#1a1a2e', '#2d3748', '#4a5568'];
                                            $currentFooterBg = $store['footer_bg_color'] ?? '#111827';
                                            foreach ($footerBgOptions as $color):
                                                $active = (strcasecmp($color, $currentFooterBg) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectFooterBgColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="footerBgColorCustom" value="<?= htmlspecialchars($currentFooterBg) ?>" oninput="applyCustomColor('footerBg', this.value)">
                                            <span class="custom-color-hex" id="footerBgColorHex"><?= htmlspecialchars(strtoupper($currentFooterBg)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="footer_bg_color" id="footerBgColorInput" value="<?= htmlspecialchars($currentFooterBg) ?>">
                                </div>
                            </div>
                            
                            <!-- Cards -->
                            <div class="color-section">
                                <div class="color-section-title">Cards (Products, Items)</div>
                                <div class="form-group">
                                    <label class="form-label">Card Background Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="cardBgColorPickerGrid">
                                            <?php
                                            $cardBgOptions = ['#ffffff', '#f9fafb', '#f3f4f6', '#fef2f2', '#ecfdf5'];
                                            $currentCardBg = $store['card_bg_color'] ?? '#ffffff';
                                            foreach ($cardBgOptions as $color):
                                                $active = (strcasecmp($color, $currentCardBg) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectCardBgColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="cardBgColorCustom" value="<?= htmlspecialchars($currentCardBg) ?>" oninput="applyCustomColor('cardBg', this.value)">
                                            <span class="custom-color-hex" id="cardBgColorHex"><?= htmlspecialchars(strtoupper($currentCardBg)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="card_bg_color" id="cardBgColorInput" value="<?= htmlspecialchars($currentCardBg) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Card Border Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="cardBorderColorPickerGrid">
                                            <?php
                                            $cardBorderOptions = ['#e5e7eb', '#d1d5db', '#cbd5e1', '#e2e8f0', '#f1f5f9'];
                                            $currentCardBorder = $store['card_border_color'] ?? '#e5e7eb';
                                            foreach ($cardBorderOptions as $color):
                                                $active = (strcasecmp($color, $currentCardBorder) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectCardBorderColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="cardBorderColorCustom" value="<?= htmlspecialchars($currentCardBorder) ?>" oninput="applyCustomColor('cardBorder', this.value)">
                                            <span class="custom-color-hex" id="cardBorderColorHex"><?= htmlspecialchars(strtoupper($currentCardBorder)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="card_border_color" id="cardBorderColorInput" value="<?= htmlspecialchars($currentCardBorder) ?>">
                                </div>
                                <div class="preview-card" style="--card-bg-demo: <?= htmlspecialchars($store['card_bg_color'] ?? '#ffffff') ?>; --card-border-demo: <?= htmlspecialchars($store['card_border_color'] ?? '#e5e7eb') ?>;">
                                    <small>Preview:</small>
                                    <div style="padding: 0.5rem 0;">Product Card Example</div>
                                </div>
                            </div>
                            
                            <!-- Buttons -->
                            <div class="color-section">
                                <div class="color-section-title">Buttons</div>
                                <div class="form-group">
                                    <label class="form-label">Button Background Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="buttonBgColorPickerGrid">
                                            <?php
                                            $buttonBgOptions = ['#6366f1', '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                                            $currentButtonBg = $store['button_bg_color'] ?? '#6366f1';
                                            foreach ($buttonBgOptions as $color):
                                                $active = (strcasecmp($color, $currentButtonBg) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectButtonBgColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="buttonBgColorCustom" value="<?= htmlspecialchars($currentButtonBg) ?>" oninput="applyCustomColor('buttonBg', this.value)">
                                            <span class="custom-color-hex" id="buttonBgColorHex"><?= htmlspecialchars(strtoupper($currentButtonBg)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="button_bg_color" id="buttonBgColorInput" value="<?= htmlspecialchars($currentButtonBg) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Button Text Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="buttonTextColorPickerGrid">
                                            <?php
                                            $buttonTextOptions = ['#ffffff', '#111827', '#1f2937', '#000000', '#374151'];
                                            $currentButtonText = $store['button_text_color'] ?? '#ffffff';
                                            foreach ($buttonTextOptions as $color):
                                                $active = (strcasecmp($color, $currentButtonText) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectButtonTextColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="buttonTextColorCustom" value="<?= htmlspecialchars($currentButtonText) ?>" oninput="applyCustomColor('buttonText', this.value)">
                                            <span class="custom-color-hex" id="buttonTextColorHex"><?= htmlspecialchars(strtoupper($currentButtonText)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="button_text_color" id="buttonTextColorInput" value="<?= htmlspecialchars($currentButtonText) ?>">
                                </div>
                                <div class="preview-button" style="--button-bg-demo: <?= htmlspecialchars($store['button_bg_color'] ?? '#6366f1') ?>; --button-text-demo: <?= htmlspecialchars($store['button_text_color'] ?? '#ffffff') ?>;">
                                    Button Preview
                                </div>
                            </div>
                            
                            <!-- Divs / Sections -->
                            <div class="color-section">
                                <div class="color-section-title">Divs & Sections</div>
                                <div class="form-group">
                                    <label class="form-label">Div Background Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="divBgColorPickerGrid">
                                            <?php
                                            $divBgOptions = ['#f3f4f6', '#e5e7eb', '#f9fafb', '#ffffff', '#fef3c7', '#ecfdf5'];
                                            $currentDivBg = $store['div_bg_color'] ?? '#f3f4f6';
                                            foreach ($divBgOptions as $color):
                                                $active = (strcasecmp($color, $currentDivBg) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectDivBgColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="divBgColorCustom" value="<?= htmlspecialchars($currentDivBg) ?>" oninput="applyCustomColor('divBg', this.value)">
                                            <span class="custom-color-hex" id="divBgColorHex"><?= htmlspecialchars(strtoupper($currentDivBg)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="div_bg_color" id="divBgColorInput" value="<?= htmlspecialchars($currentDivBg) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Div Border Color</label>
                                    <div class="color-picker-row">
                                        <div class="color-picker-grid" id="divBorderColorPickerGrid">
                                            <?php
                                            $divBorderOptions = ['#e5e7eb', '#d1d5db', '#cbd5e1', '#e2e8f0'];
                                            $currentDivBorder = $store['div_border_color'] ?? '#e5e7eb';
                                            foreach ($divBorderOptions as $color):
                                                $active = (strcasecmp($color, $currentDivBorder) === 0) ? 'active' : '';
                                            ?>
                                                <div class="color-option <?= $active ?>" style="background: <?= $color ?>;" data-color="<?= $color ?>" onclick="selectDivBorderColor(this)"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <label class="custom-color-pick">
                                            <span>Custom</span>
                                            <input type="color" id="divBorderColorCustom" value="<?= htmlspecialchars($currentDivBorder) ?>" oninput="applyCustomColor('divBorder', this.value)">
                                            <span class="custom-color-hex" id="divBorderColorHex"><?= htmlspecialchars(strtoupper($currentDivBorder)) ?></span>
                                        </label>
                                    </div>
                                    <input type="hidden" name="div_border_color" id="divBorderColorInput" value="<?= htmlspecialchars($currentDivBorder) ?>">
                                </div>
                                <div class="preview-div" style="--div-bg-demo: <?= htmlspecialchars($store['div_bg_color'] ?? '#f3f4f6') ?>; --div-border-demo: <?= htmlspecialchars($store['div_border_color'] ?? '#e5e7eb') ?>;">
                                    <small>Section / Div Preview</small>
                                    <div style="margin-top: 8px;">This is how your content sections will look</div>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_store" class="btn btn-primary">Save All Color Settings</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($isPaidUser): ?>
                <!-- Products Section – only for paid users -->
                <div class="settings-group">
                    <div class="settings-group-header">
                        <div class="settings-group-title">Products</div>
                        <div class="settings-group-desc">Add, edit or remove products.</div>
                    </div>
                    <div class="settings-group-body">
                        <button class="btn btn-primary" style="margin-bottom: 20px;" onclick="openProductModal()">+ Add New Product</button>
                        <div style="overflow-x: auto;">
                            <table class="product-table">
                                <thead><tr><th>Image</th><th>Name</th><th>Price</th><th>Stock</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr><td colspan="7">No products yet. Click "Add New Product".</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $p): ?>
                                            <tr>
                                                <td><img src="<?= htmlspecialchars($p['image'] ?? 'https://placehold.co/50x50') ?>" class="product-image-thumb"></td>
                                                <td><?= htmlspecialchars($p['name']) ?></td>
                                                <td>$<?= number_format($p['price'], 2) ?></td>
                                                <td><?= $p['stock'] ?></td>
                                                <td><?= htmlspecialchars($p['category']) ?></td>
                                                <td><span class="badge <?= $p['status'] == 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= $p['status'] ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm" onclick="editProduct(<?= $p['id'] ?>)">Edit</button>
                                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this product?');">
                                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="delete_product" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Promotional Banners Section – only for paid users -->
                <div class="settings-group">
                    <div class="settings-group-header">
                        <div class="settings-group-title">Promotional Banners</div>
                        <div class="settings-group-desc">Manage hero sliders and campaign banners.</div>
                    </div>
                    <div class="settings-group-body">
                        <button class="btn btn-primary" style="margin-bottom: 20px;" onclick="openBannerModal()">+ Add New Banner</button>
                        <div id="bannersList">
                            <?php if (empty($banners)): ?>
                                <p>No banners yet. Click "Add New Banner".</p>
                            <?php else: ?>
                                <?php foreach ($banners as $b): ?>
                                    <div class="banner-item">
                                        <img src="<?= htmlspecialchars($b['image']) ?>" class="banner-image-preview">
                                        <div class="banner-info">
                                            <div><strong><?= htmlspecialchars($b['title'] ?? 'Untitled') ?></strong></div>
                                            <div><small><?= htmlspecialchars(substr($b['description'] ?? '', 0, 80)) ?></small></div>
                                            <div>Status: <span class="badge <?= $b['status'] == 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= $b['status'] ?></span></div>
                                        </div>
                                        <div class="banner-actions">
                                            <button class="btn btn-sm" onclick="editBanner(<?= $b['id'] ?>)">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this banner?');">
                                                <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                                <button type="submit" name="delete_banner" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Typography Customizer Section – only for paid users -->
                <div class="settings-group">
                    <div class="settings-group-header">
                        <div class="settings-group-title">Typography (Live Preview)</div>
                        <div class="settings-group-desc">Customize font sizes and colors for headings and paragraphs.</div>
                    </div>
                    <div class="settings-group-body">
                        <form method="POST" id="typographyForm">
                            <?php
                            $tags = ['h1','h2','h3','h4','h5','h6','p'];
                            foreach ($tags as $tag):
                                $size = $typography[$tag]['size'] ?? ($tag=='p' ? '16' : ($tag=='h1'?'36':($tag=='h2'?'30':($tag=='h3'?'24':($tag=='h4'?'20':($tag=='h5'?'18':'16'))))));
                                $color = $typography[$tag]['color'] ?? ($tag=='p' ? '#4b5563' : '#111827');
                            ?>
                            <div class="typography-row">
                                <label><?= strtoupper($tag) ?></label>
                                <input type="number" name="<?= $tag ?>_size" class="size-input form-input" value="<?= $size ?>" min="10" max="72" step="1">
                                <input type="color" name="<?= $tag ?>_color" class="color-input" value="<?= $color ?>">
                                <div class="preview-text" style="font-size: <?= $size ?>px; color: <?= $color ?>; margin:0; font-weight: <?= $tag=='p'?'normal':'bold' ?>;">
                                    <?= $tag == 'p' ? 'The quick brown fox jumps over the lazy dog.' : $tag . ' - Heading Example' ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <button type="submit" name="save_typography" class="btn btn-primary">Save Typography</button>
                        </form>
                        <div class="live-preview-box" id="livePreviewBox">
                            <h1 style="margin:0 0 10px 0;">Heading 1</h1>
                            <h2 style="margin:0 0 8px 0;">Heading 2</h2>
                            <h3>Heading 3</h3>
                            <p>Paragraph text with custom styling. This is how your store content will look.</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <!-- For free users, show upgrade notice for paid-only features -->
                    <div class="settings-group">
                        <div class="settings-group-header">
                            <div class="settings-group-title">Unlock Full Customization</div>
                            <div class="settings-group-desc">Upgrade to Growth, Scale, or Empire to manage Colors & Appearance, Products & Banners, and Typography.</div>
                        </div>
                        <div class="settings-group-body" style="text-align: center; padding: 40px;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🚀</div>
                            <h3 style="margin-bottom: 8px;">Upgrade Your Plan</h3>
                            <p style="margin-bottom: 24px; color: var(--text-muted);">Get full control over your store’s design, products, banners, and typography.</p>
                            <a href="subscription" class="btn btn-primary">View Plans →</a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Product</h3>
                <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
            </div>
            <form id="productForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="product_id">
                <input type="hidden" name="existing_image" id="existing_image">
                <div class="form-group"><label class="form-label">Product Name</label><input type="text" name="name" id="product_name" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Price</label><input type="number" step="0.01" name="price" id="product_price" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Stock</label><input type="number" name="stock" id="product_stock" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Category</label><select name="category" id="product_category" class="form-select"><option value="electronics">Electronics</option><option value="fashion">Fashion</option><option value="beauty">Beauty</option><option value="home">Home</option><option value="sports">Sports</option><option value="other">Other</option></select></div>
                <div class="form-group"><label class="form-label">Status</label><select name="status" id="product_status" class="form-select"><option value="active">Active</option><option value="draft">Draft</option></select></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="description" id="product_description" class="form-textarea" rows="3"></textarea></div>
                <div class="form-group"><label class="form-label">Product Image</label><input type="file" name="product_image" accept="image/*"><div id="currentImagePreview" style="margin-top:8px;"></div></div>
                <div class="form-group" style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeModal('productModal')">Cancel</button>
                    <button type="submit" name="add_product" id="submitBtn" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Banner Modal -->
    <div id="bannerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="bannerModalTitle">Add Banner</h3>
                <button class="modal-close" onclick="closeModal('bannerModal')">&times;</button>
            </div>
            <form id="bannerForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="banner_id" id="banner_id">
                <input type="hidden" name="existing_image" id="banner_existing_image">
                <div class="form-group"><label class="form-label">Title</label><input type="text" name="banner_title" id="banner_title" class="form-input"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea name="banner_description" id="banner_description" class="form-textarea" rows="3"></textarea></div>
                <div class="form-group"><label class="form-label">Banner Image *</label><input type="file" name="banner_image" accept="image/*"><div id="currentBannerPreview" style="margin-top:8px;"></div></div>
                <div class="form-group"><label class="form-label">Link</label><input type="url" name="banner_link" id="banner_link" class="form-input"></div>
                <div class="form-group"><label class="form-label">Status</label><select name="banner_status" id="banner_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="form-group" style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeModal('bannerModal')">Cancel</button>
                    <button type="submit" id="bannerSubmitBtn" class="btn btn-primary">Add Banner</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ==================== SIDEBAR, THEME, MODAL HELPERS ====================
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        
        function toggleMobileSidebar() {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                if (window.innerWidth <= 768) toggleMobileSidebar();
                else sidebar.classList.toggle('collapsed');
            });
        }
        
        if (mobileToggle) mobileToggle.addEventListener('click', toggleMobileSidebar);
        if (overlay) overlay.addEventListener('click', toggleMobileSidebar);
        
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const newTheme = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('RD Vendora-theme', newTheme);
            });
        }

        // Color picker functions (available to all subscribers with color access)
        <?php if ($canCustomizeColors): ?>
        function setColorField(gridId, inputId, customId, hexId, color, previewFn) {
            const grid = document.getElementById(gridId);
            if (grid) {
                grid.querySelectorAll('.color-option').forEach(c => {
                    c.classList.toggle('active', c.getAttribute('data-color').toLowerCase() === color.toLowerCase());
                });
            }
            const input = document.getElementById(inputId);
            if (input) input.value = color;
            const custom = document.getElementById(customId);
            if (custom) custom.value = color;
            const hex = document.getElementById(hexId);
            if (hex) hex.textContent = color.toUpperCase();
            if (typeof previewFn === 'function') previewFn(color);
        }
        function selectBrandColor(el) { setColorField('brandColorPickerGrid', 'brandColorInput', 'brandColorCustom', 'brandColorHex', el.getAttribute('data-color')); }
        function selectNavColor(el) { setColorField('navColorPickerGrid', 'navColorInput', 'navColorCustom', 'navColorHex', el.getAttribute('data-color')); }
        function selectBodyBgColor(el) { setColorField('bodyBgColorPickerGrid', 'bodyBgColorInput', 'bodyBgColorCustom', 'bodyBgColorHex', el.getAttribute('data-color')); }
        function selectFooterBgColor(el) { setColorField('footerBgColorPickerGrid', 'footerBgColorInput', 'footerBgColorCustom', 'footerBgColorHex', el.getAttribute('data-color')); }
        function selectCardBgColor(el) {
            const color = el.getAttribute('data-color');
            setColorField('cardBgColorPickerGrid', 'cardBgColorInput', 'cardBgColorCustom', 'cardBgColorHex', color, function(c) {
                const preview = document.querySelector('.preview-card');
                if (preview) preview.style.setProperty('--card-bg-demo', c);
            });
        }
        function selectCardBorderColor(el) {
            const color = el.getAttribute('data-color');
            setColorField('cardBorderColorPickerGrid', 'cardBorderColorInput', 'cardBorderColorCustom', 'cardBorderColorHex', color, function(c) {
                const preview = document.querySelector('.preview-card');
                if (preview) preview.style.setProperty('--card-border-demo', c);
            });
        }
        function selectButtonBgColor(el) {
            const color = el.getAttribute('data-color');
            setColorField('buttonBgColorPickerGrid', 'buttonBgColorInput', 'buttonBgColorCustom', 'buttonBgColorHex', color, function(c) {
                const preview = document.querySelector('.preview-button');
                if (preview) preview.style.setProperty('--button-bg-demo', c);
            });
        }
        function selectButtonTextColor(el) {
            const color = el.getAttribute('data-color');
            setColorField('buttonTextColorPickerGrid', 'buttonTextColorInput', 'buttonTextColorCustom', 'buttonTextColorHex', color, function(c) {
                const preview = document.querySelector('.preview-button');
                if (preview) preview.style.setProperty('--button-text-demo', c);
            });
        }
        function selectDivBgColor(el) {
            const color = el.getAttribute('data-color');
            setColorField('divBgColorPickerGrid', 'divBgColorInput', 'divBgColorCustom', 'divBgColorHex', color, function(c) {
                const preview = document.querySelector('.preview-div');
                if (preview) preview.style.setProperty('--div-bg-demo', c);
            });
        }
        function selectDivBorderColor(el) {
            const color = el.getAttribute('data-color');
            setColorField('divBorderColorPickerGrid', 'divBorderColorInput', 'divBorderColorCustom', 'divBorderColorHex', color, function(c) {
                const preview = document.querySelector('.preview-div');
                if (preview) preview.style.setProperty('--div-border-demo', c);
            });
        }
        function applyCustomColor(key, color) {
            const map = {
                brand: ['brandColorPickerGrid', 'brandColorInput', 'brandColorCustom', 'brandColorHex', null],
                nav: ['navColorPickerGrid', 'navColorInput', 'navColorCustom', 'navColorHex', null],
                bodyBg: ['bodyBgColorPickerGrid', 'bodyBgColorInput', 'bodyBgColorCustom', 'bodyBgColorHex', null],
                footerBg: ['footerBgColorPickerGrid', 'footerBgColorInput', 'footerBgColorCustom', 'footerBgColorHex', null],
                cardBg: ['cardBgColorPickerGrid', 'cardBgColorInput', 'cardBgColorCustom', 'cardBgColorHex', function(c) {
                    const preview = document.querySelector('.preview-card');
                    if (preview) preview.style.setProperty('--card-bg-demo', c);
                }],
                cardBorder: ['cardBorderColorPickerGrid', 'cardBorderColorInput', 'cardBorderColorCustom', 'cardBorderColorHex', function(c) {
                    const preview = document.querySelector('.preview-card');
                    if (preview) preview.style.setProperty('--card-border-demo', c);
                }],
                buttonBg: ['buttonBgColorPickerGrid', 'buttonBgColorInput', 'buttonBgColorCustom', 'buttonBgColorHex', function(c) {
                    const preview = document.querySelector('.preview-button');
                    if (preview) preview.style.setProperty('--button-bg-demo', c);
                }],
                buttonText: ['buttonTextColorPickerGrid', 'buttonTextColorInput', 'buttonTextColorCustom', 'buttonTextColorHex', function(c) {
                    const preview = document.querySelector('.preview-button');
                    if (preview) preview.style.setProperty('--button-text-demo', c);
                }],
                divBg: ['divBgColorPickerGrid', 'divBgColorInput', 'divBgColorCustom', 'divBgColorHex', function(c) {
                    const preview = document.querySelector('.preview-div');
                    if (preview) preview.style.setProperty('--div-bg-demo', c);
                }],
                divBorder: ['divBorderColorPickerGrid', 'divBorderColorInput', 'divBorderColorCustom', 'divBorderColorHex', function(c) {
                    const preview = document.querySelector('.preview-div');
                    if (preview) preview.style.setProperty('--div-border-demo', c);
                }]
            };
            const cfg = map[key];
            if (!cfg) return;
            setColorField(cfg[0], cfg[1], cfg[2], cfg[3], color, cfg[4]);
        }
        <?php endif; ?>

        // Typography live preview (only for paid users, but preview works regardless)
        const sizeInputs = document.querySelectorAll('.size-input');
        const colorInputs = document.querySelectorAll('.color-input');
        const previewBox = document.getElementById('livePreviewBox');
        
        function updateLivePreview() {
            if (!previewBox) return;
            const h1Size = document.querySelector('input[name="h1_size"]')?.value || '36';
            const h1Color = document.querySelector('input[name="h1_color"]')?.value || '#111827';
            const h2Size = document.querySelector('input[name="h2_size"]')?.value || '30';
            const h2Color = document.querySelector('input[name="h2_color"]')?.value || '#111827';
            const h3Size = document.querySelector('input[name="h3_size"]')?.value || '24';
            const h3Color = document.querySelector('input[name="h3_color"]')?.value || '#111827';
            const pSize = document.querySelector('input[name="p_size"]')?.value || '16';
            const pColor = document.querySelector('input[name="p_color"]')?.value || '#4b5563';
            const h1 = previewBox.querySelector('h1');
            const h2 = previewBox.querySelector('h2');
            const h3 = previewBox.querySelector('h3');
            const p = previewBox.querySelector('p');
            if (h1) { h1.style.fontSize = h1Size + 'px'; h1.style.color = h1Color; }
            if (h2) { h2.style.fontSize = h2Size + 'px'; h2.style.color = h2Color; }
            if (h3) { h3.style.fontSize = h3Size + 'px'; h3.style.color = h3Color; }
            if (p) { p.style.fontSize = pSize + 'px'; p.style.color = pColor; }
        }
        
        if (sizeInputs.length) {
            sizeInputs.forEach(input => input.addEventListener('input', updateLivePreview));
            colorInputs.forEach(input => input.addEventListener('input', updateLivePreview));
            updateLivePreview();
        }

        // Product modal (only allowed for paid users)
        function openProductModal(id = null) {
            <?php if (!$isPaidUser): ?>
                alert("Product management is available only for Growth, Scale, and Empire plans. Please upgrade.");
                return;
            <?php endif; ?>
            const form = document.getElementById('productForm');
            if (form) form.reset();
            document.getElementById('product_id').value = '';
            document.getElementById('existing_image').value = '';
            document.getElementById('currentImagePreview').innerHTML = '';
            const submitBtn = document.getElementById('submitBtn');
            
            if (id) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'get_product=1&product_id=' + id
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('modalTitle').innerText = 'Edit Product';
                    document.getElementById('product_id').value = data.id;
                    document.getElementById('product_name').value = data.name;
                    document.getElementById('product_price').value = data.price;
                    document.getElementById('product_stock').value = data.stock;
                    document.getElementById('product_category').value = data.category;
                    document.getElementById('product_status').value = data.status;
                    document.getElementById('product_description').value = data.description || '';
                    document.getElementById('existing_image').value = data.image || '';
                    if (data.image) document.getElementById('currentImagePreview').innerHTML = `<img src="${data.image}" style="max-width:100px; border-radius:8px;">`;
                    submitBtn.name = 'update_product';
                    document.getElementById('productModal').classList.add('active');
                })
                .catch(error => console.error('Error:', error));
            } else {
                document.getElementById('modalTitle').innerText = 'Add Product';
                submitBtn.name = 'add_product';
                document.getElementById('productModal').classList.add('active');
            }
        }
        
        function editProduct(id) { openProductModal(id); }
        
        // Banner modal (only allowed for paid users)
        function openBannerModal(id = null) {
            <?php if (!$isPaidUser): ?>
                alert("Banner management is available only for Growth, Scale, and Empire plans. Please upgrade.");
                return;
            <?php endif; ?>
            const form = document.getElementById('bannerForm');
            if (form) form.reset();
            document.getElementById('banner_id').value = '';
            document.getElementById('banner_existing_image').value = '';
            document.getElementById('currentBannerPreview').innerHTML = '';
            const submitBtn = document.getElementById('bannerSubmitBtn');
            
            if (id) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'get_banner=1&banner_id=' + id
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('bannerModalTitle').innerText = 'Edit Banner';
                    document.getElementById('banner_id').value = data.id;
                    document.getElementById('banner_title').value = data.title || '';
                    document.getElementById('banner_description').value = data.description || '';
                    document.getElementById('banner_link').value = data.link || '';
                    document.getElementById('banner_status').value = data.status;
                    document.getElementById('banner_existing_image').value = data.image || '';
                    if (data.image) document.getElementById('currentBannerPreview').innerHTML = `<img src="${data.image}" style="max-width:100px; border-radius:8px;">`;
                    submitBtn.name = 'update_banner';
                    submitBtn.innerText = 'Update Banner';
                    document.getElementById('bannerModal').classList.add('active');
                })
                .catch(error => console.error('Error:', error));
            } else {
                document.getElementById('bannerModalTitle').innerText = 'Add Banner';
                submitBtn.name = 'add_banner';
                submitBtn.innerText = 'Add Banner';
                document.getElementById('bannerModal').classList.add('active');
            }
        }
        
        function editBanner(id) { openBannerModal(id); }
        
        function closeModal(modalId) { 
            document.getElementById(modalId).classList.remove('active'); 
        }
        
        window.onclick = function(event) { 
            if (event.target.classList.contains('modal')) event.target.classList.remove('active'); 
        };
        
        function handleLogout() { 
            if(confirm('Logout?')) window.location.href='logout'; 
        }
        
        // Dropdown toggle
        document.addEventListener('click', (e) => {
            const userDD = document.getElementById('userDropdown');
            if (userDD && !userDD.contains(e.target)) userDD.classList.remove('open');
            else if (userDD && e.target.closest('.dropdown-trigger')) userDD.classList.toggle('open');
        });

        (function initStoreUrlTools() {
            const copyBtn = document.getElementById('copyStoreUrlBtn');
            const editBtn = document.getElementById('editStoreSlugBtn');
            const editor = document.getElementById('storeSlugEditor');
            const input = document.getElementById('storeSlugInput');
            const status = document.getElementById('storeSlugStatus');
            const link = document.getElementById('storePublicUrlLink');
            const openBtn = document.getElementById('openStoreUrlBtn');
            let timer = null;
            if (copyBtn && link) {
                copyBtn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(link.href);
                        copyBtn.textContent = 'Copied!';
                        setTimeout(() => { copyBtn.textContent = 'Copy URL'; }, 1500);
                    } catch (e) {
                        prompt('Copy this store URL:', link.href);
                    }
                });
            }
            if (editBtn && editor) {
                editBtn.addEventListener('click', () => {
                    editor.style.display = editor.style.display === 'none' ? 'block' : 'none';
                    if (editor.style.display === 'block' && input) input.focus();
                });
            }
            if (input && status) {
                const check = () => {
                    const slug = input.value.trim().toLowerCase();
                    status.style.color = 'var(--text-muted)';
                    status.textContent = 'Checking…';
                    fetch('check-store-slug?slug=' + encodeURIComponent(slug), { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            status.textContent = data.message || '';
                            status.style.color = data.ok ? '#059669' : '#dc2626';
                            if (data.ok && data.url && link) {
                                link.href = data.url;
                                link.textContent = data.url;
                                if (openBtn) openBtn.href = data.url;
                            }
                        })
                        .catch(() => {
                            status.textContent = 'Could not validate store URL';
                            status.style.color = '#dc2626';
                        });
                };
                input.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(check, 350);
                });
            }
        })();
    </script>
</body>
</html>