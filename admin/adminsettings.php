<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

rdv_ensure_rbac_tables($conn);
rdv_hydrate_admin_session($conn);

if (!rdv_admin_flag_is_set()) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
}

$isSuperAdmin = isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin';
if (!adminHasPermission('settings', $conn)) {
    http_response_code(403);
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage settings.</p><a href="admin">Dashboard</a></div>');
}

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
    'admin_alert_email' => '',
    'default_theme' => 'light',
    'developer_credit_label' => 'RD NEXA TECH',
    'developer_credit_url' => ''
];
foreach ($defaultSettings as $key => $default) {
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $default);
    $stmt->execute();
}

// List of admin pages (for permissions UI)
$adminPages = rdv_admin_pages();

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // General settings
    if (isset($_POST['update_general'])) {
        setSetting($conn, 'site_name', $_POST['site_name']);
        setSetting($conn, 'site_email', $_POST['site_email']);
        setSetting($conn, 'admin_alert_email', trim((string) ($_POST['admin_alert_email'] ?? '')));
        setSetting($conn, 'site_phone', $_POST['site_phone']);
        setSetting($conn, 'site_address', $_POST['site_address']);
        setSetting($conn, 'currency', $_POST['currency']);
        setSetting($conn, 'tax_rate', $_POST['tax_rate']);
        setSetting($conn, 'maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
        $endTime = !empty($_POST['maintenance_end_time']) ? $_POST['maintenance_end_time'] : '';
        setSetting($conn, 'maintenance_end_time', $endTime);
        setSetting($conn, 'developer_credit_label', trim((string) ($_POST['developer_credit_label'] ?? 'RD NEXA TECH')));
        $devUrl = trim((string) ($_POST['developer_credit_url'] ?? ''));
        if ($devUrl !== '' && !filter_var($devUrl, FILTER_VALIDATE_URL)) {
            $message = "Developer credit URL must be a valid URL (include https://).";
            $messageType = "error";
        } else {
            setSetting($conn, 'developer_credit_url', $devUrl);
        }
        $gaId = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['google_analytics_id'] ?? '')));
        if ($gaId !== '' && !preg_match('/^G-[A-Z0-9]{6,}$/', $gaId)) {
            $message = "Google Analytics ID must look like G-XXXXXXXXXX.";
            $messageType = "error";
        } else {
            setSetting($conn, 'google_analytics_id', $gaId);
        }
        if ($messageType !== 'error') {
            $message = "General settings updated.";
            $messageType = "success";
        }
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
    elseif (isset($_POST['update_google_oauth'])) {
        $gid = trim((string) ($_POST['google_client_id'] ?? ''));
        $gsecret = trim((string) ($_POST['google_client_secret'] ?? ''));
        $gredir = 'https://rdvendora.com/oauth2callback.php';
        if ($gid !== '' && !preg_match('/\.apps\.googleusercontent\.com$/', $gid)) {
            $message = 'Google Client ID should end with .apps.googleusercontent.com';
            $messageType = 'error';
        } else {
            setSetting($conn, 'google_client_id', $gid);
            if ($gsecret !== '' && $gsecret !== '********') {
                setSetting($conn, 'google_client_secret', $gsecret);
            }
            setSetting($conn, 'google_redirect_uri', $gredir);
            $message = 'Google Sign-In settings updated.';
            $messageType = 'success';
        }
    }
    elseif (isset($_POST['update_payment_keys'])) {
        $publicKeys = [
            'paystack_public_key' => trim((string) ($_POST['paystack_public_key'] ?? '')),
            'flutterwave_public_key' => trim((string) ($_POST['flutterwave_public_key'] ?? '')),
        ];
        foreach ($publicKeys as $key => $val) {
            setSetting($conn, $key, $val);
        }
        $secrets = [
            'paystack_secret_key' => trim((string) ($_POST['paystack_secret_key'] ?? '')),
            'flutterwave_secret_key' => trim((string) ($_POST['flutterwave_secret_key'] ?? '')),
            'flutterwave_encryption_key' => trim((string) ($_POST['flutterwave_encryption_key'] ?? '')),
        ];
        foreach ($secrets as $key => $val) {
            if ($val !== '' && $val !== '********') {
                setSetting($conn, $key, $val);
            }
        }
        $message = 'Payment keys saved.';
        $messageType = 'success';
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
                rdv_save_page_permissions(
                    $conn,
                    'role_permissions',
                    'role_id',
                    $roleId,
                    $adminPages,
                    rdv_posted_page_permissions($adminPages)
                );
                $message = "Role permissions updated.";
                $messageType = "success";
            }
        }
        elseif (!empty($_POST['del_role_id'])) {
            $delId = intval($_POST['del_role_id']);
            $checkRole = $conn->prepare("SELECT name FROM roles WHERE id = ?");
            $checkRole->bind_param("i", $delId);
            $checkRole->execute();
            $roleData = $checkRole->get_result()->fetch_assoc();
            $checkRole->close();
            if ($roleData && ($roleData['name'] ?? '') === 'super_admin') {
                $message = "Cannot delete the Super Admin role.";
                $messageType = "error";
            } elseif (!$roleData) {
                $message = "That role was not found.";
                $messageType = "error";
            } else {
                $cols = rdv_admin_user_columns($conn, true);
                if (!empty($cols['role_id'])) {
                    $clear = $conn->prepare('UPDATE users SET role_id = NULL WHERE role_id = ?');
                    if ($clear) {
                        $clear->bind_param('i', $delId);
                        $clear->execute();
                        $clear->close();
                    }
                }
                $permDel = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
                if ($permDel) {
                    $permDel->bind_param('i', $delId);
                    $permDel->execute();
                    $permDel->close();
                }
                $delStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $delStmt->bind_param("i", $delId);
                if ($delStmt->execute() && $delStmt->affected_rows > 0) {
                    $next = function_exists('rdv_url')
                        ? rdv_url('admin/adminsettings', ['notice' => 'role_deleted'])
                        : 'adminsettings?notice=role_deleted';
                    header('Location: ' . $next);
                    exit;
                } else {
                    $message = "Could not delete this role. Assign its admins another role, then try again.";
                    $messageType = "error";
                }
                $delStmt->close();
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
                    $cols = rdv_admin_user_columns($conn, true);
                    $nameCol = !empty($cols['fullname']) ? 'fullname' : 'full_name';
                    $passCol = !empty($cols['password']) ? 'password' : 'password_hash';
                    $hashed = password_hash($pass, PASSWORD_DEFAULT);
                    $fields = [$nameCol, 'email', $passCol, 'is_admin'];
                    $placeholders = ['?', '?', '?', '1'];
                    $types = 'sss';
                    $values = [$name, $email, $hashed];
                    if (!empty($cols['role_id'])) {
                        $fields[] = 'role_id';
                        $placeholders[] = '?';
                        $types .= 'i';
                        $values[] = $roleId;
                    }
                    if (!empty($cols['is_active'])) {
                        $fields[] = 'is_active';
                        $placeholders[] = '1';
                    }
                    $sql = 'INSERT INTO users (' . implode(', ', $fields) . ', created_at) VALUES (' . implode(', ', $placeholders) . ', NOW())';
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        $message = "Database error.";
                        $messageType = "error";
                    } else {
                        $stmt->bind_param($types, ...$values);
                        if ($stmt->execute()) {
                            $newId = (int) $conn->insert_id;
                            rdv_save_page_permissions(
                                $conn,
                                'admin_permissions',
                                'admin_id',
                                $newId,
                                $adminPages,
                                rdv_posted_page_permissions($adminPages, 'new_perm_')
                            );
                            $message = "Admin account created. Page access was saved.";
                            $messageType = "success";
                        } else {
                            $message = "Database error.";
                            $messageType = "error";
                        }
                        $stmt->close();
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
                $roleCheck = $conn->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
                $roleName = '';
                if ($roleCheck) {
                    $roleCheck->bind_param('i', $newRoleId);
                    $roleCheck->execute();
                    $roleRow = $roleCheck->get_result()->fetch_assoc();
                    $roleName = (string) ($roleRow['name'] ?? '');
                    $roleCheck->close();
                }
                if ($roleName === 'super_admin') {
                    $message = "Assign a limited role instead of super admin.";
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
        }
        elseif (isset($_POST['update_admin_permissions']) && isset($_POST['admin_id'])) {
            $adminId = intval($_POST['admin_id']);
            if ($adminId === (int) ($_SESSION['user_id'] ?? 0)) {
                $message = "Change your own access from another super admin account.";
                $messageType = "error";
            } else {
                $newRoleId = intval($_POST['admin_role'] ?? 0);
                if ($newRoleId > 0) {
                    $roleCheck = $conn->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
                    if ($roleCheck) {
                        $roleCheck->bind_param('i', $newRoleId);
                        $roleCheck->execute();
                        $roleRow = $roleCheck->get_result()->fetch_assoc();
                        $roleCheck->close();
                        if ($roleRow && ($roleRow['name'] ?? '') !== 'super_admin') {
                            $upd = $conn->prepare('UPDATE users SET role_id = ? WHERE id = ? AND is_admin = 1');
                            if ($upd) {
                                $upd->bind_param('ii', $newRoleId, $adminId);
                                $upd->execute();
                                $upd->close();
                            }
                        }
                    }
                }
                rdv_save_page_permissions(
                    $conn,
                    'admin_permissions',
                    'admin_id',
                    $adminId,
                    $adminPages,
                    rdv_posted_page_permissions($adminPages)
                );
                $message = "Admin page access updated.";
                $messageType = "success";
            }
        }
        elseif (!empty($_POST['del_admin_id'])) {
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

if ($message === '' && isset($_GET['notice'])) {
    if ($_GET['notice'] === 'role_deleted') {
        $message = 'Role deleted. Admins who had it now need a new role assigned.';
        $messageType = 'success';
    }
}

// Fetch current settings
$site_name = getSetting($conn, 'site_name');
$site_email = getSetting($conn, 'site_email');
$admin_alert_email = getSetting($conn, 'admin_alert_email');
$site_phone = getSetting($conn, 'site_phone');
$site_address = getSetting($conn, 'site_address');
$currency = getSetting($conn, 'currency');
$tax_rate = getSetting($conn, 'tax_rate');
$maintenance_mode = getSetting($conn, 'maintenance_mode');
$maintenance_end_time = getSetting($conn, 'maintenance_end_time');
$admin_name = getSetting($conn, 'admin_name');
$admin_email = getSetting($conn, 'admin_email');
$default_theme = getSetting($conn, 'default_theme');
$google_analytics_id = getSetting($conn, 'google_analytics_id');
$developer_credit_label = getSetting($conn, 'developer_credit_label', 'RD NEXA TECH');
$developer_credit_url = getSetting($conn, 'developer_credit_url');

$smtp_host = getSetting($conn, 'smtp_host');
$smtp_port = getSetting($conn, 'smtp_port');
$smtp_user = getSetting($conn, 'smtp_user');
$smtp_pass = getSetting($conn, 'smtp_pass');
$smtp_encryption = getSetting($conn, 'smtp_encryption');
$google_client_id = getSetting($conn, 'google_client_id');
$google_client_secret = getSetting($conn, 'google_client_secret');
$google_redirect_uri = 'https://rdvendora.com/oauth2callback.php';
$payKeys = function_exists('rdv_payment_keys') ? rdv_payment_keys() : [];
$paystack_public_key = getSetting($conn, 'paystack_public_key', $payKeys['paystack_public'] ?? '');
$paystack_secret_key = getSetting($conn, 'paystack_secret_key', $payKeys['paystack_secret'] ?? '');
$flutterwave_public_key = getSetting($conn, 'flutterwave_public_key', $payKeys['flutterwave_public'] ?? '');
$flutterwave_secret_key = getSetting($conn, 'flutterwave_secret_key', $payKeys['flutterwave_secret'] ?? '');
$flutterwave_encryption_key = getSetting($conn, 'flutterwave_encryption_key', $payKeys['flutterwave_encryption'] ?? '');

// Fetch roles (for super admin)
$roles = [];
if ($isSuperAdmin) {
    $rolesRes = $conn->query("SELECT id, name, description FROM roles ORDER BY id ASC");
    $roles = ($rolesRes && method_exists($rolesRes, 'fetch_all')) ? $rolesRes->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($roles as &$role) {
        $role['permissions'] = [];
        $rid = (int) $role['id'];
        $permRes = $conn->query("SELECT page_name, can_access FROM role_permissions WHERE role_id = {$rid}");
        if ($permRes) {
            while ($row = $permRes->fetch_assoc()) {
                $role['permissions'][$row['page_name']] = $row['can_access'];
            }
        }
    }
    unset($role);
}

$admins = [];
if ($isSuperAdmin) {
    $cols = rdv_admin_user_columns($conn, true);
    $nameParts = [];
    foreach (['fullname', 'full_name', 'name'] as $candidate) {
        if (!empty($cols[$candidate])) {
            $nameParts[] = "NULLIF(u.$candidate, '')";
        }
    }
    $nameExpr = $nameParts ? ('COALESCE(' . implode(', ', $nameParts) . ', u.email)') : 'u.email';
    $adminsQuery = $conn->query("SELECT u.id, $nameExpr AS fullname, u.email, u.role_id, r.name AS role_name, u.created_at
                                 FROM users u
                                 LEFT JOIN roles r ON u.role_id = r.id
                                 WHERE u.is_admin = 1
                                 ORDER BY u.id ASC");
    $admins = ($adminsQuery && method_exists($adminsQuery, 'fetch_all')) ? $adminsQuery->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($admins as &$admin) {
        $admin['permissions'] = [];
        $aid = (int) $admin['id'];
        $permRes = $conn->prepare('SELECT page_name, can_access FROM admin_permissions WHERE admin_id = ?');
        if ($permRes) {
            $permRes->bind_param('i', $aid);
            $permRes->execute();
            $permRows = $permRes->get_result();
            while ($row = $permRows->fetch_assoc()) {
                $admin['permissions'][$row['page_name']] = (int) $row['can_access'];
            }
            $permRes->close();
        }
        if (!$admin['permissions'] && !empty($admin['role_id'])) {
            $rid = (int) $admin['role_id'];
            $rolePerms = $conn->prepare('SELECT page_name, can_access FROM role_permissions WHERE role_id = ?');
            if ($rolePerms) {
                $rolePerms->bind_param('i', $rid);
                $rolePerms->execute();
                $roleRows = $rolePerms->get_result();
                while ($row = $roleRows->fetch_assoc()) {
                    $admin['permissions'][$row['page_name']] = (int) $row['can_access'];
                }
                $rolePerms->close();
            }
        }
    }
    unset($admin);
}

$adminPageTitle = 'Admin Settings - RD Vendora';
$adminPageHeading = 'Settings';
$adminPageSubtitle = 'Platform configuration';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;

$adminPageGroups = [
    'Platform' => ['dashboard', 'users', 'stores', 'customers'],
    'Content' => ['testimonials', 'contacts', 'newsletter', 'blog', 'about'],
    'Commerce' => ['pricing', 'orders', 'transport', 'marketplace_design'],
    'System' => ['chat', 'send_email', 'settings'],
];
if (!function_exists('rdv_pretty_role_name')) {
    function rdv_pretty_role_name($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return 'No role';
        }
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
if (!function_exists('rdv_admin_initials')) {
    function rdv_admin_initials($name, $email = '') {
        $name = trim((string) $name);
        if ($name === '') {
            $name = (string) $email;
        }
        $parts = preg_split('/\s+/', $name);
        $initials = strtoupper(substr($parts[0] ?? 'A', 0, 1));
        if (isset($parts[1]) && $parts[1] !== '') {
            $initials .= strtoupper(substr($parts[1], 0, 1));
        }
        return $initials;
    }
}
if (!function_exists('rdv_render_access_pills')) {
    function rdv_render_access_pills(array $adminPages, array $groups, $namePrefix, array $checked = [], $locked = false) {
        $used = [];
        foreach ($groups as $group => $keys) {
            echo '<div class="access-group">';
            echo '<p class="access-group__title">' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<div class="access-pills">';
            foreach ($keys as $pageKey) {
                if (!isset($adminPages[$pageKey])) {
                    continue;
                }
                $used[$pageKey] = true;
                $on = $locked || !empty($checked[$pageKey]);
                $id = $namePrefix . $pageKey;
                echo '<label class="access-pill' . ($on ? ' is-on' : '') . ($locked ? ' is-locked' : '') . '">';
                if ($locked) {
                    echo '<input type="checkbox" checked disabled>';
                } else {
                    echo '<input type="checkbox" name="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" value="1"' . ($on ? ' checked' : '') . '>';
                }
                echo '<span>' . htmlspecialchars($adminPages[$pageKey], ENT_QUOTES, 'UTF-8') . '</span></label>';
            }
            echo '</div></div>';
        }
        $leftover = array_diff_key($adminPages, $used);
        if ($leftover) {
            echo '<div class="access-group"><p class="access-group__title">Other</p><div class="access-pills">';
            foreach ($leftover as $pageKey => $pageLabel) {
                $on = $locked || !empty($checked[$pageKey]);
                $id = $namePrefix . $pageKey;
                echo '<label class="access-pill' . ($on ? ' is-on' : '') . ($locked ? ' is-locked' : '') . '">';
                if ($locked) {
                    echo '<input type="checkbox" checked disabled>';
                } else {
                    echo '<input type="checkbox" name="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" value="1"' . ($on ? ' checked' : '') . '>';
                }
                echo '<span>' . htmlspecialchars($pageLabel, ENT_QUOTES, 'UTF-8') . '</span></label>';
            }
            echo '</div></div>';
        }
    }
}

require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <div class="settings-container">
        <!-- General Settings Card -->
        <div class="settings-card">
            <h3>⚙️ General Settings</h3>
            <?php if ($message && (strpos($message, 'General') !== false || empty($messageType)) && $messageType !== 'error') echo '<div class="message success">'.htmlspecialchars($message).'</div>';
            elseif ($message && $messageType === 'error') echo '<div class="message error">'.htmlspecialchars($message).'</div>'; ?>
            <form method="POST">
                <div class="form-group"><label>Site Name</label><input type="text" name="site_name" value="<?= htmlspecialchars($site_name) ?>" required></div>
                <div class="form-group"><label>Site Email</label><input type="email" name="site_email" value="<?= htmlspecialchars($site_email) ?>" required></div>
                <div class="form-group"><label>Admin login alert email</label><input type="email" name="admin_alert_email" value="<?= htmlspecialchars($admin_alert_email) ?>" placeholder="Your Gmail address"><small>Gmail that receives a name, email, IP, and deactivate link whenever someone signs in to the admin dashboard.</small></div>
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
                        <a href="maintenance" target="_blank" class="preview-link">Preview</a>
                    </div>
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">Toggle to enable/disable maintenance mode.</small>
                </div>
                <div class="form-group">
                    <label>Google Analytics 4 ID</label>
                    <input type="text" name="google_analytics_id" value="<?= htmlspecialchars($google_analytics_id) ?>" placeholder="G-XXXXXXXXXX" autocomplete="off">
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">From analytics.google.com → Admin → Data streams. Tracking starts after a visitor accepts analytics cookies. Leave blank to disable.</small>
                </div>

                <div class="form-group">
                    <label>Maintenance End Time (for countdown)</label>
                    <input type="datetime-local" name="maintenance_end_time" value="<?= htmlspecialchars($maintenance_end_time) ?>">
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">Leave empty for no countdown. Visitors will see a timer until this date/time.</small>
                </div>
                <div class="form-group">
                    <label>Footer developer credit label</label>
                    <input type="text" name="developer_credit_label" value="<?= htmlspecialchars($developer_credit_label) ?>" placeholder="RD NEXA TECH" required>
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">Shown in the site footer as “Developed by …”.</small>
                </div>
                <div class="form-group">
                    <label>Footer developer credit link</label>
                    <input type="url" name="developer_credit_url" value="<?= htmlspecialchars($developer_credit_url) ?>" placeholder="https://rdnexatech.com">
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">Optional URL for the developer name. Leave blank to show plain text.</small>
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

        <div class="settings-card">
            <h3>🔐 Google Sign-In</h3>
            <?php if ($message && strpos($message, 'Google') !== false) echo '<div class="message '.$messageType.'">'.htmlspecialchars($message).'</div>'; ?>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">Create a Web application OAuth client in Google Cloud, then paste the values here (or in production <code>.env</code>).</p>
            <form method="POST">
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="google_client_id" value="<?= htmlspecialchars($google_client_id) ?>" placeholder="xxxxx.apps.googleusercontent.com" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Client Secret</label>
                    <input type="password" name="google_client_secret" value="<?= $google_client_secret !== '' ? '********' : '' ?>" placeholder="Leave blank to keep current" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Authorized redirect URI</label>
                    <input type="url" name="google_redirect_uri" value="https://rdvendora.com/oauth2callback.php" readonly>
                    <small style="display:block;color:var(--text-muted);font-size:0.7rem;margin-top:4px;">Locked to the production HTTPS callback. Add this exact URI on the <strong>Web application</strong> OAuth client in Google Cloud. Authorized JavaScript origin: <code>https://rdvendora.com</code>.</small>
                </div>
                <button type="submit" name="update_google_oauth" class="btn">Save Google Sign-In</button>
            </form>
        </div>

        <div class="settings-card">
            <h3>💳 Payment Keys</h3>
            <?php if ($message && strpos($message, 'Payment') !== false) echo '<div class="message '.$messageType.'">'.htmlspecialchars($message).'</div>'; ?>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">Keys saved here are used for checkout and verification. Secret keys stay on the server and are not shown after save.</p>
            <form method="POST" autocomplete="off">
                <h4 style="margin:0 0 0.75rem;font-size:0.95rem;">Paystack</h4>
                <div class="form-group">
                    <label>Public key</label>
                    <input type="text" name="paystack_public_key" value="<?= htmlspecialchars($paystack_public_key) ?>" placeholder="pk_live_... or pk_test_..." autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Secret key</label>
                    <input type="password" name="paystack_secret_key" value="<?= $paystack_secret_key !== '' ? '********' : '' ?>" placeholder="Leave blank to keep current" autocomplete="new-password">
                </div>
                <h4 style="margin:1.25rem 0 0.75rem;font-size:0.95rem;">Flutterwave</h4>
                <div class="form-group">
                    <label>Public key</label>
                    <input type="text" name="flutterwave_public_key" value="<?= htmlspecialchars($flutterwave_public_key) ?>" placeholder="FLWPUBK_..." autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Secret key</label>
                    <input type="password" name="flutterwave_secret_key" value="<?= $flutterwave_secret_key !== '' ? '********' : '' ?>" placeholder="Leave blank to keep current" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Encryption key</label>
                    <input type="password" name="flutterwave_encryption_key" value="<?= $flutterwave_encryption_key !== '' ? '********' : '' ?>" placeholder="Leave blank to keep current" autocomplete="new-password">
                </div>
                <button type="submit" name="update_payment_keys" class="btn">Save Payment Keys</button>
            </form>
        </div>

        <?php if ($isSuperAdmin): ?>
        <section class="settings-card settings-card--wide access-studio">
            <div class="access-head">
                <div>
                    <p class="access-kicker">Access control</p>
                    <h3>Roles</h3>
                    <p class="access-lead">Create a role, then choose which dashboard pages it can open. Super Admin always keeps every page.</p>
                </div>
            </div>
            <?php if ($message && (stripos($message, 'Role') !== false || stripos($message, 'permission') !== false)): ?>
                <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="access-create">
                <div class="form-group">
                    <label for="role_name">Role name</label>
                    <input id="role_name" type="text" name="role_name" required placeholder="Content manager">
                </div>
                <div class="form-group">
                    <label for="role_description">Description</label>
                    <input id="role_description" type="text" name="role_description" placeholder="What this role can do">
                </div>
                <button type="submit" name="add_role" class="btn">Create role</button>
            </form>

            <div class="access-stack">
            <?php foreach ($roles as $role):
                $isSuperRole = ($role['name'] === 'super_admin');
                $roleDesc = trim((string) ($role['description'] ?? ''));
                ?>
                <article class="access-card<?= $isSuperRole ? ' access-card--locked' : '' ?>">
                    <header class="access-card__head" data-access-toggle role="button" tabindex="0" aria-expanded="false">
                        <div>
                            <h4><?= htmlspecialchars(rdv_pretty_role_name($role['name'])) ?></h4>
                            <p><?= htmlspecialchars($roleDesc !== '' ? $roleDesc : ($isSuperRole ? 'Full access to every page' : 'Click to set which pages this role can open')) ?></p>
                        </div>
                        <div class="access-card__meta">
                            <?php if ($isSuperRole): ?>
                                <span class="badge badge-primary">Protected</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?= htmlspecialchars(rdv_pretty_role_name($role['name'])) ?></span>
                                <button type="submit" form="delete-role-<?= (int) $role['id'] ?>" name="delete_role" class="btn btn-danger btn-sm">Delete</button>
                            <?php endif; ?>
                            <span class="access-chevron" aria-hidden="true"></span>
                        </div>
                    </header>
                    <div class="access-card__body">
                    <?php if (!$isSuperRole): ?>
                    <form id="delete-role-<?= (int) $role['id'] ?>" method="POST" onsubmit="return confirm('Delete this role? Admins using it will need a new role.')">
                        <input type="hidden" name="delete_role" value="1">
                        <input type="hidden" name="del_role_id" value="<?= (int) $role['id'] ?>">
                    </form>
                    <form method="POST">
                        <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
                        <?php rdv_render_access_pills($adminPages, $adminPageGroups, 'perm_', $role['permissions'] ?? []); ?>
                        <div class="access-card__foot">
                            <button type="submit" name="update_role_permissions" class="btn">Save role access</button>
                        </div>
                    </form>
                    <?php else: ?>
                        <?php rdv_render_access_pills($adminPages, $adminPageGroups, 'perm_', [], true); ?>
                    <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-card settings-card--wide access-studio">
            <div class="access-head">
                <div>
                    <p class="access-kicker">Team</p>
                    <h3>Administrators</h3>
                    <p class="access-lead">Invite an admin, assign a role, and turn individual pages on or off for that person.</p>
                </div>
            </div>
            <?php if ($message && (stripos($message, 'Admin') !== false || stripos($message, 'account') !== false || stripos($message, 'access') !== false || stripos($message, 'deleted') !== false)): ?>
                <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="access-card access-card--create">
                <header class="access-card__head" data-access-toggle role="button" tabindex="0" aria-expanded="false">
                    <div>
                        <h4>New administrator</h4>
                        <p>Click to add a person and choose their pages.</p>
                    </div>
                    <span class="access-chevron" aria-hidden="true"></span>
                </header>
                <div class="access-card__body">
                <div class="access-create access-create--admin">
                    <div class="form-group">
                        <label for="admin_fullname">Full name</label>
                        <input id="admin_fullname" type="text" name="admin_fullname" required placeholder="Jane Doe">
                    </div>
                    <div class="form-group">
                        <label for="admin_email_new">Email</label>
                        <input id="admin_email_new" type="email" name="admin_email_new" required placeholder="jane@company.com">
                    </div>
                    <div class="form-group">
                        <label for="admin_password_new">Password</label>
                        <input id="admin_password_new" type="password" name="admin_password_new" required minlength="6" placeholder="Min. 6 characters">
                    </div>
                    <div class="form-group">
                        <label for="admin_role_new">Role</label>
                        <select id="admin_role_new" name="admin_role" required>
                            <option value="">Select a role</option>
                            <?php foreach ($roles as $role): ?>
                                <?php if ($role['name'] === 'super_admin') continue; ?>
                                <option value="<?= (int) $role['id'] ?>"><?= htmlspecialchars(rdv_pretty_role_name($role['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php rdv_render_access_pills($adminPages, $adminPageGroups, 'new_perm_'); ?>
                <div class="access-card__foot">
                    <button type="submit" name="add_admin" class="btn">Create administrator</button>
                </div>
                </div>
            </form>

            <div class="access-stack">
            <?php foreach ($admins as $admin):
                $isSelf = ((int) $admin['id'] === (int) ($_SESSION['user_id'] ?? 0));
                $isSuperAccount = (($admin['role_name'] ?? '') === 'super_admin');
                $displayName = (string) ($admin['fullname'] ?? $admin['email']);
                ?>
                <article class="access-card<?= $isSuperAccount ? ' access-card--locked' : '' ?>">
                    <header class="access-card__head" data-access-toggle role="button" tabindex="0" aria-expanded="false">
                        <div class="access-person">
                            <span class="access-avatar" aria-hidden="true"><?= htmlspecialchars(rdv_admin_initials($displayName, $admin['email'] ?? '')) ?></span>
                            <div>
                                <h4><?= htmlspecialchars($displayName) ?><?php if ($isSelf): ?> <span class="access-you">You</span><?php endif; ?></h4>
                                <p><?= htmlspecialchars((string) $admin['email']) ?> · Joined <?= !empty($admin['created_at']) ? date('M j, Y', strtotime((string) $admin['created_at'])) : '—' ?></p>
                            </div>
                        </div>
                        <div class="access-card__meta">
                            <span class="badge <?= $isSuperAccount ? 'badge-primary' : 'badge-secondary' ?>"><?= htmlspecialchars(rdv_pretty_role_name($admin['role_name'] ?? 'No role')) ?></span>
                            <?php if (!$isSelf): ?>
                                <button type="submit" form="delete-admin-<?= (int) $admin['id'] ?>" name="delete_admin" class="btn btn-danger btn-sm">Remove</button>
                            <?php endif; ?>
                            <span class="access-chevron" aria-hidden="true"></span>
                        </div>
                    </header>
                    <div class="access-card__body">
                    <?php if (!$isSelf): ?>
                    <form id="delete-admin-<?= (int) $admin['id'] ?>" method="POST" onsubmit="return confirm('Remove this administrator?')">
                        <input type="hidden" name="delete_admin" value="1">
                        <input type="hidden" name="del_admin_id" value="<?= (int) $admin['id'] ?>">
                    </form>
                    <form method="POST">
                        <input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>">
                        <?php if ($isSuperAccount): ?>
                            <p class="access-note">This account currently has full access. Assign a limited role and save page access to restrict it.</p>
                        <?php endif; ?>
                        <div class="form-group access-role-field">
                            <label for="admin_role_<?= (int) $admin['id'] ?>">Assigned role</label>
                            <select id="admin_role_<?= (int) $admin['id'] ?>" name="admin_role">
                                <?php foreach ($roles as $role): ?>
                                    <?php if ($role['name'] === 'super_admin') continue; ?>
                                    <option value="<?= (int) $role['id'] ?>" <?= ((int) $admin['role_id'] === (int) $role['id']) ? 'selected' : '' ?>><?= htmlspecialchars(rdv_pretty_role_name($role['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php rdv_render_access_pills($adminPages, $adminPageGroups, 'perm_', $admin['permissions'] ?? []); ?>
                        <div class="access-card__foot">
                            <button type="submit" name="update_admin_permissions" class="btn">Save page access</button>
                            <button type="submit" name="update_admin_role" class="btn btn-secondary">Save role only</button>
                        </div>
                    </form>
                    <?php else: ?>
                        <?php rdv_render_access_pills($adminPages, $adminPageGroups, 'perm_', [], true); ?>
                    <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
<script>
document.querySelectorAll('.access-pill input[type="checkbox"]').forEach(function (input) {
    input.addEventListener('change', function () {
        var pill = this.closest('.access-pill');
        if (pill) pill.classList.toggle('is-on', this.checked);
    });
});
document.querySelectorAll('[data-access-toggle]').forEach(function (toggle) {
    var card = toggle.closest('.access-card');
    if (!card) return;
    function openCard() {
        document.querySelectorAll('.access-card.is-open').forEach(function (openCard) {
            if (openCard !== card) {
                openCard.classList.remove('is-open');
                var other = openCard.querySelector('[data-access-toggle]');
                if (other) other.setAttribute('aria-expanded', 'false');
            }
        });
        var open = card.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle.addEventListener('click', function (e) {
        if (e.target.closest('button, a, input, select, label')) return;
        openCard();
    });
    toggle.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openCard();
        }
    });
});
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
