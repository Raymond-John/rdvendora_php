<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';
require_once 'includes/log_activity.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Log this page view
logUserActivity($_SESSION['user_id'], 'company_documents_view', 'company-documents.php', 'Viewed company documents page');

// ---------- Get store details (for sidebar display) ----------
$stmt = $conn->prepare("SELECT id, store_name, status FROM stores WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$storeResult = $stmt->get_result();
if ($storeResult->num_rows === 0) {
    header("Location: create-store.php");
    exit();
}
$storeData = $storeResult->fetch_assoc();
$_SESSION['store_id'] = $storeData['id'];
$_SESSION['store_name'] = $storeData['store_name'];
$storeStatus = $storeData['status'];
$stmt->close();

// Check if user has an active Empire subscription
$user_id = $_SESSION['user_id'];
$has_empire = false;
$stmt = $conn->prepare("SELECT id, plan, status FROM subscriptions WHERE user_id = ? AND plan = 'Empire' AND status = 'active' ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    // No active Empire subscription – redirect to subscription page
    header('Location: subscription.php?error=You need an Empire subscription to access this page');
    exit();
}

// Check if documents already exist and their status
$documents = [];
$all_approved = true;
$status_query = $conn->prepare("SELECT * FROM company_documents WHERE user_id = ? ORDER BY document_type");
$status_query->bind_param("i", $user_id);
$status_query->execute();
$result = $status_query->get_result();
while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
    if ($row['status'] !== 'approved') $all_approved = false;
}
$status_query->close();

// If all documents are approved, redirect to dashboard with success message
if (!empty($documents) && $all_approved) {
    $_SESSION['documents_approved'] = true;
    header('Location: dashboard.php?msg=Documents approved!');
    exit();
}

// Handle form submission
$upload_message = '';
$upload_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_documents'])) {
    // Define required document types
    $required_types = ['business_registration', 'tax_id', 'proof_of_address', 'certificate_of_incorporation'];
    $errors = [];

    // Ensure upload directory exists
    $upload_dir = __DIR__ . '/uploads/company_docs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    foreach ($required_types as $type) {
        if (isset($_FILES[$type]) && $_FILES[$type]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$type];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (!in_array(strtolower($ext), $allowed_ext)) {
                $errors[] = "Invalid file type for $type. Allowed: PDF, JPG, PNG, DOC, DOCX.";
                continue;
            }
            // Generate unique filename
            $new_filename = 'doc_' . $user_id . '_' . $type . '_' . time() . '.' . $ext;
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Insert or update record
                $stmt = $conn->prepare("INSERT INTO company_documents (user_id, document_type, file_name, file_path, mime_type, file_size, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                                        ON DUPLICATE KEY UPDATE 
                                        file_name = VALUES(file_name), 
                                        file_path = VALUES(file_path), 
                                        mime_type = VALUES(mime_type), 
                                        file_size = VALUES(file_size), 
                                        status = 'pending', 
                                        updated_at = CURRENT_TIMESTAMP");
                $mime = mime_content_type($destination) ?: 'application/octet-stream';
                $size = filesize($destination);
                $stmt->bind_param("issssi", $user_id, $type, $new_filename, $destination, $mime, $size);
                if ($stmt->execute()) {
                    // Success
                } else {
                    $errors[] = "Database error for $type: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Failed to upload $type.";
            }
        } else {
            // File not uploaded – check if we already have a previous file for this type
            $check = $conn->prepare("SELECT id FROM company_documents WHERE user_id = ? AND document_type = ?");
            $check->bind_param("is", $user_id, $type);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) {
                $errors[] = "Please upload the $type document.";
            }
        }
    }

    if (empty($errors)) {
        // Redirect to dashboard with success flag
        header("Location: dashboard.php?doc_uploaded=1");
        exit();
    } else {
        $upload_error = implode('<br>', $errors);
    }
}

// Get user's fullname for sidebar
if (!isset($_SESSION['fullname'])) {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $_SESSION['fullname'] = $stmt->get_result()->fetch_assoc()['fullname'] ?? 'User';
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Documents – RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== EXACT SAME CSS AS DASHBOARD ========== */
        /* We copy the same CSS from dashboard.php to maintain consistency */
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
            --font-normal: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-bold: 700;
            --leading-normal: 1.5;
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

        /* Dropdowns */
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
        .dropdown-header a { font-size: var(--text-xs); color: var(--primary); cursor: pointer; }
        .dropdown-header a:hover { text-decoration: underline; }
        .dropdown-item {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); font-size: var(--text-sm);
            color: var(--text-secondary); transition: all var(--transition-fast); cursor: pointer; text-decoration: none;
        }
        .dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown-item svg { flex-shrink: 0; width: 16px; height: 16px; }
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

        /* Alerts */
        .alert {
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border-left: 4px solid;
        }
        .alert-success { background: var(--success-light); color: var(--success-dark); border-left-color: var(--success); }
        .alert-error { background: var(--error-light); color: var(--error-dark); border-left-color: var(--error); }
        .alert-info { background: var(--primary-light); color: var(--primary-dark); border-left-color: var(--primary); }
        .alert-warning { background: var(--warning-light); color: var(--warning-dark); border-left-color: var(--warning); }

        /* Document form */
        .doc-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-bottom: var(--space-4);
            transition: border-color var(--transition-fast);
        }
        .doc-card:hover { border-color: var(--border-secondary); }
        .doc-card h3 { font-size: var(--text-base); font-weight: var(--font-semibold); margin-bottom: var(--space-2); }
        .doc-card .desc { font-size: var(--text-sm); color: var(--text-secondary); margin-bottom: var(--space-3); }
        .file-input-wrapper { display: flex; align-items: center; gap: var(--space-4); flex-wrap: wrap; }
        .file-input-wrapper input[type="file"] { flex: 1; padding: var(--space-2); border: 1px dashed var(--border-primary); border-radius: var(--radius-md); background: var(--bg-tertiary); color: var(--text-primary); cursor: pointer; }
        .file-input-wrapper input[type="file"]:hover { border-color: var(--primary); }
        .file-status { display: inline-flex; align-items: center; gap: var(--space-2); font-size: var(--text-sm); }
        .status-badge { padding: 2px 10px; border-radius: var(--radius-full); font-size: 0.7rem; font-weight: var(--font-semibold); }
        .status-pending { background: var(--warning-light); color: var(--warning-dark); }
        .status-approved { background: var(--success-light); color: var(--success-dark); }
        .status-rejected { background: var(--error-light); color: var(--error-dark); }
        .required-star { color: var(--error); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: var(--space-2); padding: var(--space-3) var(--space-5);
            font-size: var(--text-sm); font-weight: var(--font-semibold);
            border-radius: var(--radius-md); transition: all var(--transition-fast);
            cursor: pointer; border: 1px solid transparent;
        }
        .btn-primary { background: var(--gradient-primary); color: var(--text-inverse); border: none; box-shadow: 0 2px 8px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(99,102,241,0.35); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-primary); }
        .btn-ghost:hover { background: var(--bg-hover); color: var(--text-primary); }

        .back-link { display: inline-block; margin-top: var(--space-6); color: var(--text-secondary); font-size: var(--text-sm); transition: color var(--transition-fast); }
        .back-link:hover { color: var(--primary); text-decoration: underline; }

        /* Toasts (same as dashboard) */
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

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: var(--z-fixed); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-xl); }
            .sidebar.collapsed { width: var(--sidebar-width); transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .topbar-user-info { display: none; }
            .page-content { padding: var(--space-4); }
            .file-input-wrapper { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) {
            .topbar-search { display: none; }
            .topbar { padding: 0 var(--space-3); }
            .page-title { font-size: var(--text-xl); }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <img class="rdv-brand-logo" src="assets/brand-logo.png" alt="RD Vendora">
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="products.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="sidebar-link-text">Products</span>
            </a>
            <a href="orders.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="sidebar-link-text">Orders</span>
            </a>
            <a href="customers.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="sidebar-link-text">Customers</span>
            </a>
            <div class="sidebar-section-title">Store</div>
            <a href="storefront.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="sidebar-link-text">Storefront</span>
            </a>
            <a href="settings.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="sidebar-link-text">Store Settings</span>
            </a>
            <a href="subscription.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span class="sidebar-link-text">Subscription</span>
            </a>
            <a href="vendor-chat.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span class="sidebar-link-text">Chat</span>
            </a>
            <div class="sidebar-section-title">Account</div>
            <a href="profile.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="sidebar-link-text">Profile</span>
            </a>
            <a href="#" class="sidebar-link" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="sidebar-link-text">Logout</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="sidebar-user-avatar">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                    <div class="sidebar-user-role">
                        <?= htmlspecialchars($_SESSION['store_name'] ?? 'No Store') ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-content" id="mainContent">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="globalSearch" placeholder="Search...">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role"><?= htmlspecialchars($_SESSION['store_name'] ?? 'No Store') ?></span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profile</a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <div class="page-header">
                <h1 class="page-title">📄 Company Documents</h1>
                <p class="page-subtitle">To activate your <strong>Empire</strong> plan, please upload the following documents. They will be verified by our admin within 24-48 hours.</p>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] == 1 && empty($upload_error)): ?>
                <div class="alert alert-success">✅ All documents have been submitted successfully. We'll notify you once they are approved.</div>
            <?php endif; ?>

            <?php if (isset($_GET['rejected']) && $_GET['rejected'] == 1): ?>
                <div class="alert alert-error">
                    ❌ Some of your documents were rejected. Please re‑upload the required documents below.
                </div>
            <?php endif; ?>

            <?php if ($upload_error): ?>
                <div class="alert alert-error">❌ <?= $upload_error ?></div>
            <?php endif; ?>

            <?php if (!empty($documents)): ?>
                <div class="alert alert-info">
                    <strong>Current status:</strong> 
                    <?php 
                        $pending = array_filter($documents, fn($d) => $d['status'] === 'pending');
                        $rejected = array_filter($documents, fn($d) => $d['status'] === 'rejected');
                        if ($rejected) echo "⚠️ Some documents were rejected. Please re-upload them below.";
                        elseif ($pending) echo "⏳ Your documents are under review. Please check back later.";
                        else echo "✅ All documents approved!";
                    ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="doc-card">
                    <h3>1. Business Registration Certificate <span class="required-star">*</span></h3>
                    <p class="desc">Upload your business registration or incorporation certificate (PDF, JPG, PNG, DOC, DOCX).</p>
                    <div class="file-input-wrapper">
                        <input type="file" name="business_registration" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <?php 
                            $doc = array_filter($documents, fn($d) => $d['document_type'] === 'business_registration');
                            if ($doc): $d = reset($doc); ?>
                                <span class="file-status">
                                    <span class="status-badge status-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
                                    <?php if ($d['status'] === 'rejected'): ?>
                                        <span style="color:var(--error);">(Please re-upload)</span>
                                    <?php endif; ?>
                                </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="doc-card">
                    <h3>2. Tax Identification Number (TIN) <span class="required-star">*</span></h3>
                    <p class="desc">Upload your TIN certificate or evidence of tax registration.</p>
                    <div class="file-input-wrapper">
                        <input type="file" name="tax_id" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <?php 
                            $doc = array_filter($documents, fn($d) => $d['document_type'] === 'tax_id');
                            if ($doc): $d = reset($doc); ?>
                                <span class="file-status">
                                    <span class="status-badge status-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
                                    <?php if ($d['status'] === 'rejected'): ?>
                                        <span style="color:var(--error);">(Please re-upload)</span>
                                    <?php endif; ?>
                                </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="doc-card">
                    <h3>3. Proof of Address <span class="required-star">*</span></h3>
                    <p class="desc">Utility bill, bank statement, or government-issued letter showing your business address (issued within last 3 months).</p>
                    <div class="file-input-wrapper">
                        <input type="file" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <?php 
                            $doc = array_filter($documents, fn($d) => $d['document_type'] === 'proof_of_address');
                            if ($doc): $d = reset($doc); ?>
                                <span class="file-status">
                                    <span class="status-badge status-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
                                    <?php if ($d['status'] === 'rejected'): ?>
                                        <span style="color:var(--error);">(Please re-upload)</span>
                                    <?php endif; ?>
                                </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="doc-card">
                    <h3>4. Certificate of Incorporation (CAC) <span class="required-star">*</span></h3>
                    <p class="desc">For registered companies. If you are a sole proprietor, upload a personal identification (ID card).</p>
                    <div class="file-input-wrapper">
                        <input type="file" name="certificate_of_incorporation" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <?php 
                            $doc = array_filter($documents, fn($d) => $d['document_type'] === 'certificate_of_incorporation');
                            if ($doc): $d = reset($doc); ?>
                                <span class="file-status">
                                    <span class="status-badge status-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span>
                                    <?php if ($d['status'] === 'rejected'): ?>
                                        <span style="color:var(--error);">(Please re-upload)</span>
                                    <?php endif; ?>
                                </span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" name="submit_documents" class="btn btn-primary">Submit Documents</button>
            </form>

            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ======================= SIDEBAR, THEME, DROPDOWN (same as dashboard) =======================
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        // Theme
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const cur = html.getAttribute('data-theme');
                const next = cur === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', next);
                localStorage.setItem('RD Vendora-theme', next);
            });
        }

        // Mobile sidebar
        function toggleMobileSidebar() {
            const isOpen = sidebar.classList.contains('mobile-open');
            if (isOpen) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        if (mobileToggle) mobileToggle.addEventListener('click', toggleMobileSidebar);
        if (overlay) overlay.addEventListener('click', toggleMobileSidebar);
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('collapsed');
                    toggleMobileSidebar();
                }
            });
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // User dropdown
        document.addEventListener('click', (e) => {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown && !userDropdown.contains(e.target)) userDropdown.classList.remove('open');
            else if (userDropdown && e.target.closest('.dropdown-trigger')) userDropdown.classList.toggle('open');
        });

        // Toast system
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
            toast.innerHTML = `<div class="toast-icon">${icons[type] || icons.info}</div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 300); }, 3500);
        }

        function handleLogout() { if (confirm('Logout?')) window.location.href = 'logout.php'; }

        // Search – just a placeholder
        document.getElementById('globalSearch')?.addEventListener('input', function() {
            // no-op, just for consistency
        });
    </script>
</body>
</html>