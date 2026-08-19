<?php
if (!function_exists('logUserActivity')) {
    function logUserActivity($user_id, $action, $page, $details = null) {
        global $conn, $connect;
        $db = $conn ?? $connect ?? null;
        if (!$db instanceof mysqli) {
            return false;
        }

        try {
            $db->query("CREATE TABLE IF NOT EXISTS user_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                page VARCHAR(255) NOT NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (action),
                INDEX (created_at),
                INDEX (page)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            error_log('Failed to create user_activity_log: ' . $e->getMessage());
            return false;
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $uid = (int) $user_id;
        $action = substr((string) $action, 0, 100);
        $page = substr((string) $page, 0, 255);
        $details = $details !== null ? (string) $details : null;

        try {
            $stmt = $db->prepare('INSERT INTO user_activity_log (user_id, action, page, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('isssss', $uid, $action, $page, $details, $ip, $ua);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            error_log('Failed to log user activity: ' . $e->getMessage());
            return false;
        }
    }
}
