<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Ensure store exists
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header("Location: create-store.php");
    exit();
}
$stmt->close();

// Check store active and subscription (optional – you can allow messages even if suspended)
$storeActive = isStoreActive($conn, $_SESSION['user_id']);
$hasSubscription = hasActiveSubscription($conn, $_SESSION['user_id']);

// Get user info
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

$message = '';
$messageType = '';

// Handle reply to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $parent_id = intval($_POST['parent_id']);
    $reply_text = trim($_POST['reply_text']);
    if (empty($reply_text)) {
        $message = "Reply cannot be empty.";
        $messageType = "error";
    } else {
        // Get original message subject
        $orig = $conn->query("SELECT subject FROM messages WHERE id = $parent_id AND vendor_id = {$_SESSION['user_id']}");
        if ($orig && $orig->num_rows) {
            $subject = $orig->fetch_assoc()['subject'];
            $stmt = $conn->prepare("INSERT INTO messages (vendor_id, subject, message, sender_type, parent_id) VALUES (?, ?, ?, 'vendor', ?)");
            $stmt->bind_param("issi", $_SESSION['user_id'], $subject, $reply_text, $parent_id);
            if ($stmt->execute()) {
                $message = "Reply sent to admin.";
                $messageType = "success";
            } else {
                $message = "Failed to send reply.";
                $messageType = "error";
            }
            $stmt->close();
        } else {
            $message = "Cannot reply to this message.";
            $messageType = "error";
        }
    }
}

// Fetch messages for this vendor (sent by admin and vendor's own replies)
$messages = [];
$stmt = $conn->prepare("SELECT * FROM messages WHERE vendor_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== VENDOR DASHBOARD STYLES (same as your dashboard.php) ========== */
        /* I'll include a minimal version – you can reuse your existing dashboard styles */
        :root {
            --bg-primary: #f8f9fb;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f3f6;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --border-primary: #e5e7eb;
            --primary: #6366f1;
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
            --success: #10b981;
            --error: #ef4444;
            --sidebar-width: 260px;
            --topbar-height: 64px;
        }
        [data-theme="dark"] {
            --bg-primary: #0c0e14;
            --bg-secondary: #14161f;
            --bg-tertiary: #1a1d28;
            --text-primary: #e8eaf0;
            --text-secondary: #9ca3b0;
            --border-primary: #2d3139;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.2s, color 0.2s;
        }
        /* Sidebar (same as your dashboard) – I'll assume you have it; for brevity I'll include a simplified version */
        .sidebar { position:fixed; left:0; top:0; bottom:0; width:var(--sidebar-width); background:var(--bg-secondary); border-right:1px solid var(--border-primary); display:flex; flex-direction:column; }
        .sidebar-header { padding:1rem; border-bottom:1px solid var(--border-primary); }
        .sidebar-nav { flex:1; padding:1rem 0.5rem; }
        .sidebar-link { display:flex; align-items:center; gap:0.75rem; padding:0.5rem 0.75rem; border-radius:0.5rem; color:var(--text-secondary); margin-bottom:0.25rem; }
        .sidebar-link.active, .sidebar-link:hover { background:var(--primary-light); color:var(--primary); }
        .main-content { margin-left:var(--sidebar-width); min-height:100vh; }
        .topbar { position:sticky; top:0; height:var(--topbar-height); background:var(--bg-secondary); border-bottom:1px solid var(--border-primary); display:flex; align-items:center; justify-content:space-between; padding:0 1.5rem; backdrop-filter:blur(12px); }
        .page-content { padding:1.5rem; }
        .message-card { background:var(--bg-secondary); border:1px solid var(--border-primary); border-radius:1rem; padding:1rem; margin-bottom:1rem; }
        .message-meta { display:flex; justify-content:space-between; margin-bottom:0.5rem; font-size:0.875rem; color:var(--text-muted); }
        .reply-form { margin-top:1rem; border-top:1px solid var(--border-primary); padding-top:1rem; }
        .btn { padding:0.4rem 1rem; border-radius:0.5rem; background:var(--bg-tertiary); cursor:pointer; }
        .btn-primary { background:var(--gradient-primary); color:white; }
        textarea { width:100%; padding:0.5rem; border-radius:0.5rem; border:1px solid var(--border-primary); background:var(--bg-primary); color:var(--text-primary); }
        @media (max-width:768px) { .sidebar { transform:translateX(-100%); } .sidebar.mobile-open { transform:translateX(0); } .main-content { margin-left:0; } }
    </style>
</head>
<body>
<!-- Sidebar (same as your dashboard) – copy your existing sidebar HTML -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">RD Vendora</div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link">📊 Dashboard</a>
        <a href="products.php" class="sidebar-link">📦 Products</a>
        <a href="orders.php" class="sidebar-link">🛒 Orders</a>
        <a href="customers.php" class="sidebar-link">👥 Customers</a>
        <a href="settings.php" class="sidebar-link">⚙️ Settings</a>
        <a href="subscription.php" class="sidebar-link">💳 Subscription</a>
        <a href="vendor-messages.php" class="sidebar-link active">💬 Messages</a>
        <a href="#" class="sidebar-link" onclick="logout()">🚪 Logout</a>
    </nav>
</aside>

<div class="main-content">
    <header class="topbar">
        <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">☰</button>
        <div class="topbar-actions">
            <button class="theme-toggle" id="themeToggle">🌙</button>
        </div>
    </header>
    <div class="page-content">
        <div class="page-header">
            <h1>Messages</h1>
            <p>Communication with platform admin.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (empty($messages)): ?>
            <p>No messages yet.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-card">
                    <div class="message-meta">
                        <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                        <small><?= date('M d, Y H:i', strtotime($msg['created_at'])) ?></small>
                    </div>
                    <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <div style="margin-top:0.5rem;"><span class="badge"><?= $msg['sender_type'] === 'admin' ? '📢 Admin' : '✏️ Your Reply' ?></span></div>
                    
                    <?php if ($msg['sender_type'] === 'admin'): ?>
                        <div class="reply-form">
                            <form method="POST">
                                <input type="hidden" name="parent_id" value="<?= $msg['id'] ?>">
                                <textarea name="reply_text" rows="2" placeholder="Reply to admin..."></textarea>
                                <button type="submit" name="reply_message" class="btn btn-primary" style="margin-top:0.5rem;">Send Reply</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Theme toggle (same as your dashboard)
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
    // Mobile sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => sidebar.classList.toggle('mobile-open'));
    }
    function logout() { if(confirm('Logout?')) window.location.href='logout.php'; }
</script>
</body>
</html>