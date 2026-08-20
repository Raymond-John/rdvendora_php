<?php
/**
 * Fetch PeerJS ID for chat calling.
 * Admin: ?vendor_id=123
 * Vendor: ?role=admin
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/connection.php';
$conn = $conn ?? $connect ?? null;
if (!$conn instanceof mysqli) {
    echo json_encode(['success' => false, 'peer_id' => null]);
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

$isAdmin = !empty($_SESSION['is_admin']);
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
if (!$isAdmin && $sessionUserId <= 0) {
    echo json_encode(['success' => false, 'peer_id' => null, 'error' => 'Not logged in']);
    exit;
}

$peerId = null;
$freshSeconds = 600; // consider peer stale after 10 minutes

if ($isAdmin) {
    $vendorId = (int) ($_GET['vendor_id'] ?? 0);
    if ($vendorId <= 0) {
        echo json_encode(['success' => false, 'peer_id' => null, 'error' => 'vendor_id required']);
        exit;
    }
    $stmt = $conn->prepare("SELECT peer_id, updated_at FROM chat_peer_ids
        WHERE user_id = ? AND role = 'vendor'
          AND updated_at >= (NOW() - INTERVAL {$freshSeconds} SECOND)
        LIMIT 1");
    $stmt->bind_param('i', $vendorId);
} else {
    // Vendor requesting admin peer (most recently updated admin)
    $stmt = $conn->prepare("SELECT peer_id, updated_at FROM chat_peer_ids
        WHERE role = 'admin'
          AND updated_at >= (NOW() - INTERVAL {$freshSeconds} SECOND)
        ORDER BY updated_at DESC
        LIMIT 1");
}

if ($stmt) {
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && !empty($row['peer_id'])) {
        $peerId = $row['peer_id'];
    }
}

echo json_encode([
    'success' => $peerId !== null,
    'peer_id' => $peerId,
]);
