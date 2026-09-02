<?php

if (!function_exists('rdv_ensure_store_account_details_table')) {
    function rdv_ensure_store_account_details_table(mysqli $conn) {
        $sql = "CREATE TABLE IF NOT EXISTS store_account_details (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            business_name VARCHAR(200) NOT NULL,
            contact_phone VARCHAR(30) NOT NULL,
            contact_email VARCHAR(191) NOT NULL,
            business_address TEXT NOT NULL,
            city VARCHAR(100) NOT NULL,
            state_region VARCHAR(100) NOT NULL,
            country VARCHAR(100) NOT NULL DEFAULT 'Nigeria',
            bank_name VARCHAR(120) NOT NULL,
            account_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(40) NOT NULL,
            account_type ENUM('savings','current') NOT NULL DEFAULT 'savings',
            tax_id VARCHAR(80) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status ENUM('pending','reviewed') NOT NULL DEFAULT 'pending',
            submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_store_account (store_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        @$conn->query($sql);
    }
}

if (!function_exists('rdv_store_account_details_get')) {
    function rdv_store_account_details_get(mysqli $conn, $storeId) {
        rdv_ensure_store_account_details_table($conn);
        $storeId = (int) $storeId;
        if ($storeId <= 0) {
            return null;
        }
        $stmt = $conn->prepare('SELECT * FROM store_account_details WHERE store_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $storeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('rdv_store_account_details_exists')) {
    function rdv_store_account_details_exists(mysqli $conn, $storeId) {
        return rdv_store_account_details_get($conn, $storeId) !== null;
    }
}

if (!function_exists('rdv_store_account_details_save')) {
    /**
     * @param array<string,mixed> $data
     * @return array{ok:bool,message:string}
     */
    function rdv_store_account_details_save(mysqli $conn, $storeId, $userId, array $data) {
        rdv_ensure_store_account_details_table($conn);
        $storeId = (int) $storeId;
        $userId = (int) $userId;
        if ($storeId <= 0 || $userId <= 0) {
            return ['ok' => false, 'message' => 'Invalid store.'];
        }

        $businessName = trim((string) ($data['business_name'] ?? ''));
        $contactPhone = trim((string) ($data['contact_phone'] ?? ''));
        $contactEmail = trim((string) ($data['contact_email'] ?? ''));
        $businessAddress = trim((string) ($data['business_address'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $stateRegion = trim((string) ($data['state_region'] ?? ''));
        $country = trim((string) ($data['country'] ?? 'Nigeria'));
        $bankName = trim((string) ($data['bank_name'] ?? ''));
        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountNumber = preg_replace('/\s+/', '', (string) ($data['account_number'] ?? ''));
        $accountType = strtolower(trim((string) ($data['account_type'] ?? 'savings')));
        $notes = trim((string) ($data['notes'] ?? ''));
        $taxId = '';

        if ($businessName === '' || $contactPhone === '' || $contactEmail === '' || $businessAddress === ''
            || $city === '' || $stateRegion === '' || $bankName === '' || $accountName === '' || $accountNumber === '') {
            return ['ok' => false, 'message' => 'Please fill in all required fields.'];
        }
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid contact email address.'];
        }
        if (!preg_match('/^[0-9]{10}$/', $accountNumber)) {
            return ['ok' => false, 'message' => 'Account number must be 10 digits.'];
        }
        if (!in_array($accountType, ['savings', 'current'], true)) {
            $accountType = 'savings';
        }

        $businessName = substr($businessName, 0, 200);
        $contactPhone = substr($contactPhone, 0, 30);
        $contactEmail = substr($contactEmail, 0, 191);
        $city = substr($city, 0, 100);
        $stateRegion = substr($stateRegion, 0, 100);
        $country = substr($country !== '' ? $country : 'Nigeria', 0, 100);
        $bankName = substr($bankName, 0, 120);
        $accountName = substr($accountName, 0, 120);
        $notes = $notes !== '' ? substr($notes, 0, 2000) : '';

        $existing = rdv_store_account_details_get($conn, $storeId);
        if ($existing) {
            $stmt = $conn->prepare(
                'UPDATE store_account_details SET
                    business_name = ?, contact_phone = ?, contact_email = ?, business_address = ?,
                    city = ?, state_region = ?, country = ?, bank_name = ?, account_name = ?,
                    account_number = ?, account_type = ?, tax_id = ?, notes = ?, status = \'pending\'
                 WHERE store_id = ? AND user_id = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not save your details. Please try again.'];
            }
            $stmt->bind_param(
                'sssssssssssssii',
                $businessName,
                $contactPhone,
                $contactEmail,
                $businessAddress,
                $city,
                $stateRegion,
                $country,
                $bankName,
                $accountName,
                $accountNumber,
                $accountType,
                $taxId,
                $notes,
                $storeId,
                $userId
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO store_account_details (
                    store_id, user_id, business_name, contact_phone, contact_email, business_address,
                    city, state_region, country, bank_name, account_name, account_number, account_type, tax_id, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not save your details. Please try again.'];
            }
            $stmt->bind_param(
                'iisssssssssssss',
                $storeId,
                $userId,
                $businessName,
                $contactPhone,
                $contactEmail,
                $businessAddress,
                $city,
                $stateRegion,
                $country,
                $bankName,
                $accountName,
                $accountNumber,
                $accountType,
                $taxId,
                $notes
            );
        }

        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not save your details. Please try again.'];
        }
        return ['ok' => true, 'message' => 'Account details saved. Your store is pending admin approval.'];
    }
}

if (!function_exists('rdv_store_account_owner_name_expr')) {
    function rdv_store_account_owner_name_expr(mysqli $conn) {
        $parts = [];
        $res = $conn->query('SHOW COLUMNS FROM users');
        if ($res) {
            $cols = [];
            while ($row = $res->fetch_assoc()) {
                $cols[$row['Field']] = true;
            }
            foreach (['fullname', 'full_name', 'name', 'username'] as $candidate) {
                if (!empty($cols[$candidate])) {
                    $parts[] = "NULLIF(u.$candidate, '')";
                }
            }
        }
        if (!$parts) {
            return 'u.email';
        }
        return 'COALESCE(' . implode(', ', $parts) . ', u.email)';
    }
}

if (!function_exists('rdv_store_account_details_list_for_admin')) {
    function rdv_store_account_details_list_for_admin(mysqli $conn, array $opts = []) {
        rdv_ensure_store_account_details_table($conn);
        $filter = (string) ($opts['filter'] ?? 'all');
        $search = trim((string) ($opts['q'] ?? ''));
        $limit = max(1, min(100, (int) ($opts['limit'] ?? 50)));
        $offset = max(0, (int) ($opts['offset'] ?? 0));

        $where = ['1=1'];
        $types = '';
        $params = [];

        if ($filter === 'pending_store') {
            $where[] = "s.status = 'pending'";
        } elseif ($filter === 'active_store') {
            $where[] = "s.status = 'active'";
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(s.store_name LIKE ? OR sad.business_name LIKE ? OR sad.contact_email LIKE ? OR u.email LIKE ?)';
            $types .= 'ssss';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $userNameExpr = rdv_store_account_owner_name_expr($conn);

        $sql = "SELECT sad.*, s.store_name, s.store_slug, s.status AS store_status, s.created_at AS store_created_at,
                       u.email AS owner_email, $userNameExpr AS owner_name
                FROM store_account_details sad
                INNER JOIN stores s ON s.id = sad.store_id
                INNER JOIN users u ON u.id = sad.user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sad.submitted_at DESC
                LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }
}

if (!function_exists('rdv_store_account_details_count_for_admin')) {
    function rdv_store_account_details_count_for_admin(mysqli $conn, array $opts = []) {
        rdv_ensure_store_account_details_table($conn);
        $filter = (string) ($opts['filter'] ?? 'all');
        $search = trim((string) ($opts['q'] ?? ''));

        $where = ['1=1'];
        $types = '';
        $params = [];

        if ($filter === 'pending_store') {
            $where[] = "s.status = 'pending'";
        } elseif ($filter === 'active_store') {
            $where[] = "s.status = 'active'";
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(s.store_name LIKE ? OR sad.business_name LIKE ? OR sad.contact_email LIKE ? OR u.email LIKE ?)';
            $types .= 'ssss';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $sql = "SELECT COUNT(*) AS cnt
                FROM store_account_details sad
                INNER JOIN stores s ON s.id = sad.store_id
                INNER JOIN users u ON u.id = sad.user_id
                WHERE " . implode(' AND ', $where);

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['cnt'] ?? 0);
    }
}
