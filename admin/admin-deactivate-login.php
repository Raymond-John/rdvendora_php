<?php
require_once __DIR__ . '/../includes/connection.php';
require_once dirname(__DIR__) . '/app/helpers/csrf.php';
require_once dirname(__DIR__) . '/app/helpers/admin_login_security.php';
require_once dirname(__DIR__) . '/app/helpers/public_site.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    die('Database connection failed.');
}

rdv_ensure_users_is_active_column($conn);

$uid = (int) ($_GET['uid'] ?? $_POST['uid'] ?? 0);
$exp = (int) ($_GET['exp'] ?? $_POST['exp'] ?? 0);
$sig = (string) ($_GET['sig'] ?? $_POST['sig'] ?? '');
$homeUrl = function_exists('rdv_url') ? rdv_url('index') : '../';
$loginUrl = function_exists('rdv_url') ? rdv_url('admin/admin_login') : 'admin_login';

$state = 'invalid';
$message = 'This deactivation link is invalid or has expired.';
$person = null;

if (rdv_admin_deactivate_token_valid($uid, $exp, $sig)) {
    $stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $person = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    if (!$person || empty($person['is_admin'])) {
        $state = 'missing';
        $message = 'That admin account was not found.';
    } elseif (isset($person['is_active']) && (int) $person['is_active'] === 0) {
        $state = 'already';
        $message = 'This admin account is already deactivated.';
    } else {
        $state = 'ready';
        $message = 'Deactivate this admin if you do not recognize them.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state === 'ready') {
    if (!rdv_csrf_verify() || !rdv_admin_deactivate_token_valid($uid, $exp, $sig)) {
        $state = 'invalid';
        $message = 'Please refresh this page and try again.';
    } elseif (rdv_deactivate_admin_user($conn, $uid)) {
        $state = 'done';
        $message = 'Admin access for this person has been deactivated. They can no longer sign in to the dashboard.';
        try {
            require_once __DIR__ . '/../includes/log_activity.php';
            if (function_exists('logUserActivity')) {
                logUserActivity($uid, 'admin_deactivated', 'admin-deactivate-login.php', 'Admin deactivated from login alert email');
            }
        } catch (Throwable $e) {
            error_log('Admin deactivate log failed: ' . $e->getMessage());
        }
    } else {
        $state = 'invalid';
        $message = 'Could not deactivate this admin. Try again from Admin → Users.';
    }
}

$displayName = trim((string) ($person['fullname'] ?? $person['full_name'] ?? ''));
$displayEmail = (string) ($person['email'] ?? '');
if ($displayName === '') {
    $displayName = $displayEmail;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deactivate admin | RD Vendora</title>
  <?= function_exists('rdv_favicon_tags') ? rdv_favicon_tags() : '' ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: Inter, system-ui, sans-serif; background: #f3f5f8; color: #0f172a; }
    .card { width: 100%; max-width: 460px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 28px; box-shadow: 0 18px 40px rgba(7,21,48,.08); }
    .kicker { color: #b45309; font-size: .72rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 10px; }
    h1 { font-size: 1.45rem; letter-spacing: -.03em; margin-bottom: 10px; }
    p { color: #64748b; line-height: 1.6; margin-bottom: 16px; }
    .meta { background: #f8fafc; border-left: 4px solid #d4af37; border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; }
    .meta div { font-size: .95rem; margin: 4px 0; color: #0f172a; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 16px; border-radius: 12px; border: 0; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
    .btn-danger { background: #b91c1c; color: #fff; }
    .btn-ghost { background: #e2e8f0; color: #0f172a; }
    .ok { color: #166534; }
  </style>
</head>
<body>
  <div class="card">
    <div class="kicker">Security</div>
    <h1><?= $state === 'done' ? 'Admin deactivated' : 'Deactivate admin access' ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($displayEmail !== ''): ?>
      <div class="meta">
        <div><strong>Name:</strong> <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Email:</strong> <?= htmlspecialchars($displayEmail, ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    <?php endif; ?>
    <div class="actions">
      <?php if ($state === 'ready'): ?>
        <form method="post">
          <?= rdv_csrf_field() ?>
          <input type="hidden" name="uid" value="<?= (int) $uid ?>">
          <input type="hidden" name="exp" value="<?= (int) $exp ?>">
          <input type="hidden" name="sig" value="<?= htmlspecialchars($sig, ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-danger" type="submit">Yes, deactivate this person</button>
        </form>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Admin login</a>
      <a class="btn btn-ghost" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Back to store</a>
    </div>
  </div>
</body>
</html>
