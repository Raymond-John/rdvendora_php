<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Check if any admin already exists
$checkAdmin = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
$adminExists = false;
if ($checkAdmin) {
    $row = $checkAdmin->fetch_assoc();
    if ($row['count'] > 0) {
        $adminExists = true;
    }
}

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $is_admin = 1;
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, is_admin, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssi", $fullname, $email, $hashed_password, $is_admin);
            if ($stmt->execute()) {
                $success = 'Super admin account created successfully. Please login.';
                // Optionally auto-login? Better to redirect to login.
                header('Refresh: 2; url=admin_login.php');
            } else {
                $error = 'Database error: ' . $conn->error;
            }
            $stmt->close();
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
        <h1>Super Admin Registration</h1>
        <p>Create the first administrator account</p>
    </div>
    <div class="register-body">
        <?php if ($adminExists): ?>
            <div class="alert alert-error">
                ⚠️ An admin account already exists. Registration is disabled.<br>
                <a href="admin_login.php" style="color:#667eea; font-weight:600;">Go to Login</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
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
                <button type="submit" class="btn">Register Super Admin</button>
            </form>
            <div class="login-link">Already have an admin account? <a href="admin_login.php">Login here</a></div>
        <?php endif; ?>
        <div class="back-link"><a href="index.php">← Back to Store</a></div>
    </div>
</div>
</body>
</html>