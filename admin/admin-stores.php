<?php
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
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR STORES PAGE ----------
if (!adminHasPermission('stores', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage stores.</p><a href="admin">Go to Dashboard</a></div>');
}

// ========== FIX: Update expired subscriptions (date-based) ==========
$updateResult = $conn->query("UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND DATE(end_date) <= CURDATE()");
$updatedCount = $updateResult ? $updateResult->affected_rows : 0;
error_log("Admin stores: updated $updatedCount expired subscriptions");

// ========== IMPROVED EMAIL FUNCTION WITH MULTIPLE FALLBACKS ==========
function sendExpiredSubscriptionEmailImproved($user_id, $plan, $end_date, $conn, $storeInfo = null) {
    // If storeInfo not provided, fetch it
    if (!$storeInfo) {
        $stmt = $conn->prepare("SELECT u.email, u.full_name AS name, s.store_name FROM users u JOIN stores s ON u.id = s.user_id WHERE u.id = ? LIMIT 1");
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
            if (!function_exists('rdv_smtp_settings')) {
                require_once APP_PATH . '/helpers/smtp_config.php';
            }
            $smtp = rdv_smtp_settings();
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = ($smtp['username'] !== '' && $smtp['password'] !== '');
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = (strtolower((string) $smtp['encryption']) === 'ssl')
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $smtp['port'];
            $mail->setFrom($smtp['from'], $smtp['from_name']);
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
            $infoStmt = $conn->prepare("SELECT s.user_id, s.store_name, u.email, u.full_name AS name FROM stores s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
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
    
    $redirectQs = http_build_query(array_filter([
        'message' => $message,
        'type' => $type,
        'filter' => $_GET['filter'] ?? '',
        'q' => $_GET['q'] ?? '',
        'page' => $_GET['page'] ?? '',
    ], static function ($v) {
        return $v !== '' && $v !== null;
    }));
    header('Location: admin-stores' . ($redirectQs !== '' ? '?' . $redirectQs : ''));
    exit;
}

// ========== GET MESSAGE FROM REDIRECT ==========
$message = isset($_GET['message']) ? $_GET['message'] : '';
$messageType = isset($_GET['type']) ? $_GET['type'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$searchQ = trim((string) ($_GET['q'] ?? ''));
$perPage = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));

$userCols = [];
$colResult = $conn->query('SHOW COLUMNS FROM users');
if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        $userCols[$col['Field']] = true;
    }
}
$ownerNameParts = [];
foreach (['fullname', 'full_name', 'name', 'username'] as $candidate) {
    if (!empty($userCols[$candidate])) {
        $ownerNameParts[] = "NULLIF(u.$candidate, '')";
    }
}
$ownerNameExpr = $ownerNameParts
    ? ('COALESCE(' . implode(', ', $ownerNameParts) . ", u.email, 'Unknown')")
    : "COALESCE(u.email, 'Unknown')";
$ownerSearchParts = ['s.store_name', 's.store_slug', 'u.email'];
foreach (['fullname', 'full_name', 'name', 'username'] as $candidate) {
    if (!empty($userCols[$candidate])) {
        $ownerSearchParts[] = "u.$candidate";
    }
}

$conditions = [];
if ($filter === 'pending') {
    $conditions[] = "s.status = 'pending'";
} elseif ($filter === 'active') {
    $conditions[] = "s.status = 'active'";
} elseif ($filter === 'inactive') {
    $conditions[] = "s.status = 'inactive'";
} elseif ($filter === 'expired') {
    $conditions[] = "s.status = 'active' AND NOT EXISTS (
        SELECT 1 FROM subscriptions WHERE user_id = s.user_id AND status = 'active' AND end_date > NOW()
    ) AND EXISTS (
        SELECT 1 FROM subscriptions WHERE user_id = s.user_id AND (status = 'expired' OR (status = 'active' AND DATE(end_date) <= CURDATE())) AND status != 'cancelled'
    )";
} elseif ($filter === 'no_subscription') {
    $conditions[] = "s.id NOT IN (SELECT user_id FROM subscriptions WHERE status = 'active' AND end_date > NOW()) AND NOT EXISTS (SELECT 1 FROM subscriptions WHERE user_id = s.user_id AND (status = 'expired' OR (status = 'active' AND DATE(end_date) <= CURDATE())))";
} elseif ($filter === 'trial_expiring') {
    $conditions[] = "s.id IN (
        SELECT user_id FROM subscriptions
        WHERE plan = 'Launch' AND status = 'active' AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
    )";
}

if ($searchQ !== '') {
    $like = '%' . $conn->real_escape_string($searchQ) . '%';
    $conditions[] = '(' . implode(' OR ', array_map(static function ($col) use ($like) {
        return "$col LIKE '$like'";
    }, $ownerSearchParts)) . ')';
}

$whereClause = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$storesListQs = static function (array $extra = []) use ($filter, $searchQ) {
    $params = array_merge(['filter' => $filter, 'q' => $searchQ], $extra);
    $params = array_filter($params, static function ($v) {
        return $v !== '' && $v !== null;
    });
    return '?' . http_build_query($params);
};

$countResult = $conn->query("SELECT COUNT(*) AS total FROM stores s LEFT JOIN users u ON s.user_id = u.id $whereClause");
$totalStores = $countResult ? (int) ($countResult->fetch_assoc()['total'] ?? 0) : 0;
$totalPages = max(1, (int) ceil($totalStores / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// ========== QUERY WITH DATE-BASED EXPIRED DETECTION ==========
$stores = [];
$query = "
    SELECT 
        s.*,
        u.email as owner_email,
        $ownerNameExpr as owner_name,
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
    LIMIT $perPage OFFSET $offset
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

        if (trim((string) ($row['owner_name'] ?? '')) === '') {
            $row['owner_name'] = $row['owner_email'] ?: 'Unknown';
        }
        
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

$adminPageTitle = 'Stores - RD Vendora Admin';
$adminPageHeading = 'Stores';
$adminPageSubtitle = $totalStores . ' store' . ($totalStores === 1 ? '' : 's') . ' · page ' . $page . ' of ' . $totalPages;
$adminSearchPlaceholder = 'Search stores...';
$adminShowHeader = true;
$adminPageStyles = <<<'CSS'
.filter-tabs {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0 2rem 1.25rem;
}
.filter-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.filter-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-weight: 600;
}
.filter-btn.active, .filter-btn:hover {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}
.view-toggle {
    display: inline-flex;
    gap: 0.25rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius);
    padding: 0.2rem;
}
.view-toggle button {
    width: 34px; height: 34px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px;
    color: var(--text-secondary);
    background: transparent;
}
.view-toggle button.active { background: var(--primary); color: #fff; }
.stores-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
    padding: 0 2rem 1.5rem;
}
.store-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-xl);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 100%;
}
.store-card .card-header {
    display: flex;
    gap: 0.85rem;
    align-items: center;
    padding: 1.1rem 1.2rem 0.85rem;
}
.store-avatar {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 1.15rem;
    flex-shrink: 0;
}
.store-info h3 {
    margin: 0;
    font-size: 1rem;
    display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;
}
.store-slug { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }
.store-card .card-body { padding: 0.4rem 1.2rem 1rem; flex: 1; }
.info-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid var(--border-primary);
    font-size: 0.82rem;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: var(--text-muted); font-weight: 600; min-width: 90px; }
.store-card .card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.9rem 1.2rem 1.15rem;
    border-top: 1px solid var(--border-primary);
    background: var(--bg-tertiary);
}
.admin-app .stores-grid .btn-sm,
.admin-app .stores-grid button[type="submit"].btn-sm {
    background: var(--bg-secondary);
    color: var(--text-secondary);
    border: 1px solid var(--border-primary);
    padding: 0.4rem 0.7rem;
    font-size: 0.75rem;
}
.admin-app .stores-grid .btn-sm:hover { background: var(--primary-light); color: var(--primary); }
.admin-app .stores-grid .btn-approve { background: var(--success-light); color: var(--success); border-color: transparent; }
.admin-app .stores-grid .btn-suspend { background: var(--warning-light); color: #b45309; border-color: transparent; }
.admin-app .stores-grid .btn-disable { background: var(--bg-secondary); }
.admin-app .stores-grid .btn-delete,
.admin-app .stores-grid .btn-delete:hover { background: var(--error-light); color: var(--error); }
.admin-app .stores-grid .btn-email { background: #e0f2fe; color: #0369a1; }
.admin-app .stores-grid .btn-email.sent { opacity: 0.8; }
.badge-pending { background: var(--warning-light); color: #b45309; }
.badge-expired { background: var(--error-light); color: var(--error); }
.badge-warning { background: var(--warning-light); color: #b45309; }
.stores-grid.list-view { grid-template-columns: 1fr; }
.stores-grid.list-view .store-card {
    display: grid;
    grid-template-columns: minmax(220px, 280px) 1fr auto;
    align-items: stretch;
}
.stores-grid.list-view .card-actions { border-top: none; border-left: 1px solid var(--border-primary); max-width: 280px; }
.stores-grid.list-view .card-body { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0 1rem; }
.stores-grid.list-view .info-row { border-bottom: none; flex-direction: column; gap: 0.2rem; }
.modal-footer { padding: 0.85rem 1.25rem 1.15rem; border-top: 1px solid var(--border-primary); text-align: right; }
.stores-meta { padding: 0 2rem 0.75rem; font-size: 0.8rem; color: var(--text-muted); }
@media (max-width: 900px) {
    .stores-grid.list-view .store-card { grid-template-columns: 1fr; }
    .stores-grid.list-view .card-actions { border-left: none; border-top: 1px solid var(--border-primary); max-width: none; }
}
@media (max-width: 768px) {
    .filter-tabs { padding: 0 1rem 1rem; }
    .stores-meta { padding: 0 1rem 0.75rem; }
    .stores-grid { padding: 0 1rem 1.25rem; }
}
CSS;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) === 'error' ? 'error' : (htmlspecialchars($messageType) === 'warning' ? 'warning' : 'success') ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="filter-tabs">
        <div class="filter-group">
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'all', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">All Stores</a>
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'pending', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending Approval</a>
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'active', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'inactive', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'inactive' ? 'active' : '' ?>">Disabled</a>
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'expired', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'expired' ? 'active' : '' ?>">Expired</a>
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'no_subscription', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'no_subscription' ? 'active' : '' ?>">No Active Subscription</a>
            <a href="<?= htmlspecialchars($storesListQs(['filter' => 'trial_expiring', 'page' => 1])) ?>" class="filter-btn <?= $filter === 'trial_expiring' ? 'active' : '' ?>">Trial Expiring (≤3 days)</a>
        </div>
        <div class="view-toggle" id="viewToggle">
            <button type="button" class="active" data-view="grid" title="Grid View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </button>
            <button type="button" data-view="list" title="List View">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
        </div>
    </div>
    <div class="stores-meta">Showing <?= count($stores) ?> of <?= number_format($totalStores) ?> stores</div>

    <?php if (empty($stores)): ?>
        <div class="empty-state">No stores found matching this filter<?= $searchQ !== '' ? ' or search' : '' ?>.</div>
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
                            <div class="store-slug"><a href="<?= htmlspecialchars(rdv_store_url($store), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(rdv_store_url($store), ENT_QUOTES, 'UTF-8') ?></a></div>
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
                        <a class="btn-sm btn-approve" href="<?= htmlspecialchars(rdv_store_url($store), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Visit Store</a>
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
                        <button type="button" class="btn-sm rdv-admin-json" data-fn="viewStoreDetails" data-payload="<?= admin_json_attr($store) ?>">View Details</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="page-btn" href="<?= htmlspecialchars($storesListQs(['page' => $page - 1])) ?>">Previous</a>
            <?php endif; ?>
            <?php
            $window = 2;
            $started = false;
            for ($i = 1; $i <= $totalPages; $i++):
                $show = $i === 1 || $i === $totalPages || abs($i - $page) <= $window;
                if (!$show) {
                    if ($started) {
                        echo '<span class="page-btn" style="pointer-events:none;opacity:.6">…</span>';
                        $started = false;
                    }
                    continue;
                }
                $started = true;
                if ($i === $page): ?>
                    <span class="page-btn active"><?= $i ?></span>
                <?php else: ?>
                    <a class="page-btn" href="<?= htmlspecialchars($storesListQs(['page' => $i])) ?>"><?= $i ?></a>
                <?php endif;
            endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="page-btn" href="<?= htmlspecialchars($storesListQs(['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

<div id="storeModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header"><h3 class="modal-title">Store Details</h3><button type="button" class="modal-close" onclick="closeModal()">&times;</button></div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer"><button type="button" class="btn-sm" onclick="closeModal()">Close</button></div>
    </div>
</div>
<?php
$adminSearchQJson = json_encode($searchQ, JSON_UNESCAPED_UNICODE);
$adminFooterScripts = <<<JS
<script>
(function () {
    const searchInput = document.getElementById('adminSearchInput');
    const grid = document.getElementById('storesGrid');
    const toggle = document.getElementById('viewToggle');
    const modal = document.getElementById('storeModal');
    const initialQ = {$adminSearchQJson};

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>]/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]); });
    }

    window.confirmDeleteStore = function (name) {
        return confirm('Permanently delete "' + name + '" and all of its products/orders? This cannot be undone.');
    };

    window.closeModal = function () {
        if (modal) modal.classList.remove('active');
    };

    window.viewStoreDetails = function (store) {
        if (!modal) return;
        const modalBody = document.getElementById('modalBody');
        const hasExpired = !!store.has_expired;
        let statusBadge = '';
        if (hasExpired) {
            statusBadge = '<span class="badge badge-expired">Expired</span>';
        } else if (store.status === 'active') {
            statusBadge = '<span class="badge badge-active">Active</span>';
        } else if (store.status === 'pending') {
            statusBadge = '<span class="badge badge-pending">Pending</span>';
        } else {
            statusBadge = '<span class="badge badge-inactive">Disabled</span>';
        }
        let trialInfo = '';
        if (store.subscription_plan === 'Launch' && store.trial_remaining_days !== null && store.trial_remaining_days > 0) {
            trialInfo = '<div><strong>Trial Remaining:</strong> ' + store.trial_remaining_days + ' day(s) (14-day trial)</div>';
        } else if (store.subscription_plan === 'Launch') {
            trialInfo = '<div><strong>Plan:</strong> 14-day free trial</div>';
        }
        let notificationInfo = '';
        if (hasExpired) {
            notificationInfo = '<div><strong>Expiry Notification:</strong> ' + (store.notification_sent ? 'Sent' : 'Not sent') + '</div>';
        }
        modalBody.innerHTML =
            '<div style="display:flex;flex-direction:column;gap:0.75rem;">' +
            '<div><strong>Store Name:</strong> ' + escapeHtml(store.store_name) + '</div>' +
            '<div><strong>Owner:</strong> ' + escapeHtml(store.owner_name || 'Unknown') + ' (' + escapeHtml(store.owner_email || '') + ')</div>' +
            '<div><strong>Subdomain:</strong> ' + escapeHtml(store.store_slug || 'store') + '.rdvendora.com</div>' +
            '<div><strong>Store URL:</strong> <a href="https://' + escapeHtml(store.store_slug || 'store') + '.rdvendora.com" target="_blank" rel="noopener">https://' + escapeHtml(store.store_slug || 'store') + '.rdvendora.com</a></div>' +
            '<div><strong>Status:</strong> ' + statusBadge + '</div>' +
            '<div><strong>Subscription Plan:</strong> ' + (store.subscription_plan ? escapeHtml(store.subscription_plan) : 'No active plan') + '</div>' +
            '<div><strong>Expiry Date:</strong> ' + (store.subscription_end_date ? new Date(store.subscription_end_date).toLocaleDateString() : '—') + '</div>' +
            trialInfo + notificationInfo +
            '<div><strong>Products:</strong> ' + (store.product_count || 0) + '</div>' +
            '<div><strong>Created:</strong> ' + (store.created_at ? new Date(store.created_at).toLocaleDateString() : '—') + '</div>' +
            '</div>';
        modal.classList.add('active');
    };

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) window.closeModal();
        });
    }

    if (toggle && grid) {
        const applyView = function (view) {
            grid.classList.toggle('list-view', view === 'list');
            toggle.querySelectorAll('button').forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-view') === view);
            });
            try { localStorage.setItem('rdvAdminStoresView', view); } catch (e) {}
        };
        toggle.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () { applyView(btn.getAttribute('data-view')); });
        });
        let saved = 'grid';
        try { saved = localStorage.getItem('rdvAdminStoresView') || 'grid'; } catch (e) {}
        applyView(saved);
    }

    if (searchInput) {
        searchInput.value = initialQ || '';
        let timer = null;
        const goSearch = function () {
            const params = new URLSearchParams(window.location.search);
            const q = searchInput.value.trim();
            if (q) params.set('q', q); else params.delete('q');
            params.set('page', '1');
            window.location.search = params.toString();
        };
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                goSearch();
            }
        });
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(goSearch, 500);
        });
    }
})();
</script>
JS;
require __DIR__ . '/../includes/admin_layout_end.php';
?>
