<?php
/**
 * SMTP credentials from .env, overlaid by Admin → Settings when the settings table exists.
 */
if (!function_exists('rdv_smtp_settings')) {
    function rdv_smtp_settings() {
        $cfg = [
            'host' => (string) rdv_env('SMTP_HOST', 'smtp.gmail.com'),
            'port' => (int) rdv_env('SMTP_PORT', 587),
            'username' => (string) rdv_env('SMTP_USER', ''),
            'password' => (string) rdv_env('SMTP_PASS', ''),
            'encryption' => (string) rdv_env('SMTP_ENCRYPTION', 'tls'),
            'from' => (string) rdv_env('SMTP_FROM', ''),
            'from_name' => (string) rdv_env('SMTP_FROM_NAME', 'RD Vendora'),
        ];
        $db = $GLOBALS['conn'] ?? $GLOBALS['connect'] ?? null;
        if ($db instanceof mysqli) {
            $map = [
                'smtp_host' => 'host',
                'smtp_port' => 'port',
                'smtp_user' => 'username',
                'smtp_pass' => 'password',
                'smtp_encryption' => 'encryption',
                'smtp_from' => 'from',
                'smtp_from_name' => 'from_name',
            ];
            foreach ($map as $settingKey => $cfgKey) {
                try {
                    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                    if (!$stmt) {
                        continue;
                    }
                    $stmt->bind_param('s', $settingKey);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $val = trim((string) ($row['setting_value'] ?? ''));
                    if ($val !== '') {
                        $cfg[$cfgKey] = ($cfgKey === 'port') ? (int) $val : $val;
                    }
                } catch (Throwable $e) {
                    error_log('rdv_smtp_settings: ' . $e->getMessage());
                    break;
                }
            }
        }
        if ($cfg['from'] === '') {
            $cfg['from'] = $cfg['username'];
        }
        if ($cfg['from'] === '') {
            $cfg['from'] = 'noreply@rdvendora.com';
        }
        return $cfg;
    }
}

$smtp = rdv_smtp_settings();
$smtp_host      = $smtp['host'];
$smtp_auth      = true;
$smtp_username  = $smtp['username'];
$smtp_password  = $smtp['password'];
$smtp_secure    = $smtp['encryption'];
$smtp_port      = $smtp['port'];
$smtp_from      = $smtp['from'];
$smtp_from_name = $smtp['from_name'];
$smtp_user      = $smtp_username;
$smtp_pass      = $smtp_password;
