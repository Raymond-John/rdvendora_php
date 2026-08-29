<?php

if (!function_exists('rdv_user_avatar_columns')) {
    function rdv_user_avatar_columns(mysqli $conn) {
        static $cache = [];
        $key = spl_object_hash($conn);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $cols = [];
        try {
            $res = $conn->query('SHOW COLUMNS FROM users');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $field = strtolower((string) ($row['Field'] ?? ''));
                    if ($field === 'avatar_url' || $field === 'avatar') {
                        $cols[] = $row['Field'];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('rdv_user_avatar_columns: ' . $e->getMessage());
        }
        $cache[$key] = $cols;
        return $cols;
    }
}

if (!function_exists('rdv_user_avatar_write_column')) {
    function rdv_user_avatar_write_column(mysqli $conn) {
        $cols = rdv_user_avatar_columns($conn);
        foreach ($cols as $col) {
            if (strcasecmp($col, 'avatar_url') === 0) {
                return $col;
            }
        }
        foreach ($cols as $col) {
            if (strcasecmp($col, 'avatar') === 0) {
                return $col;
            }
        }
        return $cols[0] ?? '';
    }
}

if (!function_exists('rdv_user_avatar_read_sql')) {
    function rdv_user_avatar_read_sql(mysqli $conn) {
        $cols = rdv_user_avatar_columns($conn);
        if (empty($cols)) {
            return "'' AS avatar_path";
        }
        if (count($cols) === 1) {
            return '`' . $cols[0] . '` AS avatar_path';
        }
        $preferred = rdv_user_avatar_write_column($conn);
        $fallback = $cols[0] === $preferred ? $cols[1] : $cols[0];
        return 'COALESCE(NULLIF(`' . $preferred . '`, \'\'), NULLIF(`' . $fallback . '`, \'\')) AS avatar_path';
    }
}

if (!function_exists('rdv_user_name_column')) {
    function rdv_user_name_column(mysqli $conn) {
        static $cache = [];
        $key = spl_object_hash($conn);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $nameCol = 'email';
        try {
            $res = $conn->query('SHOW COLUMNS FROM users');
            $fields = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $fields[strtolower((string) ($row['Field'] ?? ''))] = $row['Field'];
                }
            }
            if (isset($fields['fullname'])) {
                $nameCol = $fields['fullname'];
            } elseif (isset($fields['full_name'])) {
                $nameCol = $fields['full_name'];
            } elseif (isset($fields['name'])) {
                $nameCol = $fields['name'];
            }
        } catch (Throwable $e) {
            error_log('rdv_user_name_column: ' . $e->getMessage());
        }
        $cache[$key] = $nameCol;
        return $nameCol;
    }
}

if (!function_exists('rdv_user_avatar_web_url')) {
    function rdv_user_avatar_web_url($path) {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (!function_exists('rdv_web_relative')) {
            require_once dirname(__DIR__) . '/bootstrap.php';
        }
        $web = rdv_web_relative($path);
        if (function_exists('rdv_asset')) {
            return rdv_asset($web);
        }
        return '/' . ltrim($web, '/');
    }
}

if (!function_exists('rdv_user_avatar_raw_path')) {
    function rdv_user_avatar_raw_path(mysqli $conn, $userId) {
        try {
            $userId = (int) $userId;
            if ($userId < 1) {
                return '';
            }
            $select = rdv_user_avatar_read_sql($conn);
            $sql = "SELECT {$select} FROM users WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return '';
            }
            $stmt->bind_param('i', $userId);
            if (!$stmt->execute()) {
                $stmt->close();
                return '';
            }
            $result = $stmt->get_result();
            if (!$result) {
                $stmt->close();
                return '';
            }
            $row = $result->fetch_assoc();
            $stmt->close();
            return trim((string) ($row['avatar_path'] ?? ''));
        } catch (Throwable $e) {
            error_log('rdv_user_avatar_raw_path: ' . $e->getMessage());
            return '';
        }
    }
}

if (!function_exists('rdv_user_avatar_url')) {
    function rdv_user_avatar_url(mysqli $conn, $userId) {
        try {
            return rdv_user_avatar_web_url(rdv_user_avatar_raw_path($conn, $userId));
        } catch (Throwable $e) {
            error_log('rdv_user_avatar_url: ' . $e->getMessage());
            return '';
        }
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

if (!function_exists('rdv_user_avatar_save_path')) {
    function rdv_user_avatar_save_path(mysqli $conn, $userId, $webPath) {
        $cols = rdv_user_avatar_columns($conn);
        if (empty($cols)) {
            return false;
        }
        $ok = false;
        foreach ($cols as $col) {
            $stmt = $conn->prepare("UPDATE users SET `{$col}` = ? WHERE id = ?");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('si', $webPath, $userId);
            if ($stmt->execute()) {
                $ok = true;
            }
            $stmt->close();
        }
        return $ok;
    }
}

if (!function_exists('rdv_user_avatar_upload')) {
    function rdv_user_avatar_upload(mysqli $conn, $userId, array $file) {
        try {
            $userId = (int) $userId;
            if ($userId < 1) {
                return ['success' => false, 'message' => 'Invalid user.'];
            }
            if (empty(rdv_user_avatar_columns($conn))) {
                return ['success' => false, 'message' => 'Profile photos are not enabled on this site yet.'];
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

            if (!function_exists('rdv_fs_path')) {
                require_once dirname(__DIR__) . '/bootstrap.php';
            }

            $webPath = 'uploads/avatars/user_' . $userId . '_' . time() . '.' . $ext;
            $destinationFs = rdv_fs_path($webPath);
            $uploadDir = dirname($destinationFs);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            if (!move_uploaded_file($file['tmp_name'], $destinationFs)) {
                return ['success' => false, 'message' => 'Could not save uploaded file.'];
            }

            $oldPath = rdv_user_avatar_raw_path($conn, $userId);
            if (!rdv_user_avatar_save_path($conn, $userId, $webPath)) {
                @unlink($destinationFs);
                return ['success' => false, 'message' => 'Failed to update profile photo.'];
            }

            if ($oldPath !== '' && $oldPath !== $webPath && !preg_match('#^https?://#i', $oldPath)) {
                $oldFs = rdv_fs_path($oldPath);
                if (is_file($oldFs)) {
                    @unlink($oldFs);
                }
            }

            $publicUrl = rdv_user_avatar_web_url($webPath);
            $_SESSION['user_avatar'] = $publicUrl;
            return ['success' => true, 'message' => 'Profile photo updated.', 'path' => $webPath, 'url' => $publicUrl];
        } catch (Throwable $e) {
            error_log('rdv_user_avatar_upload: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not update profile photo.'];
        }
    }
}
