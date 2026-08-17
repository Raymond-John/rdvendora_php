<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/notification_helper.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['order_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

$order_id = intval($input['order_id']);
$new_status = trim($input['status']);
$user_id = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Get the order's vendor (store owner)
$stmt = $conn->prepare("SELECT store_id FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$store_id = $order['store_id'];
$vendor_user_id = null;
$stmt = $conn->prepare("SELECT user_id FROM stores WHERE id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$vendor_user_id = $store['user_id'] ?? null;
$stmt->close();

// Permission check: if not admin, vendor can only update their own store orders
if (!$isAdmin && $vendor_user_id != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

// Update status
$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $order_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    // Get order details for notification
    $stmt = $conn->prepare("SELECT order_ref, user_name FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $orderInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $title = "Order Status Updated";
    $message = "Order #{$orderInfo['order_ref']} for {$orderInfo['user_name']} is now " . ucfirst($new_status);
    $link = "orders.php?view=$order_id";

    // Notify the store owner (vendor)
    if ($vendor_user_id && $vendor_user_id != $user_id) {
        createNotification($vendor_user_id, 'order', $title, $message, $link);
    }
    // If admin updates, also notify vendor; if vendor updates, notify admin (user_id 1)
    if (!$isAdmin) {
        // Vendor updated – notify admin
        createNotification(1, 'order', $title, $message, $link);
    } else {
        // Admin updated – also notify all store staff (admins/editors)
        $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = ? AND role IN ('admin','editor')");
        $teamQuery->bind_param("i", $store_id);
        $teamQuery->execute();
        $teamResult = $teamQuery->get_result();
        while ($team = $teamResult->fetch_assoc()) {
            if ($team['user_id'] != $user_id && $team['user_id'] != $vendor_user_id) {
                createNotification($team['user_id'], 'order', $title, $message, $link);
            }
        }
        $teamQuery->close();
    }

    echo json_encode(['success' => true, 'message' => 'Status updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
exit();
?>