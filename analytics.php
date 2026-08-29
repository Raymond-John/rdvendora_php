<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login?error=Not logged in');
    exit();
}

require_once 'includes/log_activity.php';
logUserActivity($_SESSION['user_id'], 'analytics_view', 'analytics.php', 'Viewed analytics page');

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Get the NEWEST store for this user ----------
$stmt = $conn->prepare("SELECT id, store_name, status FROM stores WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$storeResult = $stmt->get_result();
if ($storeResult->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$storeData = $storeResult->fetch_assoc();
$_SESSION['store_id'] = $storeData['id'];
$_SESSION['store_name'] = $storeData['store_name'];
$storeStatus = $storeData['status'];
$stmt->close();

// ---------- Determine if store access is restricted ----------
$storeRestricted = false;
$restrictionMessage = '';

if ($storeStatus === 'pending') {
    $storeRestricted = true;
    $restrictionMessage = '⏳ Your store is pending approval. All features are locked.';
} elseif ($storeStatus === 'inactive') {
    $storeRestricted = true;
    $restrictionMessage = '⛔ Your store has been suspended. Please contact support.';
} elseif ($storeStatus === 'pending_docs') {
    $storeRestricted = true;
    $restrictionMessage = '📄 Your documents are under review. Features are locked.';
} elseif ($storeStatus === 'active') {
    $hasSubscription = hasActiveSubscription($conn, $_SESSION['user_id']);
    if (!$hasSubscription) {
        $storeRestricted = true;
        $restrictionMessage = '🔒 Your subscription has expired. Please reactivate.';
    } else {
        // get plan name
        $stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $planRow = $stmt->get_result()->fetch_assoc();
        $activePlan = $planRow['plan'] ?? null;
        $stmt->close();
    }
} else {
    $storeRestricted = true;
    $restrictionMessage = '⛔ Your store is not accessible. Please contact support.';
}

// Get user's fullname
if (!isset($_SESSION['fullname'])) {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $_SESSION['fullname'] = $stmt->get_result()->fetch_assoc()['fullname'] ?? 'User';
    $stmt->close();
}

// ---------- Helper: determine vendor column in orders ----------
$order_columns = [];
$columns_check = $conn->query("SHOW COLUMNS FROM orders");
if ($columns_check) {
    while ($col = $columns_check->fetch_assoc()) {
        $order_columns[] = $col['Field'];
    }
}
$vendor_id_col = null;
if (in_array('store_id', $order_columns)) $vendor_id_col = 'store_id';
elseif (in_array('seller_id', $order_columns)) $vendor_id_col = 'seller_id';
elseif (in_array('vendor_id', $order_columns)) $vendor_id_col = 'vendor_id';
elseif (in_array('user_id', $order_columns)) $vendor_id_col = 'user_id';

// ---------- Check if we have cost_price in products ----------
$hasCost = false;
$prod_cols = $conn->query("SHOW COLUMNS FROM products");
if ($prod_cols) {
    while ($col = $prod_cols->fetch_assoc()) {
        if ($col['Field'] === 'cost_price') {
            $hasCost = true;
            break;
        }
    }
}

// ---------- Process date range ----------
$range = $_GET['range'] ?? 'this_month';
$start_date = null;
$end_date = null;

switch ($range) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'this_week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'this_month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'last_30':
        $start_date = date('Y-m-d', strtotime('-29 days'));
        $end_date = date('Y-m-d');
        break;
    case 'custom':
        $start_date = $_GET['start'] ?? date('Y-m-01');
        $end_date = $_GET['end'] ?? date('Y-m-t');
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

$start_date = date('Y-m-d', strtotime($start_date));
$end_date = date('Y-m-d', strtotime($end_date));

// ---------- Fetch analytics data (only if not restricted) ----------
$summary = ['revenue' => 0, 'orders' => 0, 'aov' => 0, 'profit' => 0, 'profit_margin' => 0];
$dailyData = [];
$monthlyData = [];
$profitData = [];

if (!$storeRestricted && $vendor_id_col) {
    // ---- Summary ----
    $sql = "SELECT 
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(*) as total_orders,
                COALESCE(AVG(total_amount), 0) as avg_order_value
            FROM orders 
            WHERE $vendor_id_col = ? AND status = 'completed' 
              AND DATE(created_at) BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $_SESSION['store_id'], $start_date, $end_date);
    $stmt->execute();
    $sum = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $summary['revenue'] = $sum['total_revenue'] ?? 0;
    $summary['orders'] = $sum['total_orders'] ?? 0;
    $summary['aov'] = $summary['orders'] > 0 ? $summary['revenue'] / $summary['orders'] : 0;

    // ---- Profit (if cost available) ----
    if ($hasCost && in_array('total_amount', $order_columns)) {
        // We need to join order_items and products to get cost
        $sql = "SELECT 
                    SUM(oi.quantity * p.cost_price) as total_cost
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE o.$vendor_id_col = ? AND o.status = 'completed'
                  AND DATE(o.created_at) BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $_SESSION['store_id'], $start_date, $end_date);
        $stmt->execute();
        $costResult = $stmt->get_result()->fetch_assoc();
        $totalCost = $costResult['total_cost'] ?? 0;
        $stmt->close();

        $summary['profit'] = $summary['revenue'] - $totalCost;
        $summary['profit_margin'] = $summary['revenue'] > 0 ? ($summary['profit'] / $summary['revenue']) * 100 : 0;
    } else {
        // Estimate profit as 30% of revenue (fallback)
        $summary['profit'] = $summary['revenue'] * 0.30;
        $summary['profit_margin'] = 30.0;
    }

    // ---- Daily breakdown ----
    $sql = "SELECT 
                DATE(created_at) as day,
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM orders
            WHERE $vendor_id_col = ? AND status = 'completed'
              AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY day ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $_SESSION['store_id'], $start_date, $end_date);
    $stmt->execute();
    $dailyRes = $stmt->get_result();
    while ($row = $dailyRes->fetch_assoc()) {
        $dailyData[] = $row;
    }
    $stmt->close();

    // ---- Monthly breakdown ----
    $sql = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM orders
            WHERE $vendor_id_col = ? AND status = 'completed'
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY YEAR(created_at), MONTH(created_at)
            ORDER BY month ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['store_id']);
    $stmt->execute();
    $monthlyRes = $stmt->get_result();
    while ($row = $monthlyRes->fetch_assoc()) {
        $monthlyData[] = $row;
    }
    $stmt->close();
}

$conn->close();

// Prepare data for charts
$dailyLabels = array_column($dailyData, 'day');
$dailyRevenue = array_column($dailyData, 'revenue');
$dailyOrders = array_column($dailyData, 'orders');

$monthlyLabels = array_column($monthlyData, 'month');
$monthlyRevenue = array_column($monthlyData, 'revenue');

// For day-over-day growth
$dailyGrowth = [];
foreach ($dailyData as $i => $d) {
    if ($i == 0) {
        $dailyGrowth[] = 0;
    } else {
        $prev = $dailyData[$i-1]['revenue'];
        $dailyGrowth[] = $prev > 0 ? (($d['revenue'] - $prev) / $prev) * 100 : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* ====== SAME STYLES AS DASHBOARD ====== */
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; }
        body { font-family: var(--font-sans); font-size: var(--text-base); line-height: var(--leading-normal); color: var(--text-primary); background: var(--bg-primary); min-height: 100vh; transition: background var(--transition-base), color var(--transition-base); }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; font-size: inherit; color: inherit; }
        input, select { font-family: inherit; font-size: inherit; color: inherit; }
        ul { list-style: none; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--border-secondary); border-radius: var(--radius-full); }
        ::selection { background: var(--primary-light); color: var(--primary); }

        /* Sidebar & Topbar - identical to dashboard */
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
            color: var(--text-primary); white-space: nowrap; overflow: hidden;
        }
                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
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
        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: var(--space-3) var(--space-3); }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px; font-weight: var(--font-semibold);
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-muted); white-space: nowrap;
            transition: all var(--transition-fast);
            margin-top: var(--space-2);
        }
        .sidebar.collapsed .sidebar-section-title { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        .sidebar-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary); font-size: var(--text-sm); font-weight: var(--font-medium);
            transition: all var(--transition-fast);
            white-space: nowrap; cursor: pointer; text-decoration: none; margin-bottom: 1px;
        }
        .sidebar-link:hover:not(.disabled) { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-link.active { background: var(--primary-light); color: var(--primary); font-weight: var(--font-semibold); }
        .sidebar-link.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: auto; }
        .sidebar-link.disabled:hover { background: none; color: var(--text-secondary); }
        .sidebar-link svg { flex-shrink: 0; width: 18px; height: 18px; }
        .sidebar-link-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-link-text { opacity: 0; width: 0; overflow: hidden; }
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
        [data-theme="dark"] .topbar { background: rgba(20,22,31,0.85); }
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
        [data-theme="dark"] .theme-toggle .icon-sun { display: none; }
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
        .dropdown-item {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); font-size: var(--text-sm);
            color: var(--text-secondary); transition: all var(--transition-fast); cursor: pointer; text-decoration: none;
        }
        .dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
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

        /* Page content */
        .page-content { flex: 1; padding: var(--space-6); overflow-y: auto; }
        .page-header { margin-bottom: var(--space-6); }
        .page-title { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); letter-spacing: -0.02em; }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: var(--space-1); }

        /* Stats grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: var(--space-5); margin-bottom: var(--space-6); }
        .stat-card {
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); padding: var(--space-5);
            transition: all var(--transition-base); position: relative; overflow: hidden;
        }
        .stat-card:hover { box-shadow: var(--shadow-md); border-color: var(--border-secondary); transform: translateY(-1px); }
        .stat-card::after { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
        .stat-card.purple::after { background: var(--primary); }
        .stat-card.green::after { background: var(--success); }
        .stat-card.amber::after { background: var(--warning); }
        .stat-card.blue::after { background: var(--info); }
        .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3); }
        .stat-icon { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg); }
        .stat-icon.purple { background: var(--primary-light); color: var(--primary); }
        .stat-icon.green { background: var(--success-light); color: var(--success-dark); }
        .stat-icon.amber { background: var(--warning-light); color: var(--warning-dark); }
        .stat-icon.blue { background: var(--info-light); color: var(--info-dark); }
        .stat-value { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); margin-bottom: 2px; letter-spacing: -0.01em; }
        .stat-label { font-size: var(--text-sm); color: var(--text-secondary); }
        .stat-sub { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }

        /* Filter bar */
        .filter-bar {
            display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3);
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); padding: var(--space-4) var(--space-5);
            margin-bottom: var(--space-6);
        }
        .filter-bar .filter-group { display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; }
        .filter-bar .filter-label { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-secondary); }
        .filter-bar select, .filter-bar input[type="date"] {
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            background: var(--bg-tertiary);
            font-size: var(--text-sm);
            color: var(--text-primary);
            outline: none;
            transition: border-color var(--transition-fast);
        }
        .filter-bar select:focus, .filter-bar input[type="date"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .filter-bar .btn-filter {
            padding: var(--space-2) var(--space-5);
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: var(--font-semibold);
            font-size: var(--text-sm);
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        .filter-bar .btn-filter:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }

        /* Charts grid */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-6); margin-bottom: var(--space-6); }
        .chart-card { background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-lg); padding: var(--space-5); overflow: hidden; }
        .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3); }
        .chart-title { font-size: var(--text-base); font-weight: var(--font-semibold); color: var(--text-primary); }
        .chart-wrapper { position: relative; height: 280px; width: 100%; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }

        /* Table */
        .table-container { overflow-x: auto; border-radius: var(--radius-lg); border: 1px solid var(--border-primary); background: var(--bg-secondary); margin-bottom: var(--space-6); }
        .data-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
        .data-table thead th { padding: var(--space-3) var(--space-4); font-weight: var(--font-semibold); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); background: var(--bg-tertiary); border-bottom: 1px solid var(--border-primary); text-align: left; }
        .data-table tbody td { padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); vertical-align: middle; }
        .data-table tbody tr:hover { background: var(--bg-hover); }
        .text-success { color: var(--success-dark); }
        .text-error { color: var(--error-dark); }

        /* Restriction banner */
        .restriction-banner {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .restriction-banner.warning { background: #fef2f2; border-left-color: #dc2626; }
        .restriction-banner .icon { font-size: 28px; }
        .restriction-banner .message { flex: 1; color: #92400e; }
        .restriction-banner.warning .message { color: #991b1b; }
        .restriction-banner .message strong { display: block; font-size: 0.95rem; margin-bottom: 4px; }
        .restriction-banner .contact-btn {
            background: #dc2626;
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .restriction-banner .contact-btn:hover { background: #b91c1c; transform: translateY(-2px); }

        /* Toast */
        .toast-container { position: fixed; top: calc(var(--topbar-height) + var(--space-4)); right: var(--space-4); z-index: var(--z-toast); display: flex; flex-direction: column; gap: var(--space-3); }
        .toast {
            display: flex; align-items: center; gap: var(--space-3); padding: var(--space-4);
            background: var(--bg-secondary); border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary); box-shadow: var(--shadow-xl);
            min-width: 300px; transform: translateX(120%);
            animation: toastSlideIn 0.4s ease forwards;
        }
        .toast.removing { animation: toastSlideOut 0.3s ease forwards; }
        .toast-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .toast-success .toast-icon { background: var(--success-light); color: var(--success); }
        .toast-error .toast-icon { background: var(--error-light); color: var(--error); }
        .toast-info .toast-icon { background: var(--info-light); color: var(--info); }
        .toast-content { flex: 1; }
        .toast-title { font-weight: var(--font-semibold); color: var(--text-primary); }
        .toast-message { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        @keyframes toastSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }

        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; }
        .delay-100 { animation-delay: 100ms; opacity: 0; }
        .delay-200 { animation-delay: 200ms; opacity: 0; }
        .delay-300 { animation-delay: 300ms; opacity: 0; }
        .delay-400 { animation-delay: 400ms; opacity: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: var(--z-fixed); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-xl); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
            .charts-grid { grid-template-columns: 1fr; }
            .topbar-user-info { display: none; }
            .page-content { padding: var(--space-4); }
            .stat-value { font-size: var(--text-2xl); }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .topbar-search { display: none; }
            .topbar { padding: 0 var(--space-3); }
            .page-title { font-size: var(--text-xl); }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/vendor_sidebar.php'; ?>


    <div class="main-content" id="mainContent">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
                </button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                    <input type="text" id="globalSearch" placeholder="Search...">
                </div>
            </div>
            <div class="topbar-actions">
                <?php if (!$storeRestricted && isset($activePlan) && $activePlan): ?>
                <span style="background: var(--primary-light); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; margin-right: 10px;">
                    🚀 <?= htmlspecialchars($activePlan) ?>
                </span>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5" /><line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" /><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" /><line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" /><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" /></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>
                </button>
                <div class="dropdown" id="notificationDropdown">
                    <button class="topbar-btn dropdown-trigger" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                        <span class="topbar-btn-badge"></span>
                    </button>
                    <div class="dropdown-menu" style="width:340px;">
                        <div class="dropdown-header"><h4>Notifications</h4><a onclick="markAllRead()">Mark all read</a></div>
                        <div class="notification-list" id="notificationList"></div>
                    </div>
                </div>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <?php include __DIR__ . '/includes/vendor_user_avatar.php'; ?>
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role">
                                <?php if ($_SESSION['store_name']): ?>
                                    Store: <?= htmlspecialchars($_SESSION['store_name']) ?>
                                <?php else: ?>
                                    <a href="create-store" style="color:var(--primary);">Create Store</a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><polyline points="6 9 12 15 18 9" /></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profile</a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <?php if ($storeRestricted): ?>
                <div class="restriction-banner <?= ($storeStatus === 'inactive' || $storeStatus === 'pending') ? 'warning' : '' ?>">
                    <div class="icon"><?= $storeStatus === 'pending' ? '⏳' : ($storeStatus === 'pending_docs' ? '📄' : ($storeStatus === 'inactive' ? '⛔' : '🔒')) ?></div>
                    <div class="message">
                        <strong>
                            <?php if ($storeStatus === 'pending'): ?>
                                Store Pending Approval
                            <?php elseif ($storeStatus === 'pending_docs'): ?>
                                Documents Under Review
                            <?php elseif ($storeStatus === 'inactive'): ?>
                                Store Suspended
                            <?php else: ?>
                                Subscription Inactive
                            <?php endif; ?>
                        </strong>
                        <p><?= htmlspecialchars($restrictionMessage) ?></p>
                    </div>
                    <a href="contact" class="contact-btn">Contact Us →</a>
                </div>
            <?php endif; ?>

            <?php if (!$storeRestricted): ?>
                <div class="page-header animate-fade-in-up">
                    <h1 class="page-title">📊 Analytics</h1>
                    <p class="page-subtitle">Deep dive into your store's financial performance.</p>
                </div>

                <!-- Filter Bar -->
                <form method="GET" class="filter-bar animate-fade-in-up delay-100" id="filterForm">
                    <div class="filter-group">
                        <span class="filter-label">Range:</span>
                        <select name="range" id="rangeSelect" onchange="toggleCustomDates()">
                            <option value="today" <?= $range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="this_week" <?= $range == 'this_week' ? 'selected' : '' ?>>This Week</option>
                            <option value="this_month" <?= $range == 'this_month' ? 'selected' : '' ?>>This Month</option>
                            <option value="last_30" <?= $range == 'last_30' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="custom" <?= $range == 'custom' ? 'selected' : '' ?>>Custom</option>
                        </select>
                    </div>
                    <div class="filter-group" id="customDates" style="<?= $range == 'custom' ? '' : 'display:none;' ?>">
                        <input type="date" name="start" value="<?= htmlspecialchars($start_date) ?>">
                        <span>to</span>
                        <input type="date" name="end" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <button type="submit" class="btn-filter">Apply</button>
                </form>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card purple animate-fade-in-up delay-200">
                        <div class="stat-header"><div class="stat-icon purple"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
                        <div class="stat-value">₦<?= number_format($summary['revenue'], 2) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card green animate-fade-in-up delay-300">
                        <div class="stat-header"><div class="stat-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div></div>
                        <div class="stat-value"><?= number_format($summary['orders']) ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card amber animate-fade-in-up delay-400">
                        <div class="stat-header"><div class="stat-icon amber"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
                        <div class="stat-value">₦<?= number_format($summary['aov'], 2) ?></div>
                        <div class="stat-label">Avg. Order Value</div>
                    </div>
                    <div class="stat-card blue animate-fade-in-up delay-500">
                        <div class="stat-header"><div class="stat-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2v4M12 22v-4M4 12H2M22 12h-2M6.34 17.66l-1.42 1.42M17.66 6.34l1.42-1.42M6.34 6.34l-1.42-1.42M17.66 17.66l1.42 1.42"/><circle cx="12" cy="12" r="4"/></svg></div></div>
                        <div class="stat-value">₦<?= number_format($summary['profit'], 2) ?></div>
                        <div class="stat-label">Net Profit <span class="stat-sub">(<?= round($summary['profit_margin'], 1) ?>% margin)</span></div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-card animate-fade-in-up delay-300">
                        <div class="chart-header"><h3 class="chart-title">Daily Revenue</h3></div>
                        <div class="chart-wrapper"><canvas id="dailyRevenueChart"></canvas></div>
                    </div>
                    <div class="chart-card animate-fade-in-up delay-400">
                        <div class="chart-header"><h3 class="chart-title">Monthly Revenue (12 months)</h3></div>
                        <div class="chart-wrapper"><canvas id="monthlyRevenueChart"></canvas></div>
                    </div>
                </div>

                <!-- Daily Breakdown Table -->
                <div class="animate-fade-in-up delay-500">
                    <div class="section-heading"><h3>Daily Breakdown</h3></div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Growth (vs prev day)</th></tr></thead>
                            <tbody>
                                <?php if (count($dailyData) > 0): ?>
                                    <?php foreach ($dailyData as $i => $day): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($day['day'])) ?></td>
                                            <td><?= $day['orders'] ?></td>
                                            <td>₦<?= number_format($day['revenue'], 2) ?></td>
                                            <td class="<?= $dailyGrowth[$i] >= 0 ? 'text-success' : 'text-error' ?>">
                                                <?= $i == 0 ? '—' : ($dailyGrowth[$i] >= 0 ? '▲' : '▼') . number_format(abs($dailyGrowth[$i]), 1) . '%' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center;">No orders in this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Monthly Summary -->
                <div class="animate-fade-in-up delay-600">
                    <div class="section-heading"><h3>Monthly Summary</h3></div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead>
                            <tbody>
                                <?php if (count($monthlyData) > 0): ?>
                                    <?php foreach ($monthlyData as $month): ?>
                                        <tr>
                                            <td><?= date('M Y', strtotime($month['month'] . '-01')) ?></td>
                                            <td><?= $month['orders'] ?></td>
                                            <td>₦<?= number_format($month['revenue'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align:center;">No monthly data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- Restricted view -->
                <div class="page-header"><h1 class="page-title">Analytics</h1><p class="page-subtitle">Access to analytics is restricted.</p></div>
                <div style="background: var(--bg-secondary); border-radius: 16px; padding: 60px 20px; text-align: center; margin-top: 24px;">
                    <div style="font-size: 64px; margin-bottom: 16px;">🔒</div>
                    <h3 style="font-size: 20px; margin-bottom: 8px;">Access Denied</h3>
                    <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;"><?= htmlspecialchars($restrictionMessage) ?></p>
                    <?php if ($storeStatus === 'active' && strpos($restrictionMessage, 'subscription') !== false): ?>
                        <a href="subscription" class="btn btn-primary" style="margin-top: 24px; display: inline-block; padding: 10px 24px; background: var(--gradient-primary); color: white; border-radius: var(--radius-md); font-weight: var(--font-semibold);">View Plans</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Data from PHP
        const dailyLabels = <?= json_encode($dailyLabels) ?>;
        const dailyRevenue = <?= json_encode($dailyRevenue) ?>;
        const monthlyLabels = <?= json_encode($monthlyLabels) ?>;
        const monthlyRevenue = <?= json_encode($monthlyRevenue) ?>;
        const storeRestricted = <?= $storeRestricted ? 'true' : 'false' ?>;

        // Toast
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
            toast.innerHTML = `<div class="toast-icon">${icons[type] || icons.info}</div><div class="toast-content"><div class="toast-title">${escapeHtml(title)}</div><div class="toast-message">${escapeHtml(message)}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 300); }, 3500);
        }

        function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

        // Charts
        let dailyChart, monthlyChart;
        function getChartColors() {
            const style = getComputedStyle(document.documentElement);
            return {
                textColor: style.getPropertyValue('--text-muted').trim(),
                gridColor: style.getPropertyValue('--border-primary').trim()
            };
        }

        function createDailyChart() {
            const ctx = document.getElementById('dailyRevenueChart')?.getContext('2d');
            if (!ctx) return;
            const colors = getChartColors();
            const grad = ctx.createLinearGradient(0,0,0,280);
            grad.addColorStop(0,'rgba(99,102,241,0.25)');
            grad.addColorStop(1,'rgba(99,102,241,0.0)');
            if (dailyChart) dailyChart.destroy();
            dailyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dailyLabels.map(d => new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short' })),
                    datasets: [{
                        label: 'Revenue (₦)',
                        data: dailyRevenue,
                        borderColor: '#6366f1',
                        backgroundColor: grad,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => '₦' + ctx.parsed.y.toLocaleString() } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: colors.textColor } },
                        y: { beginAtZero: true, ticks: { color: colors.textColor } }
                    }
                }
            });
        }

        function createMonthlyChart() {
            const ctx = document.getElementById('monthlyRevenueChart')?.getContext('2d');
            if (!ctx) return;
            const colors = getChartColors();
            if (monthlyChart) monthlyChart.destroy();
            monthlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyLabels.map(m => {
                        const [y, mo] = m.split('-');
                        return new Date(y, mo-1).toLocaleDateString('en-NG', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Revenue (₦)',
                        data: monthlyRevenue,
                        backgroundColor: 'rgba(99,102,241,0.6)',
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => '₦' + ctx.parsed.y.toLocaleString() } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: colors.textColor } },
                        y: { beginAtZero: true, ticks: { color: colors.textColor } }
                    }
                }
            });
        }

        function updateChartsTheme() {
            createDailyChart();
            createMonthlyChart();
        }

        // Filter: show/hide custom date inputs
        function toggleCustomDates() {
            const sel = document.getElementById('rangeSelect');
            const customDiv = document.getElementById('customDates');
            if (sel.value === 'custom') {
                customDiv.style.display = 'flex';
            } else {
                customDiv.style.display = 'none';
            }
        }

        // Sidebar, theme, logout (same as dashboard)
        function handleLogout() { if(confirm('Are you sure you want to log out?')) window.location.href='logout'; }
        const html=document.documentElement;
        const savedTheme=localStorage.getItem('RD Vendora-theme')||'light';
        html.setAttribute('data-theme',savedTheme);
        document.getElementById('themeToggle')?.addEventListener('click',()=>{
            const next=html.getAttribute('data-theme')==='light'?'dark':'light';
            html.setAttribute('data-theme',next);
            localStorage.setItem('RD Vendora-theme',next);
            updateChartsTheme();
        });

        const sidebar=document.getElementById('sidebar');
        const overlay=document.getElementById('sidebarOverlay');
        document.getElementById('sidebarToggle')?.addEventListener('click',()=>{
            if(window.innerWidth<=768) toggleMobile();
            else sidebar.classList.toggle('collapsed');
        });
        document.getElementById('mobileSidebarToggle')?.addEventListener('click',toggleMobile);
        overlay?.addEventListener('click',toggleMobile);
        function toggleMobile(){
            sidebar.classList.toggle('mobile-open');
            overlay?.classList.toggle('active');
            document.body.style.overflow=sidebar.classList.contains('mobile-open')?'hidden':'';
        }
        window.addEventListener('resize',()=>{
            if(window.innerWidth>768){
                sidebar.classList.remove('mobile-open');
                overlay?.classList.remove('active');
                document.body.style.overflow='';
            }
        });

        // Dropdowns
        document.addEventListener('click',e=>{
            ['userDropdown','notificationDropdown'].forEach(id=>{
                const dd=document.getElementById(id);
                if(dd && !dd.contains(e.target)) dd.classList.remove('open');
                else if(e.target.closest('.dropdown-trigger')) dd?.classList.toggle('open');
            });
        });

        // Notifications (dummy)
        let notifications=[
            {id:1,title:'New Order Received',text:'Order #1287 has been placed.',time:'2 minutes ago',unread:true},
            {id:2,title:'Payment Confirmed',text:'Payment of $245.00 confirmed.',time:'15 minutes ago',unread:true}
        ];
        function renderNotifications(){
            const list=document.getElementById('notificationList'), badge=document.querySelector('.topbar-btn-badge');
            if(!list) return;
            const unread=notifications.filter(n=>n.unread).length;
            if(badge) badge.style.display=unread?'block':'none';
            list.innerHTML=notifications.map(n=>`<div class="notification-item ${n.unread?'unread':''}" onclick="markNotificationRead(${n.id})">${n.unread?'<div class="notification-dot"></div>':'<div style="width:8px;"></div>'}<div class="notification-content"><div class="notification-title">${escapeHtml(n.title)}</div><div class="notification-text">${escapeHtml(n.text)}</div><div class="notification-time">${escapeHtml(n.time)}</div></div></div>`).join('');
        }
        function markNotificationRead(id){ const n=notifications.find(x=>x.id===id); if(n) n.unread=false; renderNotifications(); }
        function markAllRead(){ notifications.forEach(n=>n.unread=false); renderNotifications(); }
        renderNotifications();

        // Initialize charts only if not restricted
        if(!storeRestricted) {
            createDailyChart();
            createMonthlyChart();
        }
    </script>
</body>
</html>