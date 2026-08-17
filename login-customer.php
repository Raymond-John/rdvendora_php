<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// If already logged in, verify user exists in DB
if (isset($_SESSION['user_id'])) {
    $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $check->bind_param("i", $_SESSION['user_id']);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        // User exists, redirect to profile
        header('Location: customer-profile.php');
        exit;
    } else {
        // User doesn't exist, destroy session
        session_destroy();
        // Continue to show login form
    }
    $check->close();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password_hash, avatar_url, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            $_SESSION['role'] = $user['role'] ?? 'customer';
            header('Location: customer-profile.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* (your existing styles – unchanged) */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f5f7fa; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; }
        .auth-container { background:#fff; border-radius:24px; box-shadow:0 20px 60px rgba(0,0,0,0.08); max-width:440px; width:100%; padding:2.5rem 2rem; }
        .auth-logo { text-align:center; margin-bottom:1.8rem; }
        .auth-logo a { font-size:1.8rem; font-weight:900; text-decoration:none; background:linear-gradient(135deg,#e63a2e,#c52a1f); -webkit-background-clip:text; background-clip:text; color:transparent; display:inline-flex; align-items:center; gap:0.4rem; }
        .auth-logo i { color:#e63a2e; font-size:2rem; }
        .auth-title { font-size:1.6rem; font-weight:800; text-align:center; margin-bottom:0.3rem; }
        .auth-sub { text-align:center; color:#6b7280; font-size:0.9rem; margin-bottom:1.8rem; }
        .auth-sub a { color:#e63a2e; font-weight:600; text-decoration:none; }
        .form-group { margin-bottom:1.2rem; }
        .form-group label { display:block; font-weight:600; font-size:0.85rem; margin-bottom:0.3rem; color:#1a1a2e; }
        .form-group input { width:100%; padding:0.75rem 1rem; border:1px solid #e5e7eb; border-radius:12px; font-size:0.95rem; transition:border 0.2s,box-shadow 0.2s; font-family:inherit; }
        .form-group input:focus { border-color:#e63a2e; outline:none; box-shadow:0 0 0 3px rgba(230,58,46,0.1); }
        .btn-primary { width:100%; padding:0.8rem; background:#e63a2e; color:#fff; border:none; border-radius:40px; font-weight:700; font-size:1rem; cursor:pointer; transition:background 0.2s,transform 0.1s; display:inline-flex; align-items:center; justify-content:center; gap:0.5rem; }
        .btn-primary:hover { background:#c52a1f; }
        .btn-primary:active { transform:scale(0.98); }
        .error-box { background:#fee2e2; border-radius:12px; padding:0.8rem 1rem; color:#b91c1c; font-size:0.9rem; margin-bottom:1.2rem; display:flex; align-items:center; gap:0.5rem; }
        .error-box i { font-size:1.1rem; }
        .forgot-link { text-align:right; font-size:0.8rem; margin-top:-0.5rem; margin-bottom:0.8rem; }
        .forgot-link a { color:#6b7280; text-decoration:none; }
        .forgot-link a:hover { color:#e63a2e; }
        @media (max-width:480px) { .auth-container { padding:1.8rem 1.2rem; } }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-logo"><a href="marketplace.php"><i class="fas fa-store-alt"></i> RD Vendora</a></div>
    <div class="auth-title">Welcome Back</div>
    <div class="auth-sub">Sign in to your account to continue.</div>

    <?php if ($error): ?>
        <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>
        <div class="forgot-link"><a href="forgot-password.php">Forgot password?</a></div>
        <button type="submit" name="login" class="btn-primary"><i class="fas fa-sign-in-alt"></i> Sign In</button>
    </form>

    <div style="text-align:center; margin-top:1.5rem; font-size:0.9rem; color:#6b7280;">
        Don't have an account? <a href="register-customer.php" style="color:#e63a2e; font-weight:600; text-decoration:none;">Sign Up</a>
    </div>
</div>
</body>
</html>