<?php
/**
 * Notification Helper Functions
 * Use these functions to create notifications for users
 */

/**
 * Add a notification for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to notify
 * @param string $type Type: 'order', 'product', 'review', 'system'
 * @param string $title Short title
 * @param string $message Detailed message
 * @return bool Success or failure
 */
function addNotification($conn, $user_id, $type, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $type, $title, $message);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Add notification for store owner (assuming store owner is the user linked to the store)
 * You can pass the store_id to get the owner
 */
function notifyStoreOwner($conn, $store_id, $type, $title, $message) {
    $stmt = $conn->prepare("SELECT user_id FROM stores WHERE id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $stmt->close();
        return addNotification($conn, $user_id, $type, $title, $message);
    }
    $stmt->close();
    return false;
}

/**
 * Get unread notification count for a user (for badge display)
 */
function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}
?>