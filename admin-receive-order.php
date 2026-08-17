<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/admin_auth.php'; // RBAC permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication – no hardcoded email fallback
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="index.php">Go Home</a></div>');
}

// Permission check for orders
if (!adminHasPermission('orders', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view orders.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// ---------- DETECT ORDER TABLE COLUMNS ----------
$columns_check = $conn->query("SHOW COLUMNS FROM orders");
$order_columns = [];
while ($col = $columns_check->fetch_assoc()) {
    $order_columns[] = $col['Field'];
}

// Detect name column
$name_col = null;
foreach (['user_name', 'shipping_name', 'billing_name', 'name', 'fullname'] as $cand) {
    if (in_array($cand, $order_columns)) {
        $name_col = $cand;
        break;
    }
}
if (!$name_col) $name_col = 'user_id';

// Detect email column
$email_col = null;
foreach (['user_email', 'email', 'shipping_email', 'billing_email'] as $cand) {
    if (in_array($cand, $order_columns)) {
        $email_col = $cand;
        break;
    }
}
if (!$email_col) $email_col = 'user_id';

// Detect phone column – includes 'user_phone'
$phone_col = null;
foreach (['user_phone', 'shipping_phone', 'billing_phone', 'phone', 'phone_number', 'contact_phone'] as $cand) {
    if (in_array($cand, $order_columns)) {
        $phone_col = $cand;
        break;
    }
}

// Detect total column
$total_col = 'total_amount';
if (!in_array('total_amount', $order_columns)) {
    if (in_array('order_total', $order_columns)) $total_col = 'order_total';
    elseif (in_array('amount', $order_columns)) $total_col = 'amount';
    elseif (in_array('grand_total', $order_columns)) $total_col = 'grand_total';
    else $total_col = 'total_amount';
}

// ---------- BUILD QUERY ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$paymentFilter = isset($_GET['payment']) ? trim($_GET['payment']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Base SQL – only u.fullname from users; email and phone come from orders table
$sql = "SELECT o.*, s.store_name, u.fullname as user_fullname 
        FROM orders o
        LEFT JOIN stores s ON o.store_id = s.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";
$countSql = "SELECT COUNT(*) as total FROM orders o WHERE 1=1";
$params = [];
$types = "";

// Add filters
if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR o.$name_col LIKE ? OR o.$email_col LIKE ?)";
    $countSql .= " AND (o.order_number LIKE ? OR o.$name_col LIKE ? OR o.$email_col LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if (!empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $countSql .= " AND o.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if (!empty($paymentFilter)) {
    $sql .= " AND o.payment_status = ?";
    $countSql .= " AND o.payment_status = ?";
    $params[] = $paymentFilter;
    $types .= "s";
}

// Count total
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalOrders = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Fetch paginated orders
$sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Check if order_items table exists
$has_order_items = $conn->query("SHOW TABLES LIKE 'order_items'")->num_rows > 0;

$orders = [];
while ($row = $result->fetch_assoc()) {
    // Customer name: user_fullname else order column else Guest
    $customerName = !empty($row['user_fullname']) ? $row['user_fullname'] : ($row[$name_col] ?? 'Guest');
    $row['customer_name'] = $customerName;

    // Customer email: order column (if detected) else ''
    $customerEmail = $email_col && isset($row[$email_col]) ? $row[$email_col] : '';
    $row['customer_email'] = $customerEmail;

    // Customer phone: order column (if detected) else ''
    $customerPhone = $phone_col && isset($row[$phone_col]) ? $row[$phone_col] : '';
    $row['customer_phone'] = $customerPhone;

    // Fetch items
    if (isset($row['items']) && !empty($row['items'])) {
        $row['items'] = json_decode($row['items'], true) ?? [];
    } elseif ($has_order_items) {
        $items_stmt = $conn->prepare("SELECT product_name as name, quantity as qty, price FROM order_items WHERE order_id = ?");
        $items_stmt->bind_param("i", $row['id']);
        $items_stmt->execute();
        $items_res = $items_stmt->get_result();
        $row['items'] = [];
        while ($item = $items_res->fetch_assoc()) {
            $row['items'][] = $item;
        }
        $items_stmt->close();
    } else {
        $row['items'] = [];
    }

    // Calculate total
    if (!isset($row['total_amount']) || $row['total_amount'] == 0) {
        $calc = 0;
        foreach ($row['items'] as $item) {
            $calc += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        }
        $row['total'] = $calc;
    } else {
        $row['total'] = $row['total_amount'];
    }

    // Order number fallback
    if (!isset($row['order_number']) || empty($row['order_number'])) {
        $row['order_number'] = 'ORD-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
    }

    $orders[] = $row;
}
$stmt->close();
$conn->close();

$totalPages = ceil($totalOrders / $limit);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Orders - Admin | RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== GLOBAL VARIABLES ========== */
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
            --info: #3b82f6;
            --info-light: #dbeafe;
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
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: var(--font-sans); font-size: 0.9375rem; background: var(--bg-primary); color: var(--text-primary); line-height: 1.5; transition: background var(--transition), color var(--transition); overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; }
        .sidebar { position: fixed; left:0; top:0; bottom:0; width: var(--sidebar-width); background: var(--bg-secondary); border-right: 1px solid var(--border-primary); display: flex; flex-direction: column; z-index: 300; transition: width var(--transition), transform var(--transition); overflow: hidden; }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; height: var(--topbar-height); border-bottom: 1px solid var(--border-primary); }
        .nav-logo { display: flex; align-items: center; gap: 0.75rem; font-weight: 800; font-size: 1.125rem; white-space: nowrap; }
        .nav-logo-icon { width: 32px; height: 32px; background: var(--gradient-primary); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; }
        .sidebar-toggle { width: 28px; height: 28px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
        .sidebar-toggle:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .sidebar-menu { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; }
        .sidebar-section-title { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); padding: 0.5rem 1rem; letter-spacing: 0.5px; }
        .sidebar-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem; border-radius: var(--radius); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; transition: var(--transition); margin-bottom: 2px; cursor: pointer; }
        .sidebar-item:hover, .sidebar-item.active { background: var(--primary-light); color: var(--primary); }
        .sidebar.collapsed .sidebar-item span, .sidebar.collapsed .sidebar-section-title, .sidebar.collapsed .nav-logo span { opacity: 0; width: 0; overflow: hidden; }
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition); min-height: 100vh; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        .dash-navbar { position: fixed; top:0; right:0; left: var(--sidebar-width); height: var(--topbar-height); background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border-primary); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; z-index: 200; transition: left var(--transition); }
        [data-theme="dark"] .dash-navbar { background: rgba(15,23,42,0.8); }
        .dash-search { display: flex; align-items: center; gap: 0.5rem; background: var(--bg-tertiary); padding: 0.4rem 1rem; border-radius: var(--radius-lg); width: 280px; }
        .dash-search input { background: none; border: none; outline: none; font-size: 0.875rem; width: 100%; }
        .dash-actions { display: flex; align-items: center; gap: 1rem; }
        .dash-btn { width: 38px; height: 38px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; background: var(--bg-tertiary); color: var(--text-secondary); }
        .dash-user { display: flex; align-items: center; gap: 0.75rem; padding: 0.25rem 0.5rem 0.25rem 0.25rem; border-radius: var(--radius-lg); cursor: pointer; }
        .dash-user img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .dash-user-info .name { font-size: 0.875rem; font-weight: 500; }
        .dash-user-info .role { font-size: 0.7rem; color: var(--text-muted); }
        .dropdown { position: relative; }
        .dropdown-menu { position: absolute; top: calc(100% + 8px); right: 0; min-width: 180px; background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); opacity: 0; pointer-events: none; transform: translateY(-8px); transition: var(--transition); }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; color: var(--text-secondary); }
        .dropdown-item:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .page-content { padding: 1.5rem 2rem; margin-top: var(--topbar-height); }
        .page-header { margin-bottom: 1.5rem; }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        .filters-bar { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; align-items: center; justify-content: space-between; }
        .filters-left { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
        .search-box { position: relative; width: 260px; }
        .search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; }
        .search-box input { width: 100%; padding: 0.5rem 0.75rem 0.5rem 2rem; background: var(--bg-tertiary); border: 1px solid var(--border-primary); border-radius: var(--radius-md); font-size: 0.875rem; color: var(--text-primary); }
        .filter-select { padding: 0.5rem 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border-primary); border-radius: var(--radius-md); font-size: 0.875rem; color: var(--text-primary); }
        .table-container { background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-xl); overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; min-width: 900px; }
        .data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border-primary); }
        .data-table th { background: var(--bg-tertiary); color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px; }
        .data-table tbody tr:hover { background: var(--bg-tertiary); }
        .badge { display: inline-flex; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
        .badge-success { background: var(--success-light); color: #047857; }
        .badge-warning { background: var(--warning-light); color: #92400e; }
        .badge-info { background: var(--info-light); color: #1e40af; }
        .badge-error { background: var(--error-light); color: #dc2626; }
        .btn-sm { padding: 0.2rem 0.7rem; border-radius: var(--radius); background: var(--bg-tertiary); border: 1px solid var(--border-primary); cursor: pointer; font-size: 0.75rem; }
        .btn-sm:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; }
        .page-btn { padding: 0.3rem 0.8rem; border-radius: var(--radius); background: var(--bg-tertiary); border: 1px solid var(--border-primary); cursor: pointer; }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; visibility: hidden; opacity: 0; transition: 0.2s; }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-container { background: var(--bg-secondary); border-radius: var(--radius-xl); max-width: 700px; width: 90%; padding: 1.5rem; box-shadow: var(--shadow-lg); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-primary); }
        .modal-title { font-size: 1.25rem; font-weight: 700; }
        .modal-close { cursor: pointer; font-size: 1.5rem; line-height: 1; color: var(--text-muted); }
        .modal-body { max-height: 60vh; overflow-y: auto; }
        .order-detail-row { display: flex; padding: 0.5rem 0; border-bottom: 1px solid var(--border-primary); }
        .order-detail-label { width: 140px; font-weight: 600; color: var(--text-secondary); }
        .order-detail-value { flex: 1; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); width: var(--sidebar-width); } .sidebar.mobile-open { transform: translateX(0); } .main-content { margin-left: 0 !important; } .dash-navbar { left: 0; padding: 0 1rem; } .page-content { padding: 1rem; } .filters-left { flex-direction: column; align-items: stretch; } .search-box { width: 100%; } }
    </style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="nav-logo"><div class="nav-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div><span>RD Vendora</span></a>
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
        <a href="admin-receive-order.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>All Orders</span></a>
        <a href="admin-transport.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Transport Orders</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<!-- Main content -->
<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="searchInput" placeholder="Search orders..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%236366f1'/%3E%3Ctext x='50' y='67' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3EA%3C/text%3E%3C/svg%3E" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()">Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-content">
        <div class="page-header">
            <h1 class="page-title">All Marketplace Orders</h1>
            <p class="page-subtitle">View and manage all orders from all stores.</p>
        </div>

        <div class="filters-bar">
            <div class="filters-left">
                <div class="search-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="orderSearch" placeholder="Order # or customer name..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="processing" <?= $statusFilter == 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $statusFilter == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $statusFilter == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <select class="filter-select" id="paymentFilter">
                    <option value="">All Payments</option>
                    <option value="paid" <?= $paymentFilter == 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= $paymentFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="refunded" <?= $paymentFilter == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
                <button class="btn-sm" id="resetFilters">Reset</button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Store</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="9" style="text-align:center; padding:2rem;">No orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                                <td><?= htmlspecialchars($order['store_name'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                                <td><?= count($order['items']) ?> item<?= count($order['items']) != 1 ? 's' : '' ?></td>
                                <td style="font-weight:600;">₦<?= number_format($order['total'], 2) ?></td>
                                <td><span class="badge badge-<?= ($order['payment_status'] ?? 'pending') == 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($order['payment_status'] ?? 'pending') ?></span></td>
                                <td><span class="badge badge-<?= $order['status'] == 'delivered' ? 'success' : ($order['status'] == 'cancelled' ? 'error' : 'info') ?>"><?= ucfirst($order['status'] ?? 'pending') ?></span></td>
                                <td><button class="btn-sm view-order" data-id="<?= $order['id'] ?>">View</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&payment=<?= urlencode($paymentFilter) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Order Details</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="orderModalBody">
            <div style="text-align:center;">Loading...</div>
        </div>
    </div>
</div>

<script>
    // Theme & sidebar
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    const themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.textContent = savedTheme === 'light' ? '🌙' : '☀️';
        themeBtn.addEventListener('click', () => {
            const newTheme = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('RD Vendora-theme', newTheme);
            themeBtn.textContent = newTheme === 'light' ? '🌙' : '☀️';
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

    // Filters
    const orderSearch = document.getElementById('orderSearch');
    const statusFilter = document.getElementById('statusFilter');
    const paymentFilter = document.getElementById('paymentFilter');
    const resetBtn = document.getElementById('resetFilters');
    function applyFilters() {
        let url = 'admin-receive-order.php?';
        if (orderSearch.value) url += 'search=' + encodeURIComponent(orderSearch.value) + '&';
        if (statusFilter.value) url += 'status=' + encodeURIComponent(statusFilter.value) + '&';
        if (paymentFilter.value) url += 'payment=' + encodeURIComponent(paymentFilter.value) + '&';
        window.location.href = url;
    }
    orderSearch.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    paymentFilter.addEventListener('change', applyFilters);
    resetBtn.addEventListener('click', () => { window.location.href = 'admin-receive-order.php'; });

    // View order modal
    const modal = document.getElementById('orderModal');
    function closeModal() { modal.classList.remove('active'); document.body.style.overflow = ''; }
    function escapeHtml(str) { return String(str).replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); }
    document.querySelectorAll('.view-order').forEach(btn => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('orderModalBody').innerHTML = '<div style="text-align:center;">Loading...</div>';
            try {
                const res = await fetch('admin-get-order.php?id=' + orderId);
                const order = await res.json();
                if (order.error) throw new Error(order.error);
                let itemsHtml = '';
                if (order.items && order.items.length) {
                    itemsHtml = '<ul>' + order.items.map(i => `<li>${escapeHtml(i.name)} x ${i.qty} – ₦${(i.price * i.qty).toFixed(2)}</li>`).join('') + '</ul>';
                } else {
                    itemsHtml = '<p>No items found</p>';
                }
                let address = '';
                if (order.user_address) address = order.user_address;
                else if (order.shipping_address) address = order.shipping_address;
                else if (order.address) address = order.address;
                if (order.city) address += (address ? ', ' : '') + order.city;
                if (order.state) address += (address ? ', ' : '') + order.state;
                if (order.zip) address += (address ? ' ' : '') + order.zip;
                address = address || 'Not provided';
                document.getElementById('orderModalBody').innerHTML = `
                    <div class="order-detail-row"><div class="order-detail-label">Order #</div><div class="order-detail-value">${escapeHtml(order.order_number)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Customer</div><div class="order-detail-value">${escapeHtml(order.user_name || 'Guest')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Email</div><div class="order-detail-value">${escapeHtml(order.user_email || 'N/A')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Phone</div><div class="order-detail-value">${escapeHtml(order.user_phone || 'N/A')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Store</div><div class="order-detail-value">${escapeHtml(order.store_name || 'N/A')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Date</div><div class="order-detail-value">${new Date(order.created_at).toLocaleString()}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Total</div><div class="order-detail-value">₦${parseFloat(order.total).toFixed(2)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Payment Status</div><div class="order-detail-value">${escapeHtml(order.payment_status)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Order Status</div><div class="order-detail-value">${escapeHtml(order.status)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Address</div><div class="order-detail-value">${escapeHtml(address)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Items</div><div class="order-detail-value">${itemsHtml}</div></div>
                `;
            } catch (err) {
                document.getElementById('orderModalBody').innerHTML = '<div style="color:red;">Error loading order details: ' + err.message + '</div>';
            }
        });
    });
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    window.closeModal = closeModal;

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='logout.php'; }
</script>
</body>
</html>