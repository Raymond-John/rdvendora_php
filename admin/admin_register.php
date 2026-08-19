<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

require_once dirname(__DIR__) . '/app/helpers/schema_install.php';
require_once dirname(__DIR__) . '/app/helpers/admin_auth.php';

$installMessage = '';
$installOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_schema'])) {
    $result = rdv_install_schema($conn);
    $installOk = !empty($result['ok']);
    $installMessage = (string) ($result['message'] ?? '');
    if ($installOk) {
        header('Location: admin_register.php?installed=1');
        exit;
    }
}

if (!function_exists('rdv_db_table_exists') || !rdv_db_table_exists($conn, 'users')) {
    http_response_code(503);
    $err = htmlspecialchars($installMessage, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install database</title></head>';
    echo '<body style="font-family:sans-serif;max-width:40rem;margin:3rem auto;line-height:1.5;color:#0f172a">';
    echo '<h1>Database not installed</h1>';
    echo '<p>The production database is connected, but required tables (including <code>users</code>) are missing.</p>';
    if ($err !== '') {
        echo '<p style="color:#b91c1c">' . $err . '</p>';
    }
    echo '<p>Click the button below to create the tables from <code>database/schema.sql</code> on this server. This does not drop existing data.</p>';
    echo '<form method="post"><button type="submit" name="install_schema" value="1" style="padding:0.75rem 1.25rem;background:#1d4ed8;color:#fff;border:0;border-radius:8px;font-size:1rem;cursor:pointer">Install database now</button></form>';
    echo '<p style="margin-top:2rem;color:#64748b;font-size:0.9rem">If the button fails, import the same file in Hostinger phpMyAdmin: database <code>u711829883_rdvendora</code> → Import → <code>public_html/database/schema.sql</code>.</p>';
    echo '</body></html>';
    exit;
}

$adminExists = false;
try {
    $checkAdmin = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
    if ($checkAdmin) {
        $row = $checkAdmin->fetch_assoc();
        if (!empty($row['count'])) {
            $adminExists = true;
        }
    }
} catch (Throwable $e) {
    error_log('admin_register.php: ' . $e->getMessage());
    $adminExists = false;
}

rdv_hydrate_admin_session($conn);
$isSuperAdmin = isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin';

if ($adminExists && !$isSuperAdmin) {
    header('Location: admin_login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['install_schema'])) {
    if ($adminExists && !$isSuperAdmin) {
        header('Location: admin_login.php');
        exit;
    }
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($fullname) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists in users table
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'Email already registered.';
        } else {
            $stmt->close();
            $stmt = null;
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $is_admin = 1;
            $roleId = rdv_admin_super_role_id($conn);
            if ($roleId > 0) {
                $try = $conn->prepare("INSERT INTO users (fullname, full_name, email, password, password_hash, is_admin, role_id, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'admin', NOW())");
                if ($try) {
                    $stmt = $try;
                    $stmt->bind_param("sssssii", $fullname, $fullname, $email, $hashed_password, $hashed_password, $is_admin, $roleId);
                }
            }
            if (!$stmt) {
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, is_admin, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param("sssi", $fullname, $email, $hashed_password, $is_admin);
                }
            }
            if ($stmt && $stmt->execute()) {
                $success = $adminExists
                    ? 'Admin account created. They can sign in at the admin login page.'
                    : 'Super admin account created successfully. Please login.';
                if (!$adminExists) {
                    header('Refresh: 2; url=admin_login.php');
                }
            } else {
                $error = 'Database error: ' . ($stmt ? $stmt->error : $conn->error);
            }
            if ($stmt) {
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Registration - RD Vendora</title>
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
        .register-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .register-header h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .register-header p { opacity: 0.9; font-size: 0.875rem; }
        .register-body { padding: 2rem; }
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
        .alert { padding: 0.75rem 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #bbf7d0; }
        .info-message {
            background: #e0f2fe; color: #0369a1; padding: 1rem; border-radius: 0.75rem;
            margin-bottom: 1.5rem; font-size: 0.875rem; text-align: center;
        }
        .login-link { text-align: center; margin-top: 1rem; font-size: 0.875rem; }
        .login-link a { color: #667eea; text-decoration: none; font-weight: 600; }
        .back-link { text-align: center; margin-top: 1rem; font-size: 0.75rem; }
        .back-link a { color: #9ca3af; text-decoration: none; }
        @media (max-width: 480px) { .register-body { padding: 1.5rem; } }
    </style>
</head>
<body>
<div class="register-container">
    <div class="register-header">
        <h1><?= $adminExists ? 'Add an admin' : 'Super Admin Registration' ?></h1>
        <p><?= $adminExists ? 'Only a super admin can create another administrator' : 'Create the first administrator account' ?></p>
    </div>
    <div class="register-body">
        <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($adminExists && $isSuperAdmin): ?>
                <div class="info-message">You are signed in as super admin. New accounts created here get admin access.</div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password (min. 6 characters)</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn"><?= $adminExists ? 'Create admin' : 'Register Super Admin' ?></button>
            </form>
            <?php if ($isSuperAdmin): ?>
                <div class="login-link"><a href="admin.php">Back to dashboard</a></div>
            <?php else: ?>
                <div class="login-link">Already have an admin account? <a href="admin_login.php">Login here</a></div>
            <?php endif; ?>
        <div class="back-link"><a href="../index.php">← Back to Store</a></div>
    </div>
</div>
</body>
</html>