<?php
/**
 * Newsletter subscribe / verify / unsubscribe helpers.
 */
if (!function_exists('rdv_ensure_newsletter_table')) {
    function rdv_ensure_newsletter_table(mysqli $conn) {
        $conn->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            first_name VARCHAR(100) NULL,
            status ENUM('pending','verified','unsubscribed') NOT NULL DEFAULT 'pending',
            consent TINYINT(1) NOT NULL DEFAULT 0,
            subscribed_at DATETIME NULL,
            unsubscribed_at DATETIME NULL,
            verification_token VARCHAR(64) NULL,
            verified_at DATETIME NULL,
            ip_hash VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_email (email),
            KEY idx_status (status),
            KEY idx_token (verification_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('rdv_rate_limit')) {
    function rdv_rate_limit($bucket, $max, $seconds) {
        $dir = STORAGE_PATH . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = hash('sha256', $bucket . '|' . $ip);
        $file = $dir . '/rate_' . $key . '.json';
        $now = time();
        $hits = [];
        if (is_readable($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $hits = $decoded;
            }
        }
        $hits = array_values(array_filter($hits, static function ($t) use ($now, $seconds) {
            return is_int($t) && ($now - $t) < $seconds;
        }));
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);
        return true;
    }
}

if (!function_exists('rdv_newsletter_confirm_url')) {
    function rdv_newsletter_confirm_url($token) {
        return rtrim(APP_URL, '/') . '/newsletter-confirm.php?token=' . rawurlencode($token);
    }
}

if (!function_exists('rdv_newsletter_unsubscribe_url')) {
    function rdv_newsletter_unsubscribe_url($email, $token = '') {
        $url = rtrim(APP_URL, '/') . '/newsletter-unsubscribe.php?email=' . rawurlencode($email);
        if ($token !== '') {
            $url .= '&token=' . rawurlencode($token);
        }
        return $url;
    }
}

if (!function_exists('rdv_newsletter_send_confirmation')) {
    function rdv_newsletter_send_confirmation($email, $firstName, $token) {
        if (!function_exists('sendEmail')) {
            require_once APP_PATH . '/helpers/email_functions.php';
        }
        $confirm = rdv_newsletter_confirm_url($token);
        $unsub = rdv_newsletter_unsubscribe_url($email, $token);
        $safeName = $firstName !== '' ? htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') : 'there';
        $html = '<p>Hi ' . $safeName . ',</p>'
            . '<p>Please confirm you want to receive the RD Vendora newsletter (platform news, product updates, and practical resources for running a store).</p>'
            . '<p><a href="' . htmlspecialchars($confirm, ENT_QUOTES, 'UTF-8') . '">Confirm subscription</a></p>'
            . '<p>If you did not request this, ignore this email or <a href="' . htmlspecialchars($unsub, ENT_QUOTES, 'UTF-8') . '">unsubscribe</a>.</p>';
        $plain = "Confirm your RD Vendora newsletter subscription:\n$confirm\n\nUnsubscribe: $unsub";
        return sendEmail($email, 'Confirm your RD Vendora newsletter subscription', $html, $plain);
    }
}

if (!function_exists('rdv_newsletter_subscribe')) {
    function rdv_newsletter_subscribe(mysqli $conn, $email, $firstName, $consent) {
        rdv_ensure_newsletter_table($conn);
        $email = strtolower(trim($email));
        $firstName = trim($firstName);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if (!$consent) {
            return ['ok' => false, 'message' => 'Please confirm that you want to subscribe to the RD Vendora newsletter.'];
        }
        if (strlen($firstName) > 100) {
            $firstName = substr($firstName, 0, 100);
        }

        $stmt = $conn->prepare('SELECT id, status, verification_token FROM newsletter_subscribers WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $token = bin2hex(random_bytes(32));
        $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (rdv_env('APP_KEY', 'rdv')));

        if ($existing) {
            if ($existing['status'] === 'verified') {
                return ['ok' => true, 'message' => 'This email is already subscribed. Thank you.'];
            }
            if ($existing['status'] === 'unsubscribed') {
                $stmt = $conn->prepare('UPDATE newsletter_subscribers SET first_name = ?, status = ?, consent = 1, subscribed_at = NOW(), unsubscribed_at = NULL, verification_token = ?, verified_at = NULL, ip_hash = ? WHERE id = ?');
                $pending = 'pending';
                $id = (int) $existing['id'];
                $stmt->bind_param('ssssi', $firstName, $pending, $token, $ipHash, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare('UPDATE newsletter_subscribers SET first_name = ?, consent = 1, verification_token = ?, ip_hash = ? WHERE id = ?');
                $id = (int) $existing['id'];
                $stmt->bind_param('sssi', $firstName, $token, $ipHash, $id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $status = 'pending';
            $stmt = $conn->prepare('INSERT INTO newsletter_subscribers (email, first_name, status, consent, subscribed_at, verification_token, ip_hash) VALUES (?, ?, ?, 1, NOW(), ?, ?)');
            $stmt->bind_param('sssss', $email, $firstName, $status, $token, $ipHash);
            $stmt->execute();
            $stmt->close();
        }

        $sent = false;
        try {
            $sent = rdv_newsletter_send_confirmation($email, $firstName, $token);
        } catch (Throwable $e) {
            error_log('Newsletter confirmation email failed: ' . $e->getMessage());
        }
        if (!$sent) {
            return ['ok' => true, 'message' => 'You are on the list. If a confirmation email does not arrive, check spam or try again later.'];
        }
        return ['ok' => true, 'message' => 'Check your inbox and click the confirmation link to finish subscribing.'];
    }
}
