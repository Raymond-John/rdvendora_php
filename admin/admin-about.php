<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';   // permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR ABOUT PAGE ----------
if (!adminHasPermission('about', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage the About page.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// Create uploads directory if not exists
$uploadDir = dirname(__DIR__) . '/uploads/team/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update general content
    if ($action === 'update_content') {
        $updates = [
            'hero_title' => $_POST['hero_title'],
            'hero_subtitle' => $_POST['hero_subtitle'],
            'story_title' => $_POST['story_title'],
            'story_text' => $_POST['story_text'],
            'stat1_number' => $_POST['stat1_number'],
            'stat1_label' => $_POST['stat1_label'],
            'stat2_number' => $_POST['stat2_number'],
            'stat2_label' => $_POST['stat2_label'],
            'stat3_number' => $_POST['stat3_number'],
            'stat3_label' => $_POST['stat3_label'],
            'stat4_number' => $_POST['stat4_number'],
            'stat4_label' => $_POST['stat4_label']
        ];
        
        $success = true;
        foreach ($updates as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO about_content (section_key, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)");
            $stmt->bind_param("ss", $key, $value);
            if (!$stmt->execute()) $success = false;
            $stmt->close();
        }
        if ($success) $message = "About page content updated successfully.";
        else $error = "Error updating content.";
    }
    
    // Add team member
    elseif ($action === 'add_team') {
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $bio = trim($_POST['bio']);
        $initials = strtoupper(trim($_POST['initials']));
        $avatar_color = $_POST['avatar_color'];
        $display_order = (int)$_POST['display_order'];
        
        // Handle file upload
        $avatar_path = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $avatar_path = 'uploads/team/' . $filename;
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO team_members (name, role, bio, initials, avatar, avatar_color, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssssssi", $name, $role, $bio, $initials, $avatar_path, $avatar_color, $display_order);
            if ($stmt->execute()) $message = "Team member added.";
            else $error = "Error: " . $conn->error;
            $stmt->close();
        }
    }
    
    // Edit team member
    elseif ($action === 'edit_team') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $bio = trim($_POST['bio']);
        $initials = strtoupper(trim($_POST['initials']));
        $avatar_color = $_POST['avatar_color'];
        $display_order = (int)$_POST['display_order'];
        
        // Fetch current avatar to delete old file if replacing
        $current = $conn->query("SELECT avatar FROM team_members WHERE id = $id")->fetch_assoc();
        $old_avatar = $current['avatar'] ?? '';
        
        // Handle file upload
        $avatar_path = $old_avatar; // keep existing by default
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $avatar_path = 'uploads/team/' . $filename;
                    // Delete old file if exists
                    if ($old_avatar && file_exists(rdv_fs_path($old_avatar))) {
                        unlink(rdv_fs_path($old_avatar));
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE team_members SET name=?, role=?, bio=?, initials=?, avatar=?, avatar_color=?, display_order=? WHERE id=?");
            $stmt->bind_param("ssssssii", $name, $role, $bio, $initials, $avatar_path, $avatar_color, $display_order, $id);
            if ($stmt->execute()) $message = "Team member updated.";
            else $error = "Error: " . $conn->error;
            $stmt->close();
        }
    }
    
    // Delete team member
    elseif ($action === 'delete_team') {
        $id = (int)$_POST['id'];
        // Get avatar path to delete file
        $avatar = $conn->query("SELECT avatar FROM team_members WHERE id = $id")->fetch_assoc()['avatar'] ?? '';
        $stmt = $conn->prepare("DELETE FROM team_members WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($avatar && file_exists(rdv_fs_path($avatar))) {
                unlink(rdv_fs_path($avatar));
            }
            $message = "Team member deleted.";
        } else $error = "Error: " . $conn->error;
        $stmt->close();
    }
    
    // Toggle status
    elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['status'];
        $stmt = $conn->prepare("UPDATE team_members SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        if ($stmt->execute()) $message = "Status updated.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
}

// Fetch current about content
$content = [];
$result = $conn->query("SELECT section_key, content FROM about_content");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $content[$row['section_key']] = $row['content'];
    }
}

// Fetch all team members
$team_members = [];
$teamResult = $conn->query("SELECT * FROM team_members ORDER BY display_order ASC");
if ($teamResult) {
    $team_members = $teamResult->fetch_all(MYSQLI_ASSOC);
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Manage About Page - Admin | RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* All styles remain exactly as in your provided admin-about.php – unchanged */
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
        
        /* Sidebar (same as before) */
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition);
            min-height: 100vh;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        
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
        
        .page-header {
            padding: 1.5rem 2rem 0.5rem 2rem;
            margin-top: var(--topbar-height);
        }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        
        .content-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin: 0 2rem 2rem 2rem;
            box-shadow: var(--shadow-sm);
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: var(--text-secondary);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.75rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.875rem;
            transition: var(--transition);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-primary);
        }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-danger { background: var(--error); color: white; border: none; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td {
            padding: 0.9rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }
        .data-table th {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            background: var(--bg-tertiary);
        }
        .badge {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-active { background: var(--success-light); color: var(--success); }
        .badge-inactive { background: var(--error-light); color: var(--error); }
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s;
            padding: 0.2rem;
        }
        .icon-btn:hover { color: var(--primary); }
        .message {
            padding: 0.8rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        .message-success { background: var(--success-light); color: var(--success); border-left: 4px solid var(--success); }
        .message-error { background: var(--error-light); color: var(--error); border-left: 4px solid var(--error); }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Responsive Table Wrapper */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Edit Modal Responsive Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-container {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-lg);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-primary);
            position: sticky;
            top: 0;
            background: var(--bg-secondary);
            z-index: 1;
        }
        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }
        .modal-close:hover {
            background: var(--bg-hover);
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1rem 1.5rem 1.5rem;
            border-top: 1px solid var(--border-primary);
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .mobile-sidebar-toggle { display: flex; }
            .content-card { margin: 0 1rem 1rem 1rem; padding: 1rem; }
            .page-header { padding: 1rem; margin-top: var(--topbar-height); }
            .data-table th, .data-table td { padding: 0.5rem; }
            .action-buttons { flex-direction: column; gap: 0.3rem; }
            .form-grid { grid-template-columns: 1fr; }
            .modal-container { max-width: calc(100% - 2rem); }
            .modal-body { padding: 1rem; }
        }
        @media (max-width: 480px) {
            .modal-footer { flex-direction: column; }
            .modal-footer .btn { width: 100%; justify-content: center; }
        }
        /* Preview image style */
        .avatar-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="nav-logo">
            <div class="nav-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
            <span>RD Vendora</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-section-title">Platform</div>
        <a href="admin.php" class="sidebar-item "><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a href="admin-users.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
        <a href="admin-stores.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span>Stores</span></a>
        <a href="admin-pricing.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pricing Plans</span></a>
        <a href="admin-testimonies.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span>Testimonials</span></a>
        <a href="admin-contacts.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><span>Contact Messages</span></a>
        <a href="admin-about.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>About Page</span></a>
        <a href="admin-chat.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Chat</span></a>
        <a href="admin-receive-order.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>All Orders</span></a>
        <a href="admin-transport.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Transport Orders</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="../dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Manage About Page</h1>
        <p class="page-subtitle">Edit hero section, story, stats, and team members.</p>
    </div>

    <?php if ($message): ?>
        <div class="message message-success" style="margin: 0 2rem 1rem 2rem;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message message-error" style="margin: 0 2rem 1rem 2rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- General Content Form (unchanged) -->
    <div class="content-card">
        <h3 style="margin-bottom: 1.5rem;">📝 General Content (Hero, Story, Stats)</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_content">
            <div class="form-grid">
                <div class="form-group"><label>Hero Title</label><input type="text" name="hero_title" value="<?= htmlspecialchars($content['hero_title'] ?? '') ?>" required></div>
                <div class="form-group"><label>Hero Subtitle</label><textarea name="hero_subtitle" rows="2"><?= htmlspecialchars($content['hero_subtitle'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Story Title</label><input type="text" name="story_title" value="<?= htmlspecialchars($content['story_title'] ?? '') ?>" required></div>
            </div>
            <div class="form-group"><label>Story Text (use double line breaks for paragraphs)</label><textarea name="story_text" rows="6"><?= htmlspecialchars($content['story_text'] ?? '') ?></textarea></div>
            <h4 style="margin: 1.5rem 0 0.5rem;">Stats (4 cards)</h4>
            <div class="form-grid">
                <div class="form-group"><label>Stat 1 Number</label><input type="text" name="stat1_number" value="<?= htmlspecialchars($content['stat1_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 1 Label</label><input type="text" name="stat1_label" value="<?= htmlspecialchars($content['stat1_label'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 2 Number</label><input type="text" name="stat2_number" value="<?= htmlspecialchars($content['stat2_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 2 Label</label><input type="text" name="stat2_label" value="<?= htmlspecialchars($content['stat2_label'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 3 Number</label><input type="text" name="stat3_number" value="<?= htmlspecialchars($content['stat3_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 3 Label</label><input type="text" name="stat3_label" value="<?= htmlspecialchars($content['stat3_label'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 4 Number</label><input type="text" name="stat4_number" value="<?= htmlspecialchars($content['stat4_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 4 Label</label><input type="text" name="stat4_label" value="<?= htmlspecialchars($content['stat4_label'] ?? '') ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary">Save General Content</button>
        </form>
    </div>

    <!-- Team Members Section (with file upload) -->
    <div class="content-card">
        <h3 style="margin-bottom: 1.5rem;">👥 Team Members</h3>
        
        <!-- Add Team Member Form (with file input) -->
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem; padding: 1rem; background: var(--bg-tertiary); border-radius: var(--radius);">
            <input type="hidden" name="action" value="add_team">
            <div class="form-grid">
                <div class="form-group"><label>Name *</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Role *</label><input type="text" name="role" required></div>
                <div class="form-group"><label>Bio</label><textarea name="bio" rows="2"></textarea></div>
                <div class="form-group"><label>Initials (e.g., AM)</label><input type="text" name="initials" placeholder="Auto from name if empty"></div>
                <div class="form-group"><label>Avatar Image (file)</label><input type="file" name="avatar" accept="image/*"></div>
                <div class="form-group"><label>Avatar Color (fallback)</label>
                    <select name="avatar_color">
                        <option value="primary">Blue (Primary)</option>
                        <option value="success">Green</option>
                        <option value="warning">Orange</option>
                        <option value="error">Red</option>
                    </select>
                </div>
                <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0"></div>
            </div>
            <button type="submit" class="btn btn-primary">Add Team Member</button>
        </form>
        
        <!-- Existing Team Members Table -->
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Avatar</th><th>Name</th><th>Role</th><th>Bio</th><th>Order</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                    <tr>
                        <td><?= $member['id'] ?></span></td>
                        <td><?php if($member['avatar'] && file_exists(rdv_fs_path($member['avatar']))): ?><img src="<?= htmlspecialchars(rdv_admin_src($member['avatar'])) ?>" class="avatar-preview"><?php else: ?>—<?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($member['name']) ?></strong></td>
                        <td><?= htmlspecialchars($member['role']) ?></span></td>
                        <td><?= htmlspecialchars(substr($member['bio'], 0, 60)) . (strlen($member['bio']) > 60 ? '...' : '') ?></td>
                        <td><?= $member['display_order'] ?></td>
                        <td><span class="badge <?= $member['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ucfirst($member['status']) ?></span></td>
                        <td class="action-buttons">
                            <button class="icon-btn" onclick="editTeamMember(<?= htmlspecialchars(json_encode($member)) ?>)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this team member?')">
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                <button type="submit" class="icon-btn" style="color:var(--error);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                <input type="hidden" name="status" value="<?= $member['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="icon-btn" title="Toggle Status"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Team Member Modal (with file upload and preview) -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Edit Team Member</h3>
            <div class="modal-close" onclick="closeEditModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="action" value="edit_team">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" required></div>
                <div class="form-group"><label>Role</label><input type="text" name="role" id="edit_role" required></div>
                <div class="form-group"><label>Bio</label><textarea name="bio" id="edit_bio" rows="3"></textarea></div>
                <div class="form-group"><label>Initials</label><input type="text" name="initials" id="edit_initials"></div>
                <div class="form-group">
                    <label>Avatar Image</label>
                    <div id="currentAvatarPreview" style="margin-bottom: 8px;"></div>
                    <input type="file" name="avatar" id="edit_avatar" accept="image/*">
                    <small style="color: var(--text-muted);">Leave empty to keep current image.</small>
                </div>
                <div class="form-group"><label>Avatar Color (fallback)</label>
                    <select name="avatar_color" id="edit_avatar_color">
                        <option value="primary">Blue</option><option value="success">Green</option>
                        <option value="warning">Orange</option><option value="error">Red</option>
                    </select>
                </div>
                <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="edit_display_order"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Theme, Sidebar, Dropdown same as other admin pages
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
    function logout() { if(confirm('Logout from admin panel?')) window.location.href='../logout.php'; }

    function editTeamMember(member) {
        document.getElementById('edit_id').value = member.id;
        document.getElementById('edit_name').value = member.name;
        document.getElementById('edit_role').value = member.role;
        document.getElementById('edit_bio').value = member.bio;
        document.getElementById('edit_initials').value = member.initials || '';
        document.getElementById('edit_avatar_color').value = member.avatar_color || 'primary';
        document.getElementById('edit_display_order').value = member.display_order;
        
        // Show current avatar preview
        const previewDiv = document.getElementById('currentAvatarPreview');
        if (member.avatar && member.avatar !== '') {
            previewDiv.innerHTML = `<img src="${member.avatar.startsWith('http') || member.avatar.startsWith('../') || member.avatar.startsWith('/') ? member.avatar : '../' + member.avatar}" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--border-primary);"> <span style="font-size:12px;">Current image</span>`;
        } else {
            previewDiv.innerHTML = '<span style="font-size:12px; color:var(--text-muted);">No current image</span>';
        }
        
        document.getElementById('editModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    // Close modal when clicking on overlay background
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
</script>
</body>
</html>