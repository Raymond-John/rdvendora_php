<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function rdv_admin_flag_is_set() {
    if (!isset($_SESSION['is_admin'])) {
        return false;
    }
    $value = $_SESSION['is_admin'];
    return $value === true || $value === 1 || $value === '1';
}

function rdv_admin_user_columns(mysqli $conn, $refresh = false) {
    static $columns = null;
    if ($refresh) {
        $columns = null;
    }
    if ($columns !== null) {
        return $columns;
    }
    $columns = [];
    try {
        $result = $conn->query('SHOW COLUMNS FROM users');
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }
        }
    } catch (Throwable $e) {
        $columns = [];
    }
    return $columns;
}

function rdv_admin_super_role_id(mysqli $conn) {
    static $roleId = null;
    if ($roleId !== null) {
        return $roleId;
    }
    $roleId = 0;
    try {
        $result = $conn->query("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1");
        if ($result && ($row = $result->fetch_assoc())) {
            $roleId = (int) $row['id'];
        }
    } catch (Throwable $e) {
        $roleId = 0;
    }
    return $roleId;
}

function rdv_hydrate_admin_session($conn) {
    if (!$conn instanceof mysqli) {
        return false;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $email = trim((string) ($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));
    $columns = rdv_admin_user_columns($conn);

    $user = null;
    try {
        if ($userId > 0) {
            $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
            }
        }
        if (!$user && $email !== '') {
            $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        $user = null;
    }

    if (!$user) {
        return rdv_admin_flag_is_set();
    }

    $isActive = !isset($columns['is_active']) || (int) ($user['is_active'] ?? 1) === 1;
    $isDbAdmin = !empty($user['is_admin']);
    $isKnownPlatformAdmin = strcasecmp($email, 'admin@rdvendora.com') === 0
        || strcasecmp((string) ($user['email'] ?? ''), 'admin@rdvendora.com') === 0;

    if (!$isActive || (!$isDbAdmin && !$isKnownPlatformAdmin)) {
        $_SESSION['is_admin'] = false;
        unset($_SESSION['role_name'], $_SESSION['role_id']);
        return false;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['email'] = (string) ($user['email'] ?? $email);
    $name = $user['fullname'] ?? $user['full_name'] ?? $_SESSION['fullname'] ?? $_SESSION['email'];
    $_SESSION['fullname'] = (string) $name;
    $_SESSION['is_admin'] = true;

    $roleId = isset($columns['role_id']) ? (int) ($user['role_id'] ?? 0) : 0;
    $roleName = '';
    if ($roleId > 0) {
        try {
            $roleStmt = $conn->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
            if ($roleStmt) {
                $roleStmt->bind_param('i', $roleId);
                $roleStmt->execute();
                $roleRow = $roleStmt->get_result()->fetch_assoc();
                $roleStmt->close();
                $roleName = (string) ($roleRow['name'] ?? '');
            }
        } catch (Throwable $e) {
            $roleName = '';
        }
    }

    $legacyRole = strtolower(trim((string) ($user['role'] ?? '')));
    if ($roleName === '' && in_array($legacyRole, ['super_admin', 'platform_admin'], true)) {
        $roleName = $legacyRole;
    }

    $isPlatformOwner = strcasecmp((string) ($user['email'] ?? ''), 'admin@rdvendora.com') === 0;
    if ($roleName === 'super_admin' || $isPlatformOwner) {
        $superId = rdv_admin_super_role_id($conn);
        if ($superId > 0) {
            if ($roleId !== $superId && isset($columns['role_id'])) {
                try {
                    $fix = $conn->prepare('UPDATE users SET role_id = ? WHERE id = ?');
                    if ($fix) {
                        $uid = (int) $user['id'];
                        $fix->bind_param('ii', $superId, $uid);
                        $fix->execute();
                        $fix->close();
                    }
                } catch (Throwable $e) {
                    // Keep the session usable even if the write fails.
                }
            }
            $roleId = $superId > 0 ? $superId : $roleId;
        }
        $roleName = 'super_admin';
    } elseif ($roleName === '') {
        $roleName = 'admin';
    }

    $_SESSION['role_id'] = $roleId > 0 ? $roleId : null;
    $_SESSION['role_name'] = $roleName !== '' ? $roleName : 'admin';
    return true;
}

function rdv_require_admin_page($conn, $pageName = 'dashboard') {
    if (!rdv_hydrate_admin_session($conn) || !rdv_admin_flag_is_set()) {
        $login = function_exists('rdv_url') ? rdv_url('admin/admin_login') : 'admin/admin_login';
        header('Location: ' . $login);
        exit;
    }
    if (!adminHasPermission($pageName, $conn)) {
        http_response_code(403);
        $dash = function_exists('rdv_url') ? rdv_url('admin') : 'admin';
        echo '<div style="text-align:center;padding:3rem;font-family:sans-serif"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="' . htmlspecialchars($dash, ENT_QUOTES, 'UTF-8') . '">Dashboard</a></div>';
        exit;
    }
}

function adminHasPermission($pageName, $conn) {
    if ($conn instanceof mysqli) {
        rdv_hydrate_admin_session($conn);
    }

    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin') {
        return true;
    }

    if (!isset($_SESSION['user_id']) || !rdv_admin_flag_is_set()) {
        return false;
    }

    $userId = (int) $_SESSION['user_id'];
    $pageName = (string) $pageName;

    if ($conn instanceof mysqli) {
        try {
            $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM admin_permissions WHERE admin_id = ?');
            if ($countStmt) {
                $countStmt->bind_param('i', $userId);
                $countStmt->execute();
                $countRow = $countStmt->get_result()->fetch_assoc();
                $countStmt->close();
                if ((int) ($countRow['total'] ?? 0) > 0) {
                    $stmt = $conn->prepare('SELECT can_access FROM admin_permissions WHERE admin_id = ? AND page_name = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('is', $userId, $pageName);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        return $row ? !empty($row['can_access']) : false;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall through to role permissions.
        }
    }

    $roleId = (int) ($_SESSION['role_id'] ?? 0);
    if ($roleId > 0 && $conn instanceof mysqli) {
        try {
            $stmt = $conn->prepare('SELECT can_access FROM role_permissions WHERE role_id = ? AND page_name = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('is', $roleId, $pageName);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return !empty($row['can_access']);
                }
            }
        } catch (Throwable $e) {
            // No role permission row.
        }
    }

    return false;
}

function getFirstAllowedPage($conn) {
    rdv_hydrate_admin_session($conn);

    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin') {
        return 'admin.php';
    }
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $pageFiles = [
        'dashboard' => 'admin.php',
        'users' => 'admin-users.php',
        'stores' => 'admin-stores.php',
        'pricing' => 'admin-pricing.php',
        'testimonials' => 'admin-testimonies.php',
        'contacts' => 'admin-contacts.php',
        'newsletter' => 'admin-newsletter.php',
        'blog' => 'admin-blog.php',
        'about' => 'admin-about.php',
        'chat' => 'admin-chat.php',
        'orders' => 'admin-receive-order.php',
        'transport' => 'admin-transport.php',
        'customers' => 'admin-customers.php',
        'send_email' => 'admin-send-email.php',
        'marketplace_design' => 'admin-marketplace-design.php',
        'settings' => 'adminsettings.php',
    ];

    if (adminHasPermission('dashboard', $conn)) {
        return 'admin.php';
    }

    $roleId = (int) ($_SESSION['role_id'] ?? 0);
    $allowed = [];
    try {
        $userId = (int) $_SESSION['user_id'];
        $hasUserPerms = false;
        $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM admin_permissions WHERE admin_id = ?');
        if ($countStmt) {
            $countStmt->bind_param('i', $userId);
            $countStmt->execute();
            $hasUserPerms = ((int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0) > 0);
            $countStmt->close();
        }
        if ($hasUserPerms) {
            $stmt = $conn->prepare('SELECT page_name FROM admin_permissions WHERE admin_id = ? AND can_access = 1 ORDER BY page_name ASC');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $allowed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                $stmt->close();
            }
        } elseif ($roleId > 0) {
            $stmt = $conn->prepare('SELECT page_name FROM role_permissions WHERE role_id = ? AND can_access = 1 ORDER BY page_name ASC');
            if ($stmt) {
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                $allowed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        $allowed = [];
    }

    foreach ($allowed as $perm) {
        $pageKey = $perm['page_name'] ?? '';
        if (isset($pageFiles[$pageKey])) {
            return $pageFiles[$pageKey];
        }
    }

    return null;
}

function rdv_admin_count($conn, $sql) {
    try {
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            return (float) ($row['count'] ?? $row['total'] ?? $row['daily_total'] ?? $row['daily_count'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('Admin dashboard query failed: ' . $e->getMessage());
    }
    return 0;
}

if (!function_exists('rdv_admin_pages')) {
    function rdv_admin_pages() {
        return [
            'dashboard' => 'Dashboard',
            'users' => 'Users',
            'stores' => 'Stores',
            'pricing' => 'Pricing Plans',
            'testimonials' => 'Testimonials',
            'contacts' => 'Contact Messages',
            'newsletter' => 'Newsletter',
            'blog' => 'News',
            'about' => 'About Page',
            'chat' => 'Chat',
            'orders' => 'All Orders',
            'transport' => 'Transport Orders',
            'customers' => 'Customers',
            'send_email' => 'Send Email',
            'marketplace_design' => 'Marketplace Design',
            'settings' => 'Settings',
        ];
    }
}

if (!function_exists('rdv_ensure_rbac_tables')) {
    function rdv_ensure_rbac_tables(mysqli $conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $conn->query("CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL,
            page_name VARCHAR(100) NOT NULL,
            can_access TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_permission (role_id, page_name)
        )");
        $conn->query("CREATE TABLE IF NOT EXISTS admin_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            page_name VARCHAR(100) NOT NULL,
            can_access TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_permission (admin_id, page_name)
        )");
        try {
            $idx = $conn->query("SHOW INDEX FROM role_permissions WHERE Key_name = 'unique_role_permission'");
            if ($idx && $idx->num_rows === 0) {
                $conn->query('ALTER TABLE role_permissions ADD UNIQUE KEY unique_role_permission (role_id, page_name)');
            }
        } catch (Throwable $e) {
            // Index may already exist under another name.
        }
        try {
            $idx = $conn->query("SHOW INDEX FROM admin_permissions WHERE Key_name = 'unique_permission'");
            if ($idx && $idx->num_rows === 0) {
                $conn->query('ALTER TABLE admin_permissions ADD UNIQUE KEY unique_permission (admin_id, page_name)');
            }
        } catch (Throwable $e) {
            // Index may already exist.
        }
        $conn->query("INSERT IGNORE INTO roles (name, description) VALUES ('super_admin', 'Full system access')");
        $conn->query("INSERT IGNORE INTO roles (name, description) VALUES ('staff', 'Limited admin access')");
    }
}

if (!function_exists('rdv_save_page_permissions')) {
    function rdv_save_page_permissions(mysqli $conn, $table, $idColumn, $id, array $pages, array $posted) {
        $id = (int) $id;
        if ($id < 1 || !in_array($table, ['role_permissions', 'admin_permissions'], true)) {
            return false;
        }
        if (!in_array($idColumn, ['role_id', 'admin_id'], true)) {
            return false;
        }
        $del = $conn->prepare("DELETE FROM {$table} WHERE {$idColumn} = ?");
        if (!$del) {
            return false;
        }
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        $stmt = $conn->prepare("INSERT INTO {$table} ({$idColumn}, page_name, can_access) VALUES (?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        $ok = true;
        foreach ($pages as $pageKey => $label) {
            $can = !empty($posted[$pageKey]) ? 1 : 0;
            $stmt->bind_param('isi', $id, $pageKey, $can);
            if (!$stmt->execute()) {
                $ok = false;
            }
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('rdv_posted_page_permissions')) {
    function rdv_posted_page_permissions(array $pages, $prefix = 'perm_') {
        $posted = [];
        foreach ($pages as $pageKey => $label) {
            $posted[$pageKey] = !empty($_POST[$prefix . $pageKey]) ? 1 : 0;
        }
        return $posted;
    }
}
