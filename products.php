<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';
require_once 'includes/notification_helper.php';  // <-- ADDED

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Ensure store exists (only once) ----------
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header("Location: create-store.php");
    exit();
}
$stmt->close();

// ---------- Check if store is active (not disabled by admin) ----------
if (!isStoreActive($conn, $_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Store Disabled</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⛔ Store Disabled</h1>
        <p>Your store has been disabled by the administrator. Please contact support for more information.</p>
        <a href="logout.php">Logout</a>
    </body>
    </html>
    <?php
    exit();
}

// ---------- Subscription check ----------
$hasSubscription = hasActiveSubscription($conn, $_SESSION['user_id']);

$activePlan = null;
$productLimit = 0;
if ($hasSubscription) {
    $stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $planRow = $stmt->get_result()->fetch_assoc();
    $activePlan = $planRow['plan'] ?? null;
    $stmt->close();
    
    switch ($activePlan) {
        case 'Launch':  $productLimit = 2; break;
        case 'Growth':  $productLimit = 10; break;
        case 'Scale':   $productLimit = 50; break;
        case 'Empire':  $productLimit = PHP_INT_MAX; break;
        default:
            $productLimit = 0;
            $hasSubscription = false;
            $activePlan = null;
    }
} else {
    $productLimit = 0;
}

// ---------- Get user's display name ----------
if (!isset($_SESSION['fullname'])) {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['fullname'] = $result->fetch_assoc()['fullname'] ?? 'User';
    $stmt->close();
}
if (!isset($_SESSION['store_name'])) {
    $stmt = $conn->prepare("SELECT store_name FROM stores WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['store_name'] = $result->fetch_assoc()['store_name'] ?? null;
    $stmt->close();
}

// ---------- Helper functions ----------
function saveBase64Image($base64, $userId) {
    if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
        $data = substr($base64, strpos($base64, ',') + 1);
        $type = strtolower($type[1]);
        if (!in_array($type, ['jpg','jpeg','png','gif'])) return null;
        $data = base64_decode($data);
        $filename = "uploads/products/{$userId}_" . uniqid() . ".{$type}";
        if (!is_dir('uploads/products')) mkdir('uploads/products', 0777, true);
        file_put_contents($filename, $data);
        return $filename;
    }
    return null;
}

// ---------- Get current product count ----------
$currentProductCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM products WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$countResult = $stmt->get_result();
$currentProductCount = $countResult->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// ---------- Handle POST (only if subscription active and product limit not reached) ----------
$message = '';
$messageType = '';
$canAddMore = ($hasSubscription && $currentProductCount < $productLimit);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$hasSubscription) {
        $message = "Your subscription is inactive. Please reactivate to manage products.";
        $messageType = "error";
    } elseif (isset($_POST['add_product']) && !$canAddMore && $productLimit != PHP_INT_MAX) {
        $message = "Product limit reached for your plan. Maximum allowed: " . ($productLimit == PHP_INT_MAX ? "unlimited" : $productLimit);
        $messageType = "error";
    } else {
        // Add product
        if (isset($_POST['add_product'])) {
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $category = $_POST['category'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $description = trim($_POST['description']);
            $imageData = $_POST['image_data'] ?? '';
            $imagePath = $imageData ? saveBase64Image($imageData, $_SESSION['user_id']) : null;
    
            $stmt = $conn->prepare("INSERT INTO products (user_id, name, price, stock, category, status, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdissss", $_SESSION['user_id'], $name, $price, $stock, $category, $status, $description, $imagePath);
            if ($stmt->execute()) {
                $productId = $stmt->insert_id;
                $message = "Product added successfully!";
                $messageType = "success";
                $currentProductCount++;
                $canAddMore = ($currentProductCount < $productLimit);
                
                // ========== NOTIFICATION: New Product ==========
                $title = "New Product Added";
                $msg = "Product \"$name\" has been added to your store.";
                $link = "products.php?edit=$productId";
                createNotification($_SESSION['user_id'], 'product', $title, $msg, $link);
                // Also notify all admins/editors
                $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = (SELECT id FROM stores WHERE user_id = ?) AND role IN ('admin','editor')");
                $teamQuery->bind_param("i", $_SESSION['user_id']);
                $teamQuery->execute();
                $teamResult = $teamQuery->get_result();
                while ($team = $teamResult->fetch_assoc()) {
                    if ($team['user_id'] != $_SESSION['user_id']) {
                        createNotification($team['user_id'], 'product', $title, $msg, $link);
                    }
                }
                $teamQuery->close();
                // ================================================

                header("Location: products.php");
                exit();
            } else {
                $message = "Error adding product.";
                $messageType = "error";
            }
            $stmt->close();
        }
    
        // Update product
        if (isset($_POST['update_product'])) {
            $id = intval($_POST['product_id']);
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $category = $_POST['category'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $description = trim($_POST['description']);
            $imageData = $_POST['image_data'] ?? '';
            $existingImage = $_POST['existing_image'] ?? '';
    
            // Verify ownership and get old stock value (for low stock alert)
            $check = $conn->prepare("SELECT id, stock FROM products WHERE id = ? AND user_id = ?");
            $check->bind_param("ii", $id, $_SESSION['user_id']);
            $check->execute();
            $productData = $check->get_result()->fetch_assoc();
            if (!$productData) {
                $message = "You cannot edit this product.";
                $messageType = "error";
            } else {
                $oldStock = $productData['stock'];
                $imagePath = $existingImage;
                if ($imageData) {
                    $newPath = saveBase64Image($imageData, $_SESSION['user_id']);
                    if ($newPath) $imagePath = $newPath;
                }
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category=?, status=?, description=?, image=? WHERE id=? AND user_id=?");
                $stmt->bind_param("sdissssii", $name, $price, $stock, $category, $status, $description, $imagePath, $id, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $message = "Product updated!";
                    $messageType = "success";

                    // ========== NOTIFICATION: Product Updated ==========
                    $title = "Product Updated";
                    $msg = "Product \"$name\" has been updated.";
                    $link = "products.php?edit=$id";
                    createNotification($_SESSION['user_id'], 'product', $title, $msg, $link);
                    // Notify team
                    $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = (SELECT id FROM stores WHERE user_id = ?) AND role IN ('admin','editor')");
                    $teamQuery->bind_param("i", $_SESSION['user_id']);
                    $teamQuery->execute();
                    $teamResult = $teamQuery->get_result();
                    while ($team = $teamResult->fetch_assoc()) {
                        if ($team['user_id'] != $_SESSION['user_id']) {
                            createNotification($team['user_id'], 'product', $title, $msg, $link);
                        }
                    }
                    $teamQuery->close();
                    // ====================================================

                    // ========== NOTIFICATION: Low Stock (if stock dropped to ≤5 and was >5) ==========
                    if ($stock <= 5 && $oldStock > 5) {
                        $lowTitle = "Low Stock Alert";
                        $lowMsg = "Product \"$name\" is low on stock (only $stock left).";
                        $lowLink = "products.php?edit=$id";
                        createNotification($_SESSION['user_id'], 'stock', $lowTitle, $lowMsg, $lowLink);
                        // Notify team
                        $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = (SELECT id FROM stores WHERE user_id = ?) AND role IN ('admin','editor')");
                        $teamQuery->bind_param("i", $_SESSION['user_id']);
                        $teamQuery->execute();
                        $teamResult = $teamQuery->get_result();
                        while ($team = $teamResult->fetch_assoc()) {
                            if ($team['user_id'] != $_SESSION['user_id']) {
                                createNotification($team['user_id'], 'stock', $lowTitle, $lowMsg, $lowLink);
                            }
                        }
                        $teamQuery->close();
                    }
                    // ====================================================

                } else {
                    $message = "Update failed.";
                    $messageType = "error";
                }
                $stmt->close();
            }
            $check->close();
        }
    
        // Delete product
        if (isset($_GET['delete'])) {
            $id = intval($_GET['delete']);
            // Get product name before deletion
            $nameQuery = $conn->prepare("SELECT name FROM products WHERE id = ? AND user_id = ?");
            $nameQuery->bind_param("ii", $id, $_SESSION['user_id']);
            $nameQuery->execute();
            $productName = $nameQuery->get_result()->fetch_assoc()['name'] ?? 'Unknown';
            $nameQuery->close();

            $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $_SESSION['user_id']);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Product deleted.";
                $messageType = "success";

                // ========== NOTIFICATION: Product Deleted ==========
                $title = "Product Deleted";
                $msg = "Product \"$productName\" has been removed from your store.";
                $link = "products.php";
                createNotification($_SESSION['user_id'], 'product', $title, $msg, $link);
                // Notify team
                $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = (SELECT id FROM stores WHERE user_id = ?) AND role IN ('admin','editor')");
                $teamQuery->bind_param("i", $_SESSION['user_id']);
                $teamQuery->execute();
                $teamResult = $teamQuery->get_result();
                while ($team = $teamResult->fetch_assoc()) {
                    if ($team['user_id'] != $_SESSION['user_id']) {
                        createNotification($team['user_id'], 'product', $title, $msg, $link);
                    }
                }
                $teamQuery->close();
                // ====================================================

                header("Location: products.php");
                exit();
            } else {
                $message = "Cannot delete this product.";
                $messageType = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch products for this user only
$products = [];
$stmt = $conn->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
$conn->close();

$remainingSlots = ($productLimit == PHP_INT_MAX) ? 'Unlimited' : max(0, $productLimit - $currentProductCount);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* ============================================================
           FULL DASHBOARD STYLES (same as your original dashboard.php)
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
            --gradient-hero: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
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
            --leading-tight: 1.25;
            --leading-snug: 1.375;
            --leading-normal: 1.5;
            --leading-relaxed: 1.625;
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
            --shadow-glow: 0 0 40px rgba(99,102,241,0.15);
            --transition-fast: 150ms cubic-bezier(0.4,0,0.2,1);
            --transition-base: 250ms cubic-bezier(0.4,0,0.2,1);
            --transition-slow: 350ms cubic-bezier(0.4,0,0.2,1);
            --transition-bounce: 500ms cubic-bezier(0.34,1.56,0.64,1);
            --z-dropdown: 100;
            --z-sticky: 200;
            --z-fixed: 300;
            --z-modal-backdrop: 400;
            --z-modal: 500;
            --z-toast: 800;
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
            --shadow-glow: 0 0 40px rgba(99,102,241,0.20);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: var(--font-sans);
            font-size: var(--text-base);
            line-height: var(--leading-normal);
            color: var(--text-primary);
            background: var(--bg-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background var(--transition-base), color var(--transition-base);
        }

        a {
            color: inherit;
            text-decoration: none;
        }
        button {
            cursor: pointer;
            border: none;
            background: none;
            font-family: inherit;
            font-size: inherit;
            color: inherit;
        }
        input,
        select {
            font-family: inherit;
            font-size: inherit;
            color: inherit;
        }
        ul,
        ol {
            list-style: none;
        }
        img {
            max-width: 100%;
            display: block;
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-secondary);
            border-radius: var(--radius-full);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        ::selection {
            background: var(--primary-light);
            color: var(--primary);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex;
            flex-direction: column;
            z-index: var(--z-fixed);
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
            flex-shrink: 0;
            gap: var(--space-3);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-weight: var(--font-bold);
            font-size: var(--text-lg);
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            flex-shrink: 0;
        }

                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
        .sidebar-brand-icon {
            width: 34px;
            height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            transition: opacity var(--transition-fast);
        }
        .sidebar.collapsed .sidebar-brand-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-toggle {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            transition: all var(--transition-fast);
            flex-shrink: 0;
            background: transparent;
            border: none;
            cursor: pointer;
        }
        .sidebar-toggle:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .sidebar.collapsed .sidebar-toggle svg {
            transform: rotate(180deg);
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: var(--space-3) var(--space-3);
        }

        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px;
            font-weight: var(--font-semibold);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            white-space: nowrap;
            transition: all var(--transition-fast);
            margin-top: var(--space-2);
        }
        .sidebar.collapsed .sidebar-section-title {
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            transition: all var(--transition-fast);
            position: relative;
            white-space: nowrap;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 1px;
        }
        .sidebar-link:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .sidebar-link.active {
            background: var(--primary-light);
            color: var(--primary);
            font-weight: var(--font-semibold);
        }
        .sidebar-link svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }
        .sidebar-link-text {
            transition: opacity var(--transition-fast);
        }
        .sidebar.collapsed .sidebar-link-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-footer {
            padding: var(--space-3);
            border-top: 1px solid var(--border-primary);
            flex-shrink: 0;
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md);
            transition: background var(--transition-fast);
            cursor: pointer;
        }
        .sidebar-user:hover {
            background: var(--bg-hover);
        }
        .sidebar-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-full);
            object-fit: cover;
            flex-shrink: 0;
        }
        .sidebar-user-info {
            flex: 1;
            min-width: 0;
            transition: opacity var(--transition-fast);
        }
        .sidebar.collapsed .sidebar-user-info {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        .sidebar-user-name {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user-role {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 2px;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: calc(var(--z-fixed) - 1);
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-slow);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed~.main-content {
            margin-left: var(--sidebar-collapsed);
        }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--space-6);
            z-index: var(--z-sticky);
            gap: var(--space-4);
            backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar {
            background: rgba(255,255,255,0.85);
        }
        [data-theme="dark"] .topbar {
            background: rgba(20,22,31,0.85);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .mobile-sidebar-toggle {
            display: none;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            flex-shrink: 0;
        }
        .mobile-sidebar-toggle:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .topbar-search {
            flex: 1;
            max-width: 420px;
            position: relative;
        }
        .topbar-search svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 16px;
            height: 16px;
            pointer-events: none;
        }
        .topbar-search input {
            width: 100%;
            padding: var(--space-2) var(--space-4) var(--space-2) 40px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            outline: none;
            transition: all var(--transition-fast);
            color: var(--text-primary);
        }
        .topbar-search input::placeholder {
            color: var(--text-muted);
        }
        .topbar-search input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            background: var(--bg-secondary);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .topbar-btn {
            position: relative;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            flex-shrink: 0;
        }
        .topbar-btn:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .topbar-btn-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--error);
            border-radius: var(--radius-full);
            border: 2px solid var(--bg-secondary);
        }

        .theme-toggle {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            flex-shrink: 0;
        }
        .theme-toggle:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .theme-toggle .icon-sun,
        .theme-toggle .icon-moon {
            transition: all var(--transition-base);
        }
        [data-theme="light"] .theme-toggle .icon-moon {
            display: none;
        }
        [data-theme="dark"] .theme-toggle .icon-sun {
            display: none;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-1) var(--space-3) var(--space-1) var(--space-1);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: background var(--transition-fast);
        }
        .topbar-user:hover {
            background: var(--bg-hover);
        }
        .topbar-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-full);
            object-fit: cover;
            flex-shrink: 0;
        }
        .topbar-user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .topbar-user-name {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--text-primary);
        }
        .topbar-user-role {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .dropdown {
            position: relative;
        }
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 240px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: var(--z-dropdown);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-8px);
            transition: all var(--transition-fast);
            overflow: hidden;
        }
        .dropdown.open .dropdown-menu {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
        }
        .dropdown-header {
            padding: var(--space-4);
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dropdown-header h4 {
            font-size: var(--text-sm);
            font-weight: var(--font-semibold);
        }
        .dropdown-header a {
            font-size: var(--text-xs);
            color: var(--primary);
            cursor: pointer;
        }
        .dropdown-header a:hover {
            text-decoration: underline;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-sm);
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            cursor: pointer;
            text-decoration: none;
        }
        .dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .dropdown-item svg {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
        }
        .dropdown-divider {
            height: 1px;
            background: var(--border-primary);
            margin: var(--space-1) 0;
        }
        .notification-list {
            max-height: 320px;
            overflow-y: auto;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            cursor: pointer;
            transition: background var(--transition-fast);
            border-bottom: 1px solid var(--border-primary);
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item:hover {
            background: var(--bg-hover);
        }
        .notification-item.unread {
            background: var(--primary-light);
        }
        .notification-dot {
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: var(--radius-full);
            flex-shrink: 0;
            margin-top: 6px;
        }
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        .notification-title {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--text-primary);
        }
        .notification-text {
            font-size: var(--text-xs);
            color: var(--text-secondary);
            margin-top: 2px;
        }
        .notification-time {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Page content */
        .page-content {
            flex: 1;
            padding: var(--space-6);
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: var(--space-6);
        }
        .page-title {
            font-size: var(--text-2xl);
            font-weight: var(--font-bold);
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }
        .page-subtitle {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            margin-top: var(--space-1);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-5);
            margin-bottom: var(--space-6);
        }
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--border-secondary);
            transform: translateY(-1px);
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 4px 0 0 4px;
        }
        .stat-card.purple::after { background: var(--primary); }
        .stat-card.green::after { background: var(--success); }
        .stat-card.amber::after { background: var(--warning); }
        .stat-card.blue::after { background: var(--info); }
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-3);
        }
        .stat-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-lg);
        }
        .stat-icon.purple { background: var(--primary-light); color: var(--primary); }
        .stat-icon.green { background: var(--success-light); color: var(--success-dark); }
        .stat-icon.amber { background: var(--warning-light); color: var(--warning-dark); }
        .stat-icon.blue { background: var(--info-light); color: var(--info-dark); }
        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            padding: 3px 8px;
            border-radius: var(--radius-sm);
        }
        .stat-trend.up { background: var(--success-light); color: var(--success-dark); }
        .stat-trend.down { background: var(--error-light); color: var(--error-dark); }
        .stat-value {
            font-size: var(--text-3xl);
            font-weight: var(--font-bold);
            color: var(--text-primary);
            margin-bottom: 2px;
            letter-spacing: -0.01em;
        }
        .stat-label {
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }

        /* Filters Bar */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }
        .filters-left {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-3);
            align-items: center;
        }
        .search-box {
            position: relative;
            width: 260px;
        }
        .search-box svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 16px;
            height: 16px;
        }
        .search-box input {
            width: 100%;
            padding: var(--space-2) var(--space-3) var(--space-2) 36px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            color: var(--text-primary);
        }
        .filter-select {
            padding: var(--space-2) var(--space-3);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            color: var(--text-primary);
            cursor: pointer;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            font-size: var(--text-sm);
            font-weight: var(--font-semibold);
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: var(--gradient-primary);
            color: var(--text-inverse);
            border: none;
            box-shadow: 0 2px 8px rgba(99,102,241,0.25);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(99,102,241,0.35);
        }
        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-primary);
        }
        .btn-ghost:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .btn-sm {
            padding: var(--space-1) var(--space-3);
            font-size: var(--text-xs);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-5);
        }
        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-3);
            }
        }
        .product-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-base);
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-secondary);
        }
        .product-image {
            aspect-ratio: 1;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-info {
            padding: var(--space-4);
        }
        .product-title {
            font-size: var(--text-base);
            font-weight: var(--font-semibold);
            color: var(--text-primary);
            margin-bottom: var(--space-1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-category {
            font-size: var(--text-xs);
            color: var(--text-muted);
            text-transform: capitalize;
            margin-bottom: var(--space-2);
        }
        .product-price {
            font-size: var(--text-xl);
            font-weight: var(--font-bold);
            color: var(--primary);
            margin: var(--space-2) 0;
        }
        .product-stock {
            font-size: var(--text-sm);
            margin-bottom: var(--space-3);
            color: var(--text-secondary);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: var(--font-semibold);
            border-radius: var(--radius-full);
            white-space: nowrap;
        }
        .badge-success {
            background: var(--success-light);
            color: var(--success-dark);
        }
        .badge-warning {
            background: var(--warning-light);
            color: var(--warning-dark);
        }
        .product-actions {
            display: flex;
            gap: var(--space-2);
            justify-content: flex-end;
            border-top: 1px solid var(--border-primary);
            padding: var(--space-3) var(--space-4);
        }
        .icon-btn {
            padding: 6px;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .icon-btn:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: var(--z-modal-backdrop);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-6);
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-base);
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-primary);
            width: 100%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95) translateY(20px);
            transition: transform var(--transition-bounce);
            box-shadow: var(--shadow-xl);
        }
        .modal-overlay.active .modal {
            transform: scale(1) translateY(0);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-5) var(--space-6);
            border-bottom: 1px solid var(--border-primary);
        }
        .modal-title {
            font-size: var(--text-lg);
            font-weight: var(--font-bold);
        }
        .modal-close {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            transition: all var(--transition-fast);
            cursor: pointer;
            border: none;
            background: transparent;
        }
        .modal-close:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        .modal-body {
            padding: var(--space-6);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-6);
            border-top: 1px solid var(--border-primary);
        }
        .form-group {
            margin-bottom: var(--space-4);
        }
        .form-label {
            display: block;
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--text-primary);
            margin-bottom: var(--space-2);
        }
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-sm);
            color: var(--text-primary);
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            outline: none;
        }
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        .image-upload {
            border: 2px dashed var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            text-align: center;
            cursor: pointer;
            margin-bottom: var(--space-4);
            transition: all var(--transition-fast);
        }
        .image-upload:hover {
            border-color: var(--primary);
            background: var(--bg-hover);
        }
        .image-preview {
            position: relative;
            margin-bottom: var(--space-4);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .image-preview img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
        }
        .remove-image {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0,0,0,0.6);
            border-radius: var(--radius-full);
            padding: 6px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .remove-image:hover {
            background: rgba(0,0,0,0.8);
        }
        .hidden {
            display: none;
        }

        /* Limit info bar (new) */
        .limit-info {
            background: var(--bg-tertiary);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-6);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-3);
        }
        .limit-progress {
            flex: 1;
            height: 8px;
            background: var(--border-primary);
            border-radius: var(--radius-full);
            overflow: hidden;
        }
        .limit-progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            width: 0%;
            border-radius: var(--radius-full);
            transition: width 0.3s;
        }
        .limit-text {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
        }
        .limit-warning {
            color: var(--warning-dark);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--space-12);
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            grid-column: 1 / -1;
        }
        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-4);
            background: var(--bg-tertiary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }
        .empty-state-title {
            font-size: var(--text-xl);
            font-weight: var(--font-semibold);
            margin-bottom: var(--space-2);
        }
        .empty-state-text {
            color: var(--text-secondary);
            margin-bottom: var(--space-6);
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: calc(var(--topbar-height) + var(--space-4));
            right: var(--space-4);
            z-index: var(--z-toast);
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        .toast {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-xl);
            min-width: 300px;
            max-width: 420px;
            transform: translateX(120%);
            animation: toastSlideIn 0.4s ease forwards;
            font-size: var(--text-sm);
        }
        .toast.removing {
            animation: toastSlideOut 0.3s ease forwards;
        }
        .toast-icon {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .toast-success .toast-icon { background: var(--success-light); color: var(--success); }
        .toast-error .toast-icon { background: var(--error-light); color: var(--error); }
        .toast-info .toast-icon { background: var(--info-light); color: var(--info); }
        .toast-content { flex: 1; }
        .toast-title { font-weight: var(--font-semibold); color: var(--text-primary); }
        .toast-message { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }

        @keyframes toastSlideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastSlideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(120%); opacity: 0; }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
                z-index: var(--z-fixed);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: var(--shadow-xl);
            }
            .sidebar.collapsed {
                width: var(--sidebar-width);
                transform: translateX(-100%);
            }
            .sidebar.collapsed.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
            }
            .mobile-sidebar-toggle {
                display: flex;
            }
            .topbar-search {
                max-width: 200px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-3);
            }
            .filters-left {
                width: 100%;
                flex-wrap: wrap;
            }
            .search-box {
                flex: 1;
            }
            .page-content {
                padding: var(--space-4);
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .topbar-search {
                display: none;
            }
            .topbar {
                padding: 0 var(--space-3);
            }
            .page-title {
                font-size: var(--text-xl);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6" />
                </svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" />
                </svg>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="products.php" class="sidebar-link active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                    <line x1="3" y1="6" x2="21" y2="6" /><path d="M16 10a4 4 0 0 1-8 0" />
                </svg>
                <span class="sidebar-link-text">Products</span>
            </a>
            <a href="orders.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                </svg>
                <span class="sidebar-link-text">Orders</span>
            </a>
            <a href="customers.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                <span class="sidebar-link-text">Customers</span>
            </a>
            <div class="sidebar-section-title">Store</div>
            <a href="storefront.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
                <span class="sidebar-link-text">Storefront</span>
            </a>
            <a href="settings.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                </svg>
                <span class="sidebar-link-text">Store Settings</span>
            </a>
            <a href="subscription.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
                <span class="sidebar-link-text">Subscription</span>
            </a>
            <a href="vendor-chat.php" class="sidebar-link"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span class="sidebar-link-text">Chat</span></a>
            <div class="sidebar-section-title">Account</div>
            <a href="profile.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                </svg>
                <span class="sidebar-link-text">Profile</span>
            </a>
            <a href="#" class="sidebar-link" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <span class="sidebar-link-text">Logout</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="sidebar-user-avatar">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                    <div class="sidebar-user-role">
                        <?php if ($_SESSION['store_name']): ?>
                            🏪 <?= htmlspecialchars($_SESSION['store_name']) ?>
                        <?php else: ?>
                            <a href="create-store.php" style="color: var(--primary);">Create Store</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" />
                    </svg>
                </button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" id="globalSearch" placeholder="Search products...">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5" /><line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" />
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                        <line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" />
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                    </svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                    </svg>
                </button>

                <!-- Notification Bell -->
                <div class="dropdown" id="notificationDropdown">
                    <button class="topbar-btn dropdown-trigger" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                        <span class="topbar-btn-badge"></span>
                    </button>
                    <div class="dropdown-menu" style="width:340px;">
                        <div class="dropdown-header">
                            <h4>Notifications</h4>
                            <a onclick="markAllRead()">Mark all read</a>
                        </div>
                        <div class="notification-list" id="notificationList"></div>
                    </div>
                </div>

                <!-- User Dropdown -->
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role">
                                <?php if ($_SESSION['store_name']): ?>
                                    Store: <?= htmlspecialchars($_SESSION['store_name']) ?>
                                <?php else: ?>
                                    <a href="create-store.php" style="color: var(--primary);">Create Store</a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Profile
                        </a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings page coming soon')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <h1 class="page-title">Products</h1>
                <p class="page-subtitle">Manage your product catalog</p>
            </div>

            <?php if ($message): ?>
                <div class="toast toast-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <!-- SUSPENSION WARNING BANNER (only if no subscription) -->
            <?php if (!$hasSubscription): ?>
                <div style="background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%); border-left: 4px solid #dc2626; border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="width: 48px; height: 48px; background: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">⚠️</div>
                        <div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #991b1b;">Store Suspended</h3>
                            <p style="color: #7f1d1d; margin-top: 4px;">Your store has been suspended because there is no active subscription. You cannot add, edit, or delete products. Please choose a plan to reactivate your store.</p>
                        </div>
                    </div>
                    <a href="subscription.php" class="btn" style="background: #dc2626; color: white; padding: 10px 24px; border-radius: 40px; text-decoration: none; font-weight: 600;">Reactivate Now →</a>
                </div>
            <?php endif; ?>

            <!-- Product Limit Info Bar (only for active subscribers) -->
            <?php if ($hasSubscription && $productLimit > 0): ?>
                <div class="limit-info">
                    <div style="flex: 1;">
                        <div class="limit-text">
                            <strong>Plan: <?= htmlspecialchars($activePlan) ?></strong> · 
                            <?php if ($productLimit == PHP_INT_MAX): ?>
                                Unlimited products
                            <?php else: ?>
                                <?= $currentProductCount ?> / <?= $productLimit ?> products used
                            <?php endif; ?>
                        </div>
                        <?php if ($productLimit != PHP_INT_MAX): ?>
                            <div class="limit-progress" style="margin-top: 8px;">
                                <div class="limit-progress-fill" style="width: <?= min(100, ($currentProductCount / $productLimit) * 100) ?>%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="limit-text">
                        <?php if ($productLimit != PHP_INT_MAX): ?>
                            <?php if ($remainingSlots > 0): ?>
                                ✅ <?= $remainingSlots ?> slot(s) remaining
                            <?php else: ?>
                                <span class="limit-warning">⚠️ Limit reached – upgrade to add more</span>
                            <?php endif; ?>
                        <?php else: ?>
                            ♾️ Unlimited slots
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-icon purple">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= count($products) ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-icon green">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['status'] === 'active')) ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card amber">
                    <div class="stat-header">
                        <div class="stat-icon amber">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['stock'] <= 5)) ?></div>
                    <div class="stat-label">Low Stock</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-icon blue">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        </div>
                    </div>
                    <div class="stat-value"><?= count(array_unique(array_column($products, 'category'))) ?></div>
                    <div class="stat-label">Categories</div>
                </div>
            </div>

            <!-- Filters and Add Button -->
            <div class="filters-bar">
                <div class="filters-left">
                    <div class="search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="productSearch" placeholder="Search products...">
                    </div>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="electronics">Electronics</option>
                        <option value="fashion">Fashion</option>
                        <option value="beauty">Beauty</option>
                        <option value="home">Home</option>
                        <option value="sports">Sports</option>
                    </select>
                    <select class="filter-select" id="stockFilter">
                        <option value="">All Stock</option>
                        <option value="in">In Stock</option>
                        <option value="low">Low Stock</option>
                    </select>
                </div>
                <button class="btn btn-primary" id="addProductBtn" onclick="openProductModal()" <?= (!$hasSubscription || (!$canAddMore && $productLimit != PHP_INT_MAX)) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Product
                </button>
            </div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        </div>
                        <h3 class="empty-state-title">No products found</h3>
                        <p class="empty-state-text">Click "Add Product" to start your catalog.</p>
                        <button class="btn btn-primary" onclick="openProductModal()" <?= (!$hasSubscription || (!$canAddMore && $productLimit != PHP_INT_MAX)) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>Add Product</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <div class="product-card" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" data-category="<?= $p['category'] ?>" data-stock="<?= $p['stock'] ?>">
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($p['image'] ?? 'https://placehold.co/400x400?text=No+Image') ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                        </div>
                        <div class="product-info">
                            <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="product-category"><?= htmlspecialchars($p['category']) ?></div>
                            <div class="product-price">₦ <?= number_format($p['price'], 2) ?></div>
                            <div class="product-stock">Stock: <?= $p['stock'] ?></div>
                            <div class="badge <?= $p['status'] === 'active' ? 'badge-success' : 'badge-warning' ?>"><?= $p['status'] ?></div>
                        </div>
                        <div class="product-actions">
                            <button class="icon-btn" onclick="editProduct(<?= $p['id'] ?>)" <?= !$hasSubscription ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 3l4 4-7 7H10v-4l7-7z"/><path d="M4 20h16"/></svg>
                            </button>
                            <?php if ($hasSubscription): ?>
                                <a href="?delete=<?= $p['id'] ?>" class="icon-btn" onclick="return confirm('Delete this product permanently?')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </a>
                            <?php else: ?>
                                <button class="icon-btn" disabled style="opacity:0.5; cursor:not-allowed;" onclick="showToast('error','Access Denied','Please reactivate your subscription to delete products.')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div class="modal-overlay" id="productModal">
        <div class="modal">
            <form method="post" id="productForm">
                <input type="hidden" name="product_id" id="product_id">
                <input type="hidden" name="existing_image" id="existing_image">
                <input type="hidden" name="image_data" id="image_data">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">Add Product</h3>
                    <button class="modal-close" type="button" onclick="closeModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="image-upload" id="imageUpload" onclick="document.getElementById('productImageFile').click()">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        <div>Click to upload product image</div>
                        <small style="color: var(--text-muted);">PNG, JPG up to 5MB</small>
                    </div>
                    <div class="image-preview hidden" id="imagePreview">
                        <img id="previewImg" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <input type="file" id="productImageFile" accept="image/*" style="display:none" onchange="handleImageUpload(this)">

                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" id="productName" class="form-input" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Price *</label>
                            <input type="number" step="0.01" name="price" id="productPrice" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock *</label>
                            <input type="number" name="stock" id="productStock" class="form-input" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="productCategory" class="form-select">
                            <option value="">Select</option>
                            <option value="electronics">Electronics</option>
                            <option value="fashion">Fashion</option>
                            <option value="beauty">Beauty</option>
                            <option value="home">Home</option>
                            <option value="sports">Sports</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="productStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="productDescription" class="form-textarea" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="add_product" id="submitBtn" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ============================================================
        // PRODUCTS PAGE - Full JavaScript (with limit checks)
        // ============================================================
        
        // Product limit data from PHP
        const canAddMore = <?= json_encode($canAddMore) ?>;
        const productLimit = <?= $productLimit == PHP_INT_MAX ? 'null' : $productLimit ?>;
        const currentCount = <?= $currentProductCount ?>;
        const hasSubscription = <?= json_encode($hasSubscription) ?>;
        
        // Live filtering
        const productSearch = document.getElementById('productSearch');
        const globalSearch = document.getElementById('globalSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockFilter');
        const productCards = document.querySelectorAll('.product-card');

        function filterProducts() {
            const searchTerm = productSearch.value.toLowerCase();
            const category = categoryFilter.value;
            const stock = stockFilter.value;
            productCards.forEach(card => {
                let show = true;
                const name = card.getAttribute('data-name') || '';
                const cat = card.getAttribute('data-category') || '';
                const stockVal = parseInt(card.getAttribute('data-stock') || 0);
                if (searchTerm && !name.includes(searchTerm)) show = false;
                if (category && cat !== category) show = false;
                if (stock === 'in' && stockVal <= 0) show = false;
                if (stock === 'low' && stockVal > 5) show = false;
                card.style.display = show ? '' : 'none';
            });
        }

        productSearch.addEventListener('input', filterProducts);
        categoryFilter.addEventListener('change', filterProducts);
        stockFilter.addEventListener('change', filterProducts);
        if (globalSearch) {
            globalSearch.addEventListener('input', (e) => {
                productSearch.value = e.target.value;
                filterProducts();
            });
        }

        // Notifications (mock data)
        let notifications = [
            { id: 1, title: 'New Order Received', text: 'Order #1287 has been placed.', time: '2 minutes ago', unread: true },
            { id: 2, title: 'Payment Confirmed', text: 'Payment of $245.00 confirmed.', time: '15 minutes ago', unread: true },
        ];
        function renderNotifications() {
            const list = document.getElementById('notificationList');
            if (!list) return;
            const unreadCount = notifications.filter(n => n.unread).length;
            const badge = document.querySelector('.topbar-btn-badge');
            if (badge) badge.style.display = unreadCount > 0 ? 'block' : 'none';
            list.innerHTML = notifications.map(n => `
                <div class="notification-item ${n.unread ? 'unread' : ''}" onclick="markNotificationRead(${n.id})">
                    ${n.unread ? '<div class="notification-dot"></div>' : '<div style="width:8px;"></div>'}
                    <div class="notification-content">
                        <div class="notification-title">${n.title}</div>
                        <div class="notification-text">${n.text}</div>
                        <div class="notification-time">${n.time}</div>
                    </div>
                </div>
            `).join('');
        }
        function markNotificationRead(id) {
            const notif = notifications.find(n => n.id === id);
            if (notif) notif.unread = false;
            renderNotifications();
        }
        function markAllRead() {
            notifications.forEach(n => n.unread = false);
            renderNotifications();
        }
        renderNotifications();

        // Modal handling
        let editingId = null;
        function openProductModal(id = null) {
            // Prevent adding if limit reached or no subscription
            if (id === null && !hasSubscription) {
                showToast('error', 'Access Denied', 'Please reactivate your subscription to add products.');
                return;
            }
            if (id === null && productLimit !== null && currentCount >= productLimit) {
                showToast('error', 'Limit Reached', `Your plan allows only ${productLimit} products. Please upgrade to add more.`);
                return;
            }
            if (id === null && !canAddMore && productLimit !== null) {
                showToast('error', 'Limit Reached', 'You have reached the maximum number of products for your plan.');
                return;
            }
            
            editingId = id;
            document.getElementById('productForm').reset();
            document.getElementById('product_id').value = '';
            document.getElementById('existing_image').value = '';
            document.getElementById('image_data').value = '';
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('imageUpload').classList.remove('hidden');
            if (id) {
                const productsData = <?= json_encode($products) ?>;
                const product = productsData.find(p => p.id == id);
                if (product) {
                    document.getElementById('modalTitle').innerText = 'Edit Product';
                    document.getElementById('product_id').value = product.id;
                    document.getElementById('productName').value = product.name;
                    document.getElementById('productPrice').value = product.price;
                    document.getElementById('productStock').value = product.stock;
                    document.getElementById('productCategory').value = product.category;
                    document.getElementById('productStatus').value = product.status;
                    document.getElementById('productDescription').value = product.description || '';
                    document.getElementById('existing_image').value = product.image || '';
                    if (product.image) {
                        document.getElementById('previewImg').src = product.image;
                        document.getElementById('imagePreview').classList.remove('hidden');
                        document.getElementById('imageUpload').classList.add('hidden');
                    }
                    document.getElementById('submitBtn').name = 'update_product';
                }
            } else {
                document.getElementById('modalTitle').innerText = 'Add Product';
                document.getElementById('submitBtn').name = 'add_product';
            }
            document.getElementById('productModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
            document.body.style.overflow = '';
            editingId = null;
        }

        function handleImageUpload(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('image_data').value = e.target.result;
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                    document.getElementById('imageUpload').classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            document.getElementById('image_data').value = '';
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('imageUpload').classList.remove('hidden');
            document.getElementById('productImageFile').value = '';
        }

        function editProduct(id) {
            openProductModal(id);
        }

        // Additional guard on form submission
        const productForm = document.getElementById('productForm');
        if (productForm) {
            productForm.addEventListener('submit', function(e) {
                const isAdding = document.getElementById('product_id').value === '';
                if (isAdding && !hasSubscription) {
                    e.preventDefault();
                    showToast('error', 'Access Denied', 'Please reactivate your subscription.');
                    return false;
                }
                if (isAdding && productLimit !== null && currentCount >= productLimit) {
                    e.preventDefault();
                    showToast('error', 'Limit Reached', `Your plan allows only ${productLimit} products. Upgrade to add more.`);
                    return false;
                }
                if (isAdding && !canAddMore && productLimit !== null) {
                    e.preventDefault();
                    showToast('error', 'Limit Reached', 'You have reached the maximum number of products.');
                    return false;
                }
            });
        }

        // Toast helper
        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const icons = {
                success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
            };
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<div class="toast-icon">${icons[type]}</div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('removing');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        function handleLogout() {
            if (confirm('Are you sure you want to log out?')) {
                window.location.href = 'logout.php';
            }
        }

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const htmlEl = document.documentElement;
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        htmlEl.setAttribute('data-theme', savedTheme);
        themeToggle.addEventListener('click', () => {
            const newTheme = htmlEl.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            htmlEl.setAttribute('data-theme', newTheme);
            localStorage.setItem('RD Vendora-theme', newTheme);
        });

        // Sidebar toggle (exact)
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleMobileSidebar() {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }

        sidebarToggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                toggleMobileSidebar();
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });

        mobileSidebarToggle.addEventListener('click', toggleMobileSidebar);
        overlay.addEventListener('click', toggleMobileSidebar);

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Dropdowns
        document.addEventListener('click', (e) => {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('open');
            } else if (userDropdown && e.target.closest('.dropdown-trigger')) {
                userDropdown.classList.toggle('open');
            }
            const notifDropdown = document.getElementById('notificationDropdown');
            if (notifDropdown && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('open');
            } else if (notifDropdown && e.target.closest('.dropdown-trigger')) {
                notifDropdown.classList.toggle('open');
            }
        });
        
        // Initial filter (already done on page load)
        filterProducts();
    </script>
</body>
</html>