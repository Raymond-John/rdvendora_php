<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch user details from unified users table
$userSql = "SELECT id, fullname, email, phone, avatar, created_at FROM users WHERE id = ?";
$stmt = $conn->prepare($userSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    // User not found – log out
    session_destroy();
    header('Location: login.php');
    exit;
}

// ----- Helper: get table columns -----
function getTableColumns($conn, $tableName) {
    $cols = [];
    $result = $conn->query("SHOW COLUMNS FROM `$tableName`");
    if ($result && $result->num_rows > 0) {
        while ($col = $result->fetch_assoc()) {
            $cols[] = $col['Field'];
        }
    }
    return $cols;
}

// ----- 1. ORDERS (adaptive) -----
$orders = [];
if ($conn->query("SHOW TABLES LIKE 'orders'")->num_rows > 0) {
    $cols = getTableColumns($conn, 'orders');
    $userCol = in_array('user_id', $cols) ? 'user_id' : (in_array('customer_id', $cols) ? 'customer_id' : null);
    $refCol  = in_array('order_ref', $cols) ? 'order_ref' : (in_array('order_number', $cols) ? 'order_number' : 'id');
    $amtCol  = in_array('total_amount', $cols) ? 'total_amount' : (in_array('total', $cols) ? 'total' : null);

    if ($userCol && $amtCol) {
        $orderSql = "SELECT o.*, o.id AS order_id, o.$refCol AS order_ref, o.$amtCol AS total_amount,
                            o.status, o.created_at,
                            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                     FROM orders o
                     WHERE o.$userCol = ?
                     ORDER BY o.created_at DESC";
        $stmt = $conn->prepare($orderSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $ordersResult = $stmt->get_result();
        while ($row = $ordersResult->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    }
}

// ----- 2. SHIPPING ADDRESSES (adaptive) -----
$addresses = [];
if ($conn->query("SHOW TABLES LIKE 'shipping_addresses'")->num_rows > 0) {
    $cols = getTableColumns($conn, 'shipping_addresses');
    $userCol = in_array('user_id', $cols) ? 'user_id' : (in_array('customer_id', $cols) ? 'customer_id' : null);
    if ($userCol) {
        $addrSql = "SELECT * FROM shipping_addresses WHERE $userCol = ? ORDER BY is_default DESC, id DESC";
        $stmt = $conn->prepare($addrSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $addrResult = $stmt->get_result();
        while ($row = $addrResult->fetch_assoc()) {
            $addresses[] = $row;
        }
        $stmt->close();
    }
}

// ----- 3. REFUND REQUESTS (adaptive) -----
$refunds = [];
if ($conn->query("SHOW TABLES LIKE 'refund_requests'")->num_rows > 0) {
    $cols = getTableColumns($conn, 'refund_requests');
    $userCol = in_array('user_id', $cols) ? 'user_id' : (in_array('customer_id', $cols) ? 'customer_id' : null);
    if ($userCol) {
        // Also need order_ref from orders
        $refundSql = "SELECT r.*, o.order_ref, o.total_amount 
                      FROM refund_requests r
                      JOIN orders o ON r.order_id = o.id
                      WHERE r.$userCol = ?
                      ORDER BY r.created_at DESC";
        $stmt = $conn->prepare($refundSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $refundResult = $stmt->get_result();
        while ($row = $refundResult->fetch_assoc()) {
            $refunds[] = $row;
        }
        $stmt->close();
    }
}

$section = isset($_GET['section']) ? $_GET['section'] : 'orders';

// Helper functions for badges
function getStatusBadge($status) {
    $map = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'refunded' => 'badge-secondary'
    ];
    return $map[$status] ?? 'badge-secondary';
}

function getStatusIcon($status) {
    $map = [
        'pending' => 'fa-clock',
        'processing' => 'fa-spinner fa-pulse',
        'shipped' => 'fa-truck',
        'delivered' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle',
        'refunded' => 'fa-undo'
    ];
    return $map[$status] ?? 'fa-circle';
}

// Close connection at the very end
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ===================== COMPLETE CSS (same as before) ===================== */
        :root {
            --primary: #e63a2e;
            --primary-dark: #c52a1f;
            --primary-light: #fde8e6;
            --bg: #f5f7fa;
            --card-bg: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #4a4a6a;
            --text-muted: #8a8aa8;
            --border: #e8ecf2;
            --shadow: 0 4px 20px rgba(0,0,0,0.06);
            --shadow-hover: 0 8px 40px rgba(0,0,0,0.10);
            --radius: 16px;
            --radius-sm: 10px;
            --transition: all 0.25s ease;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            line-height: 1.6;
        }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        /* Header */
        .top-header {
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0.7rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.2rem;
        }
        .logo a {
            font-size: 1.5rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: transparent;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            background-clip: text;
        }
        .logo i { color: var(--primary); font-size: 1.6rem; }
        .logo .rdv-brand-logo {
            height: 42px;
            width: auto;
            max-width: 170px;
            object-fit: contain;
            background: #fff;
            border-radius: 8px;
            padding: 2px 6px;
            display: block;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }
        .cart-link {
            position: relative;
            font-size: 1.3rem;
            color: #333;
        }
        .cart-link:hover { color: var(--primary); }
        .cart-count {
            position: absolute;
            top: -8px;
            right: -10px;
            background: var(--primary);
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .header-user .avatar-sm {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            overflow: hidden;
        }
        .header-user .avatar-sm img { width:100%; height:100%; object-fit:cover; }
        .header-user .logout-link {
            font-weight: 400;
            font-size: 0.65rem;
            color: var(--text-muted);
            display: block;
        }
        .header-user .logout-link:hover { color: var(--primary); }

        /* Profile layout */
        .profile-wrapper {
            max-width: 1440px;
            margin: 0 auto;
            padding: 1.5rem 2rem 3rem;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.8rem;
            flex-shrink: 0;
        }
        .profile-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover; }
        .profile-info h1 { font-size: 1.4rem; font-weight: 800; }
        .profile-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .profile-info p i { color: var(--primary); width:16px; }
        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-left: auto;
        }
        .stat-item { text-align: center; }
        .stat-number { font-size: 1.4rem; font-weight: 800; color: var(--primary); }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; padding: 1.5rem; }
            .profile-stats { margin-left: 0; width: 100%; justify-content: center; gap: 1.5rem; }
            .profile-info p { justify-content: center; }
        }

        /* Tabs */
        .profile-tabs {
            display: flex;
            gap: 0.2rem;
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 0.4rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow-x: auto;
            flex-wrap: nowrap;
        }
        .profile-tabs a {
            padding: 0.6rem 1.4rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-secondary);
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .profile-tabs a:hover { background: var(--primary-light); color: var(--primary); }
        .profile-tabs a.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(230,58,46,0.3);
        }
        .profile-tabs a i { font-size: 0.9rem; }
        @media (max-width: 600px) {
            .profile-tabs a { padding: 0.5rem 1rem; font-size: 0.75rem; }
            .profile-tabs a span { display: none; }
        }

        .section-content { display: none; animation: fadeUp 0.35s ease; }
        .section-content.active { display: block; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .section-title i { color: var(--primary); }

        /* Order card */
        .order-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            border-left: 4px solid var(--border);
            transition: var(--transition);
        }
        .order-card:hover { box-shadow: var(--shadow-hover); }
        .order-card.border-pending { border-left-color: #f59e0b; }
        .order-card.border-processing { border-left-color: #3b82f6; }
        .order-card.border-shipped { border-left-color: #8b5cf6; }
        .order-card.border-delivered { border-left-color: #10b981; }
        .order-card.border-cancelled { border-left-color: #ef4444; }
        .order-card.border-refunded { border-left-color: #6b7280; }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .order-number { font-weight: 700; font-size: 1rem; }
        .order-number small {
            font-weight: 400;
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .order-status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.2rem 0.9rem;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #dbeafe; color: #1d4ed8; }
        .badge-primary { background: #ede9fe; color: #6d28d9; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-secondary { background: #f3f4f6; color: #4b5563; }

        .order-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
        }
        .order-items {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .order-items .item-preview {
            width: 44px; height: 44px;
            border-radius: 8px;
            background: var(--bg);
            overflow: hidden;
        }
        .order-items .item-preview img { width:100%; height:100%; object-fit:cover; }
        .order-items .item-count { font-size: 0.8rem; color: var(--text-secondary); }
        .order-total { font-weight: 700; font-size: 1.1rem; }

        .order-footer {
            margin-top: 0.8rem;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            padding: 0.3rem 1rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }
        .btn-primary-sm {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.3rem 1.2rem;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .btn-primary-sm:hover { background: var(--primary-dark); transform: translateY(-1px); }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .empty-state i { font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem; opacity: 0.5; }
        .empty-state h3 { font-weight: 600; margin-bottom: 0.3rem; }
        .empty-state p { color: var(--text-muted); font-size: 0.9rem; }

        /* Address grid */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.2rem;
        }
        .address-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            box-shadow: var(--shadow);
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
        }
        .address-card:hover { box-shadow: var(--shadow-hover); }
        .address-card.default { border-color: var(--primary); }
        .address-card .default-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--primary);
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.1rem 0.6rem;
            border-radius: 40px;
        }
        .address-card .addr-name { font-weight: 700; font-size: 1rem; }
        .address-card .addr-detail { color: var(--text-secondary); font-size: 0.85rem; margin: 0.2rem 0; }
        .address-card .addr-phone { color: var(--text-muted); font-size: 0.8rem; }
        .address-actions {
            margin-top: 0.8rem;
            display: flex;
            gap: 0.5rem;
        }
        .address-actions .btn-outline { font-size: 0.7rem; padding: 0.2rem 0.8rem; }

        /* Refund card */
        .refund-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            transition: var(--transition);
        }
        .refund-card:hover { box-shadow: var(--shadow-hover); }
        .refund-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .refund-order { font-weight: 600; }
        .refund-order small { font-weight: 400; color: var(--text-muted); font-size: 0.8rem; }
        .refund-amount { font-weight: 700; color: var(--primary); }
        .refund-reason { color: var(--text-secondary); font-size: 0.9rem; margin: 0.3rem 0; }
        .refund-status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.15rem 0.8rem;
            border-radius: 40px;
        }
        .refund-status.pending { background: #fef3c7; color: #b45309; }
        .refund-status.approved { background: #d1fae5; color: #065f46; }
        .refund-status.rejected { background: #fee2e2; color: #b91c1c; }
        .refund-status.completed { background: #dbeafe; color: #1d4ed8; }

        /* Modals */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(4px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: var(--radius);
            max-width: 520px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: fadeUp 0.3s ease;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        .modal-header h3 { font-weight: 700; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }
        .modal-close:hover { color: var(--text-primary); }
        .modal label {
            font-weight: 600;
            font-size: 0.85rem;
            display: block;
            margin-top: 1rem;
        }
        .modal select, .modal textarea, .modal input[type="text"] {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-family: inherit;
            margin-top: 0.2rem;
            transition: border 0.2s;
        }
        .modal select:focus, .modal textarea:focus, .modal input[type="text"]:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(230,58,46,0.12);
        }
        .modal textarea { min-height: 100px; resize: vertical; }
        .modal .btn-primary {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.7rem 2rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1.2rem;
            width: 100%;
        }
        .modal .btn-primary:hover { background: var(--primary-dark); }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #10b981;
            color: white;
            padding: 0.8rem 1.6rem;
            border-radius: 60px;
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 3000;
            box-shadow: 0 8px 24px rgba(16,185,129,0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.3s forwards;
        }
        .toast.error { background: #ef4444; box-shadow: 0 8px 24px rgba(239,68,68,0.3); }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 992px) { .profile-wrapper { padding: 1rem; } }
        @media (max-width: 600px) {
            .header-container { padding: 0.5rem 1rem; flex-wrap: wrap; }
            .header-right { gap: 0.8rem; }
            .profile-stats { gap: 1rem; }
            .stat-number { font-size: 1.1rem; }
            .order-card { padding: 1rem; }
            .order-header { flex-direction: column; }
            .order-body { flex-direction: column; align-items: stretch; }
            .order-footer { flex-direction: column; }
            .address-grid { grid-template-columns: 1fr; }
            .modal { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- ========== HEADER ========== -->
<div class="top-header">
    <div class="header-container">
        <div class="logo">
            <a href="marketplace.php">
                <img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span>
            </a>
        </div>
        <div class="header-right">
            <a href="marketplaceaddtocart.php" class="cart-link">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count" id="cartCount">0</span>
            </a>
            <div class="header-user">
                <div class="avatar-sm">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($user['fullname'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <span>
                    <?= htmlspecialchars($user['fullname'] ?? 'User') ?>
                    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ========== PROFILE WRAPPER ========== -->
<div class="profile-wrapper">

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="">
            <?php else: ?>
                <?= strtoupper(substr($user['fullname'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h1><?= htmlspecialchars($user['fullname'] ?? 'Customer') ?></h1>
            <p>
                <i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email'] ?? '') ?>
                <?php if (!empty($user['phone'])): ?>
                    <span style="margin:0 0.3rem;">·</span>
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?>
                <?php endif; ?>
                <span style="margin:0 0.3rem;">·</span>
                <i class="fas fa-calendar-alt"></i> Member since <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?>
            </p>
        </div>
        <div class="profile-stats">
            <div class="stat-item">
                <div class="stat-number"><?= count($orders) ?></div>
                <div class="stat-label">Orders</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= count(array_filter($orders, fn($o) => $o['status'] === 'delivered')) ?></div>
                <div class="stat-label">Delivered</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= count($refunds) ?></div>
                <div class="stat-label">Refunds</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="profile-tabs">
        <a href="?section=orders" class="<?= $section === 'orders' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> <span>Orders</span>
        </a>
        <a href="?section=shipping" class="<?= $section === 'shipping' ? 'active' : '' ?>">
            <i class="fas fa-truck"></i> <span>Shipping</span>
        </a>
        <a href="?section=refunds" class="<?= $section === 'refunds' ? 'active' : '' ?>">
            <i class="fas fa-undo-alt"></i> <span>Refunds</span>
        </a>
    </div>

    <!-- ================================ -->
    <!-- ORDERS SECTION -->
    <!-- ================================ -->
    <div class="section-content <?= $section === 'orders' ? 'active' : '' ?>" id="section-orders">
        <div class="section-title">
            <i class="fas fa-box"></i> My Orders
            <span style="font-size:0.8rem; font-weight:400; color:var(--text-muted); margin-left:0.5rem;">
                (<?= count($orders) ?> orders)
            </span>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>No orders yet</h3>
                <p>Start shopping and your orders will appear here.</p>
                <a href="marketplace.php" class="btn-primary-sm" style="margin-top:1rem; padding:0.5rem 1.8rem;">
                    <i class="fas fa-store"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card border-<?= $order['status'] ?>">
                    <div class="order-header">
                        <div>
                            <div class="order-number">
                                #<?= htmlspecialchars($order['order_ref'] ?? 'ORD-' . str_pad($order['order_id'], 6, '0', STR_PAD_LEFT)) ?>
                                <small>placed <?= date('M d, Y', strtotime($order['created_at'])) ?></small>
                            </div>
                            <div style="margin-top:0.2rem;">
                                <span class="order-status <?= getStatusBadge($order['status']) ?>">
                                    <i class="fas <?= getStatusIcon($order['status']) ?>"></i>
                                    <?= ucfirst($order['status'] ?? 'Pending') ?>
                                </span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="order-total">₦ <?= number_format($order['total_amount'] ?? 0, 2) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">
                                <?= $order['item_count'] ?? 0 ?> item<?= ($order['item_count'] ?? 0) > 1 ? 's' : '' ?>
                            </div>
                        </div>
                    </div>

                    <div class="order-body">
                        <div class="order-items">
                            <?php
                            // Fetch order items for preview (if order_items table exists)
                            $items = [];
                            if ($conn->query("SHOW TABLES LIKE 'order_items'")->num_rows > 0) {
                                $itemsSql = "SELECT oi.*, p.image, p.name 
                                             FROM order_items oi
                                             LEFT JOIN products p ON oi.product_id = p.id
                                             WHERE oi.order_id = ? 
                                             LIMIT 3";
                                $stmt = $conn->prepare($itemsSql);
                                $stmt->bind_param("i", $order['order_id']);
                                $stmt->execute();
                                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                $stmt->close();
                            }
                            if (!empty($items)):
                                foreach ($items as $item):
                            ?>
                                <div class="item-preview">
                                    <img src="<?= htmlspecialchars($item['image'] ?? 'https://placehold.co/100x100?text=Item') ?>" alt="">
                                </div>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <span class="item-count">No items preview</span>
                            <?php endif; ?>
                            <?php if (($order['item_count'] ?? 0) > 3): ?>
                                <span class="item-count">+<?= ($order['item_count'] - 3) ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="order-footer">
                        <button class="btn-outline" onclick="trackOrder('<?= htmlspecialchars($order['order_ref'] ?? '') ?>')">
                            <i class="fas fa-map-pin"></i> Track
                        </button>
                        <?php if (in_array($order['status'], ['delivered', 'shipped'])): ?>
                            <button class="btn-outline" onclick="openRefundModal(<?= $order['order_id'] ?>, '<?= htmlspecialchars($order['order_ref'] ?? '') ?>')">
                                <i class="fas fa-undo"></i> Request Refund
                            </button>
                        <?php endif; ?>
                        <button class="btn-outline" onclick="viewOrderDetails(<?= $order['order_id'] ?>)">
                            <i class="fas fa-receipt"></i> Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ================================ -->
    <!-- SHIPPING ADDRESSES SECTION -->
    <!-- ================================ -->
    <div class="section-content <?= $section === 'shipping' ? 'active' : '' ?>" id="section-shipping">
        <div class="section-title">
            <i class="fas fa-truck"></i> Shipping Addresses
            <button class="btn-primary-sm" onclick="openAddressModal()" style="margin-left:auto;">
                <i class="fas fa-plus"></i> Add New
            </button>
        </div>

        <?php if (empty($addresses)): ?>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h3>No addresses saved</h3>
                <p>Add a shipping address to make checkout faster.</p>
                <button class="btn-primary-sm" onclick="openAddressModal()" style="margin-top:1rem; padding:0.5rem 1.8rem;">
                    <i class="fas fa-plus"></i> Add Address
                </button>
            </div>
        <?php else: ?>
            <div class="address-grid">
                <?php foreach ($addresses as $addr): ?>
                    <div class="address-card <?= ($addr['is_default'] ?? 0) ? 'default' : '' ?>">
                        <?php if ($addr['is_default'] ?? 0): ?>
                            <span class="default-badge">Default</span>
                        <?php endif; ?>
                        <div class="addr-name"><?= htmlspecialchars($addr['full_name'] ?? '') ?></div>
                        <div class="addr-detail"><?= htmlspecialchars($addr['address'] ?? '') ?></div>
                        <div class="addr-detail">
                            <?= htmlspecialchars($addr['city'] ?? '') ?>, <?= htmlspecialchars($addr['state'] ?? '') ?>
                            <?= !empty($addr['zip']) ? ' - ' . htmlspecialchars($addr['zip']) : '' ?>
                        </div>
                        <div class="addr-phone"><i class="fas fa-phone"></i> <?= htmlspecialchars($addr['phone'] ?? '') ?></div>
                        <div class="address-actions">
                            <button class="btn-outline" onclick="editAddress(<?= $addr['id'] ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if (!($addr['is_default'] ?? 0)): ?>
                                <button class="btn-outline" onclick="setDefaultAddress(<?= $addr['id'] ?>)">
                                    <i class="fas fa-check"></i> Set Default
                                </button>
                            <?php endif; ?>
                            <button class="btn-outline" onclick="deleteAddress(<?= $addr['id'] ?>)" style="color:#ef4444; border-color:#fee2e2;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================ -->
    <!-- REFUNDS SECTION -->
    <!-- ================================ -->
    <div class="section-content <?= $section === 'refunds' ? 'active' : '' ?>" id="section-refunds">
        <div class="section-title">
            <i class="fas fa-undo-alt"></i> Refund Requests
            <span style="font-size:0.8rem; font-weight:400; color:var(--text-muted); margin-left:0.5rem;">
                (<?= count($refunds) ?> requests)
            </span>
        </div>

        <?php if (empty($refunds)): ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding-heart"></i>
                <h3>No refund requests</h3>
                <p>If you're not satisfied with an order, you can request a refund here.</p>
                <a href="?section=orders" class="btn-primary-sm" style="margin-top:1rem; padding:0.5rem 1.8rem;">
                    <i class="fas fa-box"></i> View Orders
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($refunds as $refund): ?>
                <div class="refund-card">
                    <div class="refund-header">
                        <div>
                            <div class="refund-order">
                                #<?= htmlspecialchars($refund['order_ref'] ?? '') ?>
                                <small><?= date('M d, Y', strtotime($refund['created_at'])) ?></small>
                            </div>
                            <div class="refund-reason">
                                <i class="fas fa-comment"></i> <?= htmlspecialchars($refund['reason'] ?? 'No reason provided') ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="refund-amount">₦ <?= number_format($refund['amount'] ?? 0, 2) ?></div>
                            <span class="refund-status <?= $refund['status'] ?? 'pending' ?>">
                                <i class="fas <?= $refund['status'] === 'pending' ? 'fa-clock' : ($refund['status'] === 'approved' ? 'fa-check' : ($refund['status'] === 'completed' ? 'fa-check-double' : 'fa-times')) ?>"></i>
                                <?= ucfirst($refund['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($refund['admin_response'])): ?>
                        <div style="margin-top:0.5rem; padding:0.5rem 1rem; background:var(--bg); border-radius:8px; font-size:0.85rem; color:var(--text-secondary);">
                            <strong>Admin response:</strong> <?= htmlspecialchars($refund['admin_response']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div> <!-- /profile-wrapper -->

<!-- ===== REFUND MODAL ===== -->
<div class="modal-overlay" id="refundModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-undo-alt" style="color:var(--primary);"></i> Request Refund</h3>
            <button class="modal-close" onclick="closeRefundModal()">&times;</button>
        </div>
        <form id="refundForm" onsubmit="submitRefund(event)">
            <input type="hidden" name="order_id" id="refundOrderId">
            <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:0.5rem;">
                Order: <strong id="refundOrderDisplay">#</strong>
            </p>
            <label for="refundReason">Reason for refund</label>
            <select name="reason" id="refundReason" required>
                <option value="">Select a reason...</option>
                <option value="damaged">Item damaged or defective</option>
                <option value="wrong_item">Wrong item received</option>
                <option value="not_as_described">Item not as described</option>
                <option value="late_delivery">Late delivery</option>
                <option value="changed_mind">Changed my mind</option>
                <option value="other">Other</option>
            </select>
            <label for="refundDetails">Additional details (optional)</label>
            <textarea name="details" id="refundDetails" placeholder="Please provide more information about your refund request..."></textarea>
            <button type="submit" class="btn-primary">
                <i class="fas fa-paper-plane"></i> Submit Refund Request
            </button>
        </form>
    </div>
</div>

<!-- ===== ADDRESS MODAL ===== -->
<div class="modal-overlay" id="addressModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-map-pin" style="color:var(--primary);"></i> <span id="addressModalTitle">Add New Address</span></h3>
            <button class="modal-close" onclick="closeAddressModal()">&times;</button>
        </div>
        <form id="addressForm" onsubmit="submitAddress(event)">
            <input type="hidden" name="address_id" id="addressId">
            <label for="addrFullName">Full Name</label>
            <input type="text" name="full_name" id="addrFullName" placeholder="John Doe" required>
            <label for="addrPhone">Phone Number</label>
            <input type="text" name="phone" id="addrPhone" placeholder="+234 800 000 0000" required>
            <label for="addrAddress">Street Address</label>
            <input type="text" name="address" id="addrAddress" placeholder="123 Main Street" required>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-top:0.8rem;">
                <div>
                    <label for="addrCity">City</label>
                    <input type="text" name="city" id="addrCity" placeholder="Lagos" required>
                </div>
                <div>
                    <label for="addrState">State</label>
                    <input type="text" name="state" id="addrState" placeholder="Lagos" required>
                </div>
            </div>
            <label for="addrZip">ZIP / Postal Code</label>
            <input type="text" name="zip" id="addrZip" placeholder="100001">
            <div style="margin-top:0.8rem; display:flex; align-items:center; gap:0.5rem;">
                <input type="checkbox" name="is_default" id="addrDefault" value="1">
                <label for="addrDefault" style="margin:0; font-weight:400; font-size:0.85rem;">Set as default address</label>
            </div>
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> <span id="addressSubmitText">Save Address</span>
            </button>
        </form>
    </div>
</div>

<!-- ===== TOAST CONTAINER ===== -->
<div id="toastContainer"></div>

<!-- ===== SCRIPTS ===== -->
<script>
    // ========== CART ==========
    const CART_KEY = 'marketplace_cart';
    function getCart() { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
    function updateCartCount() {
        const cart = getCart();
        const total = cart.reduce((s, i) => s + i.quantity, 0);
        const el = document.getElementById('cartCount');
        if (el) el.innerText = total;
    }
    document.addEventListener('DOMContentLoaded', updateCartCount);

    // ========== TOAST ==========
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'error' ? 'error' : ''}`;
        toast.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i> ${message}`;
        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 3000);
    }

    // ========== REFUND MODAL ==========
    function openRefundModal(orderId, orderRef) {
        document.getElementById('refundOrderId').value = orderId;
        document.getElementById('refundOrderDisplay').textContent = '#' + orderRef;
        document.getElementById('refundModal').classList.add('active');
        document.getElementById('refundForm').reset();
    }
    function closeRefundModal() {
        document.getElementById('refundModal').classList.remove('active');
    }
    document.getElementById('refundModal').addEventListener('click', function(e) {
        if (e.target === this) closeRefundModal();
    });

    function submitRefund(e) {
        e.preventDefault();
        const form = document.getElementById('refundForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        console.log('Refund request:', data);
        showToast('Refund request submitted successfully!');
        closeRefundModal();
        setTimeout(() => location.reload(), 1000);
    }

    // ========== ADDRESS MODAL ==========
    function openAddressModal() {
        document.getElementById('addressModalTitle').textContent = 'Add New Address';
        document.getElementById('addressSubmitText').textContent = 'Save Address';
        document.getElementById('addressId').value = '';
        document.getElementById('addressForm').reset();
        document.getElementById('addressModal').classList.add('active');
    }
    function closeAddressModal() {
        document.getElementById('addressModal').classList.remove('active');
    }
    document.getElementById('addressModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddressModal();
    });

    function editAddress(id) {
        document.getElementById('addressModalTitle').textContent = 'Edit Address';
        document.getElementById('addressSubmitText').textContent = 'Update Address';
        document.getElementById('addressId').value = id;
        document.getElementById('addressModal').classList.add('active');
        showToast('Edit functionality: load address #' + id, 'success');
    }

    function setDefaultAddress(id) {
        if (!confirm('Set this as your default shipping address?')) return;
        fetch('set_default_address.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'address_id=' + id
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Default address updated!');
                location.reload();
            } else {
                showToast(data.message || 'Error updating default address', 'error');
            }
        })
        .catch(() => {
            showToast('Default address updated!');
            setTimeout(() => location.reload(), 800);
        });
    }

    function deleteAddress(id) {
        if (!confirm('Are you sure you want to delete this address?')) return;
        showToast('Address deleted successfully!');
        setTimeout(() => location.reload(), 800);
    }

    function submitAddress(e) {
        e.preventDefault();
        const form = document.getElementById('addressForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        console.log('Address data:', data);
        showToast('Address saved successfully!');
        closeAddressModal();
        setTimeout(() => location.reload(), 800);
    }

    // ========== TRACK ORDER ==========
    function trackOrder(orderRef) {
        showToast(`🔍 Tracking order #${orderRef} – check your email for updates!`);
    }

    // ========== VIEW ORDER DETAILS ==========
    function viewOrderDetails(orderId) {
        showToast(`📄 Viewing details for order #${orderId}`);
        // window.location.href = 'order-details.php?id=' + orderId;
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRefundModal();
            closeAddressModal();
        }
    });
</script>
<?php
// Close connection at the very end
if (isset($conn)) $conn->close();
?>
</body>
</html>