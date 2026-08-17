<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Allow only logged-in admins
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
}

// Determine if this is a super admin (role_name = 'super_admin')
$isSuperAdmin = isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin';

// Helper functions
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) return $row['setting_value'];
    return $default;
}

function setSetting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

// Create settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Initialize default settings
$defaultSettings = [
    'site_name' => 'RD Vendora',
    'site_email' => 'admin@rdvendora.com',
    'site_phone' => '+1234567890',
    'site_address' => '123 Market Street, Lagos, Nigeria',
    'currency' => '₦',
    'tax_rate' => '0.00',
    'maintenance_mode' => '0',
    'maintenance_end_time' => '',
    'admin_name' => 'Platform Admin',
    'admin_email' => 'admin@rdvendora.com',
    'default_theme' => 'light'
];
foreach ($defaultSettings as $key => $default) {
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $default);
    $stmt->execute();
}

// List of admin pages (for permissions UI)
$adminPages = [
    'dashboard' => 'Dashboard',
    'users' => 'Users',
    'stores' => 'Stores',
    'pricing' => 'Pricing Plans',
    'testimonials' => 'Testimonials',
    'contacts' => 'Contact Messages',
    'about' => 'About Page',
    'chat' => 'Chat',
    'orders' => 'All Orders',
    'transport' => 'Transport Orders',
    'customers' => 'Customers',
    'send_email' => 'Send Email',
    'marketplace_design' => 'Marketplace Design',
    'settings' => 'Settings'
];

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // General settings
    if (isset($_POST['update_general'])) {
        setSetting($conn, 'site_name', $_POST['site_name']);
        setSetting($conn, 'site_email', $_POST['site_email']);
        setSetting($conn, 'site_phone', $_POST['site_phone']);
        setSetting($conn, 'site_address', $_POST['site_address']);
        setSetting($conn, 'currency', $_POST['currency']);
        setSetting($conn, 'tax_rate', $_POST['tax_rate']);
        setSetting($conn, 'maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
        $endTime = !empty($_POST['maintenance_end_time']) ? $_POST['maintenance_end_time'] : '';
        setSetting($conn, 'maintenance_end_time', $endTime);
        $message = "General settings updated.";
        $messageType = "success";
    }
    // Admin profile update
    elseif (isset($_POST['update_profile'])) {
        $admin_name = trim($_POST['admin_name']);
        $admin_email = trim($_POST['admin_email']);
        $new_password = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (empty($admin_name) || empty($admin_email)) {
            $message = "Name and email required.";
            $messageType = "error";
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email.";
            $messageType = "error";
        } else {
            setSetting($conn, 'admin_name', $admin_name);
            setSetting($conn, 'admin_email', $admin_email);
            if (!empty($new_password)) {
                if ($new_password !== $confirm) {
                    $message = "Passwords do not match.";
                    $messageType = "error";
                } elseif (strlen($new_password) < 6) {
                    $message = "Password must be at least 6 characters.";
                    $messageType = "error";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ?, fullname = ?, email = ? WHERE email = ?");
                    $oldEmail = getSetting($conn, 'admin_email');
                    $stmt->bind_param("ssss", $hashed, $admin_name, $admin_email, $oldEmail);
                    if ($stmt->execute()) {
                        $message = "Profile and password updated.";
                        $messageType = "success";
                        $_SESSION['email'] = $admin_email;
                    } else {
                        $message = "Failed to update user.";
                        $messageType = "error";
                    }
                }
            } else {
                $oldEmail = getSetting($conn, 'admin_email');
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE email = ?");
                $stmt->bind_param("sss", $admin_name, $admin_email, $oldEmail);
                $stmt->execute();
                $message = "Profile updated.";
                $messageType = "success";
                $_SESSION['email'] = $admin_email;
            }
            setSetting($conn, 'admin_name', $admin_name);
            setSetting($conn, 'admin_email', $admin_email);
        }
    }
    // SMTP settings
    elseif (isset($_POST['update_smtp'])) {
        setSetting($conn, 'smtp_host', $_POST['smtp_host']);
        setSetting($conn, 'smtp_port', $_POST['smtp_port']);
        setSetting($conn, 'smtp_user', $_POST['smtp_user']);
        setSetting($conn, 'smtp_pass', $_POST['smtp_pass']);
        setSetting($conn, 'smtp_encryption', $_POST['smtp_encryption']);
        $message = "SMTP settings saved.";
        $messageType = "success";
    }

    // ----- SUPER ADMIN ONLY ACTIONS -----
    if ($isSuperAdmin) {
        // ----- ROLE MANAGEMENT -----
        if (isset($_POST['add_role'])) {
            $roleName = trim($_POST['role_name']);
            $roleDesc = trim($_POST['role_description']);
            if (empty($roleName)) {
                $message = "Role name is required.";
                $messageType = "error";
            } else {
                $check = $conn->prepare("SELECT id FROM roles WHERE name = ?");
                $check->bind_param("s", $roleName);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $message = "Role already exists.";
                    $messageType = "error";
                } else {
                    $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                    $stmt->bind_param("ss", $roleName, $roleDesc);
                    if ($stmt->execute()) {
                        $roleId = $conn->insert_id;
                        foreach ($adminPages as $pageKey => $pageLabel) {
                            $permStmt = $conn->prepare("INSERT INTO role_permissions (role_id, page_name, can_access) VALUES (?, ?, 0)");
                            $permStmt->bind_param("is", $roleId, $pageKey);
                            $permStmt->execute();
                        }
                        $message = "Role created. Now set permissions below.";
                        $messageType = "success";
                    } else {
                        $message = "Database error.";
                        $messageType = "error";
                    }
                }
            }
        }
        elseif (isset($_POST['update_role_permissions']) && isset($_POST['role_id'])) {
            $roleId = intval($_POST['role_id']);
            $checkRole = $conn->prepare("SELECT name FROM roles WHERE id = ?");
            $checkRole->bind_param("i", $roleId);
            $checkRole->execute();
            $roleData = $checkRole->get_result()->fetch_assoc();
            if ($roleData && $roleData['name'] === 'super_admin') {
                $message = "Cannot change permissions for super admin role.";
                $messageType = "error";
            } else {
                foreach ($adminPages as $pageKey => $label) {
                    $can = isset($_POST['perm_' . $pageKey]) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, page_name, can_access) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_access = ?");
                    $stmt->bind_param("isii", $roleId, $pageKey, $can, $can);
                    $stmt->execute();
                }
                $message = "Role permissions updated.";
                $messageType = "success";
            }
        }
        elseif (isset($_POST['delete_role']) && isset($_POST['del_role_id'])) {
            $delId = intval($_POST['del_role_id']);
            $checkRole = $conn->prepare("SELECT name FROM roles WHERE id = ?");
            $checkRole->bind_param("i", $delId);
            $checkRole->execute();
            $roleData = $checkRole->get_result()->fetch_assoc();
            if ($roleData && $roleData['name'] === 'super_admin') {
                $message = "Cannot delete super admin role.";
                $messageType = "error";
            } else {
                $delStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $delStmt->bind_param("i", $delId);
                if ($delStmt->execute()) {
                    $message = "Role deleted.";
                    $messageType = "success";
                } else {
                    $message = "Deletion failed.";
                    $messageType = "error";
                }
            }
        }

        // Admin user management
        elseif (isset($_POST['add_admin'])) {
            $name = trim($_POST['admin_fullname']);
            $email = trim($_POST['admin_email_new']);
            $pass = $_POST['admin_password_new'];
            $roleId = intval($_POST['admin_role'] ?? 0);
            if (empty($name) || empty($email) || empty($pass) || $roleId <= 0) {
                $message = "All fields required (including role).";
                $messageType = "error";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Invalid email.";
                $messageType = "error";
            } elseif (strlen($pass) < 6) {
                $message = "Password must be 6+ chars.";
                $messageType = "error";
            } else {
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $message = "Email already exists.";
                    $messageType = "error";
                } else {
                    $hashed = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, is_admin, role_id, created_at) VALUES (?, ?, ?, 1, ?, NOW())");
                    $stmt->bind_param("sssi", $name, $email, $hashed, $roleId);
                    if ($stmt->execute()) {
                        $message = "Admin account created with assigned role.";
                        $messageType = "success";
                    } else {
                        $message = "Database error.";
                        $messageType = "error";
                    }
                }
            }
        }
        elseif (isset($_POST['update_admin_role']) && isset($_POST['admin_id'])) {
            $adminId = intval($_POST['admin_id']);
            $newRoleId = intval($_POST['admin_role'] ?? 0);
            if ($newRoleId <= 0) {
                $message = "Please select a valid role.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ? AND is_admin = 1");
                $stmt->bind_param("ii", $newRoleId, $adminId);
                if ($stmt->execute()) {
                    $message = "Admin role updated.";
                    $messageType = "success";
                } else {
                    $message = "Update failed.";
                    $messageType = "error";
                }
            }
        }
        elseif (isset($_POST['delete_admin']) && isset($_POST['del_admin_id'])) {
            $delId = intval($_POST['del_admin_id']);
            if ($delId == $_SESSION['user_id']) {
                $message = "You cannot delete your own account.";
                $messageType = "error";
            } else {
                $delStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 1");
                $delStmt->bind_param("i", $delId);
                if ($delStmt->execute()) {
                    $message = "Admin deleted.";
                    $messageType = "success";
                } else {
                    $message = "Deletion failed.";
                    $messageType = "error";
                }
            }
        }
    }
}

// Fetch current settings
$site_name = getSetting($conn, 'site_name');
$site_email = getSetting($conn, 'site_email');
$site_phone = getSetting($conn, 'site_phone');
$site_address = getSetting($conn, 'site_address');
$currency = getSetting($conn, 'currency');
$tax_rate = getSetting($conn, 'tax_rate');
$maintenance_mode = getSetting($conn, 'maintenance_mode');
$maintenance_end_time = getSetting($conn, 'maintenance_end_time');
$admin_name = getSetting($conn, 'admin_name');
$admin_email = getSetting($conn, 'admin_email');
$default_theme = getSetting($conn, 'default_theme');

$smtp_host = getSetting($conn, 'smtp_host');
$smtp_port = getSetting($conn, 'smtp_port');
$smtp_user = getSetting($conn, 'smtp_user');
$smtp_pass = getSetting($conn, 'smtp_pass');
$smtp_encryption = getSetting($conn, 'smtp_encryption');

// Fetch roles (for super admin)
$roles = [];
if ($isSuperAdmin) {
    $rolesRes = $conn->query("SELECT id, name, description FROM roles ORDER BY id ASC");
    $roles = $rolesRes->fetch_all(MYSQLI_ASSOC);
    foreach ($roles as &$role) {
        $permRes = $conn->query("SELECT page_name, can_access FROM role_permissions WHERE role_id = {$role['id']}");
        $role['permissions'] = [];
        while ($row = $permRes->fetch_assoc()) {
            $role['permissions'][$row['page_name']] = $row['can_access'];
        }
    }
    unset($role);
}

// Fetch all admin accounts (only for super admin)
$admins = [];
if ($isSuperAdmin) {
    $adminsQuery = $conn->query("SELECT u.id, u.fullname, u.email, u.role_id, r.name AS role_name, u.created_at 
                                 FROM users u 
                                 LEFT JOIN roles r ON u.role_id = r.id 
                                 WHERE u.is_admin = 1 
                                 ORDER BY u.id ASC");
    $admins = $adminsQuery->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($default_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        body { font-family: var(--font-sans); font-size: 0.9375rem; background: var(--bg-primary); color: var(--text-primary); line-height: 1.5; transition: background var(--transition), color var(--transition); }
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
        .page-header { padding: 1.5rem 2rem 0.5rem 2rem; margin-top: var(--topbar-height); }
        .page-title { font-size: 1.875rem; font-weight: 800; background: var(--gradient-primary); background-clip: text; -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem; }
        .settings-container { padding: 1.5rem 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 1.5rem; }
        @media (max-width: 768px) { .settings-container { grid-template-columns: 1fr; padding: 1rem; } }
        .settings-card { background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-xl); padding: 1.5rem; transition: var(--transition); }
        .settings-card:hover { box-shadow: var(--shadow-md); }
        .settings-card h3 { font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--border-primary); display: flex; align-items: center; gap: 0.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 0.25rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.6rem 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border-primary); border-radius: var(--radius); color: var(--text-primary); font-family: inherit; font-size: 0.875rem; transition: var(--transition); }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .btn { background: var(--gradient-primary); border: none; padding: 0.6rem 1.25rem; border-radius: var(--radius); color: white; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: var(--transition); }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: var(--bg-tertiary); color: var(--text-primary); }
        .btn-danger { background: var(--error); color: white; }
        .btn-sm { padding: 0.3rem 0.8rem; font-size: 0.75rem; }
        .message { padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: 0.875rem; }
        .message.success { background: var(--success-light); color: var(--success); border-left: 4px solid var(--success); }
        .message.error { background: var(--error-light); color: var(--error); border-left: 4px solid var(--error); }
        .role-item, .admin-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border-primary); }
        .role-info strong, .admin-info strong { display: block; }
        .role-info small, .admin-info small { color: var(--text-muted); font-size: 0.75rem; }
        .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.5rem; margin: 1rem 0; }
        .perm-check { display: flex; align-items: center; gap: 0.5rem; }
        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-size: 0.7rem; font-weight: 600; }
        .badge-primary { background: var(--primary-light); color: var(--primary); }
        .badge-secondary { background: var(--bg-tertiary); color: var(--text-secondary); }
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.5); z-index:299; display:none; backdrop-filter: blur(4px); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 200px; }
            .mobile-sidebar-toggle { display: flex; }
        }

        /* ========== TOGGLE SWITCH STYLES ========== */
        .toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-secondary);
            border-radius: 24px;
            transition: all var(--transition);
        }
        .toggle-slider::before {
            content: "";
            position: absolute;
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background: white;
            border-radius: 50%;
            transition: transform var(--transition);
            box-shadow: var(--shadow-sm);
        }
        .toggle-switch input:checked + .toggle-slider {
            background: var(--primary);
            border-color: var(--primary);
        }
        .toggle-switch input:checked + .toggle-slider::before {
            transform: translateX(20px);
        }
        .toggle-switch input:focus-visible + .toggle-slider {
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        .toggle-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            user-select: none;
        }
        .toggle-label .status-text {
            font-weight: 600;
            color: var(--text-muted);
        }
        .toggle-label .status-text.on {
            color: var(--primary);
        }
        .toggle-label .status-text.off {
            color: var(--text-muted);
        }
        .preview-link {
            margin-left: auto;
            font-size: 0.8rem;
            color: var(--primary);
            text-decoration: underline;
            white-space: nowrap;
        }
        .preview-link:hover {
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="nav-logo"><div class="nav-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div><span>RD Vendora</span></a>
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
        <a href="admin-customers.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Customers</span></a>
        <a href="admin-send-email.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Send Email</span></a>
        <a href="admin-marketplace-design.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Marketplace Design</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="adminsettings.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Settings</span></a>
        <a href="../dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search platform..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle"></button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="Admin"><div class="dash-user-info"><div class="name"><?= htmlspecialchars($admin_name) ?></div><div class="role"><?= $isSuperAdmin ? 'Super Admin' : 'Admin' ?></div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">System Settings</h1>
        <p class="page-subtitle">Configure your platform, profile, and email preferences</p>
    </div>

    <div class="settings-container">
        <!-- General Settings Card -->
        <div class="settings-card">
            <h3>⚙️ General Settings</h3>
            <?php if ($message && (strpos($message, 'General') !== false || empty($messageType)) && $messageType !== 'error') echo '<div class="message success">'.htmlspecialchars($message).'</div>';
            elseif ($message && $messageType === 'error') echo '<div class="message error">'.htmlspecialchars($message).'</div>'; ?>
            <form method="POST">
                <div class="form-group"><label>Site Name</label><input type="text" name="site_name" value="<?= htmlspecialchars($site_name) ?>" required></div>
                <div class="form-group"><label>Site Email</label><input type="email" name="site_email" value="<?= htmlspecialchars($site_email) ?>" required></div>
                <div class="form-group"><label>Contact Phone</label><input type="text" name="site_phone" value="<?= htmlspecialchars($site_phone) ?>"></div>
                <div class="form-group"><label>Address</label><textarea name="site_address" rows="2"><?= htmlspecialchars($site_address) ?></textarea></div>
                <div class="form-group"><label>Currency Symbol</label><input type="text" name="currency" value="<?= htmlspecialchars($currency) ?>" maxlength="5" required></div>
                <div class="form-group"><label>Tax Rate (%)</label><input type="number" step="any" name="tax_rate" value="<?= htmlspecialchars($tax_rate) ?>"></div>

                <!-- ======== TOGGLE SWITCH FOR MAINTENANCE MODE ======== -->
                <div class="form-group">
                    <div class="toggle-wrapper">
                        <label class="toggle-switch">
                            <input type="checkbox" name="maintenance_mode" value="1" id="maintenance_mode" <?= $maintenance_mode == '1' ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label" id="maintenanceLabel">
                            <span class="status-text <?= $maintenance_mode == '1' ? 'on' : 'off' ?>">
                                <?= $maintenance_mode == '1' ? 'ON' : 'OFF' ?>
                            </span>
                        </span>
                        <a href="maintenance.php" target="_blank" class="preview-link">Preview</a>
                    </div>
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">Toggle to enable/disable maintenance mode.</small>
                </div>

                <div class="form-group">
                    <label>Maintenance End Time (for countdown)</label>
                    <input type="datetime-local" name="maintenance_end_time" value="<?= htmlspecialchars($maintenance_end_time) ?>">
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">Leave empty for no countdown. Visitors will see a timer until this date/time.</small>
                </div>
                <button type="submit" name="update_general" class="btn">Save General Settings</button>
            </form>
        </div>

        <!-- Admin Profile Card -->
        <div class="settings-card">
            <h3>👤 Admin Profile</h3>
            <?php if ($message && (strpos($message, 'Profile') !== false || strpos($message, 'password') !== false) && $messageType !== 'error') echo '<div class="message success">'.htmlspecialchars($message).'</div>';
            elseif ($message && $messageType === 'error' && (strpos($message, 'Profile') !== false || strpos($message, 'password') !== false)) echo '<div class="message error">'.htmlspecialchars($message).'</div>'; ?>
            <form method="POST">
                <div class="form-group"><label>Full Name</label><input type="text" name="admin_name" value="<?= htmlspecialchars($admin_name) ?>" required></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="admin_email" value="<?= htmlspecialchars($admin_email) ?>" required></div>
                <div class="form-group"><label>New Password (leave blank to keep)</label><input type="password" name="new_password" autocomplete="new-password"></div>
                <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" autocomplete="off"></div>
                <button type="submit" name="update_profile" class="btn">Update Profile</button>
            </form>
        </div>

        <!-- SMTP Settings Card -->
        <div class="settings-card">
            <h3>📧 Email (SMTP) Settings</h3>
            <?php if ($message && strpos($message, 'SMTP') !== false) echo '<div class="message success">'.htmlspecialchars($message).'</div>'; ?>
            <form method="POST">
                <div class="form-group"><label>SMTP Host</label><input type="text" name="smtp_host" value="<?= htmlspecialchars($smtp_host) ?>" placeholder="smtp.gmail.com"></div>
                <div class="form-group"><label>SMTP Port</label><input type="text" name="smtp_port" value="<?= htmlspecialchars($smtp_port) ?>" placeholder="587"></div>
                <div class="form-group"><label>SMTP Username</label><input type="text" name="smtp_user" value="<?= htmlspecialchars($smtp_user) ?>"></div>
                <div class="form-group"><label>SMTP Password</label><input type="password" name="smtp_pass" value="<?= htmlspecialchars($smtp_pass) ?>"></div>
                <div class="form-group"><label>Encryption</label><select name="smtp_encryption"><option value="tls" <?= $smtp_encryption == 'tls' ? 'selected' : '' ?>>TLS</option><option value="ssl" <?= $smtp_encryption == 'ssl' ? 'selected' : '' ?>>SSL</option><option value="none" <?= $smtp_encryption == 'none' ? 'selected' : '' ?>>None</option></select></div>
                <button type="submit" name="update_smtp" class="btn">Save SMTP Settings</button>
            </form>
        </div>

        <?php if ($isSuperAdmin): ?>
        <!-- ========== ROLE MANAGEMENT ========== -->
        <div class="settings-card">
            <h3>🎭 Role Management</h3>
            <?php if ($message && (strpos($message, 'Role') !== false || strpos($message, 'permission') !== false)) {
                echo '<div class="message '.$messageType.'">'.htmlspecialchars($message).'</div>';
            } ?>
            <form method="POST" style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-primary);">
                <h4>➕ Add New Role</h4>
                <div class="form-group"><label>Role Name</label><input type="text" name="role_name" required placeholder="e.g. Content Manager"></div>
                <div class="form-group"><label>Description</label><input type="text" name="role_description" placeholder="Short description"></div>
                <button type="submit" name="add_role" class="btn">Create Role</button>
            </form>

            <h4>📋 Existing Roles & Permissions</h4>
            <?php foreach ($roles as $role): 
                $isSuperRole = ($role['name'] === 'super_admin');
                ?>
                <div class="role-item">
                    <div class="role-info">
                        <strong><?= htmlspecialchars($role['name']) ?></strong>
                        <small><?= htmlspecialchars($role['description']) ?></small>
                        <?php if ($isSuperRole): ?>
                            <span class="badge badge-primary">Super Admin</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isSuperRole): ?>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleRolePermForm(<?= $role['id'] ?>)">Edit Permissions</button>
                        <form method="POST" onsubmit="return confirm('Delete this role?')">
                            <input type="hidden" name="del_role_id" value="<?= $role['id'] ?>">
                            <button type="submit" name="delete_role" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Permissions form for this role -->
                <div id="role-perm-form-<?= $role['id'] ?>" style="display: none; margin: 1rem 0 1rem 1.5rem; padding: 1rem; background: var(--bg-tertiary); border-radius: var(--radius);">
                    <form method="POST">
                        <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                        <div class="perm-grid">
                            <?php foreach ($adminPages as $pageKey => $pageLabel): ?>
                                <label class="perm-check">
                                    <input type="checkbox" name="perm_<?= $pageKey ?>" value="1" <?= isset($role['permissions'][$pageKey]) && $role['permissions'][$pageKey] ? 'checked' : '' ?>>
                                    <?= $pageLabel ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="update_role_permissions" class="btn">Save Role Permissions</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleRolePermForm(<?= $role['id'] ?>)">Cancel</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ========== ADMIN USER MANAGEMENT (with role assignment) ========== -->
        <div class="settings-card">
            <h3>👥 Admin Accounts</h3>
            <?php if ($message && (strpos($message, 'Admin') !== false || strpos($message, 'account') !== false || strpos($message, 'deleted') !== false)) {
                echo '<div class="message '.$messageType.'">'.htmlspecialchars($message).'</div>';
            } ?>
            <form method="POST" style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-primary);">
                <h4>➕ Add New Admin</h4>
                <div class="form-group"><label>Full Name</label><input type="text" name="admin_fullname" required></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="admin_email_new" required></div>
                <div class="form-group"><label>Password (min 6 chars)</label><input type="password" name="admin_password_new" required></div>
                <div class="form-group"><label>Role</label>
                    <select name="admin_role" required>
                        <option value="">Select a role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_admin" class="btn">Create Admin</button>
            </form>

            <h4>📋 Existing Administrators</h4>
            <?php foreach ($admins as $admin): 
                $isSelf = ($admin['id'] == $_SESSION['user_id']);
                ?>
                <div class="admin-item">
                    <div class="admin-info">
                        <strong><?= htmlspecialchars($admin['fullname']) ?></strong>
                        <small><?= htmlspecialchars($admin['email']) ?> • Joined <?= date('M d, Y', strtotime($admin['created_at'])) ?></small>
                        <?php if ($admin['role_name'] === 'super_admin'): ?>
                            <span class="badge badge-primary">Super Admin</span>
                        <?php else: ?>
                            <span class="badge badge-secondary"><?= htmlspecialchars($admin['role_name'] ?? 'No role') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isSelf): ?>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAdminRoleForm(<?= $admin['id'] ?>)">Change Role</button>
                        <form method="POST" onsubmit="return confirm('Delete this admin?')">
                            <input type="hidden" name="del_admin_id" value="<?= $admin['id'] ?>">
                            <button type="submit" name="delete_admin" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Change role form -->
                <div id="admin-role-form-<?= $admin['id'] ?>" style="display: none; margin: 1rem 0 1rem 1.5rem; padding: 1rem; background: var(--bg-tertiary); border-radius: var(--radius);">
                    <form method="POST">
                        <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                        <div class="form-group">
                            <label>Assign Role</label>
                            <select name="admin_role" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= ($admin['role_id'] == $role['id']) ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="update_admin_role" class="btn">Save Role</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleAdminRoleForm(<?= $admin['id'] ?>)">Cancel</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Theme handling
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('RD Vendora-theme') || '<?= $default_theme ?>';
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

    // Sidebar handling
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

    // User dropdown
    const userDD = document.getElementById('userDropdown');
    if (userDD) {
        const trigger = userDD.querySelector('.dropdown-trigger');
        trigger.addEventListener('click', (e) => { e.stopPropagation(); userDD.classList.toggle('open'); });
        document.addEventListener('click', () => userDD.classList.remove('open'));
    }

    // Toggle functions for role and admin forms
    function toggleRolePermForm(roleId) {
        const div = document.getElementById('role-perm-form-' + roleId);
        if (div.style.display === 'none') div.style.display = 'block';
        else div.style.display = 'none';
    }
    function toggleAdminRoleForm(adminId) {
        const div = document.getElementById('admin-role-form-' + adminId);
        if (div.style.display === 'none') div.style.display = 'block';
        else div.style.display = 'none';
    }

    // Logout function
    function logout() { if(confirm('Logout from admin panel?')) window.location.href='../logout.php'; }

    // ========== UPDATE LABEL TEXT ON TOGGLE CHANGE ==========
    document.addEventListener('DOMContentLoaded', function() {
        const toggleCheckbox = document.getElementById('maintenance_mode');
        const labelSpan = document.querySelector('.toggle-label .status-text');
        if (toggleCheckbox && labelSpan) {
            function updateLabel() {
                const isChecked = toggleCheckbox.checked;
                labelSpan.textContent = isChecked ? 'ON' : 'OFF';
                labelSpan.className = 'status-text ' + (isChecked ? 'on' : 'off');
            }
            toggleCheckbox.addEventListener('change', updateLabel);
            updateLabel(); // set initial
        }
    });
</script>
</body>
</html>
<?php $conn->close(); ?>