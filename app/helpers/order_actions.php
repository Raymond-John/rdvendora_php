<?php
/**
 * Order action links (e.g. customer confirms delivery).
 */

if (!function_exists('rdv_order_action_secret')) {
    function rdv_order_action_secret() {
        $secret = trim((string) rdv_env('APP_KEY', ''));
        if ($secret === '') {
            $secret = trim((string) rdv_env('SMTP_PASS', ''));
        }
        if ($secret === '') {
            $secret = 'rdv-order-action-secret';
        }
        return $secret;
    }
}

if (!function_exists('rdv_order_received_token')) {
    function rdv_order_received_token($orderId) {
        $orderId = (int) $orderId;
        return hash_hmac('sha256', 'order-received:' . $orderId, rdv_order_action_secret());
    }
}

if (!function_exists('rdv_order_received_url')) {
    function rdv_order_received_url($orderId) {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return rdv_url('marketplace');
        }
        return rdv_url('order-received', [
            'order' => $orderId,
            'token' => rdv_order_received_token($orderId),
        ]);
    }
}

if (!function_exists('rdv_order_received_token_valid')) {
    function rdv_order_received_token_valid($orderId, $token) {
        $orderId = (int) $orderId;
        $token = (string) $token;
        if ($orderId <= 0 || $token === '') {
            return false;
        }
        return hash_equals(rdv_order_received_token($orderId), $token);
    }
}

if (!function_exists('rdv_get_admin_alert_email')) {
    function rdv_get_admin_alert_email($conn) {
        if (!$conn) {
            return '';
        }
        $keys = ['admin_alert_email', 'admin_email', 'site_email'];
        foreach ($keys as $key) {
            $stmt = $conn->prepare('SELECT setting_value FROM marketplace_settings WHERE setting_key = ? LIMIT 1');
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $email = trim((string) ($row['setting_value'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }
        return '';
    }
}

if (!function_exists('rdv_confirm_order_received')) {
    /**
     * @return array{ok:bool,already:bool,message:string,order?:array}
     */
    function rdv_confirm_order_received($orderId, $token, $conn) {
        $orderId = (int) $orderId;
        if (!$conn) {
            return ['ok' => false, 'already' => false, 'message' => 'Database connection failed.'];
        }
        if (!rdv_order_received_token_valid($orderId, $token)) {
            return ['ok' => false, 'already' => false, 'message' => 'This confirmation link is invalid or has expired.'];
        }

        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'already' => false, 'message' => 'Unable to load order details.'];
        }
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$order) {
            return ['ok' => false, 'already' => false, 'message' => 'Order not found.'];
        }

        $status = strtolower((string) ($order['status'] ?? ''));
        if ($status === 'delivered') {
            return [
                'ok' => true,
                'already' => true,
                'message' => 'You have already confirmed receipt of this order. Thank you!',
                'order' => $order,
            ];
        }

        $upd = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ? LIMIT 1");
        if ($upd) {
            $upd->bind_param('i', $orderId);
            $upd->execute();
            $upd->close();
        }
        $order['status'] = 'delivered';

        if (function_exists('sendOrderDeliveredNotification')) {
            sendOrderDeliveredNotification($order, $conn);
        }

        return [
            'ok' => true,
            'already' => false,
            'message' => 'Thank you! Your delivery confirmation has been recorded and the seller has been notified.',
            'order' => $order,
        ];
    }
}
