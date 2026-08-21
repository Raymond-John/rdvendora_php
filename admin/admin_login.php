<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once dirname(__DIR__) . '/app/helpers/csrf.php';
require_once dirname(__DIR__) . '/app/helpers/public_site.php';
require_once dirname(__DIR__) . '/app/helpers/admin_login_security.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

rdv_ensure_users_is_active_column($conn);

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

    if (!rdv_csrf_verify()) {
        $error = 'Please refresh the page and try again.';
    } elseif ($email === '' || $password === '') {
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

        $hash = '';
        if (is_array($user)) {
            $hash = (string) ($user['password'] ?? $user['password_hash'] ?? '');
        }
        $isActive = !$user || !isset($user['is_active']) || (int) $user['is_active'] === 1;
        if ($user && $hash !== '' && password_verify($password, $hash)) {
            if (!$isActive) {
                $error = 'This admin account has been deactivated.';
            } else {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['fullname'] = (string) ($user['fullname'] ?? $user['full_name'] ?? $user['email']);
            $_SESSION['email'] = (string) $user['email'];
            $_SESSION['is_admin'] = true;
            rdv_hydrate_admin_session($conn);
            require_once __DIR__ . '/../includes/log_activity.php';
            logUserActivity((int) $user['id'], 'admin_login', 'admin_login.php', 'Signed in to the admin panel');

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
            if (strpos((string) $ip, ',') !== false) {
                $ip = trim(explode(',', (string) $ip)[0]);
            }
            $_SESSION['admin_login_alert'] = [
                'name' => (string) ($_SESSION['fullname'] ?? $email),
                'time' => date('j F Y, g:ia'),
                'ip' => (string) $ip,
            ];
            try {
                require_once dirname(__DIR__) . '/app/helpers/admin_login_security.php';
                require_once __DIR__ . '/../includes/email_functions.php';
                rdv_ensure_users_is_active_column($conn);
                sendAdminLoginOwnerAlert($conn, (int) $user['id'], (string) $user['email'], (string) ($_SESSION['fullname'] ?? $email));
            } catch (Throwable $e) {
                error_log('Admin login owner alert failed: ' . $e->getMessage());
            }

            $target = getFirstAllowedPage($conn);
            if ($target) {
                header("Location: $target");
                exit;
            }
            session_destroy();
            $error = 'Your account has no page permissions. Contact super admin.';
            }
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}

$homeUrl = function_exists('rdv_url') ? rdv_url('index') : '../';
$registerUrl = function_exists('rdv_url') ? rdv_url('admin/admin_register') : 'admin_register';
$noPermissions = isset($_GET['error']) && $_GET['error'] === 'no_permissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin sign in | RD Vendora</title>
  <?= function_exists('rdv_favicon_tags') ? rdv_favicon_tags() : '' ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy: #071530;
      --navy-2: #0d2450;
      --gold: #d4af37;
      --gold-2: #f2d789;
      --ink: #0f172a;
      --muted: #64748b;
      --line: #e2e8f0;
      --err: #b91c1c;
      --err-bg: #fef2f2;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { min-height: 100%; }
    body {
      font-family: Inter, system-ui, sans-serif;
      color: var(--ink);
      background: #f3f5f8;
    }
    .al {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
    }
    .al-visual {
      position: relative;
      overflow: hidden;
      color: #fff;
      padding: 40px 48px 36px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background:
        radial-gradient(700px 360px at 90% -10%, rgba(212,175,55,0.22), transparent 58%),
        linear-gradient(165deg, #071530 0%, #0d2450 48%, #163a78 100%);
    }
    .al-visual::after {
      content: '';
      position: absolute;
      width: 420px;
      height: 420px;
      right: -140px;
      bottom: -160px;
      border-radius: 50%;
      background: rgba(255,255,255,0.05);
    }
    .al-brand { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; font-weight: 800; }
    .al-brand img { height: 36px; width: auto; max-width: 120px; background: #fff; border-radius: 8px; padding: 3px 6px; }
    .al-copy { position: relative; z-index: 1; max-width: 440px; }
    .al-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.14);
      color: var(--gold-2);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 18px;
    }
    .al-copy h1 {
      font-size: clamp(1.8rem, 3vw, 2.5rem);
      line-height: 1.15;
      letter-spacing: -0.03em;
      margin-bottom: 12px;
    }
    .al-copy p { color: rgba(255,255,255,0.78); line-height: 1.65; margin-bottom: 24px; }
    .al-points { list-style: none; display: grid; gap: 12px; }
    .al-points li {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      color: rgba(255,255,255,0.9);
      font-size: 0.95rem;
    }
    .al-points span {
      width: 22px; height: 22px; border-radius: 50%;
      background: var(--gold);
      color: var(--navy);
      font-size: 0.75rem;
      font-weight: 800;
      display: grid; place-items: center;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .al-foot { position: relative; z-index: 1; color: rgba(255,255,255,0.5); font-size: 0.8rem; }
    .al-form-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 32px;
    }
    .al-card { width: 100%; max-width: 420px; }
    .al-card h2 { font-size: 1.55rem; letter-spacing: -0.03em; margin: 0 0 6px; }
    .al-card .lead { color: var(--muted); margin-bottom: 28px; line-height: 1.5; }
    .al-alert {
      padding: 12px 14px;
      border-radius: 12px;
      margin-bottom: 18px;
      font-size: 0.9rem;
      background: var(--err-bg);
      color: var(--err);
      border: 1px solid #fecaca;
    }
    label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 6px; }
    .al-field { margin-bottom: 16px; }
    .al-input {
      position: relative;
    }
    .al-input input {
      width: 100%;
      height: 48px;
      padding: 0 14px;
      border: 1px solid var(--line);
      border-radius: 12px;
      font: inherit;
      background: #fff;
      color: var(--ink);
    }
    .al-input input:focus {
      outline: none;
      border-color: #4f46e5;
      box-shadow: 0 0 0 4px rgba(79,70,229,0.12);
    }
    .al-input input[type="password"],
    .al-input input.has-toggle { padding-right: 46px; }
    .al-toggle {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      border: 0;
      background: transparent;
      color: var(--muted);
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
      padding: 6px 8px;
    }
    .al-submit {
      width: 100%;
      height: 48px;
      margin-top: 8px;
      border: 0;
      border-radius: 12px;
      background: linear-gradient(135deg, #071530, #163a78);
      color: #fff;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 10px 24px rgba(7,21,48,0.18);
    }
    .al-submit:hover { filter: brightness(1.08); }
    .al-meta { margin-top: 22px; text-align: center; font-size: 0.88rem; color: var(--muted); }
    .al-meta a { color: #0d2450; font-weight: 600; text-decoration: none; }
    .al-meta a:hover { color: #4f46e5; }
    .al-home { display: block; margin-top: 14px; color: #94a3b8; font-size: 0.8rem; text-decoration: none; }
    .al-home:hover { color: var(--ink); }
    @media (max-width: 900px) {
      .al { grid-template-columns: 1fr; }
      .al-visual { min-height: auto; padding: 28px 24px 24px; }
      .al-copy h1 { font-size: 1.6rem; }
      .al-points { display: none; }
      .al-form-wrap { padding: 28px 20px 40px; }
    }
  </style>
</head>
<body>
  <div class="al">
    <aside class="al-visual">
      <a class="al-brand" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">
        <?= function_exists('rdv_brand_logo') ? rdv_brand_logo('', '', false) : '' ?>
        <span>RD Vendora</span>
      </a>
      <div class="al-copy">
        <div class="al-kicker">Platform control</div>
        <h1>Sign in to the admin dashboard</h1>
        <p>Manage stores, orders, users, and marketplace settings from one secure control panel.</p>
        <ul class="al-points">
          <li><span>✓</span> Review vendor stores and documents</li>
          <li><span>✓</span> Monitor orders, payouts, and activity</li>
          <li><span>✓</span> Publish news and platform settings</li>
        </ul>
      </div>
      <p class="al-foot">Restricted access. Authorized administrators only.</p>
    </aside>
    <main class="al-form-wrap">
      <div class="al-card">
        <h2>Admin sign in</h2>
        <p class="lead">Use your administrator email and password.</p>
        <?php if ($error !== ''): ?>
          <div class="al-alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($noPermissions): ?>
          <div class="al-alert" role="alert">Your account has no page permissions. Contact the super admin.</div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
          <?= rdv_csrf_field() ?>
          <div class="al-field">
            <label for="email">Email address</label>
            <div class="al-input">
              <input id="email" type="email" name="email" required autofocus autocomplete="username" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>
          <div class="al-field">
            <label for="password">Password</label>
            <div class="al-input">
              <input id="password" class="has-toggle" type="password" name="password" required autocomplete="current-password">
              <button type="button" class="al-toggle" id="togglePassword">Show</button>
            </div>
          </div>
          <button class="al-submit" type="submit">Sign in</button>
        </form>
        <p class="al-meta">Need an admin account? <a href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>">Register</a></p>
        <a class="al-home" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">← Back to RD Vendora</a>
      </div>
    </main>
  </div>
  <script>
    (function () {
      var btn = document.getElementById('togglePassword');
      var input = document.getElementById('password');
      if (!btn || !input) return;
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
      });
    })();
  </script>
</body>
</html>
