<?php
/**
 * Admin login security: deactivate unknown admins from email, keep accounts locked out.
 */
if (!function_exists('rdv_client_ip')) {
    function rdv_client_ip() {
        $ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP');
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return $ip !== '' ? $ip : 'Unknown IP';
    }
}

if (!function_exists('rdv_ensure_users_is_active_column')) {
    function rdv_ensure_users_is_active_column(mysqli $conn) {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
            if ($result && $result->num_rows > 0) {
                return;
            }
            $conn->query('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
            if (function_exists('rdv_admin_user_columns')) {
                rdv_admin_user_columns($conn, true);
            }
        } catch (Throwable $e) {
            error_log('rdv_ensure_users_is_active_column: ' . $e->getMessage());
        }
    }
}

if (!function_exists('rdv_admin_security_secret')) {
    function rdv_admin_security_secret() {
        $key = '';
        if (function_exists('rdv_env')) {
            $key = (string) rdv_env('APP_KEY', '');
            if ($key === '') {
                $key = (string) rdv_env('SMTP_PASS', '');
            }
        }
        if ($key === '') {
            $key = 'rdv-admin-login-security';
        }
        return hash('sha256', $key . '|rdv-admin-deactivate', true);
    }
}

if (!function_exists('rdv_admin_deactivate_signature')) {
    function rdv_admin_deactivate_signature($userId, $exp) {
        return hash_hmac('sha256', (int) $userId . '|' . (int) $exp, rdv_admin_security_secret());
    }
}

if (!function_exists('rdv_admin_deactivate_url')) {
    function rdv_admin_deactivate_url($userId) {
        $exp = time() + 86400;
        $sig = rdv_admin_deactivate_signature($userId, $exp);
        $query = [
            'uid' => (int) $userId,
            'exp' => $exp,
            'sig' => $sig,
        ];
        if (function_exists('rdv_url')) {
            return rdv_url('admin/admin-deactivate-login', $query);
        }
        return 'admin/admin-deactivate-login?' . http_build_query($query);
    }
}

if (!function_exists('rdv_admin_deactivate_token_valid')) {
    function rdv_admin_deactivate_token_valid($userId, $exp, $sig) {
        $userId = (int) $userId;
        $exp = (int) $exp;
        if ($userId < 1 || $exp < time() || !is_string($sig) || $sig === '') {
            return false;
        }
        $expected = rdv_admin_deactivate_signature($userId, $exp);
        return hash_equals($expected, $sig);
    }
}

if (!function_exists('rdv_admin_owner_alert_emails')) {
    function rdv_admin_owner_alert_emails(mysqli $conn = null) {
        $candidates = [];
        $db = $conn instanceof mysqli ? $conn : ($GLOBALS['conn'] ?? $GLOBALS['connect'] ?? null);
        $keys = ['admin_alert_email', 'admin_email', 'site_email', 'smtp_user', 'smtp_from'];
        if ($db instanceof mysqli) {
            foreach ($keys as $key) {
                try {
                    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                    if (!$stmt) {
                        continue;
                    }
                    $stmt->bind_param('s', $key);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $candidates[] = trim((string) ($row['setting_value'] ?? ''));
                } catch (Throwable $e) {
                    break;
                }
            }
        }
        if (function_exists('rdv_env')) {
            $candidates[] = (string) rdv_env('ADMIN_ALERT_EMAIL', '');
            $candidates[] = (string) rdv_env('SMTP_USER', '');
            $candidates[] = (string) rdv_env('SMTP_FROM', '');
        }
        if (function_exists('rdv_smtp_settings')) {
            $smtp = rdv_smtp_settings();
            $candidates[] = (string) ($smtp['username'] ?? '');
            $candidates[] = (string) ($smtp['from'] ?? '');
        }

        $skip = [
            'admin@rdvendora.com',
            'admin@example.com',
            'noreply@rdvendora.com',
            'notifications@rdvendora.com',
        ];
        $emails = [];
        foreach ($candidates as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (in_array($email, $skip, true)) {
                continue;
            }
            $emails[$email] = $email;
        }
        if (!$emails) {
            foreach ($candidates as $email) {
                $email = strtolower(trim((string) $email));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[$email] = $email;
                    break;
                }
            }
        }
        return array_values($emails);
    }
}

if (!function_exists('rdv_deactivate_admin_user')) {
    function rdv_deactivate_admin_user(mysqli $conn, $userId) {
        $userId = (int) $userId;
        if ($userId < 1) {
            return false;
        }
        rdv_ensure_users_is_active_column($conn);
        $stmt = $conn->prepare('UPDATE users SET is_active = 0 WHERE id = ? AND is_admin = 1 LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $ok && $affected > 0;
    }
}

if (!function_exists('rdv_reactivate_admin_user')) {
    function rdv_reactivate_admin_user(mysqli $conn, $userId) {
        $userId = (int) $userId;
        if ($userId < 1) {
            return false;
        }
        rdv_ensure_users_is_active_column($conn);
        $stmt = $conn->prepare('UPDATE users SET is_active = 1 WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('sendAdminLoginOwnerAlert')) {
    function sendAdminLoginOwnerAlert($conn, $userId, $email, $fullname) {
        if (!function_exists('sendEmail')) {
            require_once __DIR__ . '/email_functions.php';
        }
        $recipients = rdv_admin_owner_alert_emails($conn instanceof mysqli ? $conn : null);
        if (!$recipients) {
            error_log('Admin login owner alert: no recipient email configured');
            return false;
        }

        $ip = rdv_client_ip();
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown browser');
        $time = date('j F Y, g:ia');
        $deactivateUrl = rdv_admin_deactivate_url($userId);
        $year = date('Y');
        $safeName = htmlspecialchars((string) $fullname, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8');
        $safeIp = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        $safeAgent = htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
        $safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');
        $safeDeactivate = htmlspecialchars($deactivateUrl, ENT_QUOTES, 'UTF-8');
        $ownerEmails = array_map('strtolower', $recipients);
        $isOwnerAccount = in_array(strtolower((string) $email), $ownerEmails, true);

        $actionHtml = $isOwnerAccount
            ? '<p style="font-size:15px;color:#64748B;line-height:1.6;">This looks like your own admin email. If you did not sign in, change your password immediately. Do not deactivate this account from email or you may lock yourself out.</p>'
            : '<p style="font-size:15px;color:#1E293B;line-height:1.6;margin:0 0 16px 0;"><strong>If you do not know this person, deactivate their admin access now.</strong></p>
               <table border="0" cellpadding="0" cellspacing="0" style="margin:0 auto 16px auto;"><tr><td style="background:#b91c1c;border-radius:10px;">
               <a href="' . $safeDeactivate . '" style="display:inline-block;padding:14px 22px;color:#ffffff;font-weight:700;text-decoration:none;">Deactivate this admin</a>
               </td></tr></table>
               <p style="font-size:13px;color:#94A3B8;line-height:1.5;">This link expires in 24 hours. It only removes admin access for ' . $safeEmail . '.</p>';

        $htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#F5F7FB;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
<table align="center" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FB;padding:40px 20px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:18px;border:1px solid #E5E7EB;">
<tr><td style="background:#071530;border-bottom:6px solid #D4AF37;border-radius:18px 18px 0 0;padding:22px 30px;text-align:center;color:#fff;font-size:22px;font-weight:700;">RD Vendora security</td></tr>
<tr><td style="padding:32px 30px;">
<p style="margin:0 0 8px 0;color:#b45309;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;">Admin dashboard login</p>
<h1 style="margin:0 0 12px 0;font-size:24px;color:#0f172a;">Someone signed in as an admin</h1>
<p style="margin:0 0 20px 0;color:#64748B;line-height:1.6;">Review this sign-in. If you do not recognize the person, deactivate them from the button below.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC;border-radius:14px;border-left:6px solid #D4AF37;padding:8px 4px 8px 0;margin-bottom:20px;">
<tr><td style="padding:8px 20px;font-size:15px;color:#1E293B;"><strong>Name:</strong> ' . $safeName . '</td></tr>
<tr><td style="padding:8px 20px;font-size:15px;color:#1E293B;"><strong>Email:</strong> ' . $safeEmail . '</td></tr>
<tr><td style="padding:8px 20px;font-size:15px;color:#1E293B;"><strong>IP address:</strong> ' . $safeIp . '</td></tr>
<tr><td style="padding:8px 20px;font-size:15px;color:#1E293B;"><strong>Time:</strong> ' . $safeTime . '</td></tr>
<tr><td style="padding:8px 20px;font-size:15px;color:#1E293B;"><strong>Browser:</strong> ' . $safeAgent . '</td></tr>
</table>
' . $actionHtml . '
<p style="font-size:13px;color:#94A3B8;margin-top:24px;">&copy; ' . $year . ' RD Vendora — automated security alert.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';

        $plainText = "Admin dashboard login\n\nName: $fullname\nEmail: $email\nIP: $ip\nTime: $time\nBrowser: $userAgent\n\n";
        $plainText .= $isOwnerAccount
            ? "This looks like your own admin email. If it was not you, change your password.\n"
            : "If you do not know this person, deactivate them here (expires in 24 hours):\n$deactivateUrl\n";

        $ok = true;
        foreach ($recipients as $to) {
            if (!sendEmail($to, 'Admin login alert: ' . $email, $htmlBody, $plainText)) {
                $ok = false;
            }
        }
        return $ok;
    }
}
