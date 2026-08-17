<?php
// includes/log_activity.php

function logUserActivity($user_id, $action, $page, $details = null) {
    global $conn;
    if (!$conn) return false;

    // ---- Ensure the table exists ----
    $createSQL = "
        CREATE TABLE IF NOT EXISTS `user_activity_log` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `action` VARCHAR(100) NOT NULL,
            `page` VARCHAR(255) NOT NULL,
            `details` TEXT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    if (!$conn->query($createSQL)) {
        // If creation fails, log the error but don't stop execution
        error_log("Failed to create user_activity_log: " . $conn->error);
        return false;
    }

    // ---- Insert the activity ----
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, action, page, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Failed to prepare log insert: " . $conn->error);
        return false;
    }

    $stmt->bind_param("isssss", $user_id, $action, $page, $details, $ip, $ua);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
?>