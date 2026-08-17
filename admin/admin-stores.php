<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/email_errors.log');

session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/send_approval_email.php';
require_once __DIR__ . '/../includes/subscription_check.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR STORES PAGE ----------
if (!adminHasPermission('stores', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage stores.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// ========== FIX: Update expired subscriptions (date-based) ==========
$updateResult = $conn->query("UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND DATE(end_date) <= CURDATE()");
$updatedCount = $updateResult ? $updateResult->affected_rows : 0;
error_log("Admin stores: updated $updatedCount expired subscriptions");

// ========== IMPROVED EMAIL FUNCTION WITH MULTIPLE FALLBACKS ==========
function sendExpiredSubscriptionEmailImproved($user_id, $plan, $end_date, $conn, $storeInfo = null) {
    // If storeInfo not provided, fetch it
    if (!$storeInfo) {
        $stmt = $conn->prepare("SELECT u.email, u.fullname, s.store_name FROM users u JOIN stores s ON u.id = s.user_id WHERE u.id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $storeInfo = $result->fetch_assoc();
        $stmt->close();
    }
    
    if (!$storeInfo) {
        error_log("sendExpiredSubscriptionEmailImproved: User not found for ID: $user_id");
        return ['success' => false, 'error' => 'User not found'];
    }
    
    $to = $storeInfo['email'];
    $name = $storeInfo['fullname'] ?? 'Valued Customer';
    $storeName = $storeInfo['store_name'] ?? 'your store';
    $year = date('Y');
    $renew_link = 'https://' . $_SERVER['HTTP_HOST'] . '/subscription.php';
    
    $subject = "⚠️ Your Subscription Has Expired – RD Vendora";
    
    // ---------- BUILD HTML VERSION (Royal Blue & Gold) ----------
    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expired</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">

                        <!-- ===== HEADER (Royal Blue) ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
                            <tr>
                                <td style="padding:22px 30px; text-align:center;">
                                    <span style="font-size:24px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px;">RD Vendora</span>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== BODY ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:32px 30px 24px 30px; text-align:center;">

                                    <!-- Icon & Greeting -->
                                    <span style="font-size:48px;">⚠️</span>
                                    <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 4px 0;">Hello ' . $name . ',</h1>
                                    <p style="font-size:16px; color:#64748B; margin:0 0 12px 0; line-height:1.6;">
                                        Your <strong style="color:#1A56DB;">' . $plan . '</strong> subscription expired on <strong>' . date('F j, Y', strtotime($end_date)) . '</strong>.
                                    </p>

                                    <!-- Alert Card -->
                                    <div style="background:#FEF2F2; border-left:6px solid #DC2626; padding:16px 20px; border-radius:8px; margin:16px 0 20px 0; text-align:left;">
                                        <p style="margin:0 0 6px 0; font-size:15px; color:#1E293B;"><strong>🚫 Your store "' . $storeName . '" has been suspended.</strong></p>
                                        <p style="margin:0; font-size:15px; color:#64748B;">
                                            To restore full access and continue selling, please renew your subscription immediately.
                                        </p>
                                    </div>

                                    <!-- What happens now? -->
                                    <div style="background:#F8FAFC; border-radius:12px; padding:16px 20px; margin:16px 0 20px 0; text-align:left;">
                                        <p style="margin:0 0 6px 0; font-size:15px; font-weight:600; color:#1E293B;">What happens now?</p>
                                        <ul style="margin:6px 0 0 0; padding-left:20px; font-size:15px; color:#64748B; line-height:1.7;">
                                            <li>Your store is currently suspended and not visible to customers.</li>
                                            <li>You cannot manage products, view orders, or access any store features.</li>
                                            <li>All your data is still safe and will be restored once you renew.</li>
                                        </ul>
                                    </div>

                                    <!-- CTA Button (Gold) -->
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin:10px 0 24px 0;">
                                        <tr>
                                            <td style="background-color:#D4AF37; border-radius:50px; padding:14px 40px; box-shadow:0 4px 12px rgba(212,175,55,0.25);">
                                                <a href="' . $renew_link . '" style="color:#0A3D91; text-decoration:none; font-weight:700; font-size:16px; display:inline-block;">🔄 Renew Subscription Now</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Support Message -->
                                    <p style="font-size:15px; color:#64748B; margin:0 0 10px 0;">
                                        If you have any questions, please contact our support team.
                                    </p>
                                    <p style="font-size:15px; color:#1A56DB; font-weight:500; margin:0;">– RD Vendora Team</p>

                                    <!-- FOOTER -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">rdvendora.com</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated notification. Please do not reply.</span>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>';

    // ---------- Plain text version (fallback) ----------
    $plainBody = "Hello " . $name . ",\n\n";
    $plainBody .= "Your " . $plan . " subscription expired on " . date('F j, Y', strtotime($end_date)) . ".\n\n";
    $plainBody .= "🚫 Your store \"" . $storeName . "\" has been suspended.\n\n";
    $plainBody .= "To restore full access and continue selling, please renew your subscription immediately.\n\n";
    $plainBody .= "What happens now?\n";
    $plainBody .= "- Your store is currently suspended and not visible to customers.\n";
    $plainBody .= "- You cannot manage products, view orders, or access any store features.\n";
    $plainBody .= "- All your data is still safe and will be restored once you renew.\n\n";
    $plainBody .= "Renew here: " . $renew_link . "\n\n";
    $plainBody .= "If you have any questions, please contact our support team.\n\n";
    $plainBody .= "Best regards,\nRD Vendora Team";

    // ---------- SEND WITH MULTIPART ALTERNATIVE (HTML + plain) ----------
    $boundary = md5(uniqid(time()));
    $headers = "From: subscriptions@rdvendora.com\r\n";
    $headers .= "Reply-To: support@rdvendora.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $plainBody . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--$boundary--";

    error_log("sendExpiredSubscriptionEmailImproved: Attempting to send HTML email to $to");

    $sent = @mail($to, $subject, $message, $headers);

    if ($sent) {
        error_log("sendExpiredSubscriptionEmailImproved: Email sent successfully to $to via mail() (HTML)");
        return ['success' => true, 'method' => 'mail_html'];
    }

    $error = error_get_last();
    error_log("sendExpiredSubscriptionEmailImproved: mail() failed. Error: " . ($error ? $error['message'] : 'Unknown error'));

    // ---------- Fallback to PHPMailer if available ----------
    $phpmailer_loaded = function_exists('rdv_load_phpmailer') ? rdv_load_phpmailer() : false;
    if (!$phpmailer_loaded && file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $phpmailer_loaded = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
    }

    if ($phpmailer_loaded) {
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
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->send();
            error_log("sendExpiredSubscriptionEmailImproved: Email sent successfully to $to via PHPMailer (HTML)");
            return ['success' => true, 'method' => 'phpmailer_html'];
        } catch (Exception $e) {
            error_log("sendExpiredSubscriptionEmailImproved: PHPMailer failed: " . $e->getMessage());
        }
    }

    // All methods failed
    return ['success' => false, 'error' => 'All email methods failed'];
}
// ========== HANDLE ACTIONS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['store_id'])) {
    $store_id = (int)$_POST['store_id'];
    $action = $_POST['action'];
    $newStatus = null;
    $message = '';
    $type = 'success';
    
    switch ($action) {
        case 'approve':
            $newStatus = 'active';
            $message = 'Store approved successfully.';
            break;
        case 'disable':
            $newStatus = 'inactive';
            $message = 'Store disabled successfully.';
            break;
        case 'reactivate':
            $newStatus = 'active';
            $message = 'Store reactivated successfully.';
            break;
        case 'suspend':
            $newStatus = 'inactive';
            $message = 'Store suspended successfully.';
            break;
        case 'send_expiry_email':
            // Send expired subscription email
            $infoStmt = $conn->prepare("SELECT s.user_id, s.store_name, u.email, u.fullname FROM stores s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
            $infoStmt->bind_param("i", $store_id);
            $infoStmt->execute();
            $storeInfo = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();
            
            if ($storeInfo) {
                // Get expired subscription details
                $subStmt = $conn->prepare("SELECT plan, end_date FROM subscriptions WHERE user_id = ? AND status = 'expired' ORDER BY id DESC LIMIT 1");
                $subStmt->bind_param("i", $storeInfo['user_id']);
                $subStmt->execute();
                $subData = $subStmt->get_result()->fetch_assoc();
                $subStmt->close();
                
                if ($subData) {
                    // Log the attempt
                    error_log("Attempting to send expiry email to: " . $storeInfo['email'] . " for plan: " . $subData['plan']);
                    
                    // Send the email
                    $result = sendExpiredSubscriptionEmailImproved($storeInfo['user_id'], $subData['plan'], $subData['end_date'], $conn, $storeInfo);
                    
                    if ($result['success']) {
                        // Mark as notified
                        $updateStmt = $conn->prepare("UPDATE subscriptions SET notification_sent = 1 WHERE user_id = ? AND status = 'expired'");
                        $updateStmt->bind_param("i", $storeInfo['user_id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        $message = "✅ Email sent to " . htmlspecialchars($storeInfo['email']) . " (via " . $result['method'] . ")";
                        error_log("✅ Email sent successfully to: " . $storeInfo['email'] . " via " . $result['method']);
                    } else {
                        $message = "❌ Failed to send email. Error: " . ($result['error'] ?? 'Unknown error');
                        $type = 'error';
                        error_log("❌ Failed to send email to: " . $storeInfo['email']);
                    }
                } else {
                    $message = "No expired subscription found for this store.";
                    $type = 'warning';
                }
            } else {
                $message = "Store not found.";
                $type = 'error';
            }
            break;
        case 'delete':
            // Delete store logic...
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT user_id FROM stores WHERE id = ?");
                $stmt->bind_param("i", $store_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $storeData = $res->fetch_assoc();
                $stmt->close();
                
                if (!$storeData) {
                    throw new Exception("Store not found.");
                }
                $user_id = $storeData['user_id'];
                
                $conn->query("DELETE oi FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE o.store_id = $store_id");
                $conn->query("DELETE FROM orders WHERE store_id = $store_id");
                $conn->query("DELETE FROM products WHERE user_id = $user_id");
                $conn->query("DELETE FROM stores WHERE id = $store_id");
                
                $conn->commit();
                $message = "Store and all its data permanently deleted.";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Deletion failed: " . $e->getMessage();
                $type = 'error';
            }
            break;
        default:
            $message = 'Invalid action.';
            $type = 'error';
    }
    
    if ($newStatus && $action !== 'delete' && $action !== 'send_expiry_email') {
        $stmt = $conn->prepare("UPDATE stores SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $store_id);
        if ($stmt->execute()) {
            if ($action === 'approve') {
                $infoStmt = $conn->prepare("SELECT user_id, store_name FROM stores WHERE id = ?");
                $infoStmt->bind_param("i", $store_id);
                $infoStmt->execute();
                $storeInfo = $infoStmt->get_result()->fetch_assoc();
                $infoStmt->close();
                if ($storeInfo) {
                    sendStoreApprovalEmail($storeInfo['user_id'], $storeInfo['store_name']);
                }
            }
        } else {
            $message = 'Database error: ' . $conn->error;
            $type = 'error';
        }
        $stmt->close();
    }
    
    $filterParam = isset($_GET['filter']) ? '&filter=' . urlencode($_GET['filter']) : '';
    header("Location: admin-stores.php?message=" . urlencode($message) . "&type=" . $type . $filterParam);
    exit;
}

// ========== GET MESSAGE FROM REDIRECT ==========
$message = isset($_GET['message']) ? $_GET['message'] : '';
$messageType = isset($_GET['type']) ? $_GET['type'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build WHERE clause for filters
$whereClause = "";
if ($filter === 'pending') {
    $whereClause = "WHERE s.status = 'pending'";
} elseif ($filter === 'active') {
    $whereClause = "WHERE s.status = 'active'";
} elseif ($filter === 'inactive') {
    $whereClause = "WHERE s.status = 'inactive'";
} elseif ($filter === 'expired') {
    // Expired filter: stores with no active subscription AND (subscription status='expired' OR end_date <= today)
    $whereClause = "WHERE s.status = 'active' AND NOT EXISTS (
        SELECT 1 FROM subscriptions WHERE user_id = s.user_id AND status = 'active' AND end_date > NOW()
    ) AND EXISTS (
        SELECT 1 FROM subscriptions WHERE user_id = s.user_id AND (status = 'expired' OR (status = 'active' AND DATE(end_date) <= CURDATE())) AND status != 'cancelled'
    )";
} elseif ($filter === 'no_subscription') {
    $whereClause = "WHERE s.id NOT IN (SELECT user_id FROM subscriptions WHERE status = 'active' AND end_date > NOW()) AND NOT EXISTS (SELECT 1 FROM subscriptions WHERE user_id = s.user_id AND (status = 'expired' OR (status = 'active' AND DATE(end_date) <= CURDATE())))";
} elseif ($filter === 'trial_expiring') {
    $whereClause = "WHERE s.id IN (
        SELECT user_id FROM subscriptions 
        WHERE plan = 'Launch' AND status = 'active' AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
    )";
}

// ========== QUERY WITH DATE-BASED EXPIRED DETECTION ==========
$stores = [];
$query = "
    SELECT 
        s.*,
        u.email as owner_email,
        u.full_name as owner_name,
        -- Active subscription details using correlated subqueries (per store)
        (SELECT plan FROM subscriptions WHERE user_id = s.user_id AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1) as subscription_plan,
        (SELECT end_date FROM subscriptions WHERE user_id = s.user_id AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1) as subscription_end_date,
        -- Expired flag: no active subscription AND at least one subscription that has expired by date
        CASE 
            WHEN NOT EXISTS (
                SELECT 1 FROM subscriptions a2 
                WHERE a2.user_id = s.user_id 
                  AND a2.status = 'active' 
                  AND a2.end_date > NOW()
            ) AND EXISTS (
                SELECT 1 FROM subscriptions e 
                WHERE e.user_id = s.user_id 
                  AND (e.status = 'expired' OR (e.status = 'active' AND DATE(e.end_date) <= CURDATE()))
                  AND e.status != 'cancelled'
            ) THEN 1
            ELSE NULL
        END as has_expired
    FROM stores s
    LEFT JOIN users u ON s.user_id = u.id
    $whereClause
    ORDER BY s.created_at DESC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Get product count
        $prodStmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE user_id = ?");
        $prodStmt->bind_param("i", $row['user_id']);
        $prodStmt->execute();
        $prodCount = $prodStmt->get_result()->fetch_assoc();
        $row['product_count'] = $prodCount['count'] ?? 0;
        $prodStmt->close();
        
        // Calculate trial remaining days if applicable
        $row['trial_remaining_days'] = null;
        if ($row['subscription_plan'] === 'Launch' && $row['subscription_end_date']) {
            $now = new DateTime();
            $end = new DateTime($row['subscription_end_date']);
            $diff = $now->diff($end);
            $row['trial_remaining_days'] = $diff->invert ? 0 : $diff->days;
        }
        
        // Check if notification was sent for expired subscription (only if there is an expired record)
        $row['notification_sent'] = false;
        if ($row['has_expired']) {
            // Try to find a subscription with status='expired' for this user (or any expired record)
            $notifStmt = $conn->prepare("SELECT notification_sent FROM subscriptions WHERE user_id = ? AND status = 'expired' ORDER BY id DESC LIMIT 1");
            $notifStmt->bind_param("i", $row['user_id']);
            $notifStmt->execute();
            $notifResult = $notifStmt->get_result();
            if ($notifRow = $notifResult->fetch_assoc()) {
                $row['notification_sent'] = (bool)$notifRow['notification_sent'];
            }
            $notifStmt->close();
        }
        
        $stores[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stores - RD Vendora Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ====== ALL STYLES (complete) ====== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #eef2ff;
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-primary: #e2e8f0;
            --border-secondary: #cbd5e1;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --error: #ef4444;
            --error-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition: all 0.3s ease;
            --sidebar-width: 260px;
            --sidebar-collapsed: 72px;
            --topbar-height: 64px;
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
        }
        [data-theme="dark"] {
            --bg-primary: #0c0e14;
            --bg-secondary: #14161f;
            --bg-tertiary: #1a1d28;
            --text-primary: #e8eaf0;
            --text-secondary: #9ca3b0;
            --text-muted: #6b7280;
            --border-primary: #2d3139;
            --border-secondary: #3a3f4a;
            --primary-light: rgba(99,102,241,0.15);
            --success-light: #064e3b;
            --warning-light: #451a03;
            --error-light: #7f1d1d;
            --info-light: #1e3a8a;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.3);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.4), 0 1px 2px -1px rgb(0 0 0 / 0.4);
            --shadow-md: 0 4px 12px 0 rgb(0 0 0 / 0.4), 0 2px 4px 0 rgb(0 0 0 / 0.3);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.4), 0 4px 6px -4px rgb(0 0 0 / 0.4);
        }
        body {
            font-family: var(--font-sans);
            font-size: 0.9375rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            transition: background 0.2s, color 0.2s;
            overflow-x: hidden;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; }
        .sidebar {
            position: fixed; left:0; top:0; bottom:0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: 300;
            transition: width var(--transition), transform var(--transition);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.25rem;
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 0.75rem;
            font-weight: 800; font-size: 1.125rem;
            white-space: nowrap;
        }
        .nav-logo-icon {
            width: 32px; height: 32px;
            background: var(--gradient-primary);
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            color: white;
        }
        .sidebar-toggle {
            width: 28px; height: 28px;
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted);
        }
        .sidebar-toggle:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .sidebar-menu {
            flex: 1; overflow-y: auto; padding: 1rem 0.75rem;
        }
        .sidebar-section-title {
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
            color: var(--text-muted); padding: 0.5rem 1rem; letter-spacing: 0.5px;
        }
        .sidebar-item {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.6rem 1rem; border-radius: var(--radius);
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            transition: var(--transition); margin-bottom: 2px;
            cursor: pointer;
        }
        .sidebar-item:hover, .sidebar-item.active {
            background: var(--primary-light); color: var(--primary);
        }
        .sidebar.collapsed .sidebar-item span,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .nav-logo span {
            opacity: 0; width: 0; overflow: hidden;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition);
            min-height: 100vh;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        .dash-navbar {
            position: fixed; top:0; right:0; left: var(--sidebar-width);
            height: var(--topbar-height);
            background: var(--bg-secondary);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 2rem;
            z-index: 200;
            transition: left var(--transition);
        }
        .dash-search {
            display: flex; align-items: center; gap: 0.5rem;
            background: var(--bg-tertiary);
            padding: 0.4rem 1rem;
            border-radius: var(--radius-lg);
            width: 280px;
        }
        .dash-search input {
            background: none; border: none; outline: none;
            font-size: 0.875rem;
            width: 100%;
            color: var(--text-primary);
        }
        .dash-actions { display: flex; align-items: center; gap: 1rem; }
        .dash-btn {
            width: 38px; height: 38px;
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
        }
        .dash-user {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.25rem 0.5rem 0.25rem 0.25rem;
            border-radius: var(--radius-lg);
            cursor: pointer;
        }
        .dash-user img {
            width: 32px; height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        .dash-user-info .name { font-size: 0.875rem; font-weight: 500; }
        .dash-user-info .role { font-size: 0.7rem; color: var(--text-muted); }
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0;
            min-width: 180px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            opacity: 0; pointer-events: none; transform: translateY(-8px);
            transition: var(--transition);
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-item {
            display: flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1rem; font-size: 0.875rem;
            color: var(--text-secondary);
        }
        .dropdown-item:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .page-header {
            padding: 1.5rem 2rem 0.5rem 2rem;
            margin-top: var(--topbar-height);
        }
        .page-title {
            font-size: 1.875rem;
            font-weight: 800;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .alert {
            margin: 1rem 2rem 0;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
        }
        .alert-success { background: var(--success-light); color: #10b981; border: 1px solid var(--border-primary); }
        .alert-error { background: var(--error-light); color: #ef4444; border: 1px solid var(--border-primary); }
        .alert-warning { background: var(--warning-light); color: #f59e0b; border: 1px solid var(--border-primary); }
        .filter-tabs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 2rem 0;
            flex-wrap: wrap;
        }
        .filter-tabs .filter-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            flex: 1;
        }
        .filter-tabs .view-toggle {
            display: flex;
            gap: 0.25rem;
            background: var(--bg-tertiary);
            padding: 0.25rem;
            border-radius: var(--radius-lg);
            margin-left: auto;
        }
        .filter-tabs .view-toggle button {
            padding: 0.4rem 0.7rem;
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: transparent;
            color: var(--text-secondary);
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        .filter-tabs .view-toggle button.active {
            background: var(--bg-secondary);
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
        .filter-tabs .view-toggle button:hover:not(.active) {
            background: var(--bg-hover);
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        .filter-btn.active {
            background: var(--primary);
            color: white;
        }
        .filter-btn:hover { background: var(--primary-light); color: var(--primary); }
        .stores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 2rem;
            transition: all 0.3s ease;
        }
        .stores-grid.list-view {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .stores-grid.list-view .store-card {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            width: 100%;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        .stores-grid.list-view .store-card .card-header {
            flex: 0 0 250px;
            border-bottom: none;
            border-right: 1px solid var(--border-primary);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stores-grid.list-view .store-card .card-body {
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1.5rem;
            padding: 1rem 1.25rem;
            border: none;
        }
        .stores-grid.list-view .store-card .card-body .info-row {
            flex: 0 0 auto;
            margin-bottom: 0;
            border: none;
            padding: 0;
        }
        .stores-grid.list-view .store-card .card-actions {
            flex: 0 0 auto;
            border-top: none;
            border-left: 1px solid var(--border-primary);
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.5rem;
            min-width: 140px;
        }
        .stores-grid.list-view .store-card .card-actions .btn-sm {
            width: 100%;
        }
        .stores-grid.list-view .store-card .store-avatar {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        .stores-grid.list-view .store-card .store-info h3 {
            font-size: 0.95rem;
        }
        @media (max-width: 768px) {
            .stores-grid.list-view .store-card {
                flex-direction: column;
            }
            .stores-grid.list-view .store-card .card-header {
                flex: none;
                border-right: none;
                border-bottom: 1px solid var(--border-primary);
            }
            .stores-grid.list-view .store-card .card-body {
                flex-direction: column;
                align-items: stretch;
            }
            .stores-grid.list-view .store-card .card-actions {
                flex-direction: row;
                flex-wrap: wrap;
                border-left: none;
                border-top: 1px solid var(--border-primary);
                justify-content: flex-start;
            }
            .stores-grid.list-view .store-card .card-actions .btn-sm {
                width: auto;
            }
            .filter-tabs .view-toggle {
                margin-left: 0;
                width: 100%;
                justify-content: center;
            }
            .filter-tabs {
                flex-direction: column;
                align-items: stretch;
            }
        }
        .store-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            transition: all 0.2s;
            overflow: hidden;
        }
        .store-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .card-header {
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--border-primary);
        }
        .store-avatar {
            width: 48px; height: 48px;
            background: var(--gradient-primary);
            border-radius: 1rem;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; font-weight: 700; color: white; flex-shrink: 0;
        }
        .store-info h3 { font-size: 1rem; font-weight: 700; }
        .store-slug { font-size: 0.7rem; color: var(--text-muted); }
        .card-body { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
        .info-label { color: var(--text-muted); }
        .badge {
            padding: 0.2rem 0.6rem; border-radius: 2rem; font-size: 0.7rem; font-weight: 600;
        }
        .badge-active { background: var(--success-light); color: #10b981; }
        .badge-pending { background: var(--warning-light); color: #f59e0b; }
        .badge-inactive { background: var(--error-light); color: #ef4444; }
        .badge-expired { background: var(--error-light); color: #dc2626; }
        .badge-warning { background: var(--warning-light); color: #f59e0b; }
        .badge-info { background: var(--info-light); color: #3b82f6; }
        .card-actions {
            padding: 1rem 1.25rem; border-top: 1px solid var(--border-primary);
            display: flex; gap: 0.5rem; flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.375rem 1rem; border-radius: var(--radius);
            font-size: 0.75rem; font-weight: 500;
            background: var(--bg-tertiary); color: var(--text-secondary);
            transition: var(--transition); cursor: pointer; border: none;
        }
        .btn-sm:hover { background: var(--primary); color: white; transform: translateY(-1px); }
        .btn-approve { background: var(--success-light); color: #10b981; }
        .btn-approve:hover { background: #10b981; color: white; }
        .btn-disable { background: var(--warning-light); color: #f59e0b; }
        .btn-disable:hover { background: #f59e0b; color: white; }
        .btn-suspend { background: var(--error-light); color: #ef4444; }
        .btn-suspend:hover { background: #ef4444; color: white; }
        .btn-delete { background: var(--error-light); color: #ef4444; }
        .btn-delete:hover { background: #dc2626; color: white; }
        .btn-email { background: var(--info-light); color: #3b82f6; }
        .btn-email:hover { background: #3b82f6; color: white; }
        .btn-email.sent { background: var(--success-light); color: #10b981; }
        .btn-email.sent:hover { background: #10b981; color: white; }
        .modal-overlay {
            position: fixed; inset:0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 1000; display: flex; align-items: center; justify-content: center;
            visibility: hidden; opacity: 0; transition: var(--transition);
        }
        .modal-overlay.active { visibility: visible; opacity: 1; }
        .modal-container {
            background: var(--bg-secondary); border-radius: var(--radius-xl);
            max-width: 500px; width: 90%; padding: 1.5rem;
            box-shadow: var(--shadow-lg); transform: scale(0.95);
            transition: transform var(--transition);
        }
        .modal-overlay.active .modal-container { transform: scale(1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-title { font-size: 1.25rem; font-weight: 700; }
        .modal-close { cursor: pointer; font-size: 1.5rem; line-height: 1; color: var(--text-muted); }
        .modal-body { margin-bottom: 1.5rem; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; }
        .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
        .mobile-sidebar-toggle { display: none; }
        .sidebar-overlay {
            position: fixed; inset:0; background: rgba(0,0,0,0.5);
            z-index:299; display: none; backdrop-filter: blur(4px);
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .dash-navbar { left: 0; padding: 0 1rem; }
            .dash-search { width: 200px; }
            .mobile-sidebar-toggle { display: flex; }
            .filter-tabs, .alert { margin: 1rem; }
            .stores-grid { margin: 1rem; grid-template-columns: 1fr; }
            .page-header { padding: 1rem; }
            .stores-grid.list-view .store-card .card-header {
                flex: none; border-right: none; border-bottom: 1px solid var(--border-primary);
            }
            .stores-grid.list-view .store-card .card-body {
                flex-direction: column; align-items: stretch;
            }
            .stores-grid.list-view .store-card .card-actions {
                flex-direction: row; flex-wrap: wrap;
                border-left: none; border-top: 1px solid var(--border-primary);
                justify-content: flex-start;
            }
            .stores-grid.list-view .store-card .card-actions .btn-sm {
                width: auto;
            }
            .filter-tabs .view-toggle {
                margin-left: 0; width: 100%; justify-content: center;
            }
            .filter-tabs { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="nav-logo">
            <div class="nav-logo-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
            <span>RD Vendora</span>
        </a>
        <button class="sidebar-toggle" id="sidebarToggle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6"/></svg></button>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-section-title">Platform</div>
        <a href="admin.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></a>
        <a href="admin-users.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Users</span></a>
        <a href="admin-stores.php" class="sidebar-item active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg><span>Stores</span></a>
        <a href="admin-pricing.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pricing Plans</span></a>
        <a href="admin-testimonies.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span>Testimonials</span></a>
        <a href="admin-contacts.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><span>Contact Messages</span></a>
        <a href="admin-about.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>About Page</span></a>
        <a href="admin-chat.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span>Chat</span></a>
        <a href="admin-receive-order.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>All Orders</span></a>
        <a href="admin-transport.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span>Transport Orders</span></a>
        <div class="sidebar-section-title">System</div>
        <a href="../dashboard.php" class="sidebar-item"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Back to Store</span></a>
        <a href="#" class="sidebar-item" onclick="logout()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <header class="dash-navbar">
        <button class="dash-btn mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="dash-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="searchInput" placeholder="Search store or owner..."></div>
        <div class="dash-actions">
            <button class="theme-toggle dash-btn" id="themeToggle">🌙</button>
            <div class="dropdown" id="userDropdown">
                <div class="dash-user dropdown-trigger"><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%236366f1'/%3E%3Ctext x='50' y='67' text-anchor='middle' fill='white' font-size='40' font-family='Arial'%3EA%3C/text%3E%3C/svg%3E" alt="Admin"><div class="dash-user-info"><div class="name">Platform Admin</div><div class="role">Super Admin</div></div><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg></div>
                <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="logout()">Logout</a></div>
            </div>
        </div>
    </header>

    <div class="page-header">
        <h1 class="page-title">Stores</h1>
        <p class="page-subtitle">Manage all stores on the RD Vendora platform</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) === 'error' ? 'error' : (htmlspecialchars($messageType) === 'warning' ? 'warning' : 'success') ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- DEBUG: Show how many expired subscriptions were updated -->
    <div style="background: #e0f2fe; padding: 8px 16px; margin: 0 2rem; border-radius: 8px; font-size: 0.9rem; color: #0369a1; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <span>🔄 <strong><?= $updatedCount ?></strong> expired subscription(s) updated.</span>
        <span style="font-size: 0.8rem; color: #0284c7;">(Debug message – you can remove this later.)</span>
    </div>

    <div class="filter-tabs">
        <div class="filter-group">
            <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All Stores</a>
            <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending Approval</a>
            <a href="?filter=active" class="filter-btn <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="?filter=inactive" class="filter-btn <?= $filter === 'inactive' ? 'active' : '' ?>">Disabled</a>
            <a href="?filter=expired" class="filter-btn <?= $filter === 'expired' ? 'active' : '' ?>">⚠️ Expired</a>
            <a href="?filter=no_subscription" class="filter-btn <?= $filter === 'no_subscription' ? 'active' : '' ?>">No Active Subscription</a>
            <a href="?filter=trial_expiring" class="filter-btn <?= $filter === 'trial_expiring' ? 'active' : '' ?>">Trial Expiring (≤3 days)</a>
        </div>
        <!-- View Toggle -->
        <div class="view-toggle" id="viewToggle">
            <button class="active" data-view="grid" title="Grid View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </button>
            <button data-view="list" title="List View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
        </div>
    </div>

    <?php if (empty($stores)): ?>
        <div class="empty-state">🏪 No stores found matching the filter.</div>
    <?php else: ?>
        <div class="stores-grid" id="storesGrid">
            <?php foreach ($stores as $store): 
                $hasSubscription = !empty($store['subscription_plan']);
                $planDisplay = $hasSubscription ? htmlspecialchars($store['subscription_plan']) : 'No active plan';
                $expiryDisplay = $hasSubscription ? date('M d, Y', strtotime($store['subscription_end_date'])) : '—';
                $trialNote = ($store['subscription_plan'] === 'Launch') ? ' <small>(14‑day trial)</small>' : '';
                $status = $store['status'] ?? 'pending';
                
                $hasExpired = !empty($store['has_expired']);
                $notificationSent = $store['notification_sent'] ?? false;
                
                $badgeClass = $status === 'active' ? 'badge-active' : ($status === 'pending' ? 'badge-pending' : 'badge-inactive');
                $trialWarning = ($store['subscription_plan'] === 'Launch' && $store['trial_remaining_days'] !== null && $store['trial_remaining_days'] <= 3 && $store['trial_remaining_days'] > 0);
                
                if ($hasExpired) {
                    $badgeClass = 'badge-expired';
                }
            ?>
                <div class="store-card" data-store-name="<?= strtolower(htmlspecialchars($store['store_name'])) ?>" data-owner-name="<?= strtolower(htmlspecialchars($store['owner_name'] ?? '')) ?>">
                    <div class="card-header">
                        <div class="store-avatar"><?= strtoupper(substr($store['store_name'], 0, 1)) ?></div>
                        <div class="store-info">
                            <h3><?= htmlspecialchars($store['store_name']) ?>
                                <?php if ($hasExpired): ?>
                                    <span class="badge badge-expired">⚠️ Expired</span>
                                <?php endif; ?>
                            </h3>
                            <div class="store-slug"><?= htmlspecialchars($store['store_slug'] ?? 'store') ?>.RD Vendora.com</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><span class="info-label">Owner</span><span><strong><?= htmlspecialchars($store['owner_name'] ?? 'Unknown') ?></strong><br><small><?= htmlspecialchars($store['owner_email'] ?? '') ?></small></span></div>
                        <div class="info-row"><span class="info-label">Status</span><span class="badge <?= $badgeClass ?>"><?= $hasExpired ? 'Expired' : ucfirst($status) ?></span></div>
                        <div class="info-row"><span class="info-label">Plan</span><span><?= $planDisplay ?><?php if ($trialWarning): ?> <span class="badge badge-warning">⚠️ <?= $store['trial_remaining_days'] ?> day(s) left</span><?php endif; ?></span></div>
                        <div class="info-row"><span class="info-label">Expiry</span><span><?= $expiryDisplay . $trialNote ?></span></div>
                        <div class="info-row"><span class="info-label">Products</span><span><?= number_format($store['product_count']) ?></span></div>
                        <?php if ($hasExpired): ?>
                            <div class="info-row"><span class="info-label">Notification</span><span><?= $notificationSent ? '✅ Sent' : '❌ Not sent' ?></span></div>
                        <?php endif; ?>
                        <!-- Expired banner REMOVED as requested -->
                    </div>
                    <div class="card-actions">
                        <?php if ($status === 'pending'): ?>
                            <form method="POST" style="display: inline;"><input type="hidden" name="store_id" value="<?= $store['id'] ?>"><input type="hidden" name="action" value="approve"><button type="submit" class="btn-sm btn-approve" onclick="return confirm('Approve this store?')">✓ Approve</button></form>
                        <?php elseif ($status === 'active'): ?>
                            <form method="POST" style="display: inline;"><input type="hidden" name="store_id" value="<?= $store['id'] ?>"><input type="hidden" name="action" value="suspend"><button type="submit" class="btn-sm btn-suspend" onclick="return confirm('Suspend this store? Store will be disabled until you reactivate.')">⛔ Suspend</button></form>
                            <form method="POST" style="display: inline;"><input type="hidden" name="store_id" value="<?= $store['id'] ?>"><input type="hidden" name="action" value="disable"><button type="submit" class="btn-sm btn-disable" onclick="return confirm('Disable this store? Store will be hidden but data remains.')">🔒 Disable</button></form>
                        <?php elseif ($status === 'inactive'): ?>
                            <form method="POST" style="display: inline;"><input type="hidden" name="store_id" value="<?= $store['id'] ?>"><input type="hidden" name="action" value="reactivate"><button type="submit" class="btn-sm btn-approve" onclick="return confirm('Reactivate this store?')">✓ Reactivate</button></form>
                        <?php endif; ?>
                        
                        <?php if ($hasExpired): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
                                <input type="hidden" name="action" value="send_expiry_email">
                                <button type="submit" class="btn-sm btn-email <?= $notificationSent ? 'sent' : '' ?>" onclick="return confirm('Send expiry notification email to store owner?')">
                                    <?= $notificationSent ? '✅ Re-send Email' : '📧 Send Expiry Email' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirmDeleteStore('<?= addslashes($store['store_name']) ?>')">
                            <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-sm btn-delete">🗑️ Delete</button>
                        </form>
                        <button class="btn-sm" onclick="viewStoreDetails(<?= htmlspecialchars(json_encode($store)) ?>)">View Details</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div id="storeModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header"><h3 class="modal-title">Store Details</h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer"><button class="btn-sm" onclick="closeModal()">Close</button></div>
    </div>
</div>

<script>
    // Dark mode toggle
    (function() {
        const html = document.documentElement;
        const themeBtn = document.getElementById('themeToggle');
        if (!themeBtn) return;
        
        const savedTheme = localStorage.getItem('RD Vendora_admin_theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeBtn.textContent = savedTheme === 'light' ? '🌙' : '☀️';
        
        themeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const current = html.getAttribute('data-theme');
            const newTheme = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('RD Vendora_admin_theme', newTheme);
            themeBtn.textContent = newTheme === 'light' ? '🌙' : '☀️';
        });
    })();

    // View Toggle (Grid / List)
    (function() {
        const container = document.getElementById('storesGrid');
        if (!container) return;
        const buttons = document.querySelectorAll('#viewToggle button');
        const viewKey = 'admin_stores_view';

        // Load saved preference
        const savedView = localStorage.getItem(viewKey) || 'grid';
        setView(savedView);

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const view = this.dataset.view;
                setView(view);
                localStorage.setItem(viewKey, view);
            });
        });

        function setView(view) {
            buttons.forEach(b => b.classList.toggle('active', b.dataset.view === view));
            container.classList.toggle('list-view', view === 'list');
        }
    })();

    function confirmDeleteStore(storeName) {
        const confirmation = prompt(`⚠️ PERMANENT DELETION ⚠️\n\nStore: "${storeName}"\nThis will delete ALL products, orders, and order items for this store.\nThis action CANNOT be undone.\n\nType "DELETE" to confirm:`);
        if (confirmation !== 'DELETE') {
            alert('Deletion cancelled.');
            return false;
        }
        return true;
    }

    // Sidebar
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    function closeMobile() { sidebar.classList.remove('mobile-open'); overlay.style.display = 'none'; document.body.style.overflow = ''; }
    function openMobile() { sidebar.classList.add('mobile-open'); overlay.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) { if (sidebar.classList.contains('mobile-open')) closeMobile(); else openMobile(); }
            else sidebar.classList.toggle('collapsed');
        });
    }
    if (mobileToggle) mobileToggle.addEventListener('click', openMobile);
    overlay.addEventListener('click', closeMobile);
    window.addEventListener('resize', () => { if (window.innerWidth > 768) { closeMobile(); sidebar.classList.remove('collapsed'); } });

    // Dropdown
    const userDD = document.getElementById('userDropdown');
    if (userDD) {
        const trigger = userDD.querySelector('.dropdown-trigger');
        trigger.addEventListener('click', (e) => { e.stopPropagation(); userDD.classList.toggle('open'); });
        document.addEventListener('click', () => userDD.classList.remove('open'));
    }

    // Search
    const searchInput = document.getElementById('searchInput');
    const cards = document.querySelectorAll('.store-card');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            cards.forEach(card => {
                const storeName = card.getAttribute('data-store-name') || '';
                const ownerName = card.getAttribute('data-owner-name') || '';
                card.style.display = (storeName.includes(term) || ownerName.includes(term)) ? '' : 'none';
            });
        });
    }

    // Modal
    const modal = document.getElementById('storeModal');
    function viewStoreDetails(store) {
        const modalBody = document.getElementById('modalBody');
        const hasExpired = store.has_expired ? true : false;
        let statusBadge = '';
        if (hasExpired) {
            statusBadge = '<span class="badge badge-expired">⚠️ Expired</span>';
        } else if (store.status === 'active') {
            statusBadge = '<span class="badge badge-active">Active</span>';
        } else if (store.status === 'pending') {
            statusBadge = '<span class="badge badge-pending">Pending</span>';
        } else {
            statusBadge = '<span class="badge badge-inactive">Disabled</span>';
        }
        
        let trialInfo = '';
        if (store.subscription_plan === 'Launch' && store.trial_remaining_days !== null && store.trial_remaining_days > 0) {
            trialInfo = `<div><strong>Trial Remaining:</strong> ${store.trial_remaining_days} day(s) (14‑day trial)</div>`;
        } else if (store.subscription_plan === 'Launch') {
            trialInfo = `<div><strong>Plan:</strong> 14‑day free trial</div>`;
        }
        
        let notificationInfo = '';
        if (hasExpired) {
            notificationInfo = `<div><strong>Expiry Notification:</strong> ${store.notification_sent ? '✅ Sent' : '❌ Not sent'}</div>`;
        }
        
        modalBody.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div><strong>Store Name:</strong> ${escapeHtml(store.store_name)}</div>
                <div><strong>Owner:</strong> ${escapeHtml(store.owner_name || 'Unknown')} (${escapeHtml(store.owner_email || '')})</div>
                <div><strong>Subdomain:</strong> ${escapeHtml(store.store_slug || 'store')}.RD Vendora.com</div>
                <div><strong>Status:</strong> ${statusBadge}</div>
                <div><strong>Subscription Plan:</strong> ${store.subscription_plan ? escapeHtml(store.subscription_plan) : 'No active plan'}</div>
                <div><strong>Expiry Date:</strong> ${store.subscription_end_date ? new Date(store.subscription_end_date).toLocaleDateString() : '—'}</div>
                ${trialInfo}
                ${notificationInfo}
                <div><strong>Products:</strong> ${store.product_count}</div>
                <div><strong>Created:</strong> ${new Date(store.created_at).toLocaleDateString()}</div>
            </div>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-primary);">
                <a href="storefront.php?store=${store.id}" target="_blank" class="btn-sm">Visit Store →</a>
            </div>
        `;
        modal.classList.add('active');
    }
    function closeModal() { modal.classList.remove('active'); }
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    function escapeHtml(str) { if (!str) return ''; return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); }
    function logout() { if(confirm('Logout from admin panel?')) window.location.href='../logout.php'; }
</script>
</body>
</html>