<?php
session_start();
require_once 'includes/connection.php';

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

// Create notifications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `transport_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `manifest_filename` VARCHAR(255) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `manifest_filename` (`manifest_filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle AJAX requests (mark as read, mark all read)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    if ($action === 'mark_read') {
        $filename = $_POST['filename'] ?? '';
        if ($filename) {
            $stmt = $conn->prepare("INSERT INTO transport_notifications (manifest_filename, is_read) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_read = 1");
            $stmt->bind_param("s", $filename);
            $stmt->execute();
            $stmt->close();
            $response = ['success' => true];
        }
    } elseif ($action === 'mark_all_read') {
        $conn->query("UPDATE transport_notifications SET is_read = 1");
        $response = ['success' => true];
    }
    echo json_encode($response);
    exit;
}

// Handle file deletion
$message = '';
$messageType = '';
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = 'transport_manifests/' . $filename;
    if (file_exists($filepath) && unlink($filepath)) {
        $stmt = $conn->prepare("DELETE FROM transport_notifications WHERE manifest_filename = ?");
        $stmt->bind_param("s", $filename);
        $stmt->execute();
        $stmt->close();
        $message = "Manifest deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Failed to delete manifest.";
        $messageType = "error";
    }
}

// Scan manifests directory
$manifestDir = 'transport_manifests/';
$manifests = [];
$debugInfo = '';

if (!is_dir($manifestDir)) {
    $debugInfo = "⚠️ The folder 'transport_manifests/' does not exist. Create it with write permissions.";
} else {
    $files = scandir($manifestDir);
    $fileCount = 0;
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filepath = $manifestDir . $file;
            // Ensure notification record exists (for old manifests)
            $stmt = $conn->prepare("INSERT IGNORE INTO transport_notifications (manifest_filename) VALUES (?)");
            $stmt->bind_param("s", $file);
            $stmt->execute();
            $stmt->close();
            // Get read status
            $stmt = $conn->prepare("SELECT is_read FROM transport_notifications WHERE manifest_filename = ?");
            $stmt->bind_param("s", $file);
            $stmt->execute();
            $readRes = $stmt->get_result();
            $isRead = $readRes->fetch_assoc()['is_read'] ?? 0;
            $stmt->close();

            $manifests[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'path' => $filepath,
                'is_read' => $isRead
            ];
            $fileCount++;
        }
    }
    usort($manifests, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    if ($fileCount == 0) {
        $debugInfo = "📭 No manifest files found in 'transport_manifests/'.";
    }
}

$unreadCount = 0;
$stmt = $conn->query("SELECT COUNT(*) as cnt FROM transport_notifications WHERE is_read = 0");
if ($stmt) {
    $unreadCount = $stmt->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Orders - RD Vendora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* All CSS styles remain exactly as in your original admin-transport.php */
        /* (Kept from previous answer – for brevity, I assume you have them; I'll include a minimal but functional version) */
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
        
        /* Sidebar (same as before) */
        .sidebar { position: fixed; left:0; top:0; bottom:0; width: var(--sidebar-width); background: var(--bg-secondary); border-right: 1px solid var(--border-primary); display: flex; flex-direction: column; z-index: 300; transition: width var(--transition), transform var(--transition); overflow: hidden; }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; height: var(--topbar-height); border-bottom: 1px solid var(--border-primary); }
        .nav-logo { display: flex; align-items: center; gap: 0.75rem; font-weight: 800; font-size: 1.125rem; white-space: nowrap; }
        .nav-logo-icon { width: 32px; height: 32px; background: var(--gradient-primary); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; }
        .sidebar-toggle { width: 28px; height: 28px; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
        .sidebar-toggle:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .sidebar-menu { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; }
        .sidebar-section-title { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); padding: 0.5rem 1rem; letter-spacing: 0.5px; }
        .sidebar-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem; border-radius: var(--radius); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; transition: var(--transition); margin-bottom: 2px; cursor: pointer; position: relative; }
        .sidebar-item:hover, .sidebar-item.active { background: var(--primary-light); color: var(--primary); }
        .sidebar-item .badge { margin-left: auto; background: var(--error); color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 20px; }
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
        
        .page-header { padding: 1.5rem 2rem 0.5rem 2rem; margin-top: var(--topbar-height); }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        .alert { margin: 1rem 2rem 0; padding: 0.75rem 1rem; border-radius: var(--radius-lg); font-size: 0.875rem; }
        .alert-success { background: var(--success-light); color: #047857; border: 1px solid #bbf7d0; }
        .alert-error { background: var(--error-light); color: #dc2626; border: 1px solid #fecaca; }
        .debug-info { margin: 0 2rem; padding: 0.5rem 1rem; background: var(--info-light); color: var(--info-dark); border-radius: var(--radius); font-size: 0.8rem; }
        .toolbar { margin: 1.5rem 2rem 0; display: flex; justify-content: flex-end; gap: 0.5rem; }
        .table-container { background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-xl); margin: 1.5rem 2rem; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid var(--border-primary); }
        .data-table th { font-weight: 600; color: var(--text-secondary); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .data-table tr:hover td { background: var(--bg-tertiary); }
        .btn-sm { padding: 0.375rem 1rem; border-radius: var(--radius); font-size: 0.75rem; font-weight: 500; background: var(--bg-tertiary); color: var(--text-secondary); transition: var(--transition); cursor: pointer; border: none; }
        .btn-sm:hover { background: var(--primary); color: white; transform: translateY(-1px); }
        .btn-danger { background: var(--error-light); color: #dc2626; }
        .btn-danger:hover { background: var(--error); color: white; }
        .new-badge { background: var(--error); color: white; font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; margin-left: 8px; }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
        .modal-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; visibility: hidden; opacity: 0; transition: var(--transition); }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-container { background: var(--bg-secondary); border-radius: var(--radius-xl); max-width: 800px; width: 90%; padding: 1.5rem; box-shadow: var(--shadow-lg); transform: scale(0.95); transition: transform var(--transition); }
        .modal-overlay.active .modal-container { transform: scale(1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-title { font-size: 1.25rem; font-weight: 700; }
        .modal-close { cursor: pointer; font-size: 1.5rem; line-height: 1; color: var(--text-muted); }
        .modal-body { margin-bottom: 1.5rem; max-height: 60vh; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 0.875rem; background: var(--bg-tertiary); padding: 1rem; border-radius: var(--radius); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; }
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); z-index:299; display:none; backdrop-filter: blur(4px); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 200px; }
            .mobile-sidebar-toggle { display: flex; }
            .alert, .table-container, .toolbar { margin: 1rem; }
            .page-header { padding: 1rem; }
        }
    </style>
</head>
<body>
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
        <a href="admin-about.php" class="sidebar-item "><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>About Page</span></a>
        <a href="admin-chat.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Chat</span></a>
        <a href="admin-receive-order.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>All Orders</span></a>
        <a href="admin-transport.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Transport Orders</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="searchInput" placeholder="Search manifests..."></div>
        <div class="dash-actions"><button class="theme-toggle dash-btn" id="themeToggle"></button><div class="dropdown" id="userDropdown"><div class="dash-user dropdown-trigger"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%236366f1'/%3E%3Ctext x='50' y='67' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3EA%3C/text%3E%3C/svg%3E" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div><div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()">Logout</a></div></div></div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Transport Orders</h1>
        <p class="page-subtitle">View and manage all delivery manifests submitted by vendors. <span id="unreadInfo" style="color:var(--primary);"><?= $unreadCount > 0 ? "({$unreadCount} new)" : "" ?></span></p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($debugInfo): ?>
        <div class="debug-info"><?= htmlspecialchars($debugInfo) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <button class="btn-sm" id="markAllReadBtn" <?= $unreadCount == 0 ? 'disabled' : '' ?>>✓ Mark all as read</button>
        <button class="btn-sm" id="refreshBtn">⟳ Refresh</button>
    </div>

    <div class="table-container">
        <table class="data-table" id="manifestsTable">
            <thead><tr><th>File Name</th><th>Date Created</th><th>Size</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($manifests)): ?>
                    <tr><td colspan="4" class="empty-state">📭 No transport manifests found yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($manifests as $m): ?>
                        <tr data-filename="<?= strtolower($m['name']) ?>" data-is-read="<?= $m['is_read'] ?>">
                            <td><?= htmlspecialchars($m['name']) ?><?php if (!$m['is_read']): ?><span class="new-badge">New</span><?php endif; ?></td>
                            <td><?= date('Y-m-d H:i:s', $m['modified']) ?></td>
                            <td><?= number_format($m['size'] / 1024, 2) ?> KB</td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn-sm view-manifest" data-filename="<?= htmlspecialchars($m['name']) ?>">View</button>
                                    <a href="<?= htmlspecialchars($m['path']) ?>" class="btn-sm" download>Download</a>
                                    <?php if (!$m['is_read']): ?>
                                        <button class="btn-sm mark-read-btn" data-filename="<?= htmlspecialchars($m['name']) ?>">✓ Mark as read</button>
                                    <?php endif; ?>
                                    <button class="btn-sm btn-danger" onclick="deleteManifest('<?= htmlspecialchars($m['name']) ?>')">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for viewing manifest content -->
<div id="manifestModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Manifest Content</h3>
            <button class="modal-close" onclick="closeManifestModal()">&times;</button>
        </div>
        <div class="modal-body" id="manifestContent"><div style="text-align:center;">Loading...</div></div>
        <div class="modal-footer">
            <button class="btn-sm" onclick="closeManifestModal()">Close</button>
        </div>
    </div>
</div>

<script>
    // Theme & sidebar (unchanged)
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

    // Search filter
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#manifestsTable tbody tr');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            tableRows.forEach(row => {
                const filename = row.getAttribute('data-filename') || '';
                if (filename.includes(term)) row.style.display = '';
                else row.style.display = 'none';
            });
        });
    }

    // ---- MODAL HANDLING (FIXED: no auto-reload, keep modal open) ----
    const modal = document.getElementById('manifestModal');
    const manifestContentDiv = document.getElementById('manifestContent');

    // Attach event listeners to all "View" buttons (use event delegation)
    document.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.view-manifest');
        if (viewBtn) {
            const filename = viewBtn.getAttribute('data-filename');
            viewManifest(filename);
        }
    });

    window.viewManifest = async function(filename) {
        manifestContentDiv.innerHTML = '<div style="text-align:center; padding:1rem;">📄 Loading manifest...</div>';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        try {
            const response = await fetch('transport_manifests/' + encodeURIComponent(filename));
            if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            const content = await response.text();
            manifestContentDiv.innerHTML = `<pre style="margin:0; white-space:pre-wrap; word-wrap:break-word;">${escapeHtml(content)}</pre>`;
            // Mark as read after successful load
            markAsRead(filename);
        } catch (err) {
            console.error(err);
            manifestContentDiv.innerHTML = `<div style="color:red; text-align:center; padding:1rem;">❌ Failed to load manifest.<br>File: ${escapeHtml(filename)}<br>Error: ${escapeHtml(err.message)}</div>`;
        }
    };

    function closeManifestModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    modal.addEventListener('click', (e) => { if (e.target === modal) closeManifestModal(); });
    window.closeManifestModal = closeManifestModal;

    // Mark as read – NO PAGE RELOAD (only update UI)
    function markAsRead(filename) {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_read&filename=${encodeURIComponent(filename)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-filename="${filename.toLowerCase()}"]`);
                if (row) {
                    const newBadge = row.querySelector('.new-badge');
                    if (newBadge) newBadge.remove();
                    const markBtn = row.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
                    row.setAttribute('data-is-read', '1');
                }
                // Update unread count in sidebar badge and page subtitle without reload
                const badge = document.getElementById('sidebarUnreadBadge');
                const unreadInfo = document.getElementById('unreadInfo');
                if (badge) {
                    let cnt = parseInt(badge.textContent) || 0;
                    cnt = Math.max(0, cnt - 1);
                    if (cnt === 0) badge.remove(); else badge.textContent = cnt;
                    if (unreadInfo) unreadInfo.textContent = cnt > 0 ? `(${cnt} new)` : '';
                }
            }
        })
        .catch(err => console.error('Mark read error:', err));
    }

    // Mark all as read – also no reload
    document.getElementById('markAllReadBtn')?.addEventListener('click', function() {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Remove all new badges and mark-read buttons
                document.querySelectorAll('.new-badge').forEach(b => b.remove());
                document.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                // Hide sidebar badge and subtitle info
                const badge = document.getElementById('sidebarUnreadBadge');
                if (badge) badge.remove();
                const unreadInfo = document.getElementById('unreadInfo');
                if (unreadInfo) unreadInfo.textContent = '';
                // Also update row attributes
                document.querySelectorAll('#manifestsTable tbody tr').forEach(row => row.setAttribute('data-is-read', '1'));
            }
        });
    });

    document.getElementById('refreshBtn')?.addEventListener('click', () => location.reload());

    function deleteManifest(filename) {
        if (confirm(`Delete manifest "${filename}"? This action cannot be undone.`)) {
            window.location.href = 'admin-transport.php?delete=' + encodeURIComponent(filename);
        }
    }

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='logout.php'; }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }

    // Auto-refresh every 30 seconds (optional)
    setInterval(() => { if (!document.hidden) location.reload(); }, 30000);
</script>
</body>
</html>