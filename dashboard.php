<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login?error=Not logged in');
    exit();
}

require_once 'includes/log_activity.php';
logUserActivity($_SESSION['user_id'], 'dashboard_view', 'dashboard.php', 'Viewed dashboard');

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';

// ---------- Load PHPMailer if available ----------
$phpmailer_available = function_exists('rdv_load_phpmailer') ? rdv_load_phpmailer() : false;
if (!$phpmailer_available && file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    $phpmailer_available = class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
}

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Create team tables if they don't exist ----------
$conn->query("CREATE TABLE IF NOT EXISTS store_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin','editor','viewer') DEFAULT 'viewer',
    invited_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_user (store_id, user_id),
    INDEX (store_id),
    INDEX (user_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS team_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin','editor','viewer') NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','accepted','expired') DEFAULT 'pending',
    invited_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX (token),
    INDEX (store_id),
    INDEX (status)
)");

// ---------- Get the NEWEST store for this user ----------
$stmt = $conn->prepare("SELECT id, store_name, status, store_slug FROM stores WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$storeResult = $stmt->get_result();
if ($storeResult->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$storeData = $storeResult->fetch_assoc();
$_SESSION['store_id'] = $storeData['id'];
$_SESSION['store_name'] = $storeData['store_name'];
$_SESSION['store_slug'] = trim((string) ($storeData['store_slug'] ?? ''));
$storeStatus = $storeData['status'];
$stmt->close();

// ---------- Determine if store access is restricted ----------
$storeRestricted = false;
$restrictionMessage = '';
$isSuspended = false;
$subscriptionStatus = 'active'; // Track subscription status for display

// First, update any expired subscriptions
$conn->query("UPDATE subscriptions SET status = 'expired' WHERE user_id = {$_SESSION['user_id']} AND status = 'active' AND end_date <= NOW()");

// 1. Check store status first
if ($storeStatus === 'pending') {
    $storeRestricted = true;
    $restrictionMessage = '⏳ Your store is pending approval by the administrator. All features are locked. Please contact support for assistance.';
} elseif ($storeStatus === 'inactive') {
    $storeRestricted = true;
    $restrictionMessage = '⛔ Your store has been suspended by the administrator. Please contact support to resolve this issue.';
} elseif ($storeStatus === 'pending_docs') {
    $storeRestricted = true;
    $restrictionMessage = '📄 Your company documents are under review. All features are locked until the admin approves your documents.';
} elseif ($storeStatus === 'active') {
    // 2. Check subscription status for active stores
    $hasSubscription = hasActiveSubscription($conn, $_SESSION['user_id']);
    if (!$hasSubscription) {
        $storeRestricted = true;
        $restrictionMessage = '🚫 Your account has been suspended because your subscription has expired. Please renew to restore full access.';
        $isSuspended = true;
        $subscriptionStatus = 'expired';
    } else {
        // Subscription is active – get plan name
        $stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $planRow = $stmt->get_result()->fetch_assoc();
        $activePlan = $planRow['plan'] ?? null;
        $stmt->close();
        $subscriptionStatus = 'active';
    }
} else {
    // Fallback: treat as restricted
    $storeRestricted = true;
    $restrictionMessage = '⛔ Your store is not accessible at this time. Please contact support.';
}

// ---------- Get user's display name ----------
if (!isset($_SESSION['fullname'])) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $_SESSION['fullname'] = $row['full_name'] ?? 'User';  // use correct column
    $stmt->close();
}

// ---------- Handle AJAX Invite Request ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($storeRestricted) {
            echo json_encode(['success' => false, 'message' => 'Store is restricted.']);
            exit();
        }

        if ($action === 'send_invite') {
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'viewer']) ? $_POST['role'] : 'viewer';
            if (!$email) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
                exit();
            }

            // Check if already a team member
            $checkStaff = $conn->prepare("SELECT 1 FROM store_staff WHERE store_id = ? AND user_id = (SELECT id FROM users WHERE email = ?)");
            $checkStaff->bind_param("is", $_SESSION['store_id'], $email);
            $checkStaff->execute();
            if ($checkStaff->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'User is already a team member.']);
                exit();
            }
            $checkStaff->close();

            // Remove any existing pending invite
            $deleteStmt = $conn->prepare("DELETE FROM team_invites WHERE store_id = ? AND email = ? AND status = 'pending'");
            $deleteStmt->bind_param("is", $_SESSION['store_id'], $email);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Generate token and store invite
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

            $stmt = $conn->prepare("INSERT INTO team_invites (store_id, email, role, token, invited_by, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssis", $_SESSION['store_id'], $email, $role, $token, $_SESSION['user_id'], $expires);
            if ($stmt->execute()) {
                $inviteBase = function_exists('rdv_url')
                    ? rdv_url('accept-invite')
                    : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/accept-invite');
                $inviteLink = $inviteBase . (str_contains($inviteBase, '?') ? '&' : '?') . 'token=' . urlencode($token);

                $emailSent = false;

                if ($phpmailer_available) {
                    try {
                        require_once APP_PATH . '/helpers/email_functions.php';
                        $inviterName = (string) ($_SESSION['fullname'] ?? '');
                        $emailSent = sendTeamInviteEmail($email, (string) ($_SESSION['store_name'] ?? 'Store'), $role, $inviteLink, $inviterName);
                    } catch (Exception $e) {
                        error_log("PHPMailer Error: " . $e->getMessage());
                    }
                }

                if ($emailSent) {
                    echo json_encode(['success' => true, 'message' => "✅ Invitation email sent to $email"]);
                } else {
                    $msg = $phpmailer_available 
                        ? "⚠️ Email could not be sent (SMTP error). Please share this link manually:\n$inviteLink"
                        : "📧 PHPMailer not installed. Run 'composer require phpmailer/phpmailer' to enable emails. For now, share this link manually:\n$inviteLink";
                    echo json_encode(['success' => true, 'message' => $msg]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: could not save invite.']);
            }
            $stmt->close();
            exit();
        }
        elseif ($action === 'cancel_invite') {
            $inviteId = intval($_POST['invite_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM team_invites WHERE id = ? AND store_id = ? AND status = 'pending'");
            $stmt->bind_param("ii", $inviteId, $_SESSION['store_id']);
            $deleted = $stmt->execute() && $stmt->affected_rows > 0;
            $stmt->close();
            echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Invite cancelled.' : 'Invite not found.']);
            exit();
        }
    }
    exit();
}

// ---------- Fetch team members and pending invites (only if not restricted) ----------
$teamMembers = [];
$pendingInvites = [];
if (!$storeRestricted) {
    $teamQuery = $conn->prepare("
        SELECT u.id, u.full_name AS name, u.email, ss.role, ss.created_at
        FROM store_staff ss
        JOIN users u ON ss.user_id = u.id
        WHERE ss.store_id = ?
        ORDER BY ss.created_at DESC
    ");
    if ($teamQuery) {
        $teamQuery->bind_param("i", $_SESSION['store_id']);
        if ($teamQuery->execute()) {
            $teamMembers = $teamQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $teamQuery->close();
    } else {
        error_log('dashboard team query prepare failed: ' . $conn->error);
    }

    $invitesQuery = $conn->prepare("SELECT id, email, role, created_at FROM team_invites WHERE store_id = ? AND status = 'pending' ORDER BY created_at DESC");
    if ($invitesQuery) {
        $invitesQuery->bind_param("i", $_SESSION['store_id']);
        if ($invitesQuery->execute()) {
            $pendingInvites = $invitesQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $invitesQuery->close();
    }
}

// ---------- Dashboard Metrics (only if not restricted) ----------
$total_revenue = 0;
$totalOrders = 0;
$activeCustomers = 0;
$totalProducts = 0;
$recentOrders = [];
$revenueLabels = $revenueData = $revenue30Labels = $revenue30Data = $revenue90Labels = $revenue90Data = [];
$categoryLabels = $categoryValues = [];

if (!$storeRestricted) {
    // Determine orders table structure dynamically
    $order_columns = [];
    $columns_check = $conn->query("SHOW COLUMNS FROM orders");
    if ($columns_check) {
        while ($col = $columns_check->fetch_assoc()) {
            $order_columns[] = $col['Field'];
        }
    }
    $vendor_id_col = null;
    if (in_array('store_id', $order_columns)) $vendor_id_col = 'store_id';
    elseif (in_array('seller_id', $order_columns)) $vendor_id_col = 'seller_id';
    elseif (in_array('vendor_id', $order_columns)) $vendor_id_col = 'vendor_id';
    elseif (in_array('user_id', $order_columns)) $vendor_id_col = 'user_id';

    // Total revenue (completed orders)
    if ($vendor_id_col && in_array('total_amount', $order_columns)) {
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders WHERE $vendor_id_col = ? AND status = 'completed'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['store_id']);
        $stmt->execute();
        $total_revenue = $stmt->get_result()->fetch_assoc()['total_revenue'] ?? 0;
        $stmt->close();
    }

    // Total orders
    if ($vendor_id_col) {
        $sql = "SELECT COUNT(*) as cnt FROM orders WHERE $vendor_id_col = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['store_id']);
        $stmt->execute();
        $totalOrders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
    }

    // Active customers (unique emails)
    if ($vendor_id_col && in_array('user_email', $order_columns)) {
        $sql = "SELECT COUNT(DISTINCT user_email) as cnt FROM orders WHERE $vendor_id_col = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['store_id']);
        $stmt->execute();
        $activeCustomers = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();
    }

    // Products count (using user_id, active only)
    $products_table_check = $conn->query("SHOW TABLES LIKE 'products'");
    if ($products_table_check && $products_table_check->num_rows > 0) {
        $prod_columns = [];
        $prod_cols = $conn->query("SHOW COLUMNS FROM products");
        while ($col = $prod_cols->fetch_assoc()) {
            $prod_columns[] = $col['Field'];
        }
        if (in_array('user_id', $prod_columns)) {
            $status_condition = in_array('status', $prod_columns) ? "AND status = 'active'" : "";
            $sql = "SELECT COUNT(*) as cnt FROM products WHERE user_id = ? $status_condition";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $totalProducts = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $stmt->close();
        }
    }

    // Recent orders (last 5)
    if ($vendor_id_col) {
        $select_fields = ['id', 'order_number', 'user_name', 'user_email', 'total_amount', 'status', 'payment_status', 'created_at'];
        $existing_fields = [];
        foreach ($select_fields as $field) {
            if (in_array($field, $order_columns)) {
                $existing_fields[] = $field;
            }
        }
        if (!in_array('order_number', $order_columns)) {
            $existing_fields[] = "CONCAT('ORD-', id) as order_number";
        }
        $select_str = implode(', ', $existing_fields);
        $sql = "SELECT $select_str FROM orders WHERE $vendor_id_col = ? ORDER BY created_at DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['store_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $has_order_items = $conn->query("SHOW TABLES LIKE 'order_items'")->num_rows > 0;
        while ($row = $result->fetch_assoc()) {
            if ($has_order_items) {
                $items_stmt = $conn->prepare("SELECT product_name as name, quantity as qty, price FROM order_items WHERE order_id = ?");
                $items_stmt->bind_param("i", $row['id']);
                $items_stmt->execute();
                $items_res = $items_stmt->get_result();
                $row['items'] = [];
                while ($item = $items_res->fetch_assoc()) {
                    $row['items'][] = $item;
                }
                $items_stmt->close();
            } else {
                $row['items'] = [];
            }
            if (!isset($row['total_amount']) && isset($row['total'])) {
                $row['total_amount'] = $row['total'];
            }
            if (!isset($row['total_amount']) || $row['total_amount'] == 0) {
                $calculated_total = 0;
                foreach ($row['items'] as $item) {
                    $calculated_total += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
                }
                $row['total_amount'] = $calculated_total;
            }
            if (!isset($row['payment_status'])) $row['payment_status'] = 'pending';
            if (!isset($row['status'])) $row['status'] = 'pending';
            $recentOrders[] = $row;
        }
        $stmt->close();
    }

    // Revenue data for charts (last 7, 30, 90 days)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenueLabels[] = date('M d', strtotime($date));
        if ($vendor_id_col && in_array('total_amount', $order_columns)) {
            $sql = "SELECT COALESCE(SUM(total_amount), 0) as daily_total FROM orders WHERE $vendor_id_col = ? AND status = 'completed' AND DATE(created_at) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $_SESSION['store_id'], $date);
            $stmt->execute();
            $daily = $stmt->get_result()->fetch_assoc()['daily_total'] ?? 0;
            $stmt->close();
            $revenueData[] = $daily;
        } else {
            $revenueData[] = 0;
        }
    }

    // Category sales (if order_items and products exist)
    if ($has_order_items) {
        $sql = "SELECT p.category, SUM(oi.quantity) as total_sold FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id INNER JOIN products p ON oi.product_id = p.id WHERE o.status = 'completed' AND o.$vendor_id_col = ? AND p.category IS NOT NULL AND p.category != '' GROUP BY p.category ORDER BY total_sold DESC LIMIT 6";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['store_id']);
        $stmt->execute();
        $catResult = $stmt->get_result();
        while ($row = $catResult->fetch_assoc()) {
            $categoryLabels[] = $row['category'];
            $categoryValues[] = (int)$row['total_sold'];
        }
        $stmt->close();
    }
    if (empty($categoryLabels)) {
        $categoryLabels = ['No data'];
        $categoryValues = [1];
    }
    $totalSold = array_sum($categoryValues);
    if ($totalSold > 0) {
        $categoryValues = array_map(function($v) use ($totalSold) { return round($v / $totalSold * 100, 1); }, $categoryValues);
    } else {
        $categoryValues = [100];
    }

    // 30 days revenue
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue30Labels[] = date('M d', strtotime($date));
        if ($vendor_id_col && in_array('total_amount', $order_columns)) {
            $sql = "SELECT COALESCE(SUM(total_amount), 0) as daily_total FROM orders WHERE $vendor_id_col = ? AND status = 'completed' AND DATE(created_at) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $_SESSION['store_id'], $date);
            $stmt->execute();
            $daily = $stmt->get_result()->fetch_assoc()['daily_total'] ?? 0;
            $stmt->close();
            $revenue30Data[] = $daily;
        } else {
            $revenue30Data[] = 0;
        }
    }

    // 90 days revenue
    for ($i = 89; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue90Labels[] = date('M d', strtotime($date));
        if ($vendor_id_col && in_array('total_amount', $order_columns)) {
            $sql = "SELECT COALESCE(SUM(total_amount), 0) as daily_total FROM orders WHERE $vendor_id_col = ? AND status = 'completed' AND DATE(created_at) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $_SESSION['store_id'], $date);
            $stmt->execute();
            $daily = $stmt->get_result()->fetch_assoc()['daily_total'] ?? 0;
            $stmt->close();
            $revenue90Data[] = $daily;
        } else {
            $revenue90Data[] = 0;
        }
    }
}
$conn->close();
$revenue = $total_revenue;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* ========== COMPLETE CSS ========== */
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
            --gradient-hero: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
            --text-xs: 0.75rem;
            --text-sm: 0.8125rem;
            --text-base: 0.9375rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --text-4xl: 2.25rem;
            --font-normal: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-bold: 700;
            --leading-tight: 1.25;
            --leading-snug: 1.375;
            --leading-normal: 1.5;
            --leading-relaxed: 1.625;
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
            --space-20: 5rem;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 28px;
            --radius-full: 9999px;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06),0 2px 4px rgba(0,0,0,0.04);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.08),0 4px 8px rgba(0,0,0,0.04);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.10),0 8px 16px rgba(0,0,0,0.04);
            --shadow-glow: 0 0 40px rgba(99,102,241,0.15);
            --transition-fast: 150ms cubic-bezier(0.4,0,0.2,1);
            --transition-base: 250ms cubic-bezier(0.4,0,0.2,1);
            --transition-slow: 350ms cubic-bezier(0.4,0,0.2,1);
            --transition-bounce: 500ms cubic-bezier(0.34,1.56,0.64,1);
            --z-dropdown: 100;
            --z-sticky: 200;
            --z-fixed: 300;
            --z-modal-backdrop: 400;
            --z-modal: 500;
            --z-toast: 800;
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
            --surface-primary: #14161f;
            --surface-secondary: #1a1d28;
            --surface-tertiary: #1e2130;
            --text-primary: #e8eaf0;
            --text-secondary: #9ca3b0;
            --text-muted: #6b7280;
            --border-primary: #2d3139;
            --border-secondary: #3a3f4a;
            --primary-light: rgba(99,102,241,0.15);
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.20);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.25),0 1px 2px rgba(0,0,0,0.20);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.30),0 2px 4px rgba(0,0,0,0.20);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.35),0 4px 8px rgba(0,0,0,0.25);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.40),0 8px 16px rgba(0,0,0,0.30);
            --shadow-glow: 0 0 40px rgba(99,102,241,0.20);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body {
            font-family: var(--font-sans);
            font-size: var(--text-base);
            line-height: var(--leading-normal);
            color: var(--text-primary);
            background: var(--bg-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background var(--transition-base), color var(--transition-base);
        }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; font-size: inherit; color: inherit; }
        input, select { font-family: inherit; font-size: inherit; color: inherit; }
        ul, ol { list-style: none; }
        img { max-width: 100%; display: block; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-secondary); border-radius: var(--radius-full); }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
        ::selection { background: var(--primary-light); color: var(--primary); }

        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: var(--z-fixed);
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
            flex-shrink: 0; gap: var(--space-3);
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: var(--space-3);
            font-weight: var(--font-bold); font-size: var(--text-lg);
            color: var(--text-primary); white-space: nowrap; overflow: hidden;
        }
                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
        .sidebar-brand-icon {
            width: 34px; height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: white; flex-shrink: 0;
        }
        .sidebar-brand-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-toggle {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-muted);
            transition: all var(--transition-fast); flex-shrink: 0;
            background: transparent; border: none; cursor: pointer;
        }
        .sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar.collapsed .sidebar-toggle svg { transform: rotate(180deg); }
        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: var(--space-3) var(--space-3); }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px; font-weight: var(--font-semibold);
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-muted); white-space: nowrap;
            transition: all var(--transition-fast);
            margin-top: var(--space-2);
        }
        .sidebar.collapsed .sidebar-section-title { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        .sidebar-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary); font-size: var(--text-sm); font-weight: var(--font-medium);
            transition: all var(--transition-fast);
            white-space: nowrap; cursor: pointer; text-decoration: none; margin-bottom: 1px;
        }
        .sidebar-link:hover:not(.disabled) { background: var(--bg-hover); color: var(--text-primary); }
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
            border-radius: var(--radius-md); transition: background var(--transition-fast); cursor: pointer;
        }
        .sidebar-user:hover { background: var(--bg-hover); }
        .sidebar-user-avatar { width: 34px; height: 34px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .sidebar-user-info { flex: 1; min-width: 0; transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-user-info { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: calc(var(--z-fixed) - 1); opacity: 0; pointer-events: none; transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }

        /* Suspended badge */
        .suspended-badge {
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

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition-slow); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        /* Topbar */
        .topbar {
            position: sticky; top: 0; height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 var(--space-6); z-index: var(--z-sticky);
            gap: var(--space-4); backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar { background: rgba(255,255,255,0.85); }
        [data-theme="dark"] .topbar { background: rgba(20,22,31,0.85); }
        .topbar-left { display: flex; align-items: center; gap: var(--space-3); }
        .mobile-sidebar-toggle {
            display: none; width: 38px; height: 38px;
            align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary);
            transition: all var(--transition-fast); flex-shrink: 0;
        }
        .mobile-sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-search { flex: 1; max-width: 420px; position: relative; }
        .topbar-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; pointer-events: none; }
        .topbar-search input {
            width: 100%; padding: var(--space-2) var(--space-4) var(--space-2) 40px;
            background: var(--bg-tertiary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-md); font-size: var(--text-sm); outline: none;
            transition: all var(--transition-fast); color: var(--text-primary);
        }
        .topbar-search input::placeholder { color: var(--text-muted); }
        .topbar-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: var(--bg-secondary); }
        .topbar-actions { display: flex; align-items: center; gap: var(--space-2); }
        .topbar-btn {
            position: relative; width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary);
            transition: all var(--transition-fast); flex-shrink: 0;
        }
        .topbar-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-btn-badge {
            position: absolute; top: 6px; right: 6px; width: 8px; height: 8px;
            background: var(--error); border-radius: var(--radius-full); border: 2px solid var(--bg-secondary);
        }
        .theme-toggle {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary); transition: all var(--transition-fast); flex-shrink: 0;
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        [data-theme="light"] .theme-toggle .icon-moon { display: none; }
        [data-theme="dark"] .theme-toggle .icon-sun { display: none; }
        .topbar-user {
            display: flex; align-items: center; gap: var(--space-2);
            padding: var(--space-1) var(--space-3) var(--space-1) var(--space-1);
            border-radius: var(--radius-md); cursor: pointer; transition: background var(--transition-fast);
        }
        .topbar-user:hover { background: var(--bg-hover); }
        .topbar-user-avatar { width: 32px; height: 32px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .topbar-user-info { display: flex; flex-direction: column; line-height: 1.2; }
        .topbar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); }
        .topbar-user-role { font-size: var(--text-xs); color: var(--text-muted); }

        /* Dropdowns */
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0; min-width: 240px;
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-xl);
            z-index: var(--z-dropdown); opacity: 0; pointer-events: none;
            transform: translateY(-8px); transition: all var(--transition-fast); overflow: hidden;
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-header { padding: var(--space-4); border-bottom: 1px solid var(--border-primary); display: flex; align-items: center; justify-content: space-between; }
        .dropdown-header h4 { font-size: var(--text-sm); font-weight: var(--font-semibold); }
        .dropdown-header a { font-size: var(--text-xs); color: var(--primary); cursor: pointer; }
        .dropdown-header a:hover { text-decoration: underline; }
        .dropdown-item {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); font-size: var(--text-sm);
            color: var(--text-secondary); transition: all var(--transition-fast); cursor: pointer; text-decoration: none;
        }
        .dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown-item svg { flex-shrink: 0; width: 16px; height: 16px; }
        .dropdown-divider { height: 1px; background: var(--border-primary); margin: var(--space-1) 0; }
        .notification-list { max-height: 320px; overflow-y: auto; }
        .notification-item {
            display: flex; align-items: flex-start; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); cursor: pointer;
            transition: background var(--transition-fast); border-bottom: 1px solid var(--border-primary);
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background: var(--bg-hover); }
        .notification-item.unread { background: var(--primary-light); }
        .notification-dot { width: 8px; height: 8px; background: var(--primary); border-radius: var(--radius-full); flex-shrink: 0; margin-top: 6px; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); }
        .notification-text { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        .notification-time { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        /* Page content */
        .page-content { flex: 1; padding: var(--space-6); overflow-y: auto; }
        .page-header { margin-bottom: var(--space-6); }
        .page-title { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); letter-spacing: -0.02em; }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: var(--space-1); }

        /* Quick actions */
        .quick-actions { display: grid; grid-template-columns: repeat(4,1fr); gap: var(--space-4); margin-bottom: var(--space-6); }
        .quick-action-card {
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); padding: var(--space-5);
            cursor: pointer; transition: all var(--transition-base);
            display: flex; align-items: center; gap: var(--space-4);
            text-decoration: none; color: inherit;
        }
        .quick-action-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: var(--border-secondary); }
        .quick-action-icon { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg); flex-shrink: 0; }
        .quick-action-info { flex: 1; min-width: 0; }
        .quick-action-title { font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); }
        .quick-action-desc { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }

        /* Stats grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: var(--space-5); margin-bottom: var(--space-6); }
        .stat-card {
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); padding: var(--space-5);
            transition: all var(--transition-base); position: relative; overflow: hidden;
        }
        .stat-card:hover { box-shadow: var(--shadow-md); border-color: var(--border-secondary); transform: translateY(-1px); }
        .stat-card::after { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
        .stat-card.purple::after { background: var(--primary); }
        .stat-card.green::after { background: var(--success); }
        .stat-card.amber::after { background: var(--warning); }
        .stat-card.blue::after { background: var(--info); }
        .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-3); }
        .stat-icon { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-lg); }
        .stat-icon.purple { background: var(--primary-light); color: var(--primary); }
        .stat-icon.green { background: var(--success-light); color: var(--success-dark); }
        .stat-icon.amber { background: var(--warning-light); color: var(--warning-dark); }
        .stat-icon.blue { background: var(--info-light); color: var(--info-dark); }
        .stat-trend { display: inline-flex; align-items: center; gap: 3px; font-size: var(--text-xs); font-weight: var(--font-semibold); padding: 3px 8px; border-radius: var(--radius-sm); }
        .stat-trend.up { background: var(--success-light); color: var(--success-dark); }
        .stat-trend.down { background: var(--error-light); color: var(--error-dark); }
        .stat-value { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); margin-bottom: 2px; letter-spacing: -0.01em; }
        .stat-label { font-size: var(--text-sm); color: var(--text-secondary); }

        /* Charts */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-6); margin-bottom: var(--space-6); }
        .chart-card { background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-lg); padding: var(--space-5); overflow: hidden; }
        .chart-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-3); }
        .chart-title { font-size: var(--text-base); font-weight: var(--font-semibold); color: var(--text-primary); }
        .chart-period { display: flex; gap: 2px; background: var(--bg-tertiary); border-radius: var(--radius-md); padding: 3px; }
        .chart-period button { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); font-weight: var(--font-medium); color: var(--text-secondary); border-radius: var(--radius-sm); transition: all var(--transition-fast); white-space: nowrap; border: none; background: transparent; cursor: pointer; }
        .chart-period button.active { background: var(--bg-secondary); color: var(--text-primary); box-shadow: var(--shadow-xs); font-weight: var(--font-semibold); }
        .chart-wrapper { position: relative; height: 280px; width: 100%; }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }

        /* Tables */
        .section-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); }
        .section-heading h3 { font-size: var(--text-lg); font-weight: var(--font-semibold); color: var(--text-primary); }
        .view-all-link { display: inline-flex; align-items: center; gap: var(--space-1); font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--primary); transition: color var(--transition-fast); cursor: pointer; text-decoration: none; }
        .view-all-link:hover { color: var(--primary-hover); }
        .table-container { overflow-x: auto; border-radius: var(--radius-lg); border: 1px solid var(--border-primary); background: var(--bg-secondary); }
        .data-table { width: 100%; border-collapse: collapse; font-size: var(--text-sm); }
        .data-table thead th { padding: var(--space-3) var(--space-4); font-weight: var(--font-semibold); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); background: var(--bg-tertiary); border-bottom: 1px solid var(--border-primary); text-align: left; }
        .data-table tbody td { padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-primary); color: var(--text-primary); vertical-align: middle; }
        .data-table tbody tr:hover { background: var(--bg-hover); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; font-size: 11px; font-weight: var(--font-semibold); border-radius: var(--radius-full); white-space: nowrap; }
        .badge-success { background: var(--success-light); color: var(--success-dark); }
        .badge-warning { background: var(--warning-light); color: var(--warning-dark); }
        .badge-info { background: var(--info-light); color: var(--info-dark); }
        .badge-error { background: var(--error-light); color: var(--error-dark); }
        .btn-sm { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); font-weight: var(--font-medium); border-radius: var(--radius-sm); cursor: pointer; transition: all var(--transition-fast); border: 1px solid var(--border-primary); background: var(--bg-secondary); color: var(--text-secondary); }
        .btn-sm:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-secondary); }

        /* Modals */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: var(--z-modal-backdrop); display: flex; align-items: center; justify-content: center;
            padding: var(--space-6); opacity: 0; visibility: hidden; transition: all var(--transition-base);
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal {
            background: var(--bg-secondary); border-radius: var(--radius-xl); border: 1px solid var(--border-primary);
            width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto;
            transform: scale(0.95) translateY(20px); transition: transform var(--transition-bounce); box-shadow: var(--shadow-xl);
        }
        .modal-overlay.active .modal { transform: scale(1) translateY(0); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-primary); }
        .modal-title { font-size: var(--text-lg); font-weight: var(--font-bold); }
        .modal-close { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); color: var(--text-muted); transition: all var(--transition-fast); cursor: pointer; border: none; background: transparent; }
        .modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
        .modal-body { padding: var(--space-6); }
        .modal-footer { display: flex; justify-content: flex-end; gap: var(--space-3); padding: var(--space-4) var(--space-6); border-top: 1px solid var(--border-primary); }
        .form-group { margin-bottom: var(--space-4); }
        .form-label { display: block; font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); margin-bottom: var(--space-2); }
        .form-input, .form-select { width: 100%; padding: var(--space-3) var(--space-4); font-size: var(--text-sm); color: var(--text-primary); background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-md); transition: all var(--transition-fast); outline: none; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2); padding: var(--space-3) var(--space-5); font-size: var(--text-sm); font-weight: var(--font-semibold); border-radius: var(--radius-md); transition: all var(--transition-fast); cursor: pointer; white-space: nowrap; border: 1px solid transparent; }
        .btn-primary { background: var(--gradient-primary); color: var(--text-inverse); border: none; box-shadow: 0 2px 8px rgba(99,102,241,0.25); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(99,102,241,0.35); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-primary); }
        .btn-ghost:hover { background: var(--bg-hover); color: var(--text-primary); }

        /* Toasts */
        .toast-container { position: fixed; top: calc(var(--topbar-height) + var(--space-4)); right: var(--space-4); z-index: var(--z-toast); display: flex; flex-direction: column; gap: var(--space-3); }
        .toast {
            display: flex; align-items: center; gap: var(--space-3); padding: var(--space-4);
            background: var(--bg-secondary); border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary); box-shadow: var(--shadow-xl);
            min-width: 300px; transform: translateX(120%);
            animation: toastSlideIn 0.4s ease forwards;
        }
        .toast.removing { animation: toastSlideOut 0.3s ease forwards; }
        .toast-icon { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .toast-success .toast-icon { background: var(--success-light); color: var(--success); }
        .toast-error .toast-icon { background: var(--error-light); color: var(--error); }
        .toast-info .toast-icon { background: var(--info-light); color: var(--info); }
        .toast-content { flex: 1; }
        .toast-title { font-weight: var(--font-semibold); color: var(--text-primary); }
        .toast-message { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        @keyframes toastSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }

        /* Animations */
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; }
        .delay-100 { animation-delay: 100ms; opacity: 0; }
        .delay-200 { animation-delay: 200ms; opacity: 0; }
        .delay-300 { animation-delay: 300ms; opacity: 0; }
        .delay-400 { animation-delay: 400ms; opacity: 0; }
        .delay-500 { animation-delay: 500ms; opacity: 0; }
        .delay-600 { animation-delay: 600ms; opacity: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

        /* Restriction banner */
        .restriction-banner {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .restriction-banner.warning { background: #fef2f2; border-left-color: #dc2626; }
        .restriction-banner .icon { font-size: 28px; }
        .restriction-banner .message { flex: 1; color: #92400e; }
        .restriction-banner.warning .message { color: #991b1b; }
        .restriction-banner .message strong { display: block; font-size: 0.95rem; margin-bottom: 4px; }
        .restriction-banner .contact-btn {
            background: #dc2626;
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .restriction-banner .contact-btn:hover { background: #b91c1c; transform: translateY(-2px); }

        /* Alert (for success message) */
        .alert {
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-3);
            border-left: 4px solid;
        }
        .alert-success { background: var(--success-light); color: var(--success-dark); border-left-color: var(--success); }

        /* Team section */
        .team-section {
            margin-top: 2rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            overflow: hidden;
        }
        .team-header {
            padding: 1rem 1.5rem;
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-primary);
            font-weight: 600;
        }
        .team-list {
            padding: 1rem 1.5rem;
        }
        .team-member, .pending-invite {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-primary);
        }
        .member-info, .invite-info { flex: 1; }
        .member-role { font-size: 0.75rem; color: var(--text-muted); }
        .btn-cancel-invite { color: var(--error); font-size: 0.75rem; cursor: pointer; background: none; border: none; }
        .btn-cancel-invite:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: var(--z-fixed); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-xl); }
            .sidebar.collapsed { width: var(--sidebar-width); transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
            .quick-actions { grid-template-columns: repeat(2, 1fr); gap: var(--space-3); }
            .charts-grid { grid-template-columns: 1fr; }
            .topbar-user-info { display: none; }
            .page-content { padding: var(--space-4); }
            .stat-value { font-size: var(--text-2xl); }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
            .topbar-search { display: none; }
            .topbar { padding: 0 var(--space-3); }
            .page-title { font-size: var(--text-xl); }
            .restriction-banner { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="./" class="sidebar-brand">
                <img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard" class="sidebar-link active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /></svg>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="analytics" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
                <span class="sidebar-link-text">Analytics</span>
            </a>
            <a href="products" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="sidebar-link-text">Products</span>
            </a>
            <a href="orders" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" /></svg>
                <span class="sidebar-link-text">Orders</span>
            </a>
            <a href="customers" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                <span class="sidebar-link-text">Customers</span>
            </a>
            <div class="sidebar-section-title">Store</div>
            <a href="<?= htmlspecialchars((!empty($_SESSION['store_slug']) && function_exists('rdv_store_url') ? rdv_store_url(['id' => (int) ($_SESSION['store_id'] ?? 0), 'store_slug' => (string) $_SESSION['store_slug']]) : 'storefront'), ENT_QUOTES, 'UTF-8') ?>" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>"<?= (!empty($_SESSION['store_slug']) && function_exists('rdv_store_url') && !($isSuspended || $storeRestricted)) ? ' target="_blank" rel="noopener"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>
                <span class="sidebar-link-text">Storefront</span>
            </a>
            <a href="settings" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
                <span class="sidebar-link-text">Store Settings</span>
            </a>
            <a href="subscription" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                <span class="sidebar-link-text">Subscription</span>
            </a>
            <a href="vendor-chat" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span class="sidebar-link-text">Chat</span>
            </a>
            <a href="vendor-communication" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span class="sidebar-link-text">Communication</span>
            </a>
            <a href="notifications" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="sidebar-link-text">Notifications</span>
            </a>
            <div class="sidebar-section-title">AI Tools</div>
            <a href="ai-chat" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2a10 10 0 1 0 10 10 10 10 0 0 0-10-10zM12 6v4M12 16h.01"/><line x1="12" y1="12" x2="12" y2="12"/></svg>
                <span class="sidebar-link-text">AI Chat</span>
            </a>
            <div class="sidebar-section-title">Account</div>
            <a href="profile" class="sidebar-link <?= ($isSuspended || $storeRestricted) ? 'disabled' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
                <span class="sidebar-link-text">Profile</span>
            </a>
            <a href="#" class="sidebar-link" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" /></svg>
                <span class="sidebar-link-text">Logout</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="sidebar-user-avatar">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">
                        <?= htmlspecialchars($_SESSION['fullname']) ?>
                        <?php if ($isSuspended): ?>
                            <span class="suspended-badge">Expired</span>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-user-role">
                        <?php if ($_SESSION['store_name']): ?>
                            🏪 <?= htmlspecialchars($_SESSION['store_name']) ?>
                        <?php else: ?>
                            <a href="create-store" style="color: var(--primary);">Create Store</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-content" id="mainContent">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
                </button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                    <input type="text" id="globalSearch" placeholder="Search products, orders, customers...">
                </div>
            </div>
            <div class="topbar-actions">
                <?php if (!$storeRestricted && isset($activePlan) && $activePlan): ?>
                <span style="background: var(--primary-light); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; margin-right: 10px;">
                    🚀 <?= htmlspecialchars($activePlan) ?>
                </span>
                <?php endif; ?>
                <?php if ($isSuspended): ?>
                    <span class="suspended-badge" style="margin-right: 10px;">Expired</span>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5" /><line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" /><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" /><line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" /><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" /></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>
                </button>
                <div class="dropdown" id="notificationDropdown">
                    <button class="topbar-btn dropdown-trigger" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                        <span class="topbar-btn-badge"></span>
                    </button>
                    <div class="dropdown-menu" style="width:340px;">
                        <div class="dropdown-header"><h4>Notifications</h4><a onclick="markAllRead()">Mark all read</a></div>
                        <div class="notification-list" id="notificationList"></div>
                    </div>
                </div>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role">
                                <?php if ($_SESSION['store_name']): ?>
                                    Store: <?= htmlspecialchars($_SESSION['store_name']) ?>
                                <?php else: ?>
                                    <a href="create-store" style="color:var(--primary);">Create Store</a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><polyline points="6 9 12 15 18 9" /></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profile</a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <?php if (isset($_GET['doc_uploaded']) && $_GET['doc_uploaded'] == 1 && !$storeRestricted): ?>
                <div class="alert alert-success animate-fade-in-up">
                    ✅ Your documents have been submitted successfully. They are now under review. You will be notified once approved.
                </div>
            <?php endif; ?>

            <?php if ($storeRestricted): ?>
                <div class="restriction-banner <?= ($storeStatus === 'inactive' || $storeStatus === 'pending' || $isSuspended) ? 'warning' : '' ?>">
                    <div class="icon"><?= $isSuspended ? '🚫' : ($storeStatus === 'pending' ? '⏳' : ($storeStatus === 'pending_docs' ? '📄' : ($storeStatus === 'inactive' ? '⛔' : '🔒'))) ?></div>
                    <div class="message">
                        <strong>
                            <?php if ($isSuspended): ?>
                                Subscription Expired
                            <?php elseif ($storeStatus === 'pending'): ?>
                                Store Pending Approval
                            <?php elseif ($storeStatus === 'pending_docs'): ?>
                                Documents Under Review
                            <?php elseif ($storeStatus === 'inactive'): ?>
                                Store Suspended
                            <?php else: ?>
                                Subscription Inactive
                            <?php endif; ?>
                        </strong>
                        <p><?= htmlspecialchars($restrictionMessage) ?></p>
                    </div>
                    <?php if ($isSuspended): ?>
                        <a href="subscription" class="contact-btn" style="background: #6366f1;">Renew Now →</a>
                    <?php else: ?>
                        <a href="contact" class="contact-btn">Contact Us →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$storeRestricted): ?>
                <!-- Full dashboard content -->
                <div class="page-header animate-fade-in-up"><h1 class="page-title">Dashboard</h1><p class="page-subtitle">Welcome back! Here's what's happening with your store.</p></div>
                <?php
                $dashStoreUrl = '';
                if (function_exists('rdv_store_url')) {
                    if (!empty($_SESSION['store_slug'])) {
                        $dashStoreUrl = rdv_store_url(['id' => (int) ($_SESSION['store_id'] ?? 0), 'store_slug' => (string) $_SESSION['store_slug']]);
                    } elseif (!empty($_SESSION['store_id']) && function_exists('rdv_fetch_store_by_id')) {
                        $dashRow = rdv_fetch_store_by_id($conn, (int) $_SESSION['store_id'], false);
                        if ($dashRow) {
                            $dashStoreUrl = rdv_store_url($dashRow);
                            $_SESSION['store_slug'] = $dashRow['store_slug'] ?? '';
                        }
                    }
                }
                if ($dashStoreUrl !== ''):
                ?>
                <div class="animate-fade-in-up" id="my-store-url" style="margin-bottom:1.25rem;padding:1rem 1.25rem;border:1px solid var(--border-primary);border-radius:12px;background:var(--bg-secondary);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;">
                    <div>
                        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:0.25rem;">My Store URL</div>
                        <a id="dashStoreUrlLink" href="<?= htmlspecialchars($dashStoreUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;word-break:break-all;"><?= htmlspecialchars($dashStoreUrl, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                        <button type="button" class="btn btn-outline btn-sm" id="dashCopyStoreUrl">Copy URL</button>
                        <a href="<?= htmlspecialchars($dashStoreUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open Store</a>
                        <a href="settings#my-store-url" class="btn btn-ghost btn-sm">Edit Store URL</a>
                    </div>
                </div>
                <script>
                (function(){
                    var btn = document.getElementById('dashCopyStoreUrl');
                    var link = document.getElementById('dashStoreUrlLink');
                    if (btn && link) btn.addEventListener('click', async function(){
                        try { await navigator.clipboard.writeText(link.href); btn.textContent='Copied!'; setTimeout(function(){btn.textContent='Copy URL';},1500); }
                        catch(e){ prompt('Copy this store URL:', link.href); }
                    });
                })();
                </script>
                <?php endif; ?>
                <div class="quick-actions">
                    <a href="products" class="quick-action-card animate-fade-in-up delay-100"><div class="quick-action-icon" style="background:var(--primary-light);color:var(--primary);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div><div class="quick-action-info"><div class="quick-action-title">Add Product</div><div class="quick-action-desc">Create new product</div></div></a>
                    <div class="quick-action-card animate-fade-in-up delay-200" onclick="openInviteModal()"><div class="quick-action-icon" style="background:var(--success-light);color:var(--success-dark);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div><div class="quick-action-info"><div class="quick-action-title">Invite Team</div><div class="quick-action-desc">Add staff members</div></div></div>
                    <a href="<?= htmlspecialchars($dashStoreUrl !== '' ? $dashStoreUrl : 'storefront', ENT_QUOTES, 'UTF-8') ?>" class="quick-action-card animate-fade-in-up delay-300"<?= $dashStoreUrl !== '' ? ' target="_blank" rel="noopener"' : '' ?>><div class="quick-action-icon" style="background:var(--warning-light);color:var(--warning-dark);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div><div class="quick-action-info"><div class="quick-action-title">View Store</div><div class="quick-action-desc">See your storefront</div></div></a>
                    <div class="quick-action-card animate-fade-in-up delay-400" onclick="showToast('info','Coming Soon','Reports feature will be available soon.')"><div class="quick-action-icon" style="background:var(--info-light);color:var(--info-dark);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div><div class="quick-action-info"><div class="quick-action-title">Reports</div><div class="quick-action-desc">View analytics</div></div></div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card purple animate-fade-in-up delay-100"><div class="stat-header"><div class="stat-icon purple"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><span class="stat-trend up">▲ <?= round($totalOrders > 0 ? (rand(5,20)) : 0) ?>%</span></div><div class="stat-value">₦ <?= number_format($revenue, 2) ?></div><div class="stat-label">Total Revenue</div></div>
                    <div class="stat-card green animate-fade-in-up delay-200"><div class="stat-header"><div class="stat-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div><span class="stat-trend up">▲ <?= round($totalOrders > 0 ? (rand(5,15)) : 0) ?>%</span></div><div class="stat-value"><?= $totalOrders ?></div><div class="stat-label">Total Orders</div></div>
                    <div class="stat-card amber animate-fade-in-up delay-300"><div class="stat-header"><div class="stat-icon amber"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><span class="stat-trend up">▲ <?= round($activeCustomers > 0 ? (rand(5,20)) : 0) ?>%</span></div><div class="stat-value"><?= $activeCustomers ?></div><div class="stat-label">Active Customers</div></div>
                    <div class="stat-card blue animate-fade-in-up delay-400"><div class="stat-header"><div class="stat-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div><span class="stat-trend down">▼ <?= round($totalProducts > 0 ? (rand(1,8)) : 0) ?>%</span></div><div class="stat-value"><?= $totalProducts ?></div><div class="stat-label">Products Listed</div></div>
                </div>

                <div class="charts-grid">
                    <div class="chart-card animate-fade-in-up delay-300"><div class="chart-header"><h3 class="chart-title">Revenue Overview</h3><div class="chart-period" id="revenuePeriod"><button class="active" data-period="7">7D</button><button data-period="30">30D</button><button data-period="90">90D</button></div></div><div class="chart-wrapper"><canvas id="revenueChart"></canvas></div></div>
                    <div class="chart-card animate-fade-in-up delay-400"><div class="chart-header"><h3 class="chart-title">Sales by Category</h3></div><div class="chart-wrapper"><canvas id="categoryChart"></canvas></div></div>
                </div>

                <div class="animate-fade-in-up delay-500">
                    <div class="section-heading"><h3>Recent Orders</h3><a href="orders" class="view-all-link">View All <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="9 18 15 12 9 6"/></svg></a></div>
                    <div class="table-container">
                        <table class="data-table"><thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody id="recentOrdersBody"></tbody></table>
                    </div>
                </div>

                <!-- Team Management Section -->
                <div class="team-section animate-fade-in-up delay-600">
                    <div class="team-header">👥 Team Management</div>
                    <div class="team-list">
                        <?php if (empty($teamMembers) && empty($pendingInvites)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 2rem;">No team members yet. Invite your first colleague using the "Invite Team" button.</div>
                        <?php else: ?>
                            <div style="font-weight: 600; margin-bottom: 0.5rem;">Current Team Members</div>
                            <?php foreach ($teamMembers as $member): ?>
                                <div class="team-member">
                                    <div class="member-info">
                                        <strong><?= htmlspecialchars($member['fullname']) ?></strong> (<?= htmlspecialchars($member['email']) ?>)
                                        <div class="member-role">Role: <?= ucfirst($member['role']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!empty($pendingInvites)): ?>
                                <div style="font-weight: 600; margin: 1rem 0 0.5rem;">⏳ Pending Invites</div>
                                <?php foreach ($pendingInvites as $invite): ?>
                                    <div class="pending-invite" data-invite-id="<?= $invite['id'] ?>">
                                        <div class="invite-info">
                                            <?= htmlspecialchars($invite['email']) ?> (Role: <?= ucfirst($invite['role']) ?>)
                                            <div class="member-role">Invited on <?= date('M d, Y', strtotime($invite['created_at'])) ?></div>
                                        </div>
                                        <button class="btn-cancel-invite" onclick="cancelInvite(<?= $invite['id'] ?>, this)">Cancel</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Restricted view -->
                <div class="page-header"><h1 class="page-title">Dashboard</h1><p class="page-subtitle">Your access is temporarily restricted.</p></div>
                <div style="background: var(--bg-secondary); border-radius: 16px; padding: 60px 20px; text-align: center; margin-top: 24px;">
                    <div style="font-size: 64px; margin-bottom: 16px;">
                        <?= $isSuspended ? '🚫' : ($storeStatus === 'pending' ? '⏳' : ($storeStatus === 'pending_docs' ? '📄' : ($storeStatus === 'inactive' ? '⛔' : '🔒'))) ?>
                    </div>
                    <h3 style="font-size: 20px; margin-bottom: 8px;">
                        <?php if ($isSuspended): ?>
                            Subscription Expired
                        <?php elseif ($storeStatus === 'pending'): ?>
                            Store Under Review
                        <?php elseif ($storeStatus === 'pending_docs'): ?>
                            Documents Under Review
                        <?php elseif ($storeStatus === 'inactive'): ?>
                            Store Suspended
                        <?php else: ?>
                            Subscription Required
                        <?php endif; ?>
                    </h3>
                    <p style="color: var(--text-muted); max-width: 400px; margin: 0 auto;"><?= htmlspecialchars($restrictionMessage) ?></p>
                    <?php if ($isSuspended): ?>
                        <a href="subscription" class="btn btn-primary" style="margin-top: 24px;">Renew Subscription</a>
                    <?php elseif ($storeStatus === 'active' && strpos($restrictionMessage, 'subscription') !== false): ?>
                        <a href="subscription" class="btn btn-primary" style="margin-top: 24px;">View Plans</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invite Team Modal -->
    <div class="modal-overlay" id="inviteModal">
        <div class="modal">
            <div class="modal-header"><h3 class="modal-title">Invite Team Member</h3><button class="modal-close" onclick="closeInviteModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Email Address</label><input type="email" id="inviteEmail" class="form-input" placeholder="colleague@example.com"></div>
                <div class="form-group"><label class="form-label">Role</label><select id="inviteRole" class="form-select"><option value="admin">Admin (full access)</option><option value="editor">Editor (manage products/orders)</option><option value="viewer">Viewer (read-only)</option></select></div>
            </div>
            <div class="modal-footer"><button class="btn btn-ghost" onclick="closeInviteModal()">Cancel</button><button class="btn btn-primary" id="sendInviteBtn">Send Invite</button></div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal-overlay" id="orderModal"><div class="modal"><div class="modal-header"><h3 class="modal-title">Order Details</h3><button class="modal-close" onclick="closeOrderModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div><div class="modal-body" id="orderModalBody"></div><div class="modal-footer"><button class="btn btn-ghost" onclick="closeOrderModal()">Close</button></div></div></div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Data from PHP
        const revenueData7 = { labels: <?= json_encode($revenueLabels) ?>, values: <?= json_encode($revenueData) ?> };
        const revenueData30 = { labels: <?= json_encode($revenue30Labels) ?>, values: <?= json_encode($revenue30Data) ?> };
        const revenueData90 = { labels: <?= json_encode($revenue90Labels) ?>, values: <?= json_encode($revenue90Data) ?> };
        const categoryData = { labels: <?= json_encode($categoryLabels) ?>, values: <?= json_encode($categoryValues) ?> };
        const storeRestricted = <?= $storeRestricted ? 'true' : 'false' ?>;

        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const icons = { success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>', error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>', info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>' };
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<div class="toast-icon">${icons[type] || icons.info}</div><div class="toast-content"><div class="toast-title">${escapeHtml(title)}</div><div class="toast-message">${escapeHtml(message)}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.classList.add('removing'); setTimeout(() => toast.remove(), 300); }, 3500);
        }

        function escapeHtml(str) { if (!str) return ''; return String(str).replace(/[&<>]/g, function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }

        function openInviteModal() { document.getElementById('inviteModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
        function closeInviteModal() { document.getElementById('inviteModal').classList.remove('active'); document.body.style.overflow = ''; }
        document.getElementById('inviteModal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeInviteModal(); });

        document.getElementById('sendInviteBtn')?.addEventListener('click', async function() {
            const email = document.getElementById('inviteEmail').value.trim();
            const role = document.getElementById('inviteRole').value;
            if (!email) { showToast('error', 'Error', 'Please enter an email address.'); return; }
            if (!email.includes('@')) { showToast('error', 'Invalid Email', 'Please enter a valid email address.'); return; }

            const btn = this;
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Sending...';

            try {
                const formData = new FormData();
                formData.append('action', 'send_invite');
                formData.append('email', email);
                formData.append('role', role);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });

                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server error:', text);
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                if (data.success) {
                    showToast('success', 'Invitation Sent', data.message);
                    closeInviteModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Failed', data.message);
                }
            } catch (err) {
                console.error('Invite error:', err);
                showToast('error', 'Network Error', err.message || 'Could not send invite. Check console for details.');
            } finally {
                btn.disabled = false;
                btn.innerText = originalText;
            }
        });

        async function cancelInvite(inviteId, btnElement) {
            if (!confirm('Cancel this invitation?')) return;
            const originalText = btnElement.innerText;
            btnElement.innerText = '...';
            btnElement.disabled = true;
            try {
                const formData = new FormData();
                formData.append('action', 'cancel_invite');
                formData.append('invite_id', inviteId);
                const resp = await fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
                const data = await resp.json();
                if (data.success) {
                    showToast('success', 'Cancelled', 'Invite has been cancelled.');
                    btnElement.closest('.pending-invite')?.remove();
                } else { showToast('error', 'Error', data.message); }
            } catch(e) { showToast('error', 'Error', 'Could not cancel invite.'); }
            finally { btnElement.disabled = false; btnElement.innerText = originalText; }
        }

        // Chart rendering
        let revenueChart, categoryChart;
        function getChartColors() { const style=getComputedStyle(document.documentElement); return { textColor: style.getPropertyValue('--text-muted').trim(), gridColor: style.getPropertyValue('--border-primary').trim() }; }
        function createRevenueChart(data) { const ctx=document.getElementById('revenueChart')?.getContext('2d'); if(!ctx) return; const colors=getChartColors(); const grad=ctx.createLinearGradient(0,0,0,280); grad.addColorStop(0,'rgba(99,102,241,0.25)'); grad.addColorStop(1,'rgba(99,102,241,0.0)'); if(revenueChart) revenueChart.destroy(); revenueChart=new Chart(ctx,{ type:'line', data:{ labels:data.labels, datasets:[{ label:'Revenue (₦)', data:data.values, borderColor:'#6366f1', backgroundColor:grad, borderWidth:2.5, fill:true, tension:0.4 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label:ctx=>'₦'+ctx.parsed.y.toLocaleString() } } }, scales:{ x:{ grid:{ display:false } }, y:{ beginAtZero:true } } } }); }
        function createCategoryChart() { const ctx=document.getElementById('categoryChart')?.getContext('2d'); if(!ctx) return; const colors=getChartColors(); if(categoryChart) categoryChart.destroy(); const bgColors=['#6366f1','#10b981','#f59e0b','#3b82f6','#ec4899','#8b5cf6']; categoryChart=new Chart(ctx,{ type:'doughnut', data:{ labels:categoryData.labels, datasets:[{ data:categoryData.values, backgroundColor:bgColors.slice(0,categoryData.labels.length), borderColor:getComputedStyle(document.documentElement).getPropertyValue('--bg-secondary').trim(), borderWidth:3 }] }, options:{ responsive:true, maintainAspectRatio:false, cutout:'68%', plugins:{ legend:{ position:'bottom', labels:{ color:colors.textColor } }, tooltip:{ callbacks:{ label:ctx=>ctx.label+': '+ctx.parsed+'%' } } } } }); }
        function updateChartsTheme() { const active=document.querySelector('#revenuePeriod button.active')?.dataset?.period||'7'; let data; if(active==='7') data=revenueData7; else if(active==='30') data=revenueData30; else data=revenueData90; createRevenueChart(data); createCategoryChart(); }

        // Recent orders rendering
        const recentOrders = <?= json_encode($recentOrders) ?>;
        function renderRecentOrders() { const tbody=document.getElementById('recentOrdersBody'); if(!tbody) return; if(!recentOrders.length) { tbody.innerHTML='<tr><td colspan="6" style="text-align:center;">No orders yet</td></tr>'; return; } tbody.innerHTML=recentOrders.map(order=>{ const date=order.created_at?new Date(order.created_at).toLocaleDateString('en-NG',{day:'2-digit',month:'short',year:'numeric'}):'N/A'; const total=parseFloat(order.total_amount||0).toFixed(2); const sBadge=(order.status==='delivered')?'badge-success':((order.status==='cancelled')?'badge-error':'badge-info'); return `<tr><td style="font-weight:600;color:var(--primary);">${escapeHtml(order.order_number||'N/A')}</td><td>${escapeHtml(order.user_name||'N/A')}</td><td style="white-space:nowrap;">${date}</td><td style="font-weight:600;">₦${total}</td><td><span class="badge ${sBadge}">${escapeHtml(order.status||'pending')}</span></td><td><button class="btn-sm" onclick='viewOrderDetails(${JSON.stringify(order)})'>View</button></td></tr>`; }).join(''); }
        function viewOrderDetails(order) { if(!order) return; let itemsHtml=''; if(order.items&&order.items.length){ itemsHtml='<div style="margin-top:16px;"><strong>Order Items</strong><div style="margin-top:8px;">'; order.items.forEach(item=>{ const itemTotal=(item.price||0)*(item.qty||1); itemsHtml+=`<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-primary);"><span>${escapeHtml(item.name)} × ${item.qty}</span><span>₦${itemTotal.toLocaleString()}</span></div>`; }); itemsHtml+='</div></div>'; }else{ itemsHtml='<div style="margin-top:16px;"><em>No item details available.</em></div>'; } document.getElementById('orderModalBody').innerHTML=`<div><strong>Order #:</strong> ${escapeHtml(order.order_number||order.id)}</div><div><strong>Customer:</strong> ${escapeHtml(order.user_name||'N/A')}</div><div><strong>Email:</strong> ${escapeHtml(order.user_email||'N/A')}</div><div><strong>Date:</strong> ${order.created_at?new Date(order.created_at).toLocaleString():'N/A'}</div><div><strong>Total:</strong> ₦${(order.total_amount||0).toLocaleString()}</div><div><strong>Payment:</strong> <span class="badge ${order.payment_status==='paid'?'badge-success':'badge-warning'}">${escapeHtml(order.payment_status||'pending')}</span></div><div><strong>Status:</strong> <span class="badge ${order.status==='delivered'?'badge-success':(order.status==='cancelled'?'badge-error':'badge-info')}">${escapeHtml(order.status||'pending')}</span></div>${itemsHtml}`; document.getElementById('orderModal').classList.add('active'); document.body.style.overflow='hidden'; }
        function closeOrderModal() { document.getElementById('orderModal').classList.remove('active'); document.body.style.overflow=''; }
        document.getElementById('orderModal')?.addEventListener('click',e=>{ if(e.target===e.currentTarget) closeOrderModal(); });

        // Sidebar, theme, logout
        function handleLogout() { if(confirm('Are you sure you want to log out?')) window.location.href='logout'; }
        const html=document.documentElement; const savedTheme=localStorage.getItem('RD Vendora-theme')||'light'; html.setAttribute('data-theme',savedTheme);
        document.getElementById('themeToggle')?.addEventListener('click',()=>{ const next=html.getAttribute('data-theme')==='light'?'dark':'light'; html.setAttribute('data-theme',next); localStorage.setItem('RD Vendora-theme',next); updateChartsTheme(); });
        const sidebar=document.getElementById('sidebar'); const overlay=document.getElementById('sidebarOverlay');
        document.getElementById('sidebarToggle')?.addEventListener('click',()=>{ if(window.innerWidth<=768) toggleMobile(); else sidebar.classList.toggle('collapsed'); });
        document.getElementById('mobileSidebarToggle')?.addEventListener('click',toggleMobile);
        overlay?.addEventListener('click',toggleMobile);
        function toggleMobile(){ sidebar.classList.toggle('mobile-open'); overlay?.classList.toggle('active'); document.body.style.overflow=sidebar.classList.contains('mobile-open')?'hidden':''; }
        window.addEventListener('resize',()=>{ if(window.innerWidth>768){ sidebar.classList.remove('mobile-open'); overlay?.classList.remove('active'); document.body.style.overflow=''; } });
        document.addEventListener('click',e=>{ ['userDropdown','notificationDropdown'].forEach(id=>{ const dd=document.getElementById(id); if(dd && !dd.contains(e.target)) dd.classList.remove('open'); else if(e.target.closest('.dropdown-trigger')) dd?.classList.toggle('open'); }); });

        // Notifications
        let notifications=[{id:1,title:'New Order Received',text:'Order #1287 has been placed.',time:'2 minutes ago',unread:true},{id:2,title:'Payment Confirmed',text:'Payment of $245.00 confirmed.',time:'15 minutes ago',unread:true}];
        function renderNotifications(){ const list=document.getElementById('notificationList'),badge=document.querySelector('.topbar-btn-badge'); if(!list) return; const unread=notifications.filter(n=>n.unread).length; if(badge) badge.style.display=unread?'block':'none'; list.innerHTML=notifications.map(n=>`<div class="notification-item ${n.unread?'unread':''}" onclick="markNotificationRead(${n.id})">${n.unread?'<div class="notification-dot"></div>':'<div style="width:8px;"></div>'}<div class="notification-content"><div class="notification-title">${escapeHtml(n.title)}</div><div class="notification-text">${escapeHtml(n.text)}</div><div class="notification-time">${escapeHtml(n.time)}</div></div></div>`).join(''); }
        function markNotificationRead(id){ const n=notifications.find(x=>x.id===id); if(n) n.unread=false; renderNotifications(); }
        function markAllRead(){ notifications.forEach(n=>n.unread=false); renderNotifications(); }
        renderNotifications();

        // Initialize charts and orders if full dashboard visible
        if(!storeRestricted) {
            const periodDiv=document.getElementById('revenuePeriod'); if(periodDiv) periodDiv.addEventListener('click',e=>{ if(e.target.tagName==='BUTTON'){ document.querySelectorAll('#revenuePeriod button').forEach(b=>b.classList.remove('active')); e.target.classList.add('active'); let data; if(e.target.dataset.period==='7') data=revenueData7; else if(e.target.dataset.period==='30') data=revenueData30; else data=revenueData90; createRevenueChart(data); } });
            createRevenueChart(revenueData7);
            createCategoryChart();
            renderRecentOrders();
        }

        if(storeRestricted) {
            document.querySelectorAll('.sidebar-link.disabled').forEach(link => {
                link.addEventListener('click', function(e) { 
                    e.preventDefault(); 
                    showToast('error', 'Access Denied', 'Your subscription has expired. Please renew to access this feature.');
                });
            });
        }
    </script>
</body>
</html>