<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/send_approval_email.php'; // for sending approval email

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

// Permission check
if (!adminHasPermission('stores', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage documents.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// Handle approve/reject actions
$action_message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $new_status = null;

    if ($_POST['action'] === 'approve_document') {
        $new_status = 'approved';
        $action_message = 'Document approved.';
    } elseif ($_POST['action'] === 'reject_document') {
        $new_status = 'rejected';
        $action_message = 'Document rejected.';
    } elseif ($_POST['action'] === 'approve_all') {
        // Approve all pending documents for this user and activate store
        $user_id = (int)$_POST['user_id'];
        $conn->begin_transaction();
        try {
            // Update all pending documents for this user to approved
            $stmt = $conn->prepare("UPDATE company_documents SET status = 'approved' WHERE user_id = ? AND status = 'pending'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // Get store_id and store_name for this user
            $stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($store) {
                // Update store status to active
                $stmt = $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?");
                $stmt->bind_param("i", $store['id']);
                $stmt->execute();
                $stmt->close();

                // Send approval email
                sendStoreApprovalEmail($user_id, $store['store_name']);
                $action_message = "All documents approved and store '{$store['store_name']}' activated. Email sent to owner.";
                $action_type = 'success';
            } else {
                throw new Exception("No store found for user ID $user_id");
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $action_message = "Error: " . $e->getMessage();
            $action_type = 'error';
        }
        // Redirect to refresh
        header("Location: admin-documents.php?message=" . urlencode($action_message) . "&type=" . $action_type);
        exit();
    }

    if ($new_status !== null && $doc_id > 0) {
        $stmt = $conn->prepare("UPDATE company_documents SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $doc_id);
        if ($stmt->execute()) {
            $action_message = "Document status updated to $new_status.";
            $action_type = 'success';
            // Check if all documents for this user are now approved
            $check_stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM company_documents WHERE user_id = (SELECT user_id FROM company_documents WHERE id = ?) AND status = 'pending'");
            $check_stmt->bind_param("i", $doc_id);
            $check_stmt->execute();
            $pending = $check_stmt->get_result()->fetch_assoc()['pending_count'] ?? 0;
            $check_stmt->close();
            if ($pending == 0) {
                // All documents for this user are now approved – auto‑activate store
                $user_stmt = $conn->prepare("SELECT user_id FROM company_documents WHERE id = ?");
                $user_stmt->bind_param("i", $doc_id);
                $user_stmt->execute();
                $uid = $user_stmt->get_result()->fetch_assoc()['user_id'] ?? 0;
                $user_stmt->close();
                if ($uid) {
                    $store_stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ?");
                    $store_stmt->bind_param("i", $uid);
                    $store_stmt->execute();
                    $store = $store_stmt->get_result()->fetch_assoc();
                    $store_stmt->close();
                    if ($store) {
                        $conn->query("UPDATE stores SET status = 'active' WHERE id = {$store['id']}");
                        sendStoreApprovalEmail($uid, $store['store_name']);
                        $action_message .= " All documents are now approved – store '{$store['store_name']}' has been activated and the owner notified.";
                    }
                }
            }
        } else {
            $action_message = "Database error: " . $conn->error;
            $action_type = 'error';
        }
        $stmt->close();
        header("Location: admin-documents.php?message=" . urlencode($action_message) . "&type=" . $action_type);
        exit();
    }
}

// Retrieve messages from redirect
$message = isset($_GET['message']) ? $_GET['message'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Fetch all users who have at least one document (with pending or rejected status)
$users = [];
$query = "
    SELECT 
        u.id as user_id, 
        u.fullname, 
        u.email,
        s.id as store_id,
        s.store_name,
        s.status as store_status
    FROM users u
    JOIN stores s ON u.id = s.user_id
    WHERE EXISTS (
        SELECT 1 FROM company_documents cd 
        WHERE cd.user_id = u.id 
        AND cd.status IN ('pending', 'rejected')
    )
    ORDER BY u.fullname
";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Fetch documents for this user
        $doc_stmt = $conn->prepare("SELECT * FROM company_documents WHERE user_id = ? ORDER BY document_type");
        $doc_stmt->bind_param("i", $row['user_id']);
        $doc_stmt->execute();
        $docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $doc_stmt->close();
        $row['documents'] = $docs;
        $users[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Review - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== SAME ADMIN CSS (matching admin.php) ========== */
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
        
        .alert {
            margin: 1rem 2rem 0;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
        }
        .alert-success { background: var(--success-light); color: #10b981; border: 1px solid var(--border-primary); }
        .alert-error { background: var(--error-light); color: #ef4444; border: 1px solid var(--border-primary); }
        
        .doc-container {
            padding: 1.5rem 2rem;
            display: flex; flex-direction: column; gap: 1.5rem;
        }
        .user-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            transition: var(--transition);
        }
        .user-card:hover { box-shadow: var(--shadow-md); }
        .user-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .user-info h3 { font-size: 1.1rem; font-weight: 700; }
        .user-info p { font-size: 0.875rem; color: var(--text-muted); }
        .store-status {
            font-size: 0.75rem; font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
        }
        .store-status.active { background: var(--success-light); color: var(--success); }
        .store-status.pending { background: var(--warning-light); color: var(--warning); }
        .store-status.inactive { background: var(--error-light); color: var(--error); }
        
        .doc-list {
            display: flex; flex-direction: column; gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .doc-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.75rem 1rem;
            background: var(--bg-tertiary);
            border-radius: var(--radius);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .doc-info {
            display: flex; align-items: center; gap: 0.75rem;
            flex-wrap: wrap;
        }
        .doc-type {
            font-weight: 600; font-size: 0.875rem;
            min-width: 140px;
        }
        .doc-status {
            font-size: 0.75rem; font-weight: 600;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            text-transform: uppercase;
        }
        .doc-status.pending { background: var(--warning-light); color: var(--warning); }
        .doc-status.approved { background: var(--success-light); color: var(--success); }
        .doc-status.rejected { background: var(--error-light); color: var(--error); }
        .doc-actions {
            display: flex; gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            color: var(--text-secondary);
            transition: var(--transition);
            cursor: pointer;
        }
        .btn-sm:hover { background: var(--primary-light); color: var(--primary); border-color: var(--primary); }
        .btn-approve { background: var(--success-light); color: var(--success); border-color: var(--success); }
        .btn-approve:hover { background: var(--success); color: white; }
        .btn-reject { background: var(--error-light); color: var(--error); border-color: var(--error); }
        .btn-reject:hover { background: var(--error); color: white; }
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            border: none;
            transition: var(--transition);
        }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-download {
            background: transparent;
            color: var(--primary);
            text-decoration: underline;
            font-size: 0.75rem;
        }
        .btn-download:hover { color: var(--primary-dark); }
        .empty-state {
            text-align: center; padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .empty-state svg { margin-bottom: 1rem; }
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); z-index:299; display:none; backdrop-filter: blur(4px); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 200px; }
            .mobile-sidebar-toggle { display: flex; }
            .doc-container { padding: 1rem; }
            .doc-item { flex-direction: column; align-items: stretch; }
            .doc-actions { justify-content: flex-start; }
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
        <a href="admin.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a href="admin-users.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
        <a href="admin-stores.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span>Stores</span></a>
        <a href="admin-documents.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span>Document Review</span></a>
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
        <div class="sidebar-section-title">Analytics</div>
        <a href="admin-user-activity.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>User Activity</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="adminsettings.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Settings</span></a>
        <a href="../dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="searchInput" placeholder="Search users..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Document Review</h1>
        <p class="page-subtitle">Review company documents submitted by Empire plan users and activate their stores.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($type) === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="doc-container">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <h3 style="margin-bottom: 0.5rem;">No documents pending</h3>
                <p>All submitted company documents have been reviewed.</p>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php 
                    $pending_docs = array_filter($user['documents'], fn($d) => $d['status'] === 'pending');
                    $has_pending = count($pending_docs) > 0;
                ?>
                <div class="user-card" data-user-id="<?= $user['user_id'] ?>">
                    <div class="user-header">
                        <div class="user-info">
                            <h3><?= htmlspecialchars($user['fullname']) ?></h3>
                            <p><?= htmlspecialchars($user['email']) ?> • Store: <?= htmlspecialchars($user['store_name']) ?></p>
                        </div>
                        <div>
                            <span class="store-status <?= htmlspecialchars($user['store_status']) ?>">
                                Store: <?= ucfirst($user['store_status']) ?>
                            </span>
                            <?php if ($has_pending && $user['store_status'] !== 'active'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve all documents and activate this store?')">
                                    <input type="hidden" name="action" value="approve_all">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" class="btn-primary">✅ Approve All & Activate Store</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="doc-list">
                        <?php foreach ($user['documents'] as $doc): 
                            $doc_types = [
                                'business_registration' => 'Business Registration',
                                'tax_id' => 'Tax ID (TIN)',
                                'proof_of_address' => 'Proof of Address',
                                'certificate_of_incorporation' => 'Certificate of Incorporation (CAC)'
                            ];
                            $type_label = $doc_types[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                        ?>
                        <div class="doc-item">
                            <div class="doc-info">
                                <span class="doc-type"><?= $type_label ?></span>
                                <span class="doc-status <?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span>
                                <a href="<?= htmlspecialchars(rdv_admin_src($doc['file_path'])) ?>" target="_blank" class="btn-download">📎 Download</a>
                            </div>
                            <div class="doc-actions">
                                <?php if ($doc['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="action" value="approve_document">
                                        <button type="submit" class="btn-sm btn-approve">✅ Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="action" value="reject_document">
                                        <button type="submit" class="btn-sm btn-reject">❌ Reject</button>
                                    </form>
                                <?php elseif ($doc['status'] === 'rejected'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="action" value="approve_document">
                                        <button type="submit" class="btn-sm btn-approve">✅ Approve</button>
                                    </form>
                                    <span style="font-size:0.75rem;color:var(--text-muted);">Rejected</span>
                                <?php else: ?>
                                    <span style="font-size:0.75rem;color:var(--success);">Approved ✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Theme
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

    // Sidebar
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
    document.getElementById('searchInput')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.user-card').forEach(card => {
            const name = card.querySelector('.user-info h3')?.textContent?.toLowerCase() || '';
            const email = card.querySelector('.user-info p')?.textContent?.toLowerCase() || '';
            card.style.display = (name.includes(term) || email.includes(term)) ? '' : 'none';
        });
    });

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='../logout.php'; }
</script>
</body>
</html>