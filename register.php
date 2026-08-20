<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    die('Database connection failed.');
}

if (!empty($_SESSION['user_id'])) {
    header('Location: create-store');
    exit;
}

$error = (string) ($_GET['error'] ?? '');
$googleOauth = rdv_google_oauth_config($conn);

function rdv_users_columns_reg(mysqli $conn): array {
    $cols = [];
    $res = $conn->query('SHOW COLUMNS FROM users');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
    }
    return $cols;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rdv_csrf_verify()) {
        $error = 'Please refresh the page and try again.';
    } else {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($fullname === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
        $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) {
                $error = 'That email is already registered. Try logging in.';
        } else {
                $cols = rdv_users_columns_reg($conn);
                $nameCol = !empty($cols['fullname']) ? 'fullname' : (!empty($cols['full_name']) ? 'full_name' : 'name');
                $passCol = !empty($cols['password']) ? 'password' : 'password_hash';
            $hashed = password_hash($password, PASSWORD_DEFAULT);
                $fields = [$nameCol, 'email', $passCol];
                $values = [$fullname, $email, $hashed];
                $types = 'sss';
                if (!empty($cols['created_at'])) {
                    // created_at uses DEFAULT / NOW() if we omit it
                }
                $sql = 'INSERT INTO users (`' . implode('`, `', $fields) . '`) VALUES (?, ?, ?)';
                $ins = $conn->prepare($sql);
                if (!$ins) {
                    $error = 'Could not create the account. Please try again.';
                } else {
                    $ins->bind_param($types, $values[0], $values[1], $values[2]);
                    if ($ins->execute()) {
                        $user_id = (int) $ins->insert_id;
                        $ins->close();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['fullname'] = $fullname;
                        $_SESSION['email'] = $email;
                        $prof = @$conn->query("SHOW TABLES LIKE 'seller_profiles'");
                        if ($prof && $prof->num_rows > 0) {
                            $sp = $conn->prepare('INSERT INTO seller_profiles (user_id) VALUES (?)');
                            if ($sp) {
                                $sp->bind_param('i', $user_id);
                                $sp->execute();
                                $sp->close();
                            }
                        }
                        if (file_exists(__DIR__ . '/includes/email_functions.php')) {
                            require_once __DIR__ . '/includes/email_functions.php';
                            if (function_exists('sendWelcomeEmail')) {
                sendWelcomeEmail($email, $fullname);
                            }
                        }
                header('Location: create-store');
                exit;
                    }
                    $error = 'Could not create the account. Please try again.';
                    $ins->close();
                }
            }
        }
    }
}

$authPageTitle = 'Sign up - RD Vendora';
$authVisualTitle = 'Start selling online today';
$authVisualText = 'Create an account, open a store, and take orders with Paystack or Flutterwave.';
$authVisualFeatures = [
    'Open a store from your dashboard',
    'List products and manage orders',
    'One login for your whole account',
];
require __DIR__ . '/includes/auth_layout_start.php';
?>
        <div class="auth-form-header">
          <h1 class="auth-form-title">Create your account</h1>
          <p class="auth-form-subtitle">Sign up with email. You can open a store right after.</p>
        </div>
        <?php if ($error !== ''): ?>
          <div class="auth-alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <?= rdv_csrf_field() ?>
          <div class="form-group">
            <label class="form-label" for="fullname">Full name</label>
            <input type="text" class="form-input" id="fullname" name="fullname" placeholder="Your name" required value="<?= htmlspecialchars((string) ($_POST['fullname'] ?? '')) ?>" autocomplete="name">
          </div>
          <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-input" id="email" name="email" placeholder="you@example.com" required value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" autocomplete="email">
          </div>
          <div class="form-group">
            <label class="form-label" for="reg-password">Password</label>
            <div class="password-input-wrapper">
              <input type="password" class="form-input" name="password" id="reg-password" placeholder="At least 6 characters" required minlength="6" oninput="checkStrength(this.value)" autocomplete="new-password">
              <span class="password-toggle" onclick="togglePassword('reg-password', this)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
            </div>
            <div class="password-strength" id="password-strength" style="display:none;">
              <div class="strength-bar">
                <div class="strength-segment" id="s1"></div>
                <div class="strength-segment" id="s2"></div>
                <div class="strength-segment" id="s3"></div>
                <div class="strength-segment" id="s4"></div>
              </div>
              <div class="strength-text" id="strength-text">Password strength</div>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="reg-confirm">Confirm password</label>
            <input type="password" class="form-input" name="confirm_password" id="reg-confirm" placeholder="Confirm your password" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" id="reg-terms" name="terms" required>
              <span style="font-size:14px;color:var(--text-secondary);">I agree to the <a href="terms">Terms</a> and <a href="privacy">Privacy Policy</a></span>
            </label>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;" id="reg-btn">Create account</button>
        </form>
        <?php
        $googleBtnHref = $googleOauth['redirect_uri'] ?? 'https://rdvendora.com/oauth2callback.php';
        $googleBtnLabel = 'Continue with Google';
        require __DIR__ . '/includes/auth_google_button.php';
        ?>
        <div class="auth-footer">Already have an account? <a href="login">Log in</a></div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
