<?php
require_once 'includes/connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $login = function_exists('rdv_url') ? rdv_url('login?error=Not logged in') : 'login?error=Not logged in';
    header('Location: ' . $login);
    exit();
}

require_once 'includes/subscription_check.php';
require_once 'includes/notification_helper.php';  // <-- ADDED

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Ensure store exists ----------
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$stmt->close();

// ---------- Check if store is active ----------
if (!isStoreActive($conn, $_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Store Disabled</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⛔ Store Disabled</h1>
        <p>Your store has been disabled by the administrator. Please contact support for more information.</p>
        <a href="logout">Logout</a>
    </body>
    </html>
    <?php
    exit();
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

// ---------- Get user's store details ----------
if (!isset($_SESSION['store_name']) || !isset($_SESSION['store_id'])) {
    $stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $_SESSION['store_id'] = $row['id'];
        $_SESSION['store_name'] = $row['store_name'];
    } else {
        $_SESSION['store_id'] = null;
        $_SESSION['store_name'] = null;
    }
    $stmt->close();
}

// ---------- Create chat table if not exists (add audio_url column if missing) ----------
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

$check_column = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'audio_url'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE chat_messages ADD COLUMN audio_url VARCHAR(255) DEFAULT NULL");
}

// Handle sending a message via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (vendor_id, sender_type, message) VALUES (?, 'vendor', ?)");
        $stmt->bind_param("is", $_SESSION['user_id'], $message);
        if ($stmt->execute()) {
            // ========== NOTIFICATION: Vendor sent a message ==========
            // Notify the admin (assuming admin user_id = 1)
            $admin_id = 1; // You can fetch this dynamically if needed
            $title = "New Message from Vendor";
            $msg_preview = substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
            $link = "vendor-chat.php"; // or admin-chat.php
            createNotification($admin_id, 'chat', $title, $msg_preview, $link);
            // ========================================================
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get admin last active (assuming admin user_id = 1, adjust if needed)
$adminOnline = false;
$adminQuery = $conn->query("SELECT last_active FROM users WHERE id = 1");
if ($adminQuery && $row = $adminQuery->fetch_assoc()) {
    $lastActive = strtotime($row['last_active']);
    $adminOnline = (time() - $lastActive) < 120;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>
    <style>
        /* ========== DASHBOARD BASE STYLES (same as before) ========== */
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

        /* Sidebar */
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

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition-slow); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        /* Topbar */
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
        .theme-toggle {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary); transition: all var(--transition-fast); flex-shrink: 0;
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
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
        .dropdown-item svg { flex-shrink: 0; width: 16px; height: 16px; }
        .dropdown-divider { height: 1px; background: var(--border-primary); margin: var(--space-1) 0; }

        /* Page content - chat specific */
        .page-content { flex: 1; padding: var(--space-6); overflow-y: auto; }
        .chat-container {
            display: flex;
            flex-direction: column;
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-primary);
            overflow: hidden;
            height: calc(100vh - var(--topbar-height) - var(--space-12));
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
        .online-status {
            font-size: 0.7rem;
            color: var(--success);
            margin-left: 0.5rem;
        }
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
            background: var(--primary-light);
            color: var(--primary-dark);
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
        }
        .message-vendor {
            background: var(--gradient-primary);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
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

        /* Modal for incoming call */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            visibility: hidden;
            opacity: 0;
            transition: all 0.2s;
        }
        .modal.active {
            visibility: visible;
            opacity: 1;
        }
        .modal-content {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            padding: var(--space-8) var(--space-6);
            text-align: center;
            min-width: 300px;
            box-shadow: var(--shadow-lg);
        }
        .modal-content h3 {
            margin-bottom: 1rem;
        }
        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: var(--space-6);
        }
        .accept-btn {
            background: var(--success);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
        }
        .decline-btn {
            background: var(--error);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
        }

        /* Video containers */
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
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: var(--z-fixed); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-xl); }
            .sidebar.collapsed { width: var(--sidebar-width); transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .page-content { padding: var(--space-4); }
            .chat-container { height: calc(100vh - var(--topbar-height) - var(--space-8)); }
            .local-video, .remote-video { width: 120px; height: 90px; }
            .remote-video { right: 140px; }
        }

        /* Toast styles */
        .toast-container {
            position: fixed; top: calc(var(--topbar-height) + var(--space-4)); right: var(--space-4);
            z-index: var(--z-toast); display: flex; flex-direction: column; gap: var(--space-3);
        }
        .toast {
            display: flex; align-items: center; gap: var(--space-3); padding: var(--space-4);
            background: var(--bg-secondary); border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary); box-shadow: var(--shadow-xl);
            min-width: 300px; transform: translateX(120%);
            animation: toastSlideIn 0.4s ease forwards;
        }
        .toast.removing { animation: toastSlideOut 0.3s ease forwards; }
        .toast-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: var(--font-semibold); color: var(--text-primary); }
        .toast-message { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        @keyframes toastSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
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
                    <input type="text" id="searchMessages" placeholder="Search messages...">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5" /><line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" /><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" /><line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" /><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" /></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>
                </button>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <!-- <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role">
                                <?php if ($_SESSION['store_name']): ?>
                                    Store: <?= htmlspecialchars($_SESSION['store_name']) ?>
                                <?php else: ?>
                                    <a href="create-store" style="color:var(--primary);">Create Store</a>
                                <?php endif; ?>
                            </span> -->
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><polyline points="6 9 12 15 18 9" /></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')"> Profile</a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings page coming soon')"> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()"> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <div class="chat-container">
                <div class="chat-header">
                    <span>Admin Chat <?php if ($adminOnline): ?><span class="online-status">● Online</span><?php endif; ?></span>
                    <div class="call-controls" style="display:flex;gap:0.5rem;">
                        <button type="button" class="call-btn audio-call" id="audioCallBtn" title="Voice Call" style="width:38px;height:38px;border-radius:50%;border:0;background:var(--bg-tertiary,#e2e8f0);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="call-btn video-call" id="videoCallBtn" title="Video Call" style="width:38px;height:38px;border-radius:50%;border:0;background:var(--bg-tertiary,#e2e8f0);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="14" height="14" rx="2"/><polygon points="22 7 16 12 22 17"/></svg>
                        </button>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages"></div>
                <div class="typing-indicator" id="typingIndicator" style="display: none;">Admin is typing...</div>
                <div class="chat-input-area">
                    <input type="text" id="messageInput" placeholder="Type a message...">
                    <button id="sendBtn">Send</button>
                    <button class="mic-btn" id="micBtn">🎤</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for incoming call -->
    <div class="modal" id="incomingCallModal">
        <div class="modal-content">
            <h3>📞 Incoming Call</h3>
            <p id="callTypeText">Admin is calling...</p>
            <div class="modal-buttons">
                <button class="accept-btn" id="acceptCallBtn">Accept</button>
                <button class="decline-btn" id="declineCallBtn">Decline</button>
            </div>
        </div>
    </div>

    <!-- Video call containers -->
    <video id="localVideo" class="local-video" autoplay muted playsinline></video>
    <video id="remoteVideo" class="remote-video" autoplay playsinline></video>
    <div id="callControls">
        <button id="endCallBtn">End Call</button>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Theme, sidebar, dropdown (same as dashboard)
        const htmlEl = document.documentElement;
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        htmlEl.setAttribute('data-theme', savedTheme);
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const iconSun = themeToggle.querySelector('.icon-sun');
            const iconMoon = themeToggle.querySelector('.icon-moon');
            if (savedTheme === 'light') { iconSun.style.display = 'block'; iconMoon.style.display = 'none'; }
            else { iconSun.style.display = 'none'; iconMoon.style.display = 'block'; }
            themeToggle.addEventListener('click', () => {
                const newTheme = htmlEl.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                htmlEl.setAttribute('data-theme', newTheme);
                localStorage.setItem('RD Vendora-theme', newTheme);
                if (newTheme === 'light') { iconSun.style.display = 'block'; iconMoon.style.display = 'none'; }
                else { iconSun.style.display = 'none'; iconMoon.style.display = 'block'; }
            });
        }

        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        function closeMobile() { sidebar.classList.remove('mobile-open'); sidebarOverlay.classList.remove('active'); document.body.style.overflow = ''; }
        function openMobile() { sidebar.classList.add('mobile-open'); sidebarOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    if (sidebar.classList.contains('mobile-open')) closeMobile();
                    else openMobile();
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });
        }
        if (mobileToggle) mobileToggle.addEventListener('click', openMobile);
        sidebarOverlay.addEventListener('click', closeMobile);
        window.addEventListener('resize', () => { if (window.innerWidth > 768) { closeMobile(); sidebar.classList.remove('collapsed'); } });

        const userDD = document.getElementById('userDropdown');
        if (userDD) {
            const trigger = userDD.querySelector('.dropdown-trigger');
            trigger.addEventListener('click', (e) => { e.stopPropagation(); userDD.classList.toggle('open'); });
            document.addEventListener('click', () => userDD.classList.remove('open'));
        }

        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const icons = { success: '✅', error: '❌', info: 'ℹ️' };
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<div class="toast-icon">${icons[type] || icons.info}</div><div class="toast-content"><div class="toast-title">${escapeHtml(title)}</div><div class="toast-message">${escapeHtml(message)}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 300); }, 3500);
        }

        function handleLogout() { if (confirm('Logout?')) window.location.href='logout'; }

        // Chat logic
        let adminPeerId = null;
        let peer, localStream, currentCall;
        let pendingCall = null;
        let typingInterval = null;
        let isTyping = false;
        let mediaRecorder, audioChunks = [], recording = false;

        function loadMessages() {
            fetch('chat_get_messages?action=get_messages')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const container = document.getElementById('chatMessages');
                        container.innerHTML = '';
                        data.messages.forEach(msg => {
                            const text = String(msg.message || '');
                            // Internal call signaling — never show in the chat UI
                            if (text.startsWith('__')) {
                                if (text.startsWith('__PEER_ID__') && msg.sender_type === 'admin') {
                                    adminPeerId = text.replace('__PEER_ID__', '');
                                }
                                if (text === '__REQUEST_PEER_ID__' && msg.sender_type === 'admin' && window.myPeerId) {
                                    fetch('chat_save_peer_id', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                        body: `vendor_id=<?= (int) $_SESSION['user_id'] ?>&peer_id=${encodeURIComponent(window.myPeerId)}`
                                    }).catch(() => {});
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
                        markMessagesRead();
                    }
                });
        }

        function markMessagesRead() {
            fetch('chat_mark_read', { method: 'POST' });
        }

        function sendMessage(msg) {
            if (!msg.trim()) return;
            const isSignal = String(msg).startsWith('__');
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=send_message&message=${encodeURIComponent(msg)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (!isSignal) {
                        document.getElementById('messageInput').value = '';
                    }
                    loadMessages();
                } else if (!isSignal) {
                    showToast('error', 'Failed', 'Could not send message');
                }
            });
        }

        // ========== NEW: Poll for new admin messages and create notifications ==========
        function checkNewAdminMessages() {
            fetch('chat_check_new_admin_messages')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        // Show a toast to inform the vendor
                        showToast('info', 'New Message from Admin', `You have ${data.count} new message${data.count > 1 ? 's' : ''}.`);
                        // Reload messages to show them
                        loadMessages();
                    }
                })
                .catch(err => console.error('Poll error:', err));
        }
        // Check every 10 seconds
        setInterval(checkNewAdminMessages, 10000);
        // ============================================================

        function sendTypingStart() {
            if (isTyping) return;
            isTyping = true;
            fetch('chat_typing_ping', { method: 'POST', body: 'action=start' });
            if (typingInterval) clearInterval(typingInterval);
            typingInterval = setInterval(() => {
                fetch('chat_typing_ping', { method: 'POST', body: 'action=start' });
            }, 3000);
        }
        function sendTypingStop() {
            if (!isTyping) return;
            isTyping = false;
            clearInterval(typingInterval);
            fetch('chat_typing_ping', { method: 'POST', body: 'action=stop' });
        }

        const msgInput = document.getElementById('messageInput');
        msgInput.addEventListener('focus', sendTypingStart);
        msgInput.addEventListener('input', sendTypingStart);
        msgInput.addEventListener('blur', sendTypingStop);

        function checkTyping() {
            fetch('chat_get_typing?user_id=1')
                .then(res => res.json())
                .then(data => {
                    const indicator = document.getElementById('typingIndicator');
                    indicator.style.display = data.typing ? 'block' : 'none';
                });
        }
        setInterval(checkTyping, 2000);

        document.getElementById('sendBtn').addEventListener('click', () => {
            const input = document.getElementById('messageInput');
            if (input.value.trim()) sendMessage(input.value.trim());
        });
        msgInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage(e.target.value.trim());
        });

        setInterval(loadMessages, 3000);
        loadMessages();

        setInterval(() => {
            fetch('chat_update_activity', { method: 'POST' });
        }, 30000);

        async function startRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                mediaRecorder.onstop = async () => {
                    const blob = new Blob(audioChunks, { type: 'audio/webm' });
                    const formData = new FormData();
                    formData.append('audio', blob, 'recording.webm');
                    const res = await fetch('chat_upload_audio', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) loadMessages();
                    else showToast('error', 'Upload Failed', 'Could not upload audio');
                };
                mediaRecorder.start();
            } catch(err) { showToast('error', 'Microphone Error', 'Access denied'); }
        }
        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(t => t.stop());
            }
        }
        const micBtn = document.getElementById('micBtn');
        micBtn.addEventListener('click', () => {
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

        function initPeer() {
            if (typeof Peer === 'undefined') {
                console.error('PeerJS failed to load');
                return;
            }
            const PEER_ICE = {
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' },
                    { urls: 'stun:stun2.l.google.com:19302' }
                ]
            };
            peer = new Peer({ config: PEER_ICE, debug: 1 });
            peer.on('open', id => {
                window.myPeerId = id;
                fetch('chat_save_peer_id', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `vendor_id=<?= (int) $_SESSION['user_id'] ?>&peer_id=${encodeURIComponent(id)}`
                }).catch(() => {});
            });
            peer.on('disconnected', () => {
                try { peer.reconnect(); } catch (e) {}
            });
            peer.on('call', call => {
                const type = call.metadata?.type || 'audio';
                pendingCall = call;
                document.getElementById('callTypeText').innerHTML = `Admin is calling (${type === 'video' ? 'Video' : 'Voice'})...`;
                document.getElementById('incomingCallModal').classList.add('active');
            });
            peer.on('error', err => {
                console.error('Peer error:', err);
                showToast('error', 'Call error', err.message || err.type || 'Peer connection error');
            });
        }

        function showMedia(el, stream, visible) {
            if (!el) return;
            el.srcObject = stream || null;
            el.style.display = visible ? 'block' : 'none';
            if (visible && stream) el.play?.().catch(() => {});
        }

        async function fetchAdminPeerId() {
            try {
                const res = await fetch('chat_get_peer_id?role=admin');
                const data = await res.json();
                if (data.peer_id) {
                    adminPeerId = data.peer_id;
                    return true;
                }
            } catch (e) {
                console.error(e);
            }
            return false;
        }

        async function startCall(type) {
            if (!peer || peer.destroyed) {
                showToast('error', 'Not ready', 'Calling system is connecting. Try again shortly.');
                initPeer();
                return;
            }
            if (!adminPeerId) {
                await fetchAdminPeerId();
            }
            if (!adminPeerId) {
                showToast('info', 'Waiting', 'Open Admin Chat on the other side, then try again.');
                return;
            }
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: type === 'video', audio: true });
                showMedia(document.getElementById('localVideo'), localStream, type === 'video');
                const call = peer.call(adminPeerId, localStream, { metadata: { type: type } });
                if (!call) {
                    showToast('error', 'Call failed', 'Could not start the call.');
                    endCall();
                    return;
                }
                call.on('stream', remoteStream => {
                    showMedia(document.getElementById('remoteVideo'), remoteStream, true);
                });
                call.on('close', () => endCall());
                call.on('error', err => {
                    showToast('error', 'Call failed', err.message || err.type || 'connection error');
                    endCall();
                });
                currentCall = call;
                document.getElementById('callControls').style.display = 'flex';
            } catch (err) {
                showToast('error', 'Permission Error', err.message || 'Allow mic/camera and use HTTPS.');
            }
        }

        async function acceptCall() {
            if (!pendingCall) return;
            const call = pendingCall;
            const type = call.metadata?.type || 'audio';
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: type === 'video', audio: true });
                showMedia(document.getElementById('localVideo'), localStream, type === 'video');
                call.answer(localStream);
                call.on('stream', remoteStream => {
                    showMedia(document.getElementById('remoteVideo'), remoteStream, true);
                });
                call.on('close', () => endCall());
                currentCall = call;
                document.getElementById('callControls').style.display = 'flex';
                document.getElementById('incomingCallModal').classList.remove('active');
                pendingCall = null;
            } catch (err) {
                showToast('error', 'Permission Error', err.message);
                declineCall();
            }
        }
        function declineCall() {
            if (pendingCall) {
                try { pendingCall.close(); } catch (e) {}
            }
            document.getElementById('incomingCallModal').classList.remove('active');
            pendingCall = null;
        }
        function endCall() {
            if (currentCall) {
                try { currentCall.close(); } catch (e) {}
            }
            if (localStream) localStream.getTracks().forEach(track => track.stop());
            showMedia(document.getElementById('localVideo'), null, false);
            showMedia(document.getElementById('remoteVideo'), null, false);
            document.getElementById('callControls').style.display = 'none';
            currentCall = null;
            localStream = null;
        }

        document.getElementById('acceptCallBtn').addEventListener('click', acceptCall);
        document.getElementById('declineCallBtn').addEventListener('click', declineCall);
        document.getElementById('endCallBtn').addEventListener('click', endCall);
        document.getElementById('audioCallBtn')?.addEventListener('click', () => startCall('audio'));
        document.getElementById('videoCallBtn')?.addEventListener('click', () => startCall('video'));

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
        }

        initPeer();
        fetchAdminPeerId();
        setInterval(fetchAdminPeerId, 15000);

        // Search messages
        const searchInput = document.getElementById('searchMessages');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                document.querySelectorAll('#chatMessages .message').forEach(msg => {
                    const text = msg.innerText.toLowerCase();
                    msg.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>