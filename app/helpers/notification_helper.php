<?php
// includes/notification_helper.php

function createNotification($user_id, $type, $title, $message, $link = null) {
    global $conn; // assuming $conn is available
    
    // Validate type
    $allowed_types = ['order', 'product', 'customer', 'subscription', 'system', 'stock'];
    if (!in_array($type, $allowed_types)) {
        $type = 'system';
    }
    
    $title = trim($title);
    $message = trim($message);
    if (empty($title) || empty($message)) {
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
?>