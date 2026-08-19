<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

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
    'newsletter' => 'Newsletter',
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
        $gaId = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['google_analytics_id'] ?? '')));
        if ($gaId !== '' && !preg_match('/^G-[A-Z0-9]{6,}$/', $gaId)) {
            $message = "Google Analytics ID must look like G-XXXXXXXXXX.";
            $messageType = "error";
        } else {
            setSetting($conn, 'google_analytics_id', $gaId);
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
$google_analytics_id = getSetting($conn, 'google_analytics_id');

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

$adminPageTitle = 'Admin Settings - RD Vendora';
$adminPageHeading = 'Settings';
$adminPageSubtitle = 'Platform configuration';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
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
                    <label>Google Analytics 4 ID</label>
                    <input type="text" name="google_analytics_id" value="<?= htmlspecialchars($google_analytics_id) ?>" placeholder="G-XXXXXXXXXX" autocomplete="off">
                    <small style="display:block; color:var(--text-muted); font-size:0.7rem; margin-top:4px;">From analytics.google.com → Admin → Data streams. Tracking starts after a visitor accepts analytics cookies. Leave blank to disable.</small>
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


</body>
</html>
<?php ?>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
