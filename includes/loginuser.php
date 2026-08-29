<?php
require_once 'connection.php';
require_once 'email_functions.php';
if (file_exists(__DIR__ . '/../app/helpers/csrf.php')) {
    require_once __DIR__ . '/../app/helpers/csrf.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    header('Location: ../login?error=' . urlencode('Database connection failed'));
    exit;
}

function rdv_users_columns(mysqli $conn): array {
    $cols = [];
    $res = $conn->query('SHOW COLUMNS FROM users');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
    }
    return $cols;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login');
    exit;
}

if (function_exists('rdv_csrf_verify') && !rdv_csrf_verify()) {
    header('Location: ../login?error=' . urlencode('Please refresh the page and try again'));
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$plain_password = (string) ($_POST['password'] ?? '');

if ($email === '' || $plain_password === '') {
    header('Location: ../login?error=' . urlencode('All fields are required'));
    exit;
}

$cols = rdv_users_columns($conn);
$nameCol = !empty($cols['fullname']) ? 'fullname' : (!empty($cols['full_name']) ? 'full_name' : 'email');
$passCol = !empty($cols['password']) ? 'password' : 'password_hash';
$select = "SELECT id, email, `$nameCol` AS display_name, `$passCol` AS password_hash FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($select);
if (!$stmt) {
    header('Location: ../login?error=' . urlencode('Could not look up that account'));
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['password_hash']) || !password_verify($plain_password, $user['password_hash'])) {
    header('Location: ../login?error=' . urlencode('Email or password is incorrect'));
    exit;
}

$displayName = trim((string) ($user['display_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string) $user['email'];
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['email'] = $user['email'];
$_SESSION['user_name'] = $displayName;
$_SESSION['fullname'] = $displayName;

require_once __DIR__ . '/log_activity.php';
if (function_exists('logUserActivity')) {
    logUserActivity((int) $user['id'], 'login', 'login.php', 'Signed in');
}
if (function_exists('sendLoginNotification')) {
    try {
        sendLoginNotification($email, $displayName);
    } catch (Throwable $e) {
        error_log('Login email failed: ' . $e->getMessage());
    }
}

$next = trim((string) ($_POST['next'] ?? $_GET['next'] ?? ''));
if ($next !== '' && preg_match('/^[a-z0-9_\\-]+\\.php(?:\\?.*)?$/i', $next) && stripos($next, '://') === false) {
    header('Location: ../' . $next);
    exit;
}

$hasStore = false;
$storeStmt = $conn->prepare('SELECT id FROM stores WHERE user_id = ? LIMIT 1');
if ($storeStmt) {
    $uid = (int) $user['id'];
    $storeStmt->bind_param('i', $uid);
    $storeStmt->execute();
    $hasStore = (bool) $storeStmt->get_result()->fetch_assoc();
    $storeStmt->close();
}

header('Location: ../' . ($hasStore ? 'dashboard' : 'create-store'));
exit;
