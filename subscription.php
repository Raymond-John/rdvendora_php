<?php
require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';
require_once 'includes/notification_helper.php';
require_once 'includes/email_functions.php'; // <-- ADDED: load premium email functions

if (!isset($_SESSION['user_id'])) {
    header('Location: login?error=Not logged in');
    exit();
}

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Helper: getUserEmail (unchanged) ----------
function getUserEmail($user_id, $conn) {
    if (isset($_SESSION['email'])) return $_SESSION['email'];
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $email = $result->fetch_assoc()['email'] ?? '';
    $stmt->close();
    $_SESSION['email'] = $email;
    return $email;
}

// ---------- Helper: notify all admins/editors about subscription events ----------
function notifyTeamMembers($conn, $storeId, $title, $message, $link = null) {
    $teamQuery = $conn->prepare("SELECT user_id FROM store_staff WHERE store_id = ? AND role IN ('admin', 'editor')");
    $teamQuery->bind_param("i", $storeId);
    $teamQuery->execute();
    $teamResult = $teamQuery->get_result();
    while ($team = $teamResult->fetch_assoc()) {
        if ($team['user_id'] != $_SESSION['user_id']) {
            createNotification($team['user_id'], 'subscription', $title, $message, $link);
        }
    }
    $teamQuery->close();
}
// ---------- End helper ----------

// ---------- Ensure store exists ----------
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$stmt->close();

// Get store_id for team notifications
$storeId = $_SESSION['store_id'] ?? null;
if (!$storeId) {
    $stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $storeId = $row['id'] ?? 0;
    $_SESSION['store_id'] = $storeId;
    $stmt->close();
}

if (!isStoreActive($conn, $_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Store Disabled</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⛔ Store Disabled</h1>
        <p>Your store has been disabled by the administrator. Please contact support for more information.</p>
        <a href="logout">Logout</a>
    </body>
    </html>
    <?php
    exit();
}

if (!isset($_SESSION['fullname'])) {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['fullname'] = $result->fetch_assoc()['fullname'] ?? 'User';
    $stmt->close();
}

if (!isset($_SESSION['store_name']) || !isset($_SESSION['store_id'])) {
    $stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['store_id'] = $row['id'];
        $_SESSION['store_name'] = $row['store_name'];
    }
    $stmt->close();
}

$conn->query("CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan VARCHAR(50) NOT NULL,
    billing_cycle VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('active','expired','cancelled','pending') DEFAULT 'pending',
    start_date DATETIME,
    end_date DATETIME,
    payment_ref VARCHAR(100),
    payment_method VARCHAR(50) DEFAULT 'flutterwave',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(user_id)
)");

$check_column = $conn->query("SHOW COLUMNS FROM subscriptions LIKE 'payment_method'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE subscriptions ADD COLUMN payment_method VARCHAR(50) DEFAULT 'flutterwave'");
}

// ========== Get current subscription - check if it's actually active and not expired ==========
$current_subscription = null;
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$current_subscription = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ========== Update expired subscriptions to 'expired' status ==========
$conn->query("UPDATE subscriptions SET status = 'expired' WHERE user_id = {$_SESSION['user_id']} AND status = 'active' AND end_date <= NOW()");

// ========== Check if subscription is expired for nav links ==========
$isExpired = false;
if (!$current_subscription) {
    // Check if user has any expired subscription
    $checkExpired = $conn->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'expired' LIMIT 1");
    $checkExpired->bind_param("i", $_SESSION['user_id']);
    $checkExpired->execute();
    if ($checkExpired->get_result()->num_rows > 0) {
        $isExpired = true;
    }
    $checkExpired->close();
}

$activePlans = [];
$plansQuery = $conn->query("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price ASC");
if ($plansQuery && $plansQuery->num_rows > 0) {
    $activePlans = $plansQuery->fetch_all(MYSQLI_ASSOC);
}

$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE user_id = ?");
$total_stmt->bind_param("i", $_SESSION['user_id']);
$total_stmt->execute();
$total_records = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$total_stmt->close();

$subscription_history = [];
$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $_SESSION['user_id'], $records_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Ensure expired subscriptions show as 'expired'
    if ($row['status'] === 'active' && strtotime($row['end_date']) <= time()) {
        $row['status'] = 'expired';
        // Update in database too
        $updateStmt = $conn->prepare("UPDATE subscriptions SET status = 'expired' WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
    }
    $subscription_history[] = $row;
}
$stmt->close();

$payKeys = function_exists('rdv_payment_keys') ? rdv_payment_keys() : [];
$flwConfig = function_exists('rdv_flutterwave_config') ? rdv_flutterwave_config() : [
    'secret' => trim((string) ($payKeys['flutterwave_secret'] ?? '')),
    'public' => trim((string) ($payKeys['flutterwave_public'] ?? '')),
    'configured' => !empty($payKeys['flutterwave_secret']) && !empty($payKeys['flutterwave_public']),
    'mode' => 'missing',
];
if (!defined('FLW_PUBLIC_KEY')) {
    define('FLW_PUBLIC_KEY', $flwConfig['public']);
}
if (!defined('FLW_SECRET_KEY')) {
    define('FLW_SECRET_KEY', $flwConfig['secret']);
}
define('FLW_BASE_URL', 'https://api.flutterwave.com/v3');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/storage/logs/flutterwave_error.log');

function initializeFlutterwavePayment($amount, $customerName, $customerEmail, $customerPhone, $paymentReference, $returnUrl) {
    $secret = trim((string) FLW_SECRET_KEY);
    if ($secret === '') {
        return ['error' => 'Flutterwave is not configured. Add your secret key in Admin → Settings → Payment keys.'];
    }
    if (!function_exists('curl_init')) {
        return ['error' => 'CURL missing'];
    }

    $payload = [
        'tx_ref' => $paymentReference,
        'amount' => $amount,
        'currency' => 'NGN',
        'redirect_url' => $returnUrl,
        'payment_options' => 'card,banktransfer,ussd',
        'customer' => [
            'email' => $customerEmail,
            'name' => $customerName,
            'phonenumber' => $customerPhone
        ],
        'customizations' => [
            'title' => 'RD Vendora Subscription',
            'description' => 'Subscription to ' . $customerName,
        ]
    ];

    $ch = curl_init(FLW_BASE_URL . '/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secret, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Flutterwave init curl error: ' . $curlError);
        return ['error' => 'Could not reach Flutterwave. Try again shortly.'];
    }

    $data = json_decode((string) $response, true);
    if ($httpCode === 200 && $data && ($data['status'] ?? '') === 'success' && !empty($data['data']['link'])) {
        return ['success' => true, 'checkoutUrl' => $data['data']['link']];
    }

    $apiMessage = trim((string) ($data['message'] ?? ''));
    if ($httpCode === 401) {
        error_log('Flutterwave init HTTP 401 – check FLUTTERWAVE_SECRET_KEY in Admin → Settings or .env');
        return ['error' => 'Invalid Flutterwave secret key (HTTP 401). In Admin → Settings → Payment keys, paste your live FLWSECK-... secret from the Flutterwave dashboard.'];
    }
    if ($apiMessage !== '') {
        return ['error' => $apiMessage];
    }

    error_log('Flutterwave init failed HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 500));
    return ['error' => 'HTTP ' . $httpCode];
}

function verifyFlutterwaveTransaction($transactionId) {
    $secret = trim((string) FLW_SECRET_KEY);
    if ($secret === '') {
        return ['error' => 'Flutterwave is not configured.'];
    }
    $ch = curl_init(FLW_BASE_URL . '/transactions/' . $transactionId . '/verify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secret, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success' && $data['data']['status'] === 'successful') {
            return ['success' => true, 'amount' => $data['data']['amount'], 'tx_ref' => $data['data']['tx_ref']];
        }
        return ['error' => $data['message'] ?? 'Payment not successful'];
    }
    if ($httpCode === 401) {
        return ['error' => 'Invalid Flutterwave secret key (HTTP 401).'];
    }
    return ['error' => 'HTTP ' . $httpCode];
}

$activation_message = null;
$activation_error = null;
$remaining_days = null;
$show_trial_warning = false;

if (isset($_GET['flutterwave_callback']) && isset($_GET['transaction_id'])) {
    $verification = verifyFlutterwaveTransaction($_GET['transaction_id']);
    if (isset($verification['success']) && $verification['success'] === true) {
        $user_id = $_SESSION['user_id'];
        $plan = $_SESSION['pending_plan'] ?? null;
        $billing = $_SESSION['pending_billing'] ?? null;
        $amount = $_SESSION['pending_amount'] ?? null;
        $paymentRef = $_SESSION['pending_tx_ref'] ?? null;
        if ($plan && $billing && $paymentRef) {
            $conn->query("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = $user_id AND status = 'active'");
            $start_date = date('Y-m-d H:i:s');
            $end_date = $billing === 'annual' ? date('Y-m-d H:i:s', strtotime('+1 year')) : date('Y-m-d H:i:s', strtotime('+1 month'));
            
            $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $checkUser->bind_param("i", $user_id);
            $checkUser->execute();
            $userExists = $checkUser->get_result()->num_rows > 0;
            $checkUser->close();

            if (!$userExists) {
                session_destroy();
                header("Location: login?error=Your account was not found. Please log in again.");
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, plan, billing_cycle, amount, status, start_date, end_date, payment_ref, payment_method) VALUES (?, ?, ?, ?, 'active', ?, ?, ?, 'flutterwave')");
            $stmt->bind_param("issdsss", $user_id, $plan, $billing, $amount, $start_date, $end_date, $paymentRef);
            if ($stmt->execute()) {
                unset($_SESSION['pending_plan'], $_SESSION['pending_billing'], $_SESSION['pending_amount'], $_SESSION['pending_tx_ref']);
                $activation_message = "Payment successful! Your $plan plan is now active.";
                $current_subscription = ['plan' => $plan, 'billing_cycle' => $billing, 'amount' => $amount, 'end_date' => $end_date];
                
                // ========== Update store status when subscription is reactivated ==========
                $storeCheck = $conn->query("SELECT status FROM stores WHERE user_id = $user_id");
                $storeStatus = $storeCheck->fetch_assoc();
                if ($storeStatus && ($storeStatus['status'] === 'inactive' || $storeStatus['status'] === 'pending')) {
                    $conn->query("UPDATE stores SET status = 'active' WHERE user_id = $user_id");
                    error_log("Store reactivated for user: $user_id after subscription payment");
                }
                
                // ========== NEW: Clear expired status ==========
                $clearExpired = $conn->prepare("UPDATE subscriptions SET status = 'cancelled', notification_sent = 1 WHERE user_id = ? AND status = 'expired'");
                $clearExpired->bind_param("i", $user_id);
                $clearExpired->execute();
                $clearExpired->close();
                // ========== END CLEAR EXPIRED STATUS ==========
                
                $userEmail = getUserEmail($user_id, $conn);
                // ========== USE STYLED EMAIL FUNCTION ==========
                sendSubscriptionEmail($userEmail, $_SESSION['fullname'], $plan, $billing, $amount, $start_date, $end_date);

                $title = "Subscription Activated: $plan";
                $message = "Your subscription to the $plan plan ($billing) has been activated. Amount: ₦" . number_format($amount, 2) . " /" . ($billing == 'annual' ? 'year' : 'month') . ". Expires: " . date('F j, Y', strtotime($end_date));
                $link = "subscription.php";
                createNotification($user_id, 'subscription', $title, $message, $link);
                if ($storeId) {
                    notifyTeamMembers($conn, $storeId, $title, $message, $link);
                }

                if ($plan === 'Empire') {
                    $docCheck = $conn->query("SELECT COUNT(*) as cnt FROM company_documents WHERE user_id = $user_id");
                    $docCount = $docCheck->fetch_assoc()['cnt'] ?? 0;
                    
                    if ($docCount > 0) {
                        $statusCheck = $conn->query("SELECT status FROM company_documents WHERE user_id = $user_id");
                        $allApproved = true;
                        $hasPending = false;
                        $hasRejected = false;
                        while ($row = $statusCheck->fetch_assoc()) {
                            if ($row['status'] === 'pending') $hasPending = true;
                            if ($row['status'] === 'rejected') $hasRejected = true;
                            if ($row['status'] !== 'approved') $allApproved = false;
                        }
                        
                        if ($allApproved) {
                            $conn->query("UPDATE stores SET status = 'active' WHERE user_id = $user_id");
                            header("Location: dashboard?msg=Subscription activated! Your store is now live.");
                            exit();
                        } elseif ($hasRejected) {
                            $conn->query("UPDATE stores SET status = 'pending_docs' WHERE user_id = $user_id");
                            header("Location: company-documents?subscription=active&rejected=1");
                            exit();
                        } else {
                            $conn->query("UPDATE stores SET status = 'pending_docs' WHERE user_id = $user_id");
                            header("Location: dashboard?msg=Your documents are still under review. You will be notified when approved.");
                            exit();
                        }
                    } else {
                        $conn->query("UPDATE stores SET status = 'pending_docs' WHERE user_id = $user_id");
                        header("Location: company-documents?subscription=active");
                        exit();
                    }
                } else {
                    header("Location: subscription?success=1");
                    exit();
                }

            } else {
                $activation_error = "Payment verified but activation failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $activation_error = "Missing subscription details.";
        }
    } else {
        $activation_error = "Payment verification failed: " . ($verification['error'] ?? 'Unknown error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_with_flutterwave') {
    $plan = $_POST['plan'];
    $billing = $_POST['billing'];
    $amount = floatval($_POST['amount']);
    $fullName = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $paymentReference = 'VEN_' . time() . '_' . uniqid();
    $_SESSION['pending_plan'] = $plan;
    $_SESSION['pending_billing'] = $billing;
    $_SESSION['pending_amount'] = $amount;
    $_SESSION['pending_tx_ref'] = $paymentReference;
    $returnUrl = function_exists('rdv_url')
        ? rdv_url('subscription', ['flutterwave_callback' => '1'])
        : ((function_exists('rdv_request_is_https') && rdv_request_is_https()) ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . '/subscription?flutterwave_callback=1';
    $init = initializeFlutterwavePayment($amount, $fullName, $email, $phone, $paymentReference, $returnUrl);
    if (isset($init['success']) && $init['success']) {
        header('Location: ' . $init['checkoutUrl']);
        exit();
    } else {
        $activation_error = "Failed to initialize payment: " . ($init['error'] ?? 'Unknown error');
    }
}

// ========== MODIFIED: activate_free_plan handler ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_free_plan') {
    $plan = $_POST['plan'];
    $billing = $_POST['billing'];

    // Find the price of the selected plan from $activePlans
    $planPrice = null;
    foreach ($activePlans as $p) {
        if ($p['name'] === $plan) {
            $planPrice = floatval($p['price']);
            break;
        }
    }

    // Validation
    if ($planPrice === null) {
        $activation_error = "Invalid plan selected.";
    } elseif ($planPrice > 0) {
        $activation_error = "This plan is not free. Please use the payment option.";
    } elseif ($current_subscription && $current_subscription['status'] === 'active') {
        $activation_error = "You already have an active subscription.";
    } elseif (hasUsedFreePlan($conn, $_SESSION['user_id'])) {
        $activation_error = "You have already used a free trial. Please choose a paid plan.";
    } else {
        // Proceed with free activation
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+14 days'));
        $payment_ref = 'FREE_' . uniqid();
        $stmt = $conn->prepare("INSERT INTO subscriptions (user_id, plan, billing_cycle, amount, status, start_date, end_date, payment_ref) VALUES (?, ?, ?, 0, 'active', ?, ?, ?)");
        $stmt->bind_param("isssss", $_SESSION['user_id'], $plan, $billing, $start_date, $end_date, $payment_ref);
        if ($stmt->execute()) {
            $activation_message = "Your $plan plan has been activated with a 14‑day free trial!";
            $current_subscription = ['plan' => $plan, 'billing_cycle' => $billing, 'amount' => 0, 'end_date' => $end_date];
            
            // ========== Update store status when free trial is activated ==========
            $storeCheck = $conn->query("SELECT status FROM stores WHERE user_id = {$_SESSION['user_id']}");
            $storeStatus = $storeCheck->fetch_assoc();
            if ($storeStatus && ($storeStatus['status'] === 'inactive' || $storeStatus['status'] === 'pending')) {
                $conn->query("UPDATE stores SET status = 'active' WHERE user_id = {$_SESSION['user_id']}");
                error_log("Store reactivated for user: {$_SESSION['user_id']} after free trial activation");
            }
            
            // ========== NEW: Clear expired status ==========
            $clearExpired = $conn->prepare("UPDATE subscriptions SET status = 'cancelled', notification_sent = 1 WHERE user_id = ? AND status = 'expired'");
            $clearExpired->bind_param("i", $_SESSION['user_id']);
            $clearExpired->execute();
            $clearExpired->close();
            // ========== END CLEAR EXPIRED STATUS ==========
            
            $userEmail = getUserEmail($_SESSION['user_id'], $conn);
            // ========== USE STYLED EMAIL FUNCTION ==========
            sendSubscriptionEmail($userEmail, $_SESSION['fullname'], $plan, $billing, 0, $start_date, $end_date);

            $title = "Free Trial Activated: $plan";
            $message = "Your free trial for the $plan plan has started. Expires: " . date('F j, Y', strtotime($end_date));
            $link = "subscription.php";
            createNotification($_SESSION['user_id'], 'subscription', $title, $message, $link);
            if ($storeId) {
                notifyTeamMembers($conn, $storeId, $title, $message, $link);
            }

            unset($_SESSION['free_plan_modal_dismissed']);
            $redirectTo = trim((string) ($_POST['redirect'] ?? 'subscription'));
            if (!in_array($redirectTo, ['dashboard', 'subscription'], true)) {
                $redirectTo = 'subscription';
            }
            header('Location: ' . $redirectTo);
            exit();
        } else {
            $activation_error = "Failed to activate subscription.";
        }
        $stmt->close();
    }
}
// ========== END OF MODIFIED BLOCK ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_subscription' && $current_subscription) {
    $stmt = $conn->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $current_subscription['id']);
    if ($stmt->execute()) {
        $activation_message = "Your subscription has been cancelled.";
        
        $title = "Subscription Cancelled";
        $message = "Your subscription to the " . $current_subscription['plan'] . " plan has been cancelled.";
        $link = "subscription.php";
        createNotification($_SESSION['user_id'], 'subscription', $title, $message, $link);
        if ($storeId) {
            notifyTeamMembers($conn, $storeId, $title, $message, $link);
        }

        $current_subscription = null;
        header("Location: subscription");
        exit();
    } else {
        $activation_error = "Failed to cancel subscription.";
    }
    $stmt->close();
}

// Do NOT close the connection here – it is still needed in the HTML output.

if ($current_subscription && $current_subscription['plan'] === 'Launch' && $current_subscription['status'] === 'active') {
    $remaining = (new DateTime())->diff(new DateTime($current_subscription['end_date']))->days;
    if ($remaining <= 3 && $remaining > 0) $show_trial_warning = true;
    elseif ($remaining <= 0) $activation_error = "Your free trial has expired.";
}

// ========== Re-check if expired after potential clearance ==========
if (!$current_subscription) {
    $checkExpired = $conn->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'expired' LIMIT 1");
    $checkExpired->bind_param("i", $_SESSION['user_id']);
    $checkExpired->execute();
    if ($checkExpired->get_result()->num_rows > 0) {
        $isExpired = true;
    } else {
        $isExpired = false;
    }
    $checkExpired->close();
} else {
    $isExpired = false;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Subscription - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== FULL CSS (unchanged) ========== */
        :root {
            --bg-primary: #f8f9fb;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f3f6;
            --bg-elevated: #ffffff;
            --bg-hover: #eef0f4;
            --bg-active: #e4e7ed;
            --surface-primary: #ffffff;
            --surface-secondary: #f8f9fb;
            --surface-tertiary: #f1f3f6;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --text-inverse: #ffffff;
            --border-primary: #e5e7eb;
            --border-secondary: #d1d5db;
            --border-focus: #6366f1;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #4338ca;
            --success: #10b981;
            --success-light: #ecfdf5;
            --success-dark: #047857;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --warning-dark: #b45309;
            --error: #ef4444;
            --error-light: #fef2f2;
            --error-dark: #b91c1c;
            --info: #3b82f6;
            --info-light: #eff6ff;
            --info-dark: #1d4ed8;
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --text-xs: 0.75rem;
            --text-sm: 0.8125rem;
            --text-base: 0.9375rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --font-normal: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-bold: 700;
            --leading-normal: 1.5;
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
            --space-16: 4rem;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-full: 9999px;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.10), 0 8px 16px rgba(0,0,0,0.04);
            --shadow-glow: 0 0 40px rgba(99,102,241,0.15);
            --transition-fast: 150ms cubic-bezier(0.4,0,0.2,1);
            --transition-base: 250ms cubic-bezier(0.4,0,0.2,1);
            --transition-slow: 350ms cubic-bezier(0.4,0,0.2,1);
            --sidebar-width: 260px;
            --sidebar-collapsed: 72px;
            --topbar-height: 64px;
        }

        [data-theme="dark"] {
            --bg-primary: #0c0e14;
            --bg-secondary: #14161f;
            --bg-tertiary: #1a1d28;
            --bg-elevated: #1e2130;
            --bg-hover: #242838;
            --bg-active: #2a2e40;
            --text-primary: #e8eaf0;
            --text-secondary: #9ca3b0;
            --text-muted: #6b7280;
            --border-primary: #2d3139;
            --border-secondary: #3a3f4a;
            --primary-light: rgba(99,102,241,0.15);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-sans); font-size: var(--text-base); line-height: var(--leading-normal); color: var(--text-primary); background: var(--bg-primary); overflow-x: hidden; }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: 300;
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
            flex-shrink: 0;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: var(--space-3);
            font-weight: var(--font-bold); font-size: var(--text-lg);
            color: var(--text-primary);
            white-space: nowrap;
        }
                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
        .sidebar-brand-icon {
            width: 34px; height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: white;
        }
        .sidebar-brand-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-toggle {
            width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            transition: all var(--transition-fast);
            background: transparent;
            cursor: pointer;
        }
        .sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-nav { flex: 1; overflow-y: auto; padding: var(--space-3); }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px; font-weight: var(--font-semibold);
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-muted);
            white-space: nowrap;
            transition: all var(--transition-fast);
            margin-top: var(--space-2);
        }
        .sidebar.collapsed .sidebar-section-title { opacity: 0; height: 0; padding: 0; overflow: hidden; }
        .sidebar-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            transition: all var(--transition-fast);
            white-space: nowrap;
            cursor: pointer;
            margin-bottom: 1px;
        }
        .sidebar-link:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-link.active { background: var(--primary-light); color: var(--primary); font-weight: var(--font-semibold); }
        .sidebar-link.disabled { 
            opacity: 0.5; 
            cursor: not-allowed; 
            pointer-events: none;
            color: var(--text-muted);
        }
        .sidebar-link.disabled:hover { background: none; color: var(--text-muted); }
        .sidebar-link svg { flex-shrink: 0; width: 18px; height: 18px; }
        .sidebar-link-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-link-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-footer { padding: var(--space-3); border-top: 1px solid var(--border-primary); flex-shrink: 0; }
        .sidebar-user {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md);
            transition: background var(--transition-fast);
            cursor: pointer;
        }
        .sidebar-user:hover { background: var(--bg-hover); }
        .sidebar-user-avatar { width: 34px; height: 34px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .sidebar-user-info { flex: 1; min-width: 0; transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-user-info { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 299; opacity: 0; pointer-events: none; transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition-slow); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }
        
        /* Expired badge */
        .expired-badge {
            display: inline-block;
            background: #dc2626;
            color: white;
            font-weight: 700;
            font-size: 0.65rem;
            padding: 2px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-left: 8px;
            animation: pulse-badge 2s infinite;
        }
        @keyframes pulse-badge {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .topbar {
            position: sticky; top: 0; height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 var(--space-6);
            z-index: 200;
            backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar { background: rgba(255,255,255,0.85); }
        [data-theme="dark"] .topbar { background: rgba(20,22,31,0.85); }
        .topbar-left { display: flex; align-items: center; gap: var(--space-3); }
        .mobile-sidebar-toggle { display: none; width: 38px; height: 38px; align-items: center; justify-content: center; border-radius: var(--radius-md); color: var(--text-secondary); flex-shrink: 0; }
        .mobile-sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-search { flex: 1; max-width: 420px; position: relative; }
        .topbar-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; }
        .topbar-search input { width: 100%; padding: var(--space-2) var(--space-4) var(--space-2) 40px; background: var(--bg-tertiary); border: 1px solid var(--border-primary); border-radius: var(--radius-md); font-size: var(--text-sm); outline: none; color: var(--text-primary); }
        .topbar-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .topbar-actions { display: flex; align-items: center; gap: var(--space-2); }
        .theme-toggle { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); color: var(--text-secondary); }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        [data-theme="light"] .theme-toggle .icon-moon { display: none; }
        [data-theme="dark"] .theme-toggle .icon-sun { display: none; }
        .topbar-user {
            display: flex; align-items: center; gap: var(--space-2);
            padding: var(--space-1) var(--space-3) var(--space-1) var(--space-1);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: background var(--transition-fast);
        }
        .topbar-user:hover { background: var(--bg-hover); }
        .topbar-user-avatar { width: 32px; height: 32px; border-radius: var(--radius-full); object-fit: cover; }
        .topbar-user-info { display: flex; flex-direction: column; line-height: 1.2; }
        .topbar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); }
        .topbar-user-role { font-size: var(--text-xs); color: var(--text-muted); }
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0;
            min-width: 240px; background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 100;
            opacity: 0; pointer-events: none;
            transform: translateY(-8px);
            transition: all var(--transition-fast);
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-item {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            font-size: var(--text-sm);
            color: var(--text-secondary);
            transition: background var(--transition-fast);
            cursor: pointer;
        }
        .dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown-divider { height: 1px; background: var(--border-primary); margin: var(--space-1) 0; }
        
        .page-content { flex: 1; padding: var(--space-6); overflow-y: auto; }
        .page-header { margin-bottom: var(--space-6); }
        .page-title { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); letter-spacing: -0.02em; }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: var(--space-1); }
        
        .pricing-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 1200px) { .pricing-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .pricing-grid { grid-template-columns: 1fr; } }
        .pricing-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            transition: all var(--transition-base);
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .pricing-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--border-secondary); }
        .pricing-card.popular { border: 2px solid var(--primary); box-shadow: var(--shadow-md); }
        .pricing-badge {
            position: absolute; top: -12px; left: 20px;
            background: var(--gradient-primary);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: var(--radius-full);
        }
        .pricing-name { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        .pricing-desc { font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.4; }
        .pricing-price { margin-bottom: 1rem; }
        .pricing-amount { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
        .pricing-period { font-size: 0.75rem; color: var(--text-muted); }
        .hidden { display: none; }
        .pricing-features { list-style: none; margin: 0 0 1.25rem 0; flex: 1; }
        .pricing-feature { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; padding: 0.4rem 0; color: var(--text-secondary); }
        .pricing-feature svg { width: 14px; height: 14px; flex-shrink: 0; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: var(--space-2); padding: 0.5rem 1rem; font-size: 0.8rem;
            font-weight: 600; border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            cursor: pointer; border: 1px solid transparent;
            width: 100%;
        }
        .btn-primary { background: var(--gradient-primary); color: var(--text-inverse); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.35); }
        .btn-outline { background: transparent; border-color: var(--border-primary); color: var(--text-primary); }
        .btn-outline:hover { background: var(--bg-hover); border-color: var(--border-secondary); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .tabs {
            display: flex; justify-content: center; gap: var(--space-2);
            background: var(--bg-tertiary); padding: 4px;
            border-radius: var(--radius-full);
            width: fit-content;
            margin: 0 auto var(--space-8) auto;
        }
        .tab-btn {
            padding: var(--space-2) var(--space-5);
            border-radius: var(--radius-full);
            font-weight: var(--font-medium);
            transition: all var(--transition-fast);
            background: transparent;
            cursor: pointer;
        }
        .tab-btn.active { background: var(--bg-secondary); box-shadow: var(--shadow-sm); color: var(--primary); font-weight: var(--font-semibold); }
        .badge { display: inline-flex; align-items: center; padding: 2px 8px; font-size: 10px; font-weight: var(--font-semibold); border-radius: var(--radius-full); }
        .badge-success { background: var(--success-light); color: var(--success-dark); }
        
        .current-plan-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-5);
            margin-bottom: var(--space-6);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-4);
        }
        .current-plan-info h3 { font-size: var(--text-lg); font-weight: var(--font-bold); margin-bottom: var(--space-1); }
        .current-plan-info p { font-size: var(--text-sm); color: var(--text-secondary); }
        .alert {
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        .alert-success { background: var(--success-light); color: var(--success-dark); border-left: 4px solid var(--success); }
        .alert-error { background: var(--error-light); color: var(--error-dark); border-left: 4px solid var(--error); }
        .alert-info { background: var(--primary-light); color: var(--primary-dark); border-left: 4px solid var(--primary); }
        .alert-warning { background: var(--warning-light); color: var(--warning-dark); border-left: 4px solid var(--warning); }
        
        .history-section {
            margin-top: var(--space-8);
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-primary);
            overflow: hidden;
        }
        .history-header {
            padding: var(--space-5) var(--space-6);
            border-bottom: 1px solid var(--border-primary);
            background: var(--bg-tertiary);
        }
        .history-header h3 { font-size: var(--text-lg); font-weight: var(--font-semibold); margin-bottom: 4px; }
        .history-header p { font-size: var(--text-sm); color: var(--text-muted); }
        .history-table-container { overflow-x: auto; background: var(--bg-secondary); }
        .history-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
        .history-table th { padding: var(--space-4) var(--space-5); text-align: left; font-weight: var(--font-semibold); background: var(--bg-tertiary); border-bottom: 1px solid var(--border-primary); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .history-table td { padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--border-primary); vertical-align: middle; }
        .history-table tbody tr:hover { background: var(--bg-hover); }
        .status-badge { display: inline-flex; align-items: center; padding: 4px 12px; font-size: 11px; font-weight: var(--font-semibold); border-radius: var(--radius-full); gap: 6px; }
        .status-active { background: var(--success-light); color: var(--success-dark); }
        .status-cancelled { background: var(--error-light); color: var(--error-dark); }
        .status-expired { background: var(--warning-light); color: var(--warning-dark); }
        .status-pending { background: var(--info-light); color: var(--info-dark); }
        .empty-history { text-align: center; padding: var(--space-12); color: var(--text-muted); }
        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 0.5rem; padding: 1.5rem; border-top: 1px solid var(--border-primary);
            background: var(--bg-secondary); flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 36px; height: 36px; padding: 0 0.75rem;
            border-radius: 8px; font-size: 0.875rem; font-weight: 500;
            transition: all 0.2s ease;
            background: var(--bg-tertiary); color: var(--text-secondary);
            cursor: pointer; text-decoration: none;
        }
        .pagination a:hover { background: var(--primary-light); color: var(--primary); }
        .pagination .active { background: var(--primary); color: white; cursor: default; }
        .pagination .disabled { opacity: 0.5; pointer-events: none; background: var(--bg-tertiary); }
        
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000; opacity: 0; visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            padding: var(--space-4);
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .payment-modal {
            background: var(--bg-secondary);
            border-radius: var(--radius-xl);
            max-width: 500px; width: 100%; max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-primary);
            transform: scale(0.95); transition: transform 0.3s ease;
        }
        .modal-overlay.active .payment-modal { transform: scale(1); }
        .modal-header {
            padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-primary);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; background: var(--bg-secondary);
        }
        .modal-header h3 { font-size: var(--text-xl); font-weight: var(--font-bold); }
        .modal-close { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .modal-body { padding: var(--space-6); }
        .form-group { margin-bottom: var(--space-4); }
        .form-group label { display: block; margin-bottom: var(--space-2); font-weight: var(--font-medium); font-size: var(--text-sm); }
        .form-group input { width: 100%; padding: var(--space-3); border: 1px solid var(--border-primary); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary); font-size: var(--text-base); }
        .plan-summary { background: var(--bg-tertiary); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-6); font-size: var(--text-sm); }
        .btn-pay { width: 100%; background: var(--gradient-primary); color: white; padding: var(--space-3); font-weight: var(--font-semibold); margin-top: var(--space-2); }
        .btn-pay:disabled { opacity: 0.6; cursor: not-allowed; }
        .flutterwave-badge { display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--primary-light); padding: 10px; border-radius: var(--radius-md); margin-bottom: var(--space-4); color: var(--primary); font-weight: var(--font-medium); }
        
        .toast-container { position: fixed; top: calc(var(--topbar-height) + var(--space-4)); right: var(--space-4); z-index: 1100; display: flex; flex-direction: column; gap: var(--space-3); }
        .toast {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-4) var(--space-5);
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-xl);
            min-width: 300px; max-width: 420px;
            transform: translateX(120%);
            animation: toastSlideIn 0.4s ease forwards;
        }
        .toast.removing { animation: toastSlideOut 0.3s ease forwards; }
        @keyframes toastSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: 300; }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .page-content { padding: var(--space-4); }
            .topbar-user-info { display: none; }
            .history-table th, .history-table td { padding: var(--space-3); }
        }
        @media (max-width: 480px) { .topbar-search { display: none; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/vendor_sidebar.php'; ?>


    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
                <div class="topbar-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" id="globalSearch" placeholder="Search..."></div>
            </div>
            <div class="topbar-actions">
                <?php if ($isExpired): ?>
                    <span class="expired-badge" style="margin-right: 10px;">Expired</span>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle"><svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></button>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger"><?php include __DIR__ . '/includes/vendor_user_avatar.php'; ?><div class="topbar-user-info"><span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span><span class="topbar-user-role"><?= htmlspecialchars($_SESSION['store_name'] ?? 'No Store') ?></span></div><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color:var(--text-muted);"><polyline points="6 9 12 15 18 9"/></svg></div>
                    <div class="dropdown-menu"><a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a><a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings</a><div class="dropdown-divider"></div><a href="#" class="dropdown-item" onclick="handleLogout()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a></div>
                </div>
            </div>
        </header>
        <div class="page-content">
            <div class="page-header">
                <h1 class="page-title">Subscription</h1>
                <p class="page-subtitle">Manage your plan and billing.</p>
            </div>

            <?php if ($isExpired): ?>
                <div class="alert alert-error">
                    <strong>⚠️ Your subscription has expired.</strong> Please choose a plan below to reactivate your account and regain access to all features.
                </div>
            <?php endif; ?>

            <?php if ($activation_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($activation_message) ?></div>
            <?php elseif ($activation_error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($activation_error) ?></div>
            <?php endif; ?>

            <?php if (!$flwConfig['configured']): ?>
                <div class="alert alert-warning">
                    <strong>Payments not configured.</strong> Add your Flutterwave public and secret keys in <strong>Admin → Settings → Payment keys</strong>, then try again.
                </div>
            <?php endif; ?>

            <?php if ($show_trial_warning): ?>
                <div class="alert alert-warning">⚠️ Your free trial ends in <strong><?= $remaining_days ?> day(s)</strong>. <a href="#" onclick="scrollToPlans()">Upgrade now</a> to avoid service interruption.</div>
            <?php endif; ?>

            <?php if ($current_subscription): ?>
                <div class="current-plan-card">
                    <div class="current-plan-info">
                        <h3>Current Plan: <?= htmlspecialchars($current_subscription['plan']) ?></h3>
                        <p>Billing: <?= ucfirst($current_subscription['billing_cycle']) ?> · ₦ <?= number_format($current_subscription['amount'], 2) ?> /<?= $current_subscription['billing_cycle'] === 'annual' ? 'year' : 'month' ?></p>
                        <p>Expires on: <?= date('F j, Y', strtotime($current_subscription['end_date'])) ?></p>
                        <p>Payment method: <?= ucfirst($current_subscription['payment_method'] ?? 'Flutterwave') ?></p>
                    </div>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel your subscription?');">
                        <input type="hidden" name="action" value="cancel_subscription">
                        <button type="submit" class="btn btn-outline" style="width:auto;">Cancel Subscription</button>
                    </form>
                </div>
                <div class="alert alert-info">You can upgrade or change your plan below. Any changes will be prorated.</div>
            <?php else: ?>
                <div class="alert alert-info">You don't have an active subscription. Choose a plan below to get started.</div>
            <?php endif; ?>

            <div class="tabs" id="billingTabs">
                <button class="tab-btn active" data-billing="monthly">Monthly</button>
                <button class="tab-btn" data-billing="annual">Annual <span class="badge badge-success">-20%</span></button>
            </div>

            <div class="pricing-grid" id="pricingGrid">
                <?php if (empty($activePlans)): ?>
                    <div class="pricing-card" style="grid-column: 1/-1; text-align: center;">
                        <p>No active subscription plans available at the moment. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $planCount = count($activePlans);
                    $hasUsedFree = hasUsedFreePlan($conn, $_SESSION['user_id']);
                    foreach ($activePlans as $index => $plan):
                        $isPopular = ($index === 1 && $planCount > 2);
                        $planName = htmlspecialchars($plan['name']);
                        $basePrice = floatval($plan['price']);
                        $isFree = ($basePrice == 0);
                        if ($plan['duration'] === 'monthly') {
                            $monthlyPrice = $basePrice;
                            $annualPrice = round($basePrice * 12 * 0.8, 2);
                        } else {
                            $annualPrice = $basePrice;
                            $monthlyPrice = round($basePrice / 12, 2);
                        }
                        $features = json_decode($plan['features'], true);
                        if (!is_array($features)) $features = [];
                    ?>
                    <div class="pricing-card <?= $isPopular ? 'popular' : '' ?>" 
                         data-plan="<?= $planName ?>" 
                         data-price-monthly="<?= $monthlyPrice ?>" 
                         data-price-annual="<?= $annualPrice ?>" 
                         data-free="<?= $isFree ? 'true' : 'false' ?>">
                        <?php if ($isPopular): ?>
                            <div class="pricing-badge">Most Popular</div>
                        <?php endif; ?>
                        <div class="pricing-name"><?= $planName ?></div>
                        <p class="pricing-desc"><?= $plan['duration'] === 'monthly' ? 'Billed monthly' : 'Billed yearly' ?></p>
                        <div class="pricing-price">
                            <span class="pricing-amount monthly-price">₦<?= number_format($monthlyPrice, 2) ?></span>
                            <span class="pricing-amount annual-price hidden">₦<?= number_format($annualPrice, 2) ?></span>
                            <span class="pricing-period">/<?= $plan['duration'] === 'monthly' ? 'month' : 'year' ?></span>
                        </div>
                        <div class="pricing-features">
                            <?php if (empty($features)): ?>
                                <div class="pricing-feature"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Standard features included</div>
                            <?php else: ?>
                                <?php foreach ($features as $feature): ?>
                                    <div class="pricing-feature"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?= htmlspecialchars($feature) ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($isFree): ?>
                            <?php if ($hasUsedFree): ?>
                                <button class="btn btn-outline" disabled>Already used free trial</button>
                            <?php else: ?>
                                <button class="btn btn-outline plan-btn">Start free trial</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?> plan-btn">Subscribe Now</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($subscription_history)): ?>
                <div class="history-section">
                    <div class="history-header">
                        <h3>📜 Subscription History</h3>
                        <p>View all your past and current subscription records.</p>
                    </div>
                    <div class="history-table-container">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Plan</th>
                                    <th>Billing Cycle</th>
                                    <th>Amount</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscription_history as $sub): 
                                    $statusClass = '';
                                    $statusIcon = '';
                                    if ($sub['status'] === 'active') {
                                        $statusClass = 'status-active';
                                        $statusIcon = '✓';
                                    } elseif ($sub['status'] === 'cancelled') {
                                        $statusClass = 'status-cancelled';
                                        $statusIcon = '✗';
                                    } elseif ($sub['status'] === 'expired') {
                                        $statusClass = 'status-expired';
                                        $statusIcon = '⌛';
                                    } else {
                                        $statusClass = 'status-pending';
                                        $statusIcon = '⏳';
                                    }
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($sub['plan']) ?></strong></td>
                                        <td><?= ucfirst($sub['billing_cycle']) ?></td>
                                        <td>₦ <?= number_format($sub['amount'], 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($sub['start_date'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($sub['end_date'])) ?></td>
                                        <td><span class="status-badge <?= $statusClass ?>"><?= $statusIcon ?> <?= ucfirst($sub['status']) ?></span></td>
                                        <td><?= ucfirst($sub['payment_method'] ?? 'Flutterwave') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>" class="page-link">&laquo; Previous</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Previous</span>
                        <?php endif; ?>
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if ($start_page > 1) echo '<a href="?page=1">1</a> ... ';
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<span class="active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . '">' . $i . '</a>';
                            }
                        }
                        if ($end_page < $total_pages) echo ' ... <a href="?page=' . $total_pages . '">' . $total_pages . '</a>';
                        ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>" class="page-link">Next &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="history-section">
                    <div class="history-header">
                        <h3>📜 Subscription History</h3>
                        <p>You haven't subscribed to any plan yet. Choose a plan above to get started.</p>
                    </div>
                    <div class="empty-history">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
                        <p>No subscription history found.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="paymentModal" class="modal-overlay">
        <div class="payment-modal">
            <div class="modal-header">
                <h3>Pay with Flutterwave</h3>
                <div class="modal-close" id="closeModalBtn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
            </div>
            <div class="modal-body">
                <div class="flutterwave-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
                    <span>Secured by Flutterwave</span>
                </div>
                <div class="plan-summary" id="planSummary">
                    <p><strong>Plan:</strong> <span id="selectedPlan">-</span></p>
                    <p><strong>Billing:</strong> <span id="selectedBilling">Monthly</span></p>
                    <p><strong>Amount:</strong> ₦<span id="selectedAmount">0</span></p>
                </div>
                <form id="flutterwavePaymentForm">
                    <div class="form-group"><label>Full Name *</label><input type="text" id="fullName" required placeholder="John Doe"></div>
                    <div class="form-group"><label>Email Address *</label><input type="email" id="email" required placeholder="john@example.com"></div>
                    <div class="form-group"><label>Phone Number *</label><input type="tel" id="phone" required placeholder="08012345678"></div>
                    <button type="submit" class="btn btn-pay" id="payNowBtn">Proceed to Flutterwave</button>
                </form>
                <div class="remita-note" style="font-size: var(--text-xs); color: var(--text-muted); margin-top: var(--space-4); text-align:center;">
                    <?php if ($flwConfig['mode'] === 'test'): ?>
                        Test mode – use Flutterwave test cards only.
                    <?php elseif ($flwConfig['mode'] === 'live'): ?>
                        Secured checkout – real charges apply.
                    <?php else: ?>
                        Configure Flutterwave keys in Admin → Settings before paying.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ======================= Core UI =======================
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        html.setAttribute('data-theme', savedTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const cur = html.getAttribute('data-theme');
                const next = cur === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', next);
                localStorage.setItem('RD Vendora-theme', next);
            });
        }

        function toggleMobileSidebar() {
            const isOpen = sidebar.classList.contains('mobile-open');
            if (isOpen) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
        if (mobileToggle) mobileToggle.addEventListener('click', toggleMobileSidebar);
        if (overlay) overlay.addEventListener('click', toggleMobileSidebar);
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('collapsed');
                    toggleMobileSidebar();
                }
            });
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        document.addEventListener('click', (e) => {
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown && !userDropdown.contains(e.target)) userDropdown.classList.remove('open');
            else if (userDropdown && e.target.closest('.dropdown-trigger')) userDropdown.classList.toggle('open');
        });

        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const icons = { success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>', error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>', info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' };
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<div class="toast-icon">${icons[type] || icons.info}</div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 300); }, 3500);
        }

        function handleLogout() { if(confirm('Logout?')) window.location.href='logout'; }

        // ======================= Pricing & Billing Toggle =======================
        let activeBilling = 'monthly';
        const billingBtns = document.querySelectorAll('.tab-btn');
        const monthlyPrices = document.querySelectorAll('.monthly-price');
        const annualPrices = document.querySelectorAll('.annual-price');

        function updateBillingVisibility() {
            monthlyPrices.forEach(el => el.classList.toggle('hidden', activeBilling !== 'monthly'));
            annualPrices.forEach(el => el.classList.toggle('hidden', activeBilling !== 'annual'));
        }
        billingBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                billingBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeBilling = this.getAttribute('data-billing');
                updateBillingVisibility();
            });
        });
        updateBillingVisibility();

        // ======================= Flutterwave Modal Logic =======================
        let currentPlan = null;
        let currentAmount = 0;
        let currentBilling = 'monthly';
        const modal = document.getElementById('paymentModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const flutterwaveForm = document.getElementById('flutterwavePaymentForm');
        const selectedPlanSpan = document.getElementById('selectedPlan');
        const selectedBillingSpan = document.getElementById('selectedBilling');
        const selectedAmountSpan = document.getElementById('selectedAmount');

        function openPaymentModal(planCard) {
            const planName = planCard.getAttribute('data-plan');
            const isFree = planCard.getAttribute('data-free') === 'true';
            let price = activeBilling === 'monthly' ? parseFloat(planCard.getAttribute('data-price-monthly')) : parseFloat(planCard.getAttribute('data-price-annual'));
            
            if (isFree && price === 0) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="activate_free_plan"><input type="hidden" name="plan" value="${planName}"><input type="hidden" name="billing" value="${activeBilling}">`;
                document.body.appendChild(form);
                form.submit();
                return;
            }
            
            currentPlan = planName;
            currentAmount = price;
            currentBilling = activeBilling;
            selectedPlanSpan.textContent = currentPlan;
            selectedBillingSpan.textContent = currentBilling === 'monthly' ? 'Monthly' : 'Annual (20% off)';
            selectedAmountSpan.textContent = currentAmount.toLocaleString();
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        document.querySelectorAll('.plan-btn:not(:disabled)').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const planCard = btn.closest('.pricing-card');
                openPaymentModal(planCard);
            });
        });

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            flutterwaveForm.reset();
        }
        closeModalBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        flutterwaveForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            if (!fullName || !email || !phone) {
                showToast('error', 'Error', 'Please fill all fields.');
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="pay_with_flutterwave">
                <input type="hidden" name="plan" value="${currentPlan}">
                <input type="hidden" name="billing" value="${currentBilling}">
                <input type="hidden" name="amount" value="${currentAmount}">
                <input type="hidden" name="fullname" value="${fullName}">
                <input type="hidden" name="email" value="${email}">
                <input type="hidden" name="phone" value="${phone}">
            `;
            document.body.appendChild(form);
            form.submit();
        });

        function scrollToPlans() {
            document.getElementById('pricingGrid').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>