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

// ---------- PERMISSION CHECK FOR CHAT PAGE ----------
if (!adminHasPermission('chat', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to access the chat.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// Create chat table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `vendor_id` INT(11) NOT NULL,
    `sender_type` ENUM('admin','vendor') NOT NULL,
    `message` TEXT NOT NULL,
    `audio_url` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle sending a message (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $vendor_id = intval($_POST['vendor_id']);
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (vendor_id, sender_type, message) VALUES (?, 'admin', ?)");
        $stmt->bind_param("is", $vendor_id, $message);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
    }
    exit;
}

// Get all vendors (active stores with user details)
$vendors = [];
$vendorQuery = $conn->query("
    SELECT s.user_id, u.full_name, s.store_name, s.id as store_id, u.last_active
    FROM stores s
    JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    ORDER BY s.store_name ASC
");
if ($vendorQuery) {
    while ($row = $vendorQuery->fetch_assoc()) {
        // Last message and unread count
        $lastMsg = $conn->query("SELECT message, created_at, sender_type FROM chat_messages WHERE vendor_id = {$row['user_id']} ORDER BY created_at DESC LIMIT 1");
        $last = $lastMsg->fetch_assoc();
        $row['last_message'] = $last ? htmlspecialchars(substr($last['message'], 0, 50)) : 'No messages yet';
        $row['last_time'] = $last ? date('H:i', strtotime($last['created_at'])) : '';
        $unreadResult = $conn->query("SELECT COUNT(*) as cnt FROM chat_messages WHERE vendor_id = {$row['user_id']} AND sender_type = 'vendor' AND is_read = 0");
        $row['unread'] = $unreadResult->fetch_assoc()['cnt'];
        
        // Online status
        $lastActive = strtotime($row['last_active']);
        $row['is_online'] = (time() - $lastActive) < 120; // 2 minutes
        $vendors[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>
    <style>
        /* ========== GLOBAL VARIABLES (same as admin dashboard) ========== */
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
            height: 100vh;
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
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        
        /* Topbar */
        .dash-navbar {
            position: sticky; top:0; right:0; left: var(--sidebar-width);
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
            color: var(--text-primary);
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
        
        /* Chat container */
        .chat-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
            margin: 1rem 2rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow);
        }
        .vendor-list {
            width: 320px;
            border-right: 1px solid var(--border-primary);
            overflow-y: auto;
            background: var(--bg-secondary);
        }
        .vendor-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-primary);
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }
        .vendor-item:hover { background: var(--bg-tertiary); }
        .vendor-item.active {
            background: var(--primary-light);
            border-left: 3px solid var(--primary);
        }
        .vendor-avatar {
            width: 48px; height: 48px;
            background: var(--gradient-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            flex-shrink: 0;
            position: relative;
        }
        .online-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #10b981;
            border-radius: 50%;
            border: 2px solid var(--bg-secondary);
        }
        .vendor-info {
            flex: 1;
            min-width: 0;
        }
        .vendor-name {
            font-weight: 600;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .vendor-last-msg {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .unread-badge {
            background: var(--primary);
            color: white;
            border-radius: 2rem;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
        }
        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-primary);
            background: var(--bg-secondary);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .call-controls {
            display: flex;
            gap: 0.5rem;
        }
        .call-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .call-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }
        .audio-call:hover { background: #10b981; }
        .video-call:hover { background: #6366f1; }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .message {
            max-width: 70%;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            word-wrap: break-word;
        }
        .message-admin {
            background: var(--gradient-primary);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
        }
        .message-vendor {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
        }
        .message-time {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
            text-align: right;
        }
        .audio-message {
            background: inherit;
            padding: 5px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .audio-message audio {
            max-width: 200px;
            height: 36px;
        }
        .typing-indicator {
            font-size: 0.75rem;
            color: var(--text-muted);
            padding: 0.25rem 1rem;
            font-style: italic;
        }
        .chat-input-area {
            padding: 1rem;
            border-top: 1px solid var(--border-primary);
            background: var(--bg-secondary);
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .chat-input-area input {
            flex: 1;
            padding: 0.6rem 1rem;
            border-radius: 2rem;
            border: 1px solid var(--border-primary);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            outline: none;
        }
        .chat-input-area button {
            background: var(--gradient-primary);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .chat-input-area .mic-btn {
            background: var(--bg-tertiary);
            border-radius: 50%;
            width: 40px;
            padding: 0.6rem;
            font-size: 1.2rem;
            color: var(--text-secondary);
        }
        .mic-btn.recording {
            background: var(--error);
            color: white;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .local-video, .remote-video {
            width: 200px;
            height: 150px;
            background: #000;
            border-radius: 8px;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: none;
            object-fit: cover;
        }
        .remote-video {
            right: 240px;
        }
        #callControls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1001;
            display: none;
            background: var(--bg-secondary);
            padding: 8px 12px;
            border-radius: 2rem;
            box-shadow: var(--shadow-lg);
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 200px; }
            .mobile-sidebar-toggle { display: flex; }
            .chat-layout { margin: 0.5rem; flex-direction: column; height: calc(100vh - var(--topbar-height) - 1rem); }
            .vendor-list { width: 100%; max-height: 200px; border-right: none; border-bottom: 1px solid var(--border-primary); }
            .local-video, .remote-video { width: 120px; height: 90px; }
            .remote-video { right: 140px; }
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
        <a href="admin.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a href="admin-users.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
        <a href="admin-stores.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span>Stores</span></a>
        <a href="admin-pricing.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pricing Plans</span></a>
        <a href="admin-testimonies.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span>Testimonials</span></a>
        <a href="admin-contacts.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><span>Contact Messages</span></a>
        <a href="admin-about.php" class="sidebar-item "><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>About Page</span></a>
        <a href="admin-chat.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Chat</span></a>
        <a href="admin-receive-order.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>All Orders</span></a>
        <a href="admin-transport.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Transport Orders</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="searchVendor" placeholder="Search vendor..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()">Logout</a></div>
            </div>
        </div>
    </header>

    <div class="chat-layout">
        <div class="vendor-list" id="vendorList">
            <?php foreach ($vendors as $v): ?>
                <div class="vendor-item" data-vendor-id="<?= $v['user_id'] ?>" data-vendor-name="<?= htmlspecialchars($v['store_name'] ?? $v['full_name']) ?>">
                    <div class="vendor-avatar">
                        <?= strtoupper(substr($v['store_name'] ?? $v['full_name'], 0, 1)) ?>
                        <?php if ($v['is_online']): ?><span class="online-dot"></span><?php endif; ?>
                    </div>
                    <div class="vendor-info">
                        <div class="vendor-name"><?= htmlspecialchars($v['store_name'] ?? $v['full_name']) ?></div>
                        <div class="vendor-last-msg"><?= $v['last_message'] ?></div>
                    </div>
                    <?php if ($v['unread'] > 0): ?>
                        <span class="unread-badge"><?= $v['unread'] ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-area" id="chatArea">
            <div class="chat-header" id="chatHeader">
                <span>Select a vendor to start chatting</span>
                <div class="call-controls" id="callControlsHeader" style="display: none;"></div>
            </div>
            <div class="chat-messages" id="chatMessages"></div>
            <div class="typing-indicator" id="typingIndicator" style="display: none;">Vendor is typing...</div>
            <div class="chat-input-area" id="chatInputArea" style="display: none;">
                <input type="text" id="messageInput" placeholder="Type a message...">
                <button id="sendBtn">Send</button>
                <button class="mic-btn" id="micBtn">🎤</button>
            </div>
        </div>
    </div>
</div>

<!-- Video call containers -->
<video id="localVideo" class="local-video" autoplay muted></video>
<video id="remoteVideo" class="remote-video" autoplay></video>
<div id="callControls">
    <button id="endCallBtn">End Call</button>
</div>

<script>
    // Theme, sidebar, dropdown
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

    // Search vendor filter
    const searchInput = document.getElementById('searchVendor');
    const vendorItems = document.querySelectorAll('.vendor-item');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            vendorItems.forEach(item => {
                const name = item.querySelector('.vendor-name')?.innerText.toLowerCase() || '';
                if (name.includes(term)) item.style.display = 'flex';
                else item.style.display = 'none';
            });
        });
    }

    // ---------------------- Chat Logic ----------------------
    let currentVendorId = null;
    let currentVendorName = '';
    let typingInterval = null;
    let isTyping = false;
    let vendorPeerId = null;
    let peer, localStream, currentCall;
    
    // Activity ping (online status)
    setInterval(() => {
        fetch('chat_update_activity.php', { method: 'POST' });
    }, 30000);
    
    async function fetchVendorPeerIdFromDB(vendorId) {
        const res = await fetch(`chat_get_peer_id.php?vendor_id=${vendorId}`);
        const data = await res.json();
        if (data.peer_id) {
            vendorPeerId = data.peer_id;
            return true;
        }
        return false;
    }
    
    function loadMessages() {
        if (!currentVendorId) return;
        fetch(`chat_get_messages.php?action=get_messages&vendor_id=${currentVendorId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('chatMessages');
                    container.innerHTML = '';
                    data.messages.forEach(msg => {
                        // Skip internal peer ID messages
                        if (msg.message.startsWith('__PEER_ID__')) {
                            // Save peer ID to DB if from vendor
                            if (msg.sender_type === 'vendor') {
                                const peerId = msg.message.replace('__PEER_ID__', '');
                                vendorPeerId = peerId;
                                fetch('chat_save_peer_id.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `vendor_id=${currentVendorId}&peer_id=${peerId}`
                                });
                            }
                            return;
                        }
                        // Also handle request for peer ID
                        if (msg.message === '__REQUEST_PEER_ID__' && msg.sender_type === 'vendor') {
                            // Admin should resend its peer ID
                            if (window.myPeerId) {
                                sendMessage(`__PEER_ID__${window.myPeerId}`);
                            }
                            return;
                        }
                        const msgDiv = document.createElement('div');
                        msgDiv.className = `message ${msg.sender_type === 'admin' ? 'message-admin' : 'message-vendor'}`;
                        if (msg.audio_url) {
                            msgDiv.innerHTML = `<div class="audio-message"><audio controls src="${escapeHtml(msg.audio_url)}"></audio><div class="message-time">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div></div>`;
                        } else {
                            msgDiv.innerHTML = `${escapeHtml(msg.message)}<div class="message-time">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</div>`;
                        }
                        container.appendChild(msgDiv);
                    });
                    container.scrollTop = container.scrollHeight;
                    markAsRead(currentVendorId);
                }
            });
    }

    function markAsRead(vendorId) {
        fetch('chat_mark_read.php', { method: 'POST' });
        const activeItem = document.querySelector(`.vendor-item[data-vendor-id="${vendorId}"]`);
        if (activeItem) {
            const badge = activeItem.querySelector('.unread-badge');
            if (badge) badge.remove();
        }
    }

    function sendMessage(msg) {
        if (!msg.trim() || !currentVendorId) return;
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=send_message&vendor_id=${currentVendorId}&message=${encodeURIComponent(msg)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('messageInput').value = '';
                loadMessages();
            } else {
                alert('Failed to send message');
            }
        });
    }

    async function selectVendor(vendorId, vendorName) {
        currentVendorId = vendorId;
        currentVendorName = vendorName;
        const headerHtml = `<span>Chat with ${escapeHtml(vendorName)}</span>
                            <div class="call-controls">
                                <button class="call-btn audio-call" id="audioCallBtn" title="Voice Call">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                    </svg>
                                </button>
                                <button class="call-btn video-call" id="videoCallBtn" title="Video Call">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="5" width="14" height="14" rx="2"/>
                                        <polygon points="22 7 16 12 22 17"/>
                                    </svg>
                                </button>
                            </div>`;
        document.getElementById('chatHeader').innerHTML = headerHtml;
        document.getElementById('chatInputArea').style.display = 'flex';
        loadMessages();
        // Attach call button events
        document.getElementById('audioCallBtn')?.addEventListener('click', () => startCall('audio'));
        document.getElementById('videoCallBtn')?.addEventListener('click', () => startCall('video'));
        // Update active class
        document.querySelectorAll('.vendor-item').forEach(el => {
            el.classList.remove('active');
            if (el.dataset.vendorId == vendorId) el.classList.add('active');
        });
        
        // Get peer ID from DB first
        if (!vendorPeerId) {
            const found = await fetchVendorPeerIdFromDB(vendorId);
            if (!found) {
                // Request vendor to send its peer ID
                sendMessage('__REQUEST_PEER_ID__');
                setTimeout(() => {
                    if (!vendorPeerId) {
                        alert('Could not get vendor peer ID. Please ensure vendor is online.');
                    }
                }, 3000);
            }
        }
        
        // Send admin's peer ID if not sent
        if (window.myPeerId && !window.peerIdSentToVendor) {
            sendMessage(`__PEER_ID__${window.myPeerId}`);
            window.peerIdSentToVendor = true;
        }
        // Start typing detection
        setupTyping();
    }

    // Typing indicator logic
    function sendTypingStart() {
        if (isTyping) return;
        isTyping = true;
        fetch('chat_typing_ping.php', { method: 'POST', body: 'action=start' });
        if (typingInterval) clearInterval(typingInterval);
        typingInterval = setInterval(() => {
            fetch('chat_typing_ping.php', { method: 'POST', body: 'action=start' });
        }, 3000);
    }
    function sendTypingStop() {
        if (!isTyping) return;
        isTyping = false;
        clearInterval(typingInterval);
        fetch('chat_typing_ping.php', { method: 'POST', body: 'action=stop' });
    }
    function setupTyping() {
        const input = document.getElementById('messageInput');
        if (!input) return;
        input.removeEventListener('focus', sendTypingStart);
        input.removeEventListener('input', sendTypingStart);
        input.removeEventListener('blur', sendTypingStop);
        input.addEventListener('focus', sendTypingStart);
        input.addEventListener('input', sendTypingStart);
        input.addEventListener('blur', sendTypingStop);
    }
    function checkTyping() {
        if (!currentVendorId) return;
        fetch(`chat_get_typing.php?user_id=${currentVendorId}`)
            .then(res => res.json())
            .then(data => {
                const indicator = document.getElementById('typingIndicator');
                if (data.typing) indicator.style.display = 'block';
                else indicator.style.display = 'none';
            });
    }
    setInterval(checkTyping, 2000);

    document.querySelectorAll('.vendor-item').forEach(el => {
        el.addEventListener('click', () => {
            const vid = el.dataset.vendorId;
            const vname = el.dataset.vendorName;
            selectVendor(vid, vname);
        });
    });

    document.getElementById('sendBtn').addEventListener('click', () => {
        const input = document.getElementById('messageInput');
        if (input.value.trim()) sendMessage(input.value.trim());
    });
    document.getElementById('messageInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage(e.target.value.trim());
    });

    // Auto-refresh messages every 3 seconds
    setInterval(() => {
        if (currentVendorId) loadMessages();
    }, 3000);

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='logout.php'; }

    // ---------------------- Audio Messages ----------------------
    let mediaRecorder;
    let audioChunks = [];
    let recording = false;

    async function startRecording() {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        mediaRecorder.ondataavailable = event => audioChunks.push(event.data);
        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.webm');
            formData.append('vendor_id', currentVendorId);
            const res = await fetch('chat_upload_audio.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) loadMessages();
            else alert('Audio upload failed');
        };
        mediaRecorder.start();
    }

    function stopRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
    }

    const micBtn = document.getElementById('micBtn');
    micBtn.addEventListener('click', () => {
        if (!currentVendorId) { alert('Select a vendor first'); return; }
        if (!recording) {
            startRecording();
            recording = true;
            micBtn.classList.add('recording');
        } else {
            stopRecording();
            recording = false;
            micBtn.classList.remove('recording');
        }
    });

    // ---------------------- Voice/Video Calls (PeerJS) ----------------------
    function initPeer() {
        peer = new Peer();
        peer.on('open', id => {
            window.myPeerId = id;
            console.log('Admin Peer ID:', id);
            // Send to current vendor if already selected
            if (currentVendorId && !window.peerIdSentToVendor) {
                sendMessage(`__PEER_ID__${id}`);
                window.peerIdSentToVendor = true;
            }
        });
        peer.on('call', async call => {
            if (confirm(`Incoming ${call.metadata?.type === 'video' ? 'video' : 'audio'} call from vendor. Accept?`)) {
                try {
                    const type = call.metadata?.type || 'audio';
                    localStream = await navigator.mediaDevices.getUserMedia({ video: type === 'video', audio: true });
                    document.getElementById('localVideo').srcObject = localStream;
                    document.getElementById('localVideo').style.display = 'block';
                    call.answer(localStream);
                    call.on('stream', remoteStream => {
                        document.getElementById('remoteVideo').srcObject = remoteStream;
                        document.getElementById('remoteVideo').style.display = 'block';
                    });
                    currentCall = call;
                    document.getElementById('callControls').style.display = 'block';
                } catch (err) { console.error(err); }
            } else {
                call.close();
            }
        });
    }

    function startCall(type) {
        if (!vendorPeerId) {
            alert('Vendor peer ID not yet available. Wait a moment or send a message first.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: type === 'video', audio: true })
            .then(stream => {
                localStream = stream;
                document.getElementById('localVideo').srcObject = stream;
                document.getElementById('localVideo').style.display = 'block';
                const call = peer.call(vendorPeerId, stream, { metadata: { type: type } });
                call.on('stream', remoteStream => {
                    document.getElementById('remoteVideo').srcObject = remoteStream;
                    document.getElementById('remoteVideo').style.display = 'block';
                });
                currentCall = call;
                document.getElementById('callControls').style.display = 'block';
            })
            .catch(err => alert('Could not access camera/mic: ' + err.message));
    }

    function endCall() {
        if (currentCall) currentCall.close();
        if (localStream) localStream.getTracks().forEach(track => track.stop());
        document.getElementById('localVideo').style.display = 'none';
        document.getElementById('remoteVideo').style.display = 'none';
        document.getElementById('callControls').style.display = 'none';
    }

    document.getElementById('endCallBtn')?.addEventListener('click', endCall);
    
    // Initialize peer
    initPeer();
</script>
</body>
</html>