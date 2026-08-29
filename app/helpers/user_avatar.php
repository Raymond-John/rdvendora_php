<?php

if (!function_exists('rdv_user_avatar_column')) {
    function rdv_user_avatar_column(mysqli $conn) {
        static $cache = [];
        $key = spl_object_hash($conn);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $cols = [];
        $res = $conn->query('SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[strtolower($row['Field'])] = $row['Field'];
            }
        }
        if (isset($cols['avatar_url'])) {
            $cache[$key] = $cols['avatar_url'];
        } elseif (isset($cols['avatar'])) {
            $cache[$key] = $cols['avatar'];
        } else {
            @$conn->query('ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL');
            $cache[$key] = 'avatar_url';
        }
        return $cache[$key];
    }
}

if (!function_exists('rdv_user_avatar_raw_path')) {
    function rdv_user_avatar_raw_path(mysqli $conn, $userId) {
        $userId = (int) $userId;
        if ($userId < 1) {
            return '';
        }
        $col = rdv_user_avatar_column($conn);
        $stmt = $conn->prepare("SELECT `{$col}` AS avatar_path FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return '';
        }
        return trim((string) ($row['avatar_path'] ?? ''));
    }
}

if (!function_exists('rdv_user_avatar_url')) {
    function rdv_user_avatar_url(mysqli $conn, $userId) {
        $path = rdv_user_avatar_raw_path($conn, $userId);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (!function_exists('rdv_fs_path')) {
            require_once dirname(__DIR__) . '/bootstrap.php';
        }
        if (!file_exists(rdv_fs_path($path))) {
            return '';
        }
        return function_exists('rdv_asset') ? rdv_asset($path) : $path;
    }
}

if (!function_exists('rdv_user_avatar_initials')) {
    function rdv_user_avatar_initials($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return 'U';
        }
        $parts = preg_split('/\s+/', $name);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 1));
    }
}

if (!function_exists('rdv_user_avatar_upload')) {
    function rdv_user_avatar_upload(mysqli $conn, $userId, array $file) {
        $userId = (int) $userId;
        if ($userId < 1) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed. Please choose a valid image.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return ['success' => false, 'message' => 'Use JPG, PNG, GIF, or WEBP.'];
        }
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Image must be 2MB or smaller.'];
        }
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'Could not save uploaded file.'];
        }

        $oldPath = rdv_user_avatar_raw_path($conn, $userId);
        $col = rdv_user_avatar_column($conn);
        $stmt = $conn->prepare("UPDATE users SET `{$col}` = ? WHERE id = ?");
        if (!$stmt) {
            @unlink($destination);
            return ['success' => false, 'message' => 'Failed to update profile photo.'];
        }
        $stmt->bind_param('si', $destination, $userId);
        if (!$stmt->execute()) {
            @unlink($destination);
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update profile photo.'];
        }
        $stmt->close();

        if ($oldPath !== '' && $oldPath !== $destination && !preg_match('#^https?://#i', $oldPath)) {
            if (!function_exists('rdv_fs_path')) {
                require_once dirname(__DIR__) . '/bootstrap.php';
            }
            $oldFs = rdv_fs_path($oldPath);
            if (is_file($oldFs)) {
                @unlink($oldFs);
            }
        }

        $_SESSION['user_avatar'] = rdv_user_avatar_url($conn, $userId);
        return ['success' => true, 'message' => 'Profile photo updated.', 'path' => $destination];
    }
}
