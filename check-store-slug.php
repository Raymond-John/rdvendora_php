<?php
/**
 * AJAX: check store slug availability for the logged-in seller.
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/includes/connection.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database unavailable']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : (isset($_POST['slug']) ? (string) $_POST['slug'] : '');

$storeId = 0;
$stmt = $conn->prepare('SELECT id FROM stores WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $storeId = (int) ($row['id'] ?? 0);
}

$result = rdv_store_slug_availability($conn, $slug, $storeId);
$result['url'] = $result['ok'] ? rdv_store_url(['id' => $storeId, 'store_slug' => $result['slug']]) : '';
echo json_encode($result);
