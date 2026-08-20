<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit;
}

$googleOauth = rdv_google_oauth_config($conn);
$googleNext = $googleOauth['redirect_uri'];
if (!empty($_GET['next']) && preg_match('/^[a-z0-9_\\-]+\\.php$/i', (string) $_GET['next'])) {
    $googleNext .= (strpos($googleNext, '?') === false ? '?' : '&') . 'next=' . rawurlencode((string) $_GET['next']);
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

        <form id="login-form" method="POST" action="includes/loginuser">
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
            <a href="forgot-password" class="auth-forgot-link">Forgot password?</a>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;" id="login-btn">Log in</button>
        </form>
        <?php
        $googleBtnHref = $googleNext;
        $googleBtnLabel = 'Continue with Google';
        require __DIR__ . '/includes/auth_google_button.php';
        ?>
        <div class="auth-footer">
          Don't have an account? <a href="register">Sign up</a>
        </div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
