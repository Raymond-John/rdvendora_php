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

$message = '';
$messageType = '';

// Handle sending a new message (broadcast to all vendors)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject']);
    $content = trim($_POST['message']);
    
    if (empty($subject) || empty($content)) {
        $message = "Please fill in both subject and message.";
        $messageType = "error";
    } else {
        // Get all vendor IDs from stores that are active
        $vendorQuery = $conn->query("SELECT user_id FROM stores WHERE status = 'active'");
        if ($vendorQuery && $vendorQuery->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO messages (vendor_id, subject, message, sender_type) VALUES (?, ?, ?, 'admin')");
            while ($vendor = $vendorQuery->fetch_assoc()) {
                $stmt->bind_param("iss", $vendor['user_id'], $subject, $content);
                $stmt->execute();
            }
            $stmt->close();
            $message = "Message sent to all active vendors.";
            $messageType = "success";
        } else {
            $message = "No active vendors found.";
            $messageType = "error";
        }
    }
}

// Fetch all messages (threaded view)
$threads = [];
$query = $conn->query("
    SELECT m.*, u.full_name as vendor_name, s.store_name
    FROM messages m
    LEFT JOIN users u ON m.vendor_id = u.id
    LEFT JOIN stores s ON m.vendor_id = s.user_id
    ORDER BY m.created_at DESC
");
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $threads[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Messages</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== ADMIN STYLES (same as admin.php) ========== */
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
            --error: #ef4444;
            --error-light: #fee2e2;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --transition: all 0.3s ease;
            --sidebar-width: 260px;
            --topbar-height: 64px;
            --font-sans: 'Inter', system-ui, sans-serif;
        }
        [data-theme="dark"] {
            --bg-primary: #0c0e14;
            --bg-secondary: #14161f;
            --bg-tertiary: #1a1d28;
            --text-primary: #e8eaf0;
            --text-secondary: #9ca3b0;
            --text-muted: #6b7280;
            --border-primary: #2d3139;
            --border-secondary: #3a3f4a;
            --primary-light: rgba(99,102,241,0.15);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-sans);
            font-size: 0.9375rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            transition: background var(--transition), color var(--transition);
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
        }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.25rem;
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 0.75rem;
            font-weight: 800; font-size: 1.125rem;
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
        .sidebar-footer {
            padding: 1rem; border-top: 1px solid var(--border-primary);
        }
        .sidebar-user {
            display: flex; align-items: center; gap: 0.75rem;
        }
        .sidebar-user-avatar {
            width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
        }
        
        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition);
            min-height: 100vh;
        }
        .topbar {
            position: sticky; top:0; right:0; left: var(--sidebar-width);
            height: var(--topbar-height);
            background: var(--bg-secondary);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem;
            z-index: 200;
            transition: left var(--transition);
        }
        .page-content { padding: 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        
        /* Message specific */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
        }
        .alert-success { background: var(--success-light); color: #047857; }
        .alert-error { background: var(--error-light); color: #dc2626; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 0.5rem; border-radius: var(--radius);
            border: 1px solid var(--border-primary);
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        .btn {
            padding: 0.5rem 1rem; border-radius: var(--radius);
            font-weight: 500; transition: var(--transition);
        }
        .btn-primary { background: var(--primary); color: white; }
        .message-thread {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .message-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 2rem; font-size: 0.7rem; background: var(--bg-tertiary); }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content, .topbar { margin-left: 0 !important; left: 0; }
            .page-content { padding: 1rem; }
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
        <button class="sidebar-toggle" id="sidebarToggle">◀</button>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-section-title">Platform</div>
        <a href="admin.php" class="sidebar-item">📊 Dashboard</a>
        <a href="admin-users.php" class="sidebar-item">👥 Users</a>
        <a href="admin-stores.php" class="sidebar-item">🏪 Stores</a>
        <a href="admin-messages.php" class="sidebar-item active">💬 Messages</a>
        <div class="sidebar-section-title">System</div>
        <a href="dashboard.php" class="sidebar-item">↩️ Back to Store</a>
        <a href="#" class="sidebar-item" onclick="logout()">🚪 Logout</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%236366f1'/%3E%3Ctext x='50' y='67' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3EA%3C/text%3E%3C/svg%3E" class="sidebar-user-avatar">
            <div><div class="sidebar-user-name">Admin</div><div class="sidebar-user-role">Super Admin</div></div>
        </div>
    </div>
</div>

<div class="main-content">
    <header class="topbar">
        <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">☰</button>
        <div class="topbar-actions">
            <button class="theme-toggle" id="themeToggle">🌙</button>
        </div>
    </header>
    <div class="page-content">
        <div class="page-header">
            <h1 class="page-title">Admin Messages</h1>
            <p>Send broadcast messages to all vendors, and view their replies.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- New message form -->
        <div style="background:var(--bg-secondary); border-radius:1rem; padding:1.5rem; margin-bottom:2rem;">
            <h3>Send to all vendors</h3>
            <form method="POST">
                <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
                <div class="form-group"><label>Message</label><textarea name="message" rows="4" required></textarea></div>
                <button type="submit" name="send_message" class="btn btn-primary">Send Broadcast</button>
            </form>
        </div>
        
        <h3>All Messages (Admin & Vendor Replies)</h3>
        <?php if (empty($threads)): ?>
            <p>No messages yet.</p>
        <?php else: ?>
            <?php foreach ($threads as $msg): ?>
                <div class="message-thread">
                    <div class="message-header">
                        <strong><?= htmlspecialchars($msg['vendor_name'] ?? 'Vendor') ?> (<?= htmlspecialchars($msg['store_name'] ?? 'Store') ?>)</strong>
                        <small><?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?></small>
                    </div>
                    <div><strong><?= htmlspecialchars($msg['subject']) ?></strong></div>
                    <div style="margin:0.5rem 0;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <div><span class="badge"><?= $msg['sender_type'] === 'admin' ? '📢 Admin' : '💬 Vendor Reply' ?></span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Theme toggle
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
    // Mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => sidebar.classList.toggle('mobile-open'));
    }
    function logout() { if(confirm('Logout?')) window.location.href='logout.php'; }
</script>
</body>
</html>