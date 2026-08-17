<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/admin_auth.php'; // for permission helper functions

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// If already logged in as admin, redirect to their permitted page
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    $target = getFirstAllowedPage($conn);
    if ($target) {
        header("Location: $target");
    } else {
        session_destroy();
        header('Location: admin_login.php?error=no_permissions');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        // Fetch user with role_id (if it exists; fallback to 'role' column for backward compatibility)
        $stmt = $conn->prepare("SELECT id, fullname, email, password, is_admin, role_id, role FROM users WHERE email = ? AND is_admin = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = true;

            // Determine role – prefer role_id + roles table, else fallback to 'role' column
            if (!empty($user['role_id'])) {
                $_SESSION['role_id'] = $user['role_id'];
                // Fetch role name from roles table
                $roleStmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
                $roleStmt->bind_param("i", $user['role_id']);
                $roleStmt->execute();
                $roleRes = $roleStmt->get_result();
                $roleRow = $roleRes->fetch_assoc();
                $_SESSION['role_name'] = $roleRow ? $roleRow['name'] : 'admin';
                $roleStmt->close();
            } else {
                // Fallback to old 'role' column
                $_SESSION['role_name'] = $user['role'] ?? 'admin';
                $_SESSION['role_id'] = null;
            }

            $target = getFirstAllowedPage($conn);
            if ($target) {
                header("Location: $target");
            } else {
                session_destroy();
                $error = 'Your account has no page permissions. Contact super admin.';
            }
            exit;
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}

/**
 * Get the first allowed page for the current admin.
 * Super admin (role_name = 'super_admin') always goes to admin.php.
 * For other admins, uses role_permissions (new RBAC) or fallback to admin_permissions.
 */
function getFirstAllowedPage($conn) {
    // Super admin check (based on role_name)
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin') {
        return 'admin.php';
    }

    if (!isset($_SESSION['user_id'])) return null;

    $roleId = $_SESSION['role_id'] ?? null;

    // If role_id is set, use role_permissions (new RBAC)
    if ($roleId) {
        // Check dashboard permission via role_permissions
        $stmt = $conn->prepare("SELECT can_access FROM role_permissions WHERE role_id = ? AND page_name = 'dashboard'");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $dashboardPerm = $res->fetch_assoc();
        $stmt->close();
        if ($dashboardPerm && $dashboardPerm['can_access'] == 1) {
            return 'admin.php';
        }

        // Otherwise, fetch any page they have access to
        $stmt = $conn->prepare("SELECT page_name FROM role_permissions WHERE role_id = ? AND can_access = 1 ORDER BY page_name ASC");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $allowed = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        // Fallback to old admin_permissions table (if role_id not present)
        $stmt = $conn->prepare("SELECT can_access FROM admin_permissions WHERE admin_id = ? AND page_name = 'dashboard'");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $dashboardPerm = $res->fetch_assoc();
        $stmt->close();
        if ($dashboardPerm && $dashboardPerm['can_access'] == 1) {
            return 'admin.php';
        }

        $stmt = $conn->prepare("SELECT page_name FROM admin_permissions WHERE admin_id = ? AND can_access = 1 ORDER BY page_name ASC");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $allowed = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    if (empty($allowed)) return null;

    // Map page_key to actual file name
    $pageFiles = [
        'dashboard' => 'admin.php',
        'users' => 'admin-users.php',
        'stores' => 'admin-stores.php',
        'pricing' => 'admin-pricing.php',
        'testimonials' => 'admin-testimonies.php',
        'contacts' => 'admin-contacts.php',
        'about' => 'admin-about.php',
        'chat' => 'admin-chat.php',
        'orders' => 'admin-receive-order.php',
        'transport' => 'admin-transport.php',
        'customers' => 'admin-customers.php',
        'send_email' => 'admin-send-email.php',
        'marketplace_design' => 'admin-marketplace-design.php',
        'settings' => 'adminsettings.php'
    ];

    foreach ($allowed as $perm) {
        $pageKey = $perm['page_name'];
        if (isset($pageFiles[$pageKey])) {
            return $pageFiles[$pageKey];
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-container {
            max-width: 420px;
            width: 100%;
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .login-header h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .login-header p { opacity: 0.9; font-size: 0.875rem; }
        .login-body { padding: 2rem; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #1f2937; }
        input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb; border-radius: 0.75rem;
            font-size: 0.875rem; transition: all 0.2s;
        }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .btn {
            width: 100%; padding: 0.875rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 0.75rem;
            font-weight: 700; font-size: 1rem; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.3); }
        .alert { padding: 0.75rem 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .register-link { text-align: center; margin-top: 1rem; font-size: 0.875rem; }
        .register-link a { color: #667eea; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
        .back-link { text-align: center; margin-top: 1rem; font-size: 0.75rem; }
        .back-link a { color: #9ca3af; text-decoration: none; }
        @media (max-width: 480px) { .login-body { padding: 1.5rem; } }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-header">
        <h1>Admin Login</h1>
        <p>Access the platform control panel</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'no_permissions'): ?>
            <div class="alert">Your account has no page permissions. Please contact the super admin.</div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <div class="register-link">No admin account? <a href="admin_register.php">Register here</a></div>
        <div class="back-link"><a href="index.php">← Back to Store</a></div>
    </div>
</div>
</body>
</html>