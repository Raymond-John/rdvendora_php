<?php
/**
 * email_config.php - Dynamic SMTP configuration loaded from database
 * Edit these settings via Admin → Settings → Email (SMTP) Settings
 */

if (!isset($conn) && isset($connect)) $conn = $connect;

if (!$conn) {
    error_log("Email config: Database connection failed.");
    return ['error' => 'Database connection unavailable'];
}

// Helper to get a setting value
function getEmailSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Read all SMTP-related settings from the database
$smtp_host        = getEmailSetting($conn, 'smtp_host', 'smtp.gmail.com');
$smtp_port        = getEmailSetting($conn, 'smtp_port', '587');
$smtp_username    = getEmailSetting($conn, 'smtp_user', '');
$smtp_password    = getEmailSetting($conn, 'smtp_pass', '');
$smtp_secure      = getEmailSetting($conn, 'smtp_encryption', 'tls');
$smtp_from        = getEmailSetting($conn, 'smtp_from', $smtp_username);
$smtp_from_name   = getEmailSetting($conn, 'smtp_from_name', 'RD Vendora Marketplace');

// Fallback for "from" address if not set separately
if (empty($smtp_from)) {
    $smtp_from = getEmailSetting($conn, 'site_email', $smtp_username);
    if (empty($smtp_from)) $smtp_from = 'noreply@' . $_SERVER['HTTP_HOST'];
}

// Build configuration array
$email_config = [
    'host'       => $smtp_host,
    'port'       => (int)$smtp_port,
    'username'   => $smtp_username,
    'password'   => $smtp_password,
    'encryption' => $smtp_secure,
    'from'       => $smtp_from,
    'from_name'  => $smtp_from_name,
    'auth'       => (!empty($smtp_username) && !empty($smtp_password))
];

return $email_config;
?>