<?php
require_once __DIR__ . '/includes/connection.php';
require_once APP_PATH . '/lib/GoogleOAuth.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    rdv_google_oauth_fail('Database connection failed.');
}

$oauth = rdv_google_oauth_config($conn);
if (!$oauth['configured']) {
    rdv_google_oauth_fail('Google Sign-In is not configured on this server yet.');
}

$google = new GoogleOAuth($oauth['client_id'], $oauth['client_secret'], $oauth['redirect_uri']);

if (empty($_GET['code'])) {
    if (!empty($_GET['error'])) {
        rdv_google_oauth_fail('Google sign-in was cancelled.');
    }
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    $next = trim((string) ($_GET['next'] ?? ''));
    if ($next !== '' && preg_match('/^[a-z0-9_\\-]+\\.php(?:\\?.*)?$/i', $next) && stripos($next, '://') === false) {
        $_SESSION['oauth_next'] = $next;
    }
    header('Location: ' . $google->getAuthUrl($_SESSION['oauth_state']));
    exit;
}

$state = (string) ($_GET['state'] ?? '');
if ($state === '' || empty($_SESSION['oauth_state']) || !hash_equals((string) $_SESSION['oauth_state'], $state)) {
    unset($_SESSION['oauth_state']);
    rdv_google_oauth_fail('Google sign-in expired. Please try again.');
}
unset($_SESSION['oauth_state']);

try {
    $tokenData = $google->getAccessToken((string) $_GET['code']);
    $userInfo = $google->getUserInfo($tokenData['access_token']);
} catch (Exception $e) {
    rdv_google_oauth_fail('Google sign-in failed. Check that the live redirect URI matches Google Cloud exactly.');
}

$google_id = (string) $userInfo['id'];
$email = strtolower(trim((string) $userInfo['email']));
$fullname = trim((string) ($userInfo['name'] ?? ''));
if ($fullname === '') {
    $fullname = $email;
}

$cols = [];
$colRes = $conn->query('SHOW COLUMNS FROM users');
if ($colRes) {
    while ($row = $colRes->fetch_assoc()) {
        $cols[$row['Field']] = true;
    }
}

if (empty($cols['google_id'])) {
    @$conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL");
    @$conn->query('ALTER TABLE users ADD UNIQUE KEY google_id (google_id)');
    $cols['google_id'] = true;
}

$nameCol = !empty($cols['full_name']) ? 'full_name' : (!empty($cols['fullname']) ? 'fullname' : null);
$passCol = !empty($cols['password_hash']) ? 'password_hash' : (!empty($cols['password']) ? 'password' : null);

$selectName = $nameCol ? "`$nameCol` AS display_name" : 'email AS display_name';
$stmt = $conn->prepare("SELECT id, email, $selectName, google_id FROM users WHERE google_id = ? OR email = ? LIMIT 1");
if (!$stmt) {
    rdv_google_oauth_fail('Could not look up that account.');
}
$stmt->bind_param('ss', $google_id, $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isNewUser = false;

if ($user) {
    $userId = (int) $user['id'];
    if (empty($user['google_id']) && !empty($cols['google_id'])) {
        $upd = $conn->prepare('UPDATE users SET google_id = ? WHERE id = ?');
        $upd->bind_param('si', $google_id, $userId);
        $upd->execute();
        $upd->close();
    }
    if ($nameCol && (string) ($user['display_name'] ?? '') !== $fullname) {
        $upd = $conn->prepare("UPDATE users SET `$nameCol` = ? WHERE id = ?");
        $upd->bind_param('si', $fullname, $userId);
        $upd->execute();
        $upd->close();
    }
} else {
    $dummy = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $fields = ['email'];
    $placeholders = ['?'];
    $types = 's';
    $values = [$email];

    if ($nameCol) {
        $fields[] = $nameCol;
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $fullname;
    }
    if ($passCol) {
        $fields[] = $passCol;
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $dummy;
        if ($passCol === 'password_hash' && !empty($cols['password'])) {
            $fields[] = 'password';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $dummy;
        }
    }
    if (!empty($cols['google_id'])) {
        $fields[] = 'google_id';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $google_id;
    }
    if (!empty($cols['email_verified'])) {
        $fields[] = 'email_verified';
        $placeholders[] = '1';
    }
    if (!empty($cols['role']) && empty($cols['role_id'])) {
        $fields[] = 'role';
        $placeholders[] = "'vendor'";
    }

    $sql = 'INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $ins = $conn->prepare($sql);
    if (!$ins) {
        rdv_google_oauth_fail('Could not create your account.');
    }
    $ins->bind_param($types, ...$values);
    if (!$ins->execute()) {
        error_log('Google OAuth insert: ' . $ins->error);
        $ins->close();
        rdv_google_oauth_fail('Could not create your account.');
    }
    $userId = (int) $ins->insert_id;
    $ins->close();
    $isNewUser = true;
}

$_SESSION['user_id'] = $userId;
$_SESSION['user_email'] = $email;
$_SESSION['email'] = $email;
$_SESSION['user_name'] = $fullname;
$_SESSION['fullname'] = $fullname;

if (file_exists(__DIR__ . '/includes/email_functions.php')) {
    require_once __DIR__ . '/includes/email_functions.php';
}
if ($isNewUser && function_exists('sendWelcomeEmail')) {
    try {
        sendWelcomeEmail($email, $fullname);
    } catch (Throwable $e) {
        error_log('Google welcome email: ' . $e->getMessage());
    }
} elseif (!$isNewUser && function_exists('sendLoginNotification')) {
    try {
        sendLoginNotification($email, $fullname);
    } catch (Throwable $e) {
        error_log('Google login email: ' . $e->getMessage());
    }
}

$next = (string) ($_SESSION['oauth_next'] ?? '');
unset($_SESSION['oauth_next']);
if ($next !== '' && preg_match('/^[a-z0-9_\\-]+\\.php(?:\\?.*)?$/i', $next) && stripos($next, '://') === false) {
    header('Location: ' . $next);
    exit;
}

$hasStore = false;
$storeStmt = $conn->prepare('SELECT id FROM stores WHERE user_id = ? LIMIT 1');
if ($storeStmt) {
    $storeStmt->bind_param('i', $userId);
    $storeStmt->execute();
    $hasStore = (bool) $storeStmt->get_result()->fetch_assoc();
    $storeStmt->close();
}

header('Location: ' . ($hasStore ? 'dashboard.php' : 'create-store.php'));
exit;
