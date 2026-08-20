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

function rdv_admin_user_columns(mysqli $conn) {
    static $columns = null;
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

    $isDbAdmin = $user && !empty($user['is_admin']);
    $isKnownPlatformAdmin = strcasecmp($email, 'admin@rdvendora.com') === 0
        || ($user && strcasecmp((string) ($user['email'] ?? ''), 'admin@rdvendora.com') === 0);

    if (!$isDbAdmin && !$isKnownPlatformAdmin && !rdv_admin_flag_is_set()) {
        return false;
    }
    if (!$user) {
        return rdv_admin_flag_is_set();
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
    if ($roleName === '' && in_array($legacyRole, ['super_admin', 'admin', 'platform_admin'], true)) {
        $roleName = $legacyRole === 'admin' ? 'super_admin' : $legacyRole;
    }

    if ($roleId < 1 || $roleName === '' || $roleName === 'vendor') {
        $superId = rdv_admin_super_role_id($conn);
        if ($superId > 0 && isset($columns['role_id'])) {
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
            $roleId = $superId;
        }
        $roleName = 'super_admin';
    }

    $_SESSION['role_id'] = $roleId > 0 ? $roleId : null;
    $_SESSION['role_name'] = $roleName !== '' ? $roleName : 'admin';
    return true;
}

function rdv_require_admin_page($conn, $pageName = 'dashboard') {
    if (!rdv_hydrate_admin_session($conn) || !rdv_admin_flag_is_set()) {
        header('Location: admin_login');
        exit;
    }
    if (!adminHasPermission($pageName, $conn)) {
        http_response_code(403);
        echo '<div style="text-align:center;padding:3rem;font-family:sans-serif"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="admin">Dashboard</a></div>';
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

    $roleId = (int) ($_SESSION['role_id'] ?? 0);
    if ($roleId > 0 && $conn instanceof mysqli) {
        try {
            $stmt = $conn->prepare('SELECT can_access FROM role_permissions WHERE role_id = ? AND page_name = ?');
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
            // Fall through to admin_permissions / is_admin.
        }
    }

    if ($conn instanceof mysqli) {
        try {
            $stmt = $conn->prepare('SELECT can_access FROM admin_permissions WHERE admin_id = ? AND page_name = ?');
            if ($stmt) {
                $userId = (int) $_SESSION['user_id'];
                $stmt->bind_param('is', $userId, $pageName);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return !empty($row['can_access']);
                }
            }
        } catch (Throwable $e) {
            // Ignore missing legacy table.
        }
    }

    return $roleId < 1 && rdv_admin_flag_is_set();
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
        if ($roleId > 0) {
            $stmt = $conn->prepare('SELECT page_name FROM role_permissions WHERE role_id = ? AND can_access = 1 ORDER BY page_name ASC');
            if ($stmt) {
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                $allowed = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare('SELECT page_name FROM admin_permissions WHERE admin_id = ? AND can_access = 1 ORDER BY page_name ASC');
            if ($stmt) {
                $userId = (int) $_SESSION['user_id'];
                $stmt->bind_param('i', $userId);
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

    return rdv_admin_flag_is_set() ? 'admin.php' : null;
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
