<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/admin_auth.php';   // <-- permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@rdvendora.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="index.php">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR DASHBOARD ----------
if (!adminHasPermission('dashboard', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view the dashboard.</p><a href="index.php">Go Home</a></div>');
}

// ---------- STATS FROM DATABASE (LIVE) ----------
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
$totalStores = $conn->query("SELECT COUNT(*) as count FROM stores")->fetch_assoc()['count'] ?? 0;
$totalProducts = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'] ?? 0;
$totalOrder = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$totalTransportOrder = $conn->query("SELECT COUNT(*) as count FROM transport_notifications")->fetch_assoc()['count'] ?? 0;

// Total revenue from completed orders
$totalRevenue = 0;
$revenueResult = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
if ($revenueResult) {
    $totalRevenue = $revenueResult->fetch_assoc()['total'] ?? 0;
}

// ---------- CHART DATA (LAST 7 DAYS) ----------
$chartLabels = [];
$chartRevenue = [];
$chartUsers = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $displayDate = date('M d', strtotime($date));
    $chartLabels[] = $displayDate;
    
    // Daily revenue from completed orders
    $dailyRevenue = 0;
    $revStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as daily_total FROM orders WHERE status = 'completed' AND DATE(created_at) = ?");
    $revStmt->bind_param("s", $date);
    $revStmt->execute();
    $revRow = $revStmt->get_result()->fetch_assoc();
    $dailyRevenue = $revRow['daily_total'] ?? 0;
    $revStmt->close();
    $chartRevenue[] = $dailyRevenue;
    
    // Daily new users
    $dailyUsers = 0;
    $userStmt = $conn->prepare("SELECT COUNT(*) as daily_count FROM users WHERE DATE(created_at) = ?");
    $userStmt->bind_param("s", $date);
    $userStmt->execute();
    $userRow = $userStmt->get_result()->fetch_assoc();
    $dailyUsers = $userRow['daily_count'] ?? 0;
    $userStmt->close();
    $chartUsers[] = $dailyUsers;
}

// Calculate percentage changes (vs previous month)
$lastMonthRevenue = 0;
$prevMonthResult = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed' AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
if ($prevMonthResult) {
    $lastMonthRevenue = $prevMonthResult->fetch_assoc()['total'] ?? 0;
}
$revenueChange = $lastMonthRevenue > 0 ? round(($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100) : 0;

$lastMonthUsers = 0;
$prevUserResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
if ($prevUserResult) {
    $lastMonthUsers = $prevUserResult->fetch_assoc()['count'] ?? 0;
}
$userChange = $lastMonthUsers > 0 ? round(($totalUsers - $lastMonthUsers) / $lastMonthUsers * 100) : 0;

$lastMonthStores = 0;
$prevStoreResult = $conn->query("SELECT COUNT(*) as count FROM stores WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
if ($prevStoreResult) {
    $lastMonthStores = $prevStoreResult->fetch_assoc()['count'] ?? 0;
}
$storeChange = $lastMonthStores > 0 ? round(($totalStores - $lastMonthStores) / $lastMonthStores * 100) : 0;

$lastMonthProducts = 0;
$prevProductResult = $conn->query("SELECT COUNT(*) as count FROM products WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
if ($prevProductResult) {
    $lastMonthProducts = $prevProductResult->fetch_assoc()['count'] ?? 0;
}
$productChange = $lastMonthProducts > 0 ? round(($totalProducts - $lastMonthProducts) / $lastMonthProducts * 100) : 0;

$lastMonthOrder = 0;
$prevOrderResult = $conn->query("SELECT COUNT(*) as count FROM orders WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
if ($prevOrderResult) {
    $lastMonthOrder = $prevOrderResult->fetch_assoc()['count'] ?? 0;
}
$OrderChange = $lastMonthOrder > 0 ? round(($totalOrder - $lastMonthOrder) / $lastMonthOrder * 100) : 0;

$lastMonthTransportOrder = 0;
$prevTransportOrderResult = $conn->query("SELECT COUNT(*) as count FROM transport_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
if ($prevTransportOrderResult) {
    $lastMonthTransportOrder = $prevTransportOrderResult->fetch_assoc()['count'] ?? 0;
}
$TransportOrderChange = $lastMonthTransportOrder > 0 ? round(($totalTransportOrder - $lastMonthTransportOrder) / $lastMonthTransportOrder * 100) : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* ========== GLOBAL VARIABLES (unchanged from original) ========== */
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
        
        /* Sidebar (same as original) */
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
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition);
            min-height: 100vh;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        
        /* Topbar */
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
        
        /* Page content */
        .page-header {
            padding: 1.5rem 2rem 0.5rem 2rem;
            margin-top: var(--topbar-height);
        }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        
        /* Stats */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem; padding: 1.5rem 2rem;
        }
        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: var(--gradient-primary); opacity: 0; transition: opacity var(--transition);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .stat-card:hover::before { opacity: 1; }
        .stat-title { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; font-weight: 600; }
        .stat-value { font-size: 1.6rem; font-weight: 800; margin-top: 0.5rem; color: var(--text-primary); }
        .stat-change { font-size: 0.75rem; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.25rem; }
        .stat-change.up { color: var(--success); }
        .stat-change.down { color: var(--error); }
        
        /* Charts */
        .charts-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 1.5rem; padding: 0 2rem 1.5rem;
        }
        @media (max-width: 1024px) { .charts-grid { grid-template-columns: 1fr; } }
        .chart-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
        }
        .chart-card:hover { box-shadow: var(--shadow-md); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .chart-title { font-weight: 700; font-size: 1rem; }
        .chart-container { height: 300px; position: relative; }
        
        /* Mobile */
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); z-index:299; display:none; backdrop-filter: blur(4px); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 200px; }
            .mobile-sidebar-toggle { display: flex; }
            .stats-grid, .charts-grid { padding: 1rem; margin: 1rem; }
            .page-header { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="nav-logo">
            <div class="nav-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
            <span>RD Vendora</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-section-title">Platform</div>
        <a href="admin.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
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
        <a href="admin-marketplace-design.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Marketplace Design</span></a>
        <a href="admin-documents.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span>Document Review</span></a>
         <div class="sidebar-section-title">Analytics</div>
        <a href="admin-user-activity.php" class="sidebar-item "><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>User Activity</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="adminsettings.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Settings</span></a>
        <a href="dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search platform..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Platform Dashboard</h1>
        <p class="page-subtitle">Overview of the entire RD Vendora ecosystem</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Total Users</div>
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-change <?= $userChange >= 0 ? 'up' : 'down' ?>">
                <?= $userChange >= 0 ? '↑' : '↓' ?> <?= abs($userChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Active Stores</div>
            <div class="stat-value"><?= number_format($totalStores) ?></div>
            <div class="stat-change <?= $storeChange >= 0 ? 'up' : 'down' ?>">
                <?= $storeChange >= 0 ? '↑' : '↓' ?> <?= abs($storeChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Products</div>
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-change <?= $productChange >= 0 ? 'up' : 'down' ?>">
                <?= $productChange >= 0 ? '↑' : '↓' ?> <?= abs($productChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Orders</div>
            <div class="stat-value"><?= number_format($totalOrder) ?></div>
            <div class="stat-change <?= $productChange >= 0 ? 'up' : 'down' ?>">
                <?= $productChange >= 0 ? '↑' : '↓' ?> <?= abs($productChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Order Transport</div>
            <div class="stat-value"><?= number_format($totalTransportOrder) ?></div>
            <div class="stat-change <?= $productChange >= 0 ? 'up' : 'down' ?>">
                <?= $productChange >= 0 ? '↑' : '↓' ?> <?= abs($productChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Platform Revenue</div>
            <div class="stat-value">₦<?= number_format($totalRevenue, 2) ?></div>
            <div class="stat-change <?= $revenueChange >= 0 ? 'up' : 'down' ?>">
                <?= $revenueChange >= 0 ? '↑' : '↓' ?> <?= abs($revenueChange) ?>% vs last month
            </div>
        </div>
        
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Revenue Trend (Last 7 days)</h3>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">User Acquisition (Last 7 days)</h3>
            </div>
            <div class="chart-container">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // Live data passed from PHP
    const revenueData = <?= json_encode($chartRevenue) ?>;
    const userData = <?= json_encode($chartUsers) ?>;
    const labels = <?= json_encode($chartLabels) ?>;

    // Theme handling
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
            // Recreate charts to adapt to new theme colors
            createCharts();
        });
    }

    // Sidebar & mobile (same as original)
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

    // Dropdown
    const userDD = document.getElementById('userDropdown');
    if (userDD) {
        const trigger = userDD.querySelector('.dropdown-trigger');
        trigger.addEventListener('click', (e) => { e.stopPropagation(); userDD.classList.toggle('open'); });
        document.addEventListener('click', () => userDD.classList.remove('open'));
    }

    // Chart creation function (reusable for theme change)
    let revenueChart, userChart;
    function createCharts() {
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        const userCtx = document.getElementById('userGrowthChart')?.getContext('2d');
        if (revenueCtx) {
            if (revenueChart) revenueChart.destroy();
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (₦)',
                        data: revenueData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '₦' + v.toLocaleString() } }
                    }
                }
            });
        }
        if (userCtx) {
            if (userChart) userChart.destroy();
            userChart = new Chart(userCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'New Users',
                        data: userData,
                        backgroundColor: '#10b981',
                        borderRadius: 8,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    }

    // Initial chart creation
    createCharts();

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='logout.php'; }
</script>
</body>
</html>