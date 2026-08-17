<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/admin_auth.php';   // permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="index.php">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR CUSTOMERS PAGE ----------
if (!adminHasPermission('customers', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view customers.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// Fetch all unique customers from orders table
// We group by email or user_id (if exists) to avoid duplicates
$sql = "SELECT 
            user_email,
            MAX(user_name) as user_name,
            MAX(user_phone) as user_phone,
            MAX(user_address) as user_address,
            COUNT(*) as total_orders,
            SUM(total_amount) as total_spent,
            MAX(created_at) as last_order_date,
            GROUP_CONCAT(DISTINCT order_ref ORDER BY id DESC SEPARATOR ', ') as order_refs
        FROM orders 
        WHERE user_email IS NOT NULL AND user_email != ''
        GROUP BY user_email
        ORDER BY last_order_date DESC";

$customers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$totalCustomers = count($customers);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== SAME STYLES AS admin.php ========== */
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
        
        /* Sidebar */
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
        
        /* Customers Table */
        .customers-container {
            padding: 1.5rem 2rem;
        }
        .customers-table-wrapper {
            overflow-x: auto;
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-primary);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }
        th {
            background: var(--bg-tertiary);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }
        tr:hover td {
            background: var(--bg-tertiary);
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary);
        }
        .btn-view {
            background: transparent;
            border: 1px solid var(--border-primary);
            padding: 0.3rem 0.8rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-view:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .order-list {
            font-size: 0.75rem;
            max-width: 200px;
            white-space: normal;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 160px; }
            .customers-container { padding: 1rem; }
            th, td { padding: 0.75rem; }
        }
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); z-index:299; display:none; backdrop-filter: blur(4px); }
        @media (max-width: 768px) { .mobile-sidebar-toggle { display: flex; } }
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
        <a href="admin-customers.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Customers</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="searchInput" placeholder="Search customers..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Customer Management</h1>
        <p class="page-subtitle">View all customers who have placed orders</p>
    </div>

    <div class="customers-container">
        <div class="customers-table-wrapper">
            <table id="customersTable">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Last Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="8" style="text-align:center;">No customers found.</span></td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr class="customer-row">
                            <td data-label="Name"><?= htmlspecialchars($customer['user_name'] ?: 'N/A') ?></td>
                            <td data-label="Email"><?= htmlspecialchars($customer['user_email']) ?></td>
                            <td data-label="Phone"><?= htmlspecialchars($customer['user_phone'] ?: 'N/A') ?></td>
                            <td data-label="Address"><?= htmlspecialchars($customer['user_address'] ?: 'N/A') ?></td>
                            <td data-label="Orders"><?= $customer['total_orders'] ?> order(s)</td>
                            <td data-label="Total">₦<?= number_format($customer['total_spent'], 2) ?></td>
                            <td data-label="Last Order"><?= date('M j, Y', strtotime($customer['last_order_date'])) ?></td>
                            <td data-label="Actions"><button class="btn-view" onclick="viewCustomerOrders('<?= htmlspecialchars($customer['user_email']) ?>')">View Orders</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Theme toggle
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

    // Sidebar & mobile
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

    // Search filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#customersTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    // View customer orders (simple alert – you can replace with modal or redirect)
    function viewCustomerOrders(email) {
        window.location.href = `admin-receive-order.php?email=${encodeURIComponent(email)}`;
    }

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='logout.php'; }
</script>
</body>
</html>