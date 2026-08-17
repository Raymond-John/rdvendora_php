<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/connection.php';

// Use the correct connection variable
if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$message = '';
$messageType = '';
$validToken = false;
$userId = null;
$token = $_GET['token'] ?? '';

// 1. Validate token
if (empty($token)) {
    $message = 'No reset token provided. Please use the link from your email.';
    $messageType = 'error';
} else {
    // Check token in database
    $stmt = $conn->prepare("SELECT user_id, expires_at, used FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $now = new DateTime();
        $expires = new DateTime($row['expires_at']);
        if ($row['used'] == 1) {
            $message = 'This reset link has already been used. Please request a new one.';
            $messageType = 'error';
        } elseif ($now > $expires) {
            $message = 'This reset link has expired (valid for 1 hour). Please request a new one.';
            $messageType = 'error';
        } else {
            // Also verify that the user actually exists in the users table
            $userCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->bind_param("i", $row['user_id']);
            $userCheck->execute();
            $userResult = $userCheck->get_result();
            if ($userResult->num_rows === 0) {
                $message = 'The user associated with this token no longer exists.';
                $messageType = 'error';
            } else {
                $validToken = true;
                $userId = $row['user_id'];
            }
            $userCheck->close();
        }
    } else {
        $message = 'Invalid reset token. Please request a new reset link.';
        $messageType = 'error';
    }
    $stmt->close();
}

// 2. Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken && $userId) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'error';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashed, $userId);
        if ($updateStmt->execute()) {
            // Check if any row was actually updated
            if ($updateStmt->affected_rows > 0) {
                // Mark token as used
                $useStmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $useStmt->bind_param("s", $token);
                $useStmt->execute();
                $useStmt->close();
                
                $message = 'Password has been reset successfully. You can now <a href="login.php">login</a>.';
                $messageType = 'success';
                $validToken = false; // hide form
            } else {
                // No rows updated - maybe the user was deleted?
                $message = 'Failed to update password. User account may not exist.';
                $messageType = 'error';
                error_log("Password reset: UPDATE affected 0 rows for user_id $userId");
            }
        } else {
            // Log the exact database error
            error_log("Password reset UPDATE failed: " . $updateStmt->error);
            $message = 'Failed to reset password. Please try again. Error: ' . $updateStmt->error;
            $messageType = 'error';
        }
        $updateStmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        [data-theme="dark"] .card {
            background: #1e1e2f;
            color: #e0e0e0;
        }
        .logo { text-align: center; margin-bottom: 30px; }
        h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; text-align: center; }
        .subtitle { text-align: center; color: #6b7280; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s;
        }
        button:hover { transform: translateY(-1px); }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #6366f1; text-decoration: none; font-size: 14px; }
        .message {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .message.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        [data-theme="dark"] .message.success { background: #064e3b; color: #a7f3d0; }
        [data-theme="dark"] .message.error { background: #7f1d1d; color: #fecaca; }
        [data-theme="dark"] input { background: #2d2d3f; border-color: #3a3a4f; color: #e0e0e0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
        </div>
        <h1>Set new password</h1>
        <p class="subtitle">Create a strong password for your account.</p>
        
        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>New password</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit">Reset password</button>
        </form>
        <?php elseif (!$validToken && $token): ?>
            <div class="back-link">
                <a href="forgot-password.php">Request new reset link</a>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">← Back to login</a>
        </div>
    </div>
    <script>
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefersDark) document.documentElement.setAttribute('data-theme', 'dark');
    </script>
</body>
</html>