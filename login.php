<?php
session_start();
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errorMsg = (string) ($_SESSION['login_error'] ?? $_GET['error'] ?? '');
$successMsg = (string) ($_SESSION['login_success'] ?? $_GET['success'] ?? '');
unset($_SESSION['login_error'], $_SESSION['login_success']);

$authPageTitle = 'Log in - RD Vendora';
$authVisualTitle = 'Welcome back to your store';
$authVisualText = 'Log in to manage products, track orders, or continue shopping on RD Vendora.';
$authVisualFeatures = [
    'One account for selling and buying',
    'Dashboard for stores you already run',
    'Checkout with Paystack or Flutterwave',
];
require __DIR__ . '/includes/auth_layout_start.php';
?>
        <div class="auth-form-header">
          <h1 class="auth-form-title">Welcome back</h1>
          <p class="auth-form-subtitle">Enter your email and password to continue.</p>
        </div>
        <?php if ($errorMsg !== ''): ?>
          <div class="auth-alert error"><?= htmlspecialchars($errorMsg) ?></div>
        <?php elseif ($successMsg !== ''): ?>
          <div class="auth-alert success"><?= htmlspecialchars($successMsg) ?></div>
        <?php endif; ?>

        <a href="oauth2callback.php" class="btn btn-google">
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Continue with Google
        </a>
        <div class="auth-divider"><span>or</span></div>

        <form id="login-form" method="POST" action="includes/loginuser.php">
          <?= rdv_csrf_field() ?>
          <?php if (!empty($_GET['next']) && preg_match('/^[a-z0-9_\\-]+\\.php$/i', (string) $_GET['next'])): ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars((string) $_GET['next'], ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>
          <div class="form-group">
            <label class="form-label" for="login-email">Email</label>
            <input type="email" class="form-input" id="login-email" name="email" placeholder="you@example.com" required autocomplete="email">
          </div>
          <div class="form-group">
            <label class="form-label" for="login-password">Password</label>
            <div class="password-input-wrapper">
              <input type="password" class="form-input" id="login-password" name="password" placeholder="Enter your password" required autocomplete="current-password">
              <span class="password-toggle" onclick="togglePassword('login-password', this)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
          </div>
          <div class="auth-options">
            <label class="form-check" style="margin:0;">
              <input type="checkbox" id="login-remember" name="remember">
              <span style="font-size:14px;color:var(--text-secondary);">Remember me</span>
            </label>
            <a href="forgot-password.php" class="auth-forgot-link">Forgot password?</a>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;" id="login-btn">Log in</button>
        </form>
        <div class="auth-footer">
          Don't have an account? <a href="register.php">Sign up</a>
        </div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
