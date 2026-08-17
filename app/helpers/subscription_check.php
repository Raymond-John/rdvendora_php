<?php
// includes/subscription_check.php

function hasActiveSubscription($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $active = $result->num_rows > 0;
    $stmt->close();
    return $active;
}

function getSubscriptionDetails($conn, $user_id) {
    $stmt = $conn->prepare("SELECT plan, end_date FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();
    $stmt->close();
    return $details;
}

function isStoreActive($conn, $user_id) {
    $stmt = $conn->prepare("SELECT active FROM stores WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return ($row && $row['active'] == 1);
}

function canAccessStore($conn, $user_id) {
    return (isStoreActive($conn, $user_id) && hasActiveSubscription($conn, $user_id));
}

function hasUsedFreePlan($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND amount = 0 LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $used = $result->num_rows > 0;
    $stmt->close();
    return $used;
}

// ========== NEW: Check and send expired subscription notification ==========
function checkAndNotifyExpiredSubscription($conn, $user_id) {
    // Check if user has an expired subscription that hasn't been notified
    $stmt = $conn->prepare("SELECT id, plan, end_date, notification_sent FROM subscriptions WHERE user_id = ? AND status = 'expired' AND (notification_sent IS NULL OR notification_sent = 0) ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscription = $result->fetch_assoc();
    $stmt->close();
    
    if ($subscription) {
        // Send notification email
        $sent = sendExpiredSubscriptionEmail($user_id, $subscription['plan'], $subscription['end_date'], $conn);
        
        if ($sent) {
            // Mark as notified
            $updateStmt = $conn->prepare("UPDATE subscriptions SET notification_sent = 1 WHERE id = ?");
            $updateStmt->bind_param("i", $subscription['id']);
            $updateStmt->execute();
            $updateStmt->close();
            return true;
        }
    }
    return false;
}

// ========== NEW: Send expired subscription email ==========
function sendExpiredSubscriptionEmail($user_id, $plan, $end_date, $conn) {
    // Get user details
    $stmt = $conn->prepare("SELECT email, fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) return false;
    
    $subject = "⚠️ Your Subscription Has Expired – RD Vendora";
    $message = "
        <html>
        <head>
            <title>Subscription Expired</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc2626; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                .btn { display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; }
                .btn:hover { background: #4f46e5; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
                .highlight { background: #fef2f2; padding: 15px; border-left: 4px solid #dc2626; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>⚠️ Subscription Expired</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars($user['fullname']) . "</strong>,</p>
                    
                    <p>Your <strong>" . htmlspecialchars($plan) . "</strong> subscription has expired on <strong>" . date('F j, Y', strtotime($end_date)) . "</strong>.</p>
                    
                    <div class='highlight'>
                        <p><strong>🚫 Your store access has been suspended.</strong></p>
                        <p>To restore full access and continue selling, please renew your subscription immediately.</p>
                    </div>
                    
                    <p><strong>What happens now?</strong></p>
                    <ul>
                        <li>Your store is currently <strong>suspended</strong> and not visible to customers.</li>
                        <li>You cannot manage products, view orders, or access any store features.</li>
                        <li>All your data is <strong>still safe</strong> and will be restored once you renew.</li>
                    </ul>
                    
                    <p style='text-align: center; margin-top: 30px;'>
                        <a href='https://" . $_SERVER['HTTP_HOST'] . "/subscription.php' class='btn'>Renew Subscription Now</a>
                    </p>
                    
                    <p style='margin-top: 20px;'>If you have any questions, please contact our support team.</p>
                    
                    <p>Best regards,<br><strong>RD Vendora Team</strong></p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " RD Vendora. All rights reserved.</p>
                    <p>You received this email because your subscription with RD Vendora has expired.</p>
                </div>
            </div>
        </body>
        </html>
    ";
    $altMessage = strip_tags(str_replace(['<br>','</p>'], ["\n","\n"], $message));
    
    // Try PHPMailer first
    global $phpmailer_available;
    if ($phpmailer_available) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'mrrayjohnson2@gmail.com';
            $mail->Password   = 'tpkt rcnc lgmw wzzp';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('subscriptions@rdvendora.com', 'RD Vendora');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = $altMessage;
            $mail->send();
            error_log("Expired subscription email sent to " . $user['email']);
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer failed for expired subscription: " . $e->getMessage());
        }
    }
    
    // Fallback to mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: subscriptions@rdvendora.com\r\n";
    $sent = @mail($user['email'], $subject, $message, $headers);
    if ($sent) {
        error_log("Expired subscription email sent via mail() to " . $user['email']);
        return true;
    } else {
        error_log("Failed to send expired subscription email to " . $user['email']);
        return false;
    }
}
?>