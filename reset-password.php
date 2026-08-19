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

$authPageTitle = 'Reset Password - RD Vendora';
$authVisualTitle = 'Choose a new password';
$authVisualText = 'Use the link from your email, then sign in with the new password.';
$authVisualFeatures = [
    'Minimum 6 characters',
    'The reset link works once',
    'You can request a new link anytime',
];
require __DIR__ . '/includes/auth_layout_start.php';
?>
        <div class="auth-form-header">
          <h1 class="auth-form-title">Set a new password</h1>
          <p class="auth-form-subtitle">Create a password you will remember, then return to login.</p>
        </div>
        <?php if ($message): ?>
          <div class="auth-alert <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= $messageType === 'success' ? $message : htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($validToken): ?>
        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label" for="password">New password</label>
            <div class="password-input-wrapper">
              <input type="password" class="form-input" id="password" name="password" required minlength="6" autocomplete="new-password">
              <span class="password-toggle" onclick="togglePassword('password', this)" aria-label="Show password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm password</label>
            <input type="password" class="form-input" id="confirm_password" name="confirm_password" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">Reset password</button>
        </form>
        <?php elseif ($token): ?>
        <a href="forgot-password.php" class="btn btn-outline w-full" style="justify-content:center;">Request a new link</a>
        <?php endif; ?>
        <div class="auth-footer">
          <a href="login.php">Back to login</a>
        </div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>