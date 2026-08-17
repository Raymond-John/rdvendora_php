<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';
require_once 'includes/notification_helper.php'; // <-- ADDED FOR NOTIFICATIONS

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Admin check ----------
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin && isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
    $_SESSION['is_admin'] = true;
    $isAdmin = true;
}

// ---------- For vendors: ensure store exists and get store_id ----------
$storeData = null;
if (!$isAdmin) {
    $stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $storeResult = $stmt->get_result();
    if ($storeResult->num_rows === 0) {
        header("Location: create-store.php");
        exit();
    }
    $storeData = $storeResult->fetch_assoc();
    $_SESSION['store_id'] = $storeData['id'];
    $_SESSION['store_name'] = $storeData['store_name'];
    $stmt->close();

    if (!isStoreActive($conn, $_SESSION['user_id'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>Store Disabled</title></head>
        <body style="font-family: sans-serif; text-align: center; padding: 50px;">
            <h1>⛔ Store Disabled</h1>
            <p>Your store has been disabled by the administrator. Please contact support.</p>
            <a href="logout.php">Logout</a>
        </body>
        </html>
        <?php
        exit();
    }

    $hasSubscription = hasActiveSubscription($conn, $_SESSION['user_id']);
    $activePlan = null;
    if ($hasSubscription) {
        $stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $planRow = $stmt->get_result()->fetch_assoc();
        $activePlan = $planRow['plan'] ?? null;
        $stmt->close();
    }
} else {
    $hasSubscription = true;
    $activePlan = null;
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

// ---------- Fetch orders ----------
$orders = [];

$table_check = $conn->query("SHOW TABLES LIKE 'orders'");
if ($table_check && $table_check->num_rows > 0) {
    $where = "";
    $params = [];
    $types = "";

    if (!$isAdmin) {
        $where = " WHERE o.store_id = ?";
        $params[] = $_SESSION['store_id'];
        $types .= "i";
    }

    $sql = "SELECT o.*, s.store_name,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            LEFT JOIN stores s ON o.store_id = s.id
            {$where}
            ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Fetch items for this order
        $items = [];
        $itemStmt = $conn->prepare("SELECT product_name as name, quantity as qty, price, image_url as image FROM order_items WHERE order_id = ?");
        $itemStmt->bind_param("i", $row['id']);
        $itemStmt->execute();
        $itemRes = $itemStmt->get_result();
        while ($item = $itemRes->fetch_assoc()) {
            $items[] = $item;
        }
        $itemStmt->close();

        $row['items'] = $items;
        $row['order_number'] = $row['order_ref'];
        $row['total'] = $row['total_amount'];
        $row['customer_name'] = $row['user_name'];
        $row['location_display'] = $row['user_address'];
        $row['payment_status'] = ($row['status'] === 'completed') ? 'paid' : 'pending';

        $orders[] = $row;
    }
    $stmt->close();
}
$conn->close();

$totalOrdersCount = count($orders);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isAdmin ? 'All Marketplace Orders' : 'My Store Orders' ?> - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           COMPLETE CSS – same as your dashboard
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
            --text-xs: 0.75rem;
            --text-sm: 0.8125rem;
            --text-base: 0.9375rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
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
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; font-size: inherit; color: inherit; }
        input, select { font-family: inherit; font-size: inherit; color: inherit; }
        ul, ol { list-style: none; }
        img { max-width: 100%; display: block; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-secondary); border-radius: var(--radius-full); }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
        ::selection { background: var(--primary-light); color: var(--primary); }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: var(--z-fixed);
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
            flex-shrink: 0; gap: var(--space-3);
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: var(--space-3);
            font-weight: var(--font-bold); font-size: var(--text-lg);
            color: var(--text-primary); white-space: nowrap; overflow: hidden; flex-shrink: 0;
        }
        .sidebar-brand-icon {
            width: 34px; height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: white; flex-shrink: 0;
        }
        .sidebar-brand-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-toggle {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-muted);
            transition: all var(--transition-fast); flex-shrink: 0;
            background: transparent; border: none; cursor: pointer;
        }
        .sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar.collapsed .sidebar-toggle svg { transform: rotate(180deg); }
        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: var(--space-3); }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px; font-weight: var(--font-semibold);
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-muted); white-space: nowrap;
            transition: all var(--transition-fast); margin-top: var(--space-2);
        }
        .sidebar.collapsed .sidebar-section-title { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        .sidebar-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary); font-size: var(--text-sm); font-weight: var(--font-medium);
            transition: all var(--transition-fast); position: relative;
            white-space: nowrap; cursor: pointer; text-decoration: none; margin-bottom: 1px;
        }
        .sidebar-link:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-link.active { background: var(--primary-light); color: var(--primary); font-weight: var(--font-semibold); }
        .sidebar-link svg { flex-shrink: 0; width: 18px; height: 18px; }
        .sidebar-link-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-link-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-link-badge {
            margin-left: auto; font-size: 10px; padding: 2px 8px;
            background: var(--primary); color: white;
            border-radius: var(--radius-full); font-weight: var(--font-bold);
            flex-shrink: 0; line-height: 1.4;
        }
        .sidebar.collapsed .sidebar-link-badge {
            position: absolute; top: 4px; right: 4px;
            width: 8px; height: 8px; padding: 0; font-size: 0; min-width: 8px;
        }
        .sidebar-footer { padding: var(--space-3); border-top: 1px solid var(--border-primary); flex-shrink: 0; }
        .sidebar-user {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md); transition: background var(--transition-fast); cursor: pointer;
        }
        .sidebar-user:hover { background: var(--bg-hover); }
        .sidebar-user-avatar { width: 34px; height: 34px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .sidebar-user-info { flex: 1; min-width: 0; transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-user-info { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: calc(var(--z-fixed) - 1); opacity: 0; pointer-events: none; transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }

        /* ── Main Content ── */
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition-slow); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        .topbar {
            position: sticky; top: 0; height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 var(--space-6); z-index: var(--z-sticky);
            gap: var(--space-4); backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar { background: rgba(255,255,255,0.85); }
        [data-theme="dark"]  .topbar { background: rgba(20,22,31,0.85); }
        .topbar-left { display: flex; align-items: center; gap: var(--space-3); }
        .mobile-sidebar-toggle {
            display: none; width: 38px; height: 38px;
            align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary);
            transition: all var(--transition-fast); flex-shrink: 0;
        }
        .mobile-sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-search { flex: 1; max-width: 420px; position: relative; }
        .topbar-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; pointer-events: none; }
        .topbar-search input {
            width: 100%; padding: var(--space-2) var(--space-4) var(--space-2) 40px;
            background: var(--bg-tertiary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-md); font-size: var(--text-sm); outline: none;
            transition: all var(--transition-fast); color: var(--text-primary);
        }
        .topbar-search input::placeholder { color: var(--text-muted); }
        .topbar-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: var(--bg-secondary); }
        .topbar-actions { display: flex; align-items: center; gap: var(--space-2); }
        .topbar-btn {
            position: relative; width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary);
            transition: all var(--transition-fast); flex-shrink: 0;
        }
        .topbar-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-btn-badge {
            position: absolute; top: 6px; right: 6px; width: 8px; height: 8px;
            background: var(--error); border-radius: var(--radius-full); border: 2px solid var(--bg-secondary);
        }
        .theme-toggle {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary); transition: all var(--transition-fast); flex-shrink: 0;
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        [data-theme="light"] .theme-toggle .icon-moon { display: none; }
        [data-theme="dark"]  .theme-toggle .icon-sun  { display: none; }
        .topbar-user {
            display: flex; align-items: center; gap: var(--space-2);
            padding: var(--space-1) var(--space-3) var(--space-1) var(--space-1);
            border-radius: var(--radius-md); cursor: pointer; transition: background var(--transition-fast);
        }
        .topbar-user:hover { background: var(--bg-hover); }
        .topbar-user-avatar { width: 32px; height: 32px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .topbar-user-info { display: flex; flex-direction: column; line-height: 1.2; }
        .topbar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); }
        .topbar-user-role { font-size: var(--text-xs); color: var(--text-muted); }

        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0; min-width: 240px;
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-xl);
            z-index: var(--z-dropdown); opacity: 0; pointer-events: none;
            transform: translateY(-8px); transition: all var(--transition-fast); overflow: hidden;
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-header { padding: var(--space-4); border-bottom: 1px solid var(--border-primary); display: flex; align-items: center; justify-content: space-between; }
        .dropdown-header h4 { font-size: var(--text-sm); font-weight: var(--font-semibold); }
        .dropdown-header a { font-size: var(--text-xs); color: var(--primary); cursor: pointer; }
        .dropdown-header a:hover { text-decoration: underline; }
        .dropdown-item {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); font-size: var(--text-sm);
            color: var(--text-secondary); transition: all var(--transition-fast); cursor: pointer; text-decoration: none;
        }
        .dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown-item svg { flex-shrink: 0; width: 16px; height: 16px; }
        .dropdown-divider { height: 1px; background: var(--border-primary); margin: var(--space-1) 0; }
        .notification-list { max-height: 320px; overflow-y: auto; }
        .notification-item {
            display: flex; align-items: flex-start; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); cursor: pointer;
            transition: background var(--transition-fast); border-bottom: 1px solid var(--border-primary);
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background: var(--bg-hover); }
        .notification-item.unread { background: var(--primary-light); }
        .notification-dot { width: 8px; height: 8px; background: var(--primary); border-radius: var(--radius-full); flex-shrink: 0; margin-top: 6px; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); }
        .notification-text { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        .notification-time { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        .page-content { flex: 1; padding: var(--space-6); overflow-y: auto; }
        .page-header { margin-bottom: var(--space-6); }
        .page-title { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); letter-spacing: -0.02em; }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: var(--space-1); }

        .filters-bar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--space-4); margin-bottom: var(--space-6); }
        .filters-left { display: flex; flex-wrap: wrap; gap: var(--space-3); align-items: center; }
        .search-box { position: relative; width: 260px; }
        .search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; }
        .search-box input {
            width: 100%; padding: var(--space-2) var(--space-3) var(--space-2) 36px;
            background: var(--bg-tertiary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-md); font-size: var(--text-sm); color: var(--text-primary);
            outline: none; transition: all var(--transition-fast);
        }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .filter-select {
            padding: var(--space-2) var(--space-3);
            background: var(--bg-tertiary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-md); font-size: var(--text-sm); color: var(--text-primary); cursor: pointer;
            outline: none; transition: all var(--transition-fast);
        }
        .filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

        .btn {
            display: inline-flex; align-items: center; gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
            font-size: var(--text-sm); font-weight: var(--font-semibold);
            border-radius: var(--radius-md); transition: all var(--transition-fast);
            cursor: pointer; border: 1px solid transparent;
        }
        .btn-primary { background: var(--gradient-primary); color: var(--text-inverse); border: none; box-shadow: 0 2px 8px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(99,102,241,0.35); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-primary); }
        .btn-ghost:hover { background: var(--bg-hover); color: var(--text-primary); }
        .btn-sm { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); }

        .table-container { overflow-x: auto; border-radius: var(--radius-lg); border: 1px solid var(--border-primary); background: var(--bg-secondary); }
        .data-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); min-width: 900px; }
        .data-table th { padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--font-semibold); background: var(--bg-tertiary); border-bottom: 1px solid var(--border-primary); color: var(--text-muted); }
        .data-table td { padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-primary); vertical-align: middle; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: var(--bg-hover); }
        .checkbox-col { width: 40px; text-align: center; }

        .badge { display: inline-flex; padding: 3px 10px; font-size: 11px; font-weight: var(--font-semibold); border-radius: var(--radius-full); }
        .badge-success { background: var(--success-light); color: var(--success-dark); }
        .badge-warning { background: var(--warning-light); color: var(--warning-dark); }
        .badge-error   { background: var(--error-light);   color: var(--error-dark); }
        .badge-info    { background: var(--info-light);    color: var(--info-dark); }
        .badge-primary { background: var(--primary-light); color: var(--primary); }

        .avatar { width: 32px; height: 32px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .avatar-sm { width: 28px; height: 28px; font-size: 12px; }

        .empty-state { text-align: center; padding: var(--space-12); }
        .empty-state-icon { width: 80px; height: 80px; margin: 0 auto var(--space-4); background: var(--bg-tertiary); border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
        .empty-state-title { font-size: var(--text-lg); font-weight: var(--font-semibold); margin-bottom: var(--space-2); }
        .empty-state-text { color: var(--text-secondary); margin-bottom: var(--space-6); }

        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: var(--z-modal-backdrop); display: flex; align-items: center; justify-content: center;
            padding: var(--space-6); opacity: 0; visibility: hidden; transition: all var(--transition-base);
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal {
            background: var(--bg-secondary); border-radius: var(--radius-xl); border: 1px solid var(--border-primary);
            width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto;
            transform: scale(0.95) translateY(20px); transition: transform var(--transition-bounce); box-shadow: var(--shadow-xl);
        }
        .modal-overlay.active .modal { transform: scale(1) translateY(0); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-primary); }
        .modal-title { font-size: var(--text-lg); font-weight: var(--font-bold); }
        .modal-close { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); color: var(--text-muted); transition: all var(--transition-fast); cursor: pointer; }
        .modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
        .modal-body { padding: var(--space-6); }
        .modal-footer { display: flex; justify-content: flex-end; gap: var(--space-3); padding: var(--space-4) var(--space-6); border-top: 1px solid var(--border-primary); }
        .form-group { margin-bottom: var(--space-4); }
        .form-label { display: block; font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); margin-bottom: var(--space-2); }
        .form-input, .form-select, .form-textarea {
            width: 100%; padding: var(--space-3) var(--space-4);
            font-size: var(--text-sm); color: var(--text-primary);
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-md); transition: all var(--transition-fast); outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .form-textarea { resize: vertical; min-height: 80px; font-family: inherit; }

        #pagination { display: flex; justify-content: center; margin-top: var(--space-6); gap: var(--space-2); flex-wrap: wrap; }
        .page-btn { padding: var(--space-1) var(--space-3); border-radius: var(--radius-md); background: var(--bg-tertiary); color: var(--text-secondary); cursor: pointer; border: 1px solid var(--border-primary); font-size: var(--text-sm); transition: all var(--transition-fast); }
        .page-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .transport-actions { display: flex; align-items: center; gap: var(--space-3); }
        .selected-count { background: var(--primary-light); color: var(--primary); padding: 4px 12px; border-radius: 20px; font-size: var(--text-sm); font-weight: var(--font-medium); }

        .sub-banner {
            background: linear-gradient(135deg, #fee2e2 0%, #fef3c7 100%);
            border-left: 4px solid #dc2626; border-radius: 16px;
            padding: 20px 24px; margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
        }
        .sub-banner-inner { display: flex; align-items: center; gap: 16px; }
        .sub-banner-icon { width: 48px; height: 48px; background: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .sub-banner h3 { font-size: 18px; font-weight: 700; color: #991b1b; }
        .sub-banner p { color: #7f1d1d; margin-top: 4px; }
        .sub-banner-btn { background: #dc2626; color: white; padding: 10px 24px; border-radius: 40px; font-weight: 600; display: inline-block; }

        .toast-container { position: fixed; top: calc(var(--topbar-height) + var(--space-4)); right: var(--space-4); z-index: var(--z-toast); display: flex; flex-direction: column; gap: var(--space-3); }
        .toast {
            display: flex; align-items: center; gap: var(--space-3); padding: var(--space-4);
            background: var(--bg-secondary); border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary); box-shadow: var(--shadow-xl);
            min-width: 280px; transform: translateX(120%);
            animation: toastSlideIn 0.4s ease forwards;
        }
        .toast.removing { animation: toastSlideOut 0.3s ease forwards; }
        .toast-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .toast-success .toast-icon { background: var(--success-light); color: var(--success); }
        .toast-error   .toast-icon { background: var(--error-light);   color: var(--error); }
        .toast-info    .toast-icon { background: var(--info-light);    color: var(--info); }
        .toast-content { flex: 1; }
        .toast-title   { font-weight: var(--font-semibold); color: var(--text-primary); }
        .toast-message { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        @keyframes toastSlideIn  { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: var(--z-fixed); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-xl); }
            .sidebar.collapsed { width: var(--sidebar-width); transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .page-content { padding: var(--space-4); }
            .transport-actions { width: 100%; justify-content: flex-start; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filters-left { flex-direction: column; }
            .search-box { width: 100%; }
        }
        @media (max-width: 480px) {
            .topbar-search { display: none; }
            .topbar { padding: 0 var(--space-3); }
            .page-title { font-size: var(--text-xl); }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                </div>
                <span class="sidebar-brand-text">RD Vendora</span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="products.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="sidebar-link-text">Products</span>
            </a>
            <a href="orders.php" class="sidebar-link active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="sidebar-link-text">Orders</span>
                <span class="sidebar-link-badge"><?= $totalOrdersCount ?></span>
            </a>
            <a href="customers.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="sidebar-link-text">Customers</span>
            </a>

            <div class="sidebar-section-title">Store</div>
            <a href="storefront.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="sidebar-link-text">Storefront</span>
            </a>
            <a href="settings.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="sidebar-link-text">Store Settings</span>
            </a>
            <a href="subscription.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span class="sidebar-link-text">Subscription</span>
            </a>
            <a href="vendor-chat.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span class="sidebar-link-text">Chat</span>
            </a>

            <div class="sidebar-section-title">Account</div>
            <a href="profile.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                </svg>
                <span class="sidebar-link-text">Profile</span>
            </a>
            <a href="#" class="sidebar-link" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="sidebar-link-text">Logout</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="sidebar-user-avatar">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                    <div class="sidebar-user-role">
                        <?php if ($isAdmin): ?>
                            👑 Admin
                        <?php elseif (!empty($_SESSION['store_name'])): ?>
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
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="globalSearch" placeholder="Search orders...">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>

                <div class="dropdown" id="notificationDropdown">
                    <button class="topbar-btn dropdown-trigger" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
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

                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role">
                                <?php if ($isAdmin): ?>
                                    Admin
                                <?php elseif (!empty($_SESSION['store_name'])): ?>
                                    Store: <?= htmlspecialchars($_SESSION['store_name']) ?>
                                <?php else: ?>
                                    <a href="create-store.php" style="color:var(--primary);">Create Store</a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Profile
                        </a>
                        <a href="settings.php" class="dropdown-item">
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
                <h1 class="page-title"><?= $isAdmin ? 'Marketplace Orders' : 'My Store Orders' ?></h1>
                <p class="page-subtitle">
                    <?php if ($isAdmin): ?>
                        View and manage all orders across the marketplace.
                    <?php else: ?>
                        Manage orders for <strong><?= htmlspecialchars($_SESSION['store_name']) ?></strong>
                    <?php endif; ?>
                    <?php if ($totalOrdersCount > 0): ?>
                        &mdash; <?= $totalOrdersCount ?> total order<?= $totalOrdersCount !== 1 ? 's' : '' ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!$isAdmin && !$hasSubscription): ?>
            <div class="sub-banner">
                <div class="sub-banner-inner">
                    <div class="sub-banner-icon">⚠️</div>
                    <div>
                        <h3>Store Suspended</h3>
                        <p>Your store has no active subscription. Choose a plan to reactivate.</p>
                    </div>
                </div>
                <a href="subscription.php" class="sub-banner-btn">Reactivate Now →</a>
            </div>
            <?php endif; ?>

            <div class="filters-bar">
                <div class="filters-left">
                    <div class="search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="orderSearch" placeholder="Search by order # or customer...">
                    </div>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select class="filter-select" id="paymentFilter">
                        <option value="">All Payments</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                <?php if (!$isAdmin): ?>
                <div class="transport-actions">
                    <button class="btn btn-primary" id="sendToTransportBtn" <?= (!$hasSubscription) ? 'disabled' : '' ?>>
                        🚚 Send to Transport
                    </button>
                    <span class="selected-count" id="selectedCount">0 selected</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <table class="data-table" id="ordersTable">
                    <thead>
                        <tr>
                            <?php if (!$isAdmin): ?><th class="checkbox-col"><input type="checkbox" id="selectAll"></th><?php endif; ?>
                            <th>Order #</th>
                            <th>Customer</th>
                            <?php if ($isAdmin): ?><th>Store</th><?php endif; ?>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody"></tbody>
                </table>
            </div>
            <div id="pagination"></div>
        </div>
    </div>

    <!-- Transport Modal (Vendor Only) -->
    <?php if (!$isAdmin): ?>
    <div id="transportModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Send Orders to Transport</h3>
                <button class="modal-close" onclick="closeTransportModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div id="selectedOrdersList" style="margin-bottom:1rem;max-height:200px;overflow-y:auto;background:var(--bg-tertiary);padding:12px;border-radius:var(--radius-md);font-size:var(--text-sm);"></div>
                <div class="form-group">
                    <label class="form-label">Transport Company</label>
                    <select id="transportCompany" class="form-select">
                        <option value="Fast Delivery Express">Fast Delivery Express</option>
                        <option value="Logistics Plus">Logistics Plus</option>
                        <option value="Speed Cargo">Speed Cargo</option>
                        <option value="Local Courier Service">Local Courier Service</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes (optional)</label>
                    <textarea id="transportNotes" class="form-textarea" rows="3" placeholder="Special instructions for the transport company..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeTransportModal()">Cancel</button>
                <button class="btn btn-primary" id="confirmTransportBtn">Confirm & Send</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order Details Modal -->
    <div class="modal-overlay" id="orderModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Order Details</h3>
                <button class="modal-close" onclick="closeOrderModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body" id="orderModalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeOrderModal()">Close</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
    // ========== ORDERS DATA (populated from PHP) ==========
    const ordersData = <?= json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;

    let currentPage    = 1;
    const itemsPerPage = 10;
    let selectedOrders = new Set();

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            if (m === '"') return '&quot;';
            return '&#39;';
        });
    }

    const statusColors = {
        pending:    'badge-warning',
        processing: 'badge-info',
        shipped:    'badge-primary',
        delivered:  'badge-success',
        cancelled:  'badge-error',
        completed:  'badge-success'
    };
    const paymentColors = {
        paid:     'badge-success',
        pending:  'badge-warning',
        refunded: 'badge-error'
    };

    // ========== FILTER ==========
    function getFilteredOrders() {
        const search  = (document.getElementById('orderSearch')?.value  || '').toLowerCase();
        const status  =  document.getElementById('statusFilter')?.value  || '';
        const payment =  document.getElementById('paymentFilter')?.value || '';
        return ordersData.filter(o => {
            const matchSearch  = !search  || (o.order_ref || '').toLowerCase().includes(search)
                                          || (o.customer_name || '').toLowerCase().includes(search);
            const matchStatus  = !status  || o.status === status;
            const matchPayment = !payment || o.payment_status === payment;
            return matchSearch && matchStatus && matchPayment;
        });
    }

    // ========== RENDER ==========
    function renderOrders() {
        const filtered   = getFilteredOrders();
        const totalPages = Math.ceil(filtered.length / itemsPerPage) || 1;
        if (currentPage > totalPages) currentPage = totalPages;

        const start     = (currentPage - 1) * itemsPerPage;
        const paginated = filtered.slice(start, start + itemsPerPage);
        const tbody     = document.getElementById('ordersTableBody');
        if (!tbody) return;

        if (paginated.length === 0) {
            tbody.innerHTML = `<tr><td colspan="<?= ($isAdmin ? '10' : '9') ?>">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </div>
                    <h3 class="empty-state-title">No orders found</h3>
                    <p class="empty-state-text">Try adjusting your search or filter criteria.</p>
                </div>
            </td></tr>`;
        } else {
            tbody.innerHTML = paginated.map(order => {
                const name      = order.customer_name || 'Unknown';
                const initials  = name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
                const itemCount = order.item_count || (Array.isArray(order.items) ? order.items.length : 0);
                const total     = parseFloat(order.total || 0).toFixed(2);
                const date      = order.created_at ? new Date(order.created_at).toLocaleDateString('en-NG', {day:'2-digit',month:'short',year:'numeric'}) : 'N/A';
                const checked   = selectedOrders.has(order.id) ? 'checked' : '';
                const sBadge    = statusColors[order.status] || 'badge-info';
                const pBadge    = paymentColors[order.payment_status] || 'badge-warning';

                // Build status dropdown options
                const statusOptions = ['pending','processing','shipped','delivered','cancelled'].map(opt =>
                    `<option value="${opt}" ${order.status === opt ? 'selected' : ''}>${opt.charAt(0).toUpperCase() + opt.slice(1)}</option>`
                ).join('');

                let row = `<tr>
                    ${!isAdmin ? `<td class="checkbox-col"><input type="checkbox" class="order-checkbox" value="${order.id}" ${checked}></td>` : ''}
                    <td><span style="font-weight:600;color:var(--primary);">${escapeHtml(order.order_ref || 'N/A')}</span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar avatar-sm" style="background:var(--primary-light);color:var(--primary);">${initials}</div>
                            <span>${escapeHtml(name)}</span>
                        </div>
                    </td>`;
                if (isAdmin) {
                    row += `<td>${escapeHtml(order.store_name || 'N/A')}</td>`;
                }
                row += `
                    <td style="white-space:nowrap;">${date}</td>
                    <td>${itemCount} item${itemCount !== 1 ? 's' : ''}</td>
                    <td style="font-weight:600;">₦${total}</td>
                    <td><span class="badge ${pBadge}">${escapeHtml(order.payment_status || 'pending')}</span></td>
                    <td>
                        <select class="status-select" data-order-id="${order.id}" data-original="${order.status}" style="font-size:11px;padding:4px 8px;border-radius:4px;border:1px solid var(--border-primary);background:var(--bg-tertiary);">
                            ${statusOptions}
                        </select>
                    </td>
                    <td style="max-width:180px;white-space:normal;font-size:var(--text-xs);color:var(--text-secondary);">${escapeHtml(order.user_address || order.location_display || 'No address')}</td>
                    <td><button class="btn btn-ghost btn-sm" onclick="viewOrder(${order.id})">View</button></td>
                </tr>`;
                return row;
            }).join('');
        }

        // Attach event listeners for status change
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function(e) {
                const orderId = this.dataset.orderId;
                const newStatus = this.value;
                const original = this.dataset.original;
                // Confirm cancellation
                if (newStatus === 'cancelled' && !confirm('Are you sure you want to cancel this order?')) {
                    this.value = original;
                    return;
                }
                // Send update
                fetch('update_order_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, status: newStatus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', 'Status Updated', data.message);
                        // Update data-original to new status
                        this.dataset.original = newStatus;
                        // Update the order in ordersData
                        const order = ordersData.find(o => o.id == orderId);
                        if (order) order.status = newStatus;
                        // Also update the badge in the table row (optional)
                        // Refresh the table to reflect changes if needed
                    } else {
                        showToast('error', 'Update Failed', data.message);
                        this.value = original;
                    }
                })
                .catch(err => {
                    showToast('error', 'Network Error', err.message);
                    this.value = original;
                });
            });
        });

        if (!isAdmin) {
            document.querySelectorAll('.order-checkbox').forEach(cb => {
                cb.addEventListener('change', function () {
                    const id = parseInt(this.value);
                    if (this.checked) selectedOrders.add(id);
                    else selectedOrders.delete(id);
                    updateSelectedCount();
                    syncSelectAll();
                });
            });
            syncSelectAll();
            updateSelectedCount();
        }
        renderPagination(totalPages);
    }

    function syncSelectAll() {
        if (isAdmin) return;
        const all = document.querySelectorAll('.order-checkbox');
        const sa  = document.getElementById('selectAll');
        if (!sa || all.length === 0) return;
        const checkedCount = [...all].filter(c => c.checked).length;
        sa.checked = checkedCount === all.length;
        sa.indeterminate = checkedCount > 0 && checkedCount < all.length;
    }

    function renderPagination(totalPages) {
        const div = document.getElementById('pagination');
        if (!div) return;
        if (totalPages <= 1) { div.innerHTML = ''; return; }
        let html = '';
        if (currentPage > 1) html += `<button class="page-btn" onclick="goToPage(${currentPage - 1})">‹ Prev</button>`;
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
        }
        if (currentPage < totalPages) html += `<button class="page-btn" onclick="goToPage(${currentPage + 1})">Next ›</button>`;
        div.innerHTML = html;
    }

    function updateSelectedCount() {
        if (isAdmin) return;
        const el = document.getElementById('selectedCount');
        if (el) el.textContent = `${selectedOrders.size} selected`;
    }

    function goToPage(page) { currentPage = page; renderOrders(); }
    function filterOrders() { currentPage = 1; selectedOrders.clear(); updateSelectedCount(); renderOrders(); }

    // Select All (vendor only)
    if (!isAdmin) {
        document.getElementById('selectAll')?.addEventListener('change', function () {
            const all = document.querySelectorAll('.order-checkbox');
            all.forEach(cb => {
                cb.checked = this.checked;
                const id = parseInt(cb.value);
                if (this.checked) selectedOrders.add(id);
                else selectedOrders.delete(id);
            });
            updateSelectedCount();
        });
    }

    // ========== TRANSPORT MODAL ==========
    if (!isAdmin) {
        function openTransportModal() {
            if (selectedOrders.size === 0) {
                showToast('error', 'No orders selected', 'Please select at least one order first.');
                return;
            }
            const list = [...selectedOrders]
                .map(id => ordersData.find(o => o.id == id))
                .filter(Boolean)
                .map(o => `<div style="padding:4px 0;">📦 <strong>${escapeHtml(o.order_ref)}</strong> — ${escapeHtml(o.customer_name)}</div>`)
                .join('');
            document.getElementById('selectedOrdersList').innerHTML = `<div style="font-weight:600;margin-bottom:8px;">Selected orders (${selectedOrders.size}):</div>${list}`;
            document.getElementById('transportModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeTransportModal() {
            document.getElementById('transportModal').classList.remove('active');
            document.body.style.overflow = '';
            document.getElementById('transportNotes').value = '';
        }

        document.getElementById('sendToTransportBtn')?.addEventListener('click', openTransportModal);

        document.getElementById('confirmTransportBtn')?.addEventListener('click', async function () {
            const company = document.getElementById('transportCompany').value;
            const notes   = document.getElementById('transportNotes').value;
            const ids     = [...selectedOrders];
            if (!ids.length) { showToast('error', 'No orders', 'No orders selected.'); return; }

            this.disabled = true; this.textContent = 'Sending…';
            try {
                const response = await fetch('transport_orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_ids: ids, company: company, notes: notes })
                });
                const data = await response.json();
                if (data.success) {
                    showToast('success', 'Success!', data.message || 'Orders sent to transport.');
                    closeTransportModal();
                    selectedOrders.clear();
                    renderOrders();
                } else {
                    showToast('error', 'Failed', data.message || 'Unknown error.');
                }
            } catch (err) {
                showToast('error', 'Network Error', err.message);
            } finally {
                this.disabled = false; this.textContent = 'Confirm & Send';
            }
        });
        window.closeTransportModal = closeTransportModal;
    }

    // ========== ORDER DETAILS MODAL ==========
    function viewOrder(orderId) {
        const order = ordersData.find(o => o.id == orderId);
        if (!order) { showToast('error', 'Error', 'Order not found.'); return; }

        const itemsHtml = Array.isArray(order.items) && order.items.length
            ? order.items.map(item => `<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-primary);">
                <span>${escapeHtml(item.name)} &times; ${item.qty || 1}</span>
                <span style="font-weight:500;">₦${((item.price || 0) * (item.qty || 1)).toFixed(2)}</span>
            </div>`).join('')
            : '<div style="padding:8px 0;color:var(--text-muted);text-align:center;">No item details available</div>';

        const address = order.user_address || order.location_display || 'Not provided';
        const date = order.created_at ? new Date(order.created_at).toLocaleString('en-NG') : 'N/A';
        const sBadge = statusColors[order.status] || 'badge-info';
        const pBadge = paymentColors[order.payment_status] || 'badge-warning';

        let storeRow = '';
        if (isAdmin && order.store_name) storeRow = `<div><strong>Store:</strong> ${escapeHtml(order.store_name)}</div>`;

        document.getElementById('orderModalBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div><div style="font-size:11px;color:var(--text-muted);">Order Number</div><div>${escapeHtml(order.order_ref)}</div></div>
                <div><div style="font-size:11px;color:var(--text-muted);">Date</div><div>${date}</div></div>
                <div><div style="font-size:11px;color:var(--text-muted);">Customer</div><div>${escapeHtml(order.customer_name)}</div></div>
                ${storeRow}
                <div><div style="font-size:11px;color:var(--text-muted);">Total</div><div>₦${parseFloat(order.total).toFixed(2)}</div></div>
            </div>
            <div><strong>Delivery Address:</strong> ${escapeHtml(address)}</div>
            <div style="margin-top:16px;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;">Items</div>
                ${itemsHtml}
            </div>
            <div style="display:flex;gap:16px;margin-top:16px;">
                <div><div style="font-size:11px;color:var(--text-muted);">Status</div><span class="badge ${sBadge}">${order.status}</span></div>
                <div><div style="font-size:11px;color:var(--text-muted);">Payment</div><span class="badge ${pBadge}">${order.payment_status}</span></div>
            </div>`;
        document.getElementById('orderModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeOrderModal() {
        document.getElementById('orderModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('orderModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeOrderModal(); });
    if (!isAdmin) document.getElementById('transportModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeTransportModal(); });

    // ========== FILTERS ==========
    document.getElementById('orderSearch')?.addEventListener('input', filterOrders);
    document.getElementById('statusFilter')?.addEventListener('change', filterOrders);
    document.getElementById('paymentFilter')?.addEventListener('change', filterOrders);
    document.getElementById('globalSearch')?.addEventListener('input', e => {
        const searchBox = document.getElementById('orderSearch');
        if (searchBox) { searchBox.value = e.target.value; filterOrders(); }
    });

    // ========== NOTIFICATIONS ==========
    let notifications = [
        { id: 1, title: 'New Order Received', text: 'Order #ORD-1002 has been placed.', time: '5 minutes ago', unread: true },
        { id: 2, title: 'Payment Confirmed', text: 'Payment for ORD-1001 has been confirmed.', time: '1 hour ago', unread: false }
    ];
    function renderNotifications() {
        const list  = document.getElementById('notificationList');
        const badge = document.querySelector('.topbar-btn-badge');
        if (!list) return;
        const unread = notifications.filter(n => n.unread).length;
        if (badge) badge.style.display = unread > 0 ? 'block' : 'none';
        list.innerHTML = notifications.map(n => `<div class="notification-item ${n.unread ? 'unread' : ''}" onclick="markNotificationRead(${n.id})">
            ${n.unread ? '<div class="notification-dot"></div>' : '<div style="width:8px;"></div>'}
            <div class="notification-content"><div class="notification-title">${escapeHtml(n.title)}</div><div class="notification-text">${escapeHtml(n.text)}</div><div class="notification-time">${escapeHtml(n.time)}</div></div>
        </div>`).join('');
    }
    function markNotificationRead(id) { const n = notifications.find(x => x.id === id); if (n) n.unread = false; renderNotifications(); }
    function markAllRead() { notifications.forEach(n => n.unread = false); renderNotifications(); }
    renderNotifications();

    // ========== TOAST ==========
    function showToast(type, title, message) {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const icons = { success: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                        error:   '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                        info:    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' };
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<div class="toast-icon">${icons[type]||icons.info}</div><div class="toast-content"><div class="toast-title">${escapeHtml(title)}</div><div class="toast-message">${escapeHtml(message)}</div></div>`;
        container.appendChild(toast);
        setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 300); }, 3500);
    }

    // ========== LOGOUT ==========
    function handleLogout() { if (confirm('Are you sure you want to logout?')) window.location.href = 'logout.php'; }

    // ========== THEME ==========
    const htmlEl = document.documentElement;
    const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
    htmlEl.setAttribute('data-theme', savedTheme);
    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const next = htmlEl.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        htmlEl.setAttribute('data-theme', next);
        localStorage.setItem('RD Vendora-theme', next);
    });

    // ========== SIDEBAR ==========
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        if (window.innerWidth <= 768) toggleMobile();
        else sidebar.classList.toggle('collapsed');
    });
    document.getElementById('mobileSidebarToggle')?.addEventListener('click', toggleMobile);
    overlay?.addEventListener('click', toggleMobile);
    function toggleMobile() {
        sidebar.classList.toggle('mobile-open');
        overlay?.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
    }
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            overlay?.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // ========== DROPDOWNS ==========
    document.addEventListener('click', e => {
        ['userDropdown', 'notificationDropdown'].forEach(id => {
            const dd = document.getElementById(id);
            if (!dd) return;
            if (!dd.contains(e.target)) dd.classList.remove('open');
            else if (e.target.closest('.dropdown-trigger')) dd.classList.toggle('open');
        });
    });

    // ========== INITIAL RENDER ==========
    renderOrders();
    </script>
</body>
</html>