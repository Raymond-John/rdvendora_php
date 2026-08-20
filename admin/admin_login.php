<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

if (rdv_hydrate_admin_session($conn) && rdv_admin_flag_is_set()) {
    $target = getFirstAllowedPage($conn);
    if ($target) {
        header("Location: $target");
    } else {
        session_destroy();
        header('Location: admin_login?error=no_permissions');
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
        $user = null;
        try {
            $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? AND is_admin = 1 LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('Admin login query failed: ' . $e->getMessage());
            $user = null;
        }

        $hash = (string) ($user['password'] ?? '');
        if ($user && $hash !== '' && password_verify($password, $hash)) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['fullname'] = (string) ($user['fullname'] ?? $user['full_name'] ?? $user['email']);
            $_SESSION['email'] = (string) $user['email'];
            $_SESSION['is_admin'] = true;
            rdv_hydrate_admin_session($conn);
            require_once __DIR__ . '/../includes/log_activity.php';
            logUserActivity((int) $user['id'], 'admin_login', 'admin_login.php', 'Signed in to the admin panel');

            $target = getFirstAllowedPage($conn);
            if ($target) {
                header("Location: $target");
                exit;
            }
            session_destroy();
            $error = 'Your account has no page permissions. Contact super admin.';
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
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
        <div class="register-link">No admin account? <a href="admin_register">Register here</a></div>
        <div class="back-link"><a href="../">← Back to Store</a></div>
    </div>
</div>
</body>
</html>