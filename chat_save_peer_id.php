<?php
/**
 * Save PeerJS ID for admin or vendor chat calling.
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/connection.php';
$conn = $conn ?? $connect ?? null;
if (!$conn instanceof mysqli) {
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS chat_peer_ids (
    user_id INT NOT NULL,
    role ENUM('admin','vendor') NOT NULL,
    peer_id VARCHAR(128) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role),
    KEY peer_lookup (role, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$peerId = trim((string) ($_POST['peer_id'] ?? ''));
if ($peerId === '' || strlen($peerId) > 128 || !preg_match('/^[A-Za-z0-9_-]+$/', $peerId)) {
    echo json_encode(['success' => false, 'error' => 'Invalid peer id']);
    exit;
}

$isAdmin = !empty($_SESSION['is_admin']);
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$postedVendor = (int) ($_POST['vendor_id'] ?? 0);

if ($isAdmin && $postedVendor > 0) {
    // Admin recording a vendor peer ID learned from chat signaling
    $role = 'vendor';
    $userId = $postedVendor;
} elseif ($isAdmin) {
    $role = 'admin';
    $userId = $sessionUserId > 0 ? $sessionUserId : 1;
} elseif ($sessionUserId > 0) {
    $role = 'vendor';
    $userId = $sessionUserId;
    if ($postedVendor > 0 && $postedVendor !== $userId) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO chat_peer_ids (user_id, role, peer_id) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE peer_id = VALUES(peer_id), updated_at = CURRENT_TIMESTAMP');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed']);
    exit;
}
$stmt->bind_param('iss', $userId, $role, $peerId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => (bool) $ok, 'role' => $role, 'user_id' => $userId]);
