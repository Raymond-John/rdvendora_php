<?php
// ============================================================
// 1. AJAX DETECTION – MUST BE FIRST (before any output)
// ============================================================
$isAjax = false;
if (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_POST['ajax']) && $_POST['ajax'] === '1') ||
    (isset($_GET['ajax']) && $_GET['ajax'] === '1')
) {
    $isAjax = true;
}

if ($isAjax) {
    // Suppress all PHP errors/warnings for AJAX – we handle them ourselves
    error_reporting(0);
    ini_set('display_errors', 0);

    // Clean any output buffers that might have been started
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set JSON header immediately
    header('Content-Type: application/json');

    // Catch fatal errors and output JSON
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $error['message']]);
            exit;
        }
    });

    // Catch warnings/notices as exceptions (optional)
    set_error_handler(function ($errno, $errstr) {
        echo json_encode(['success' => false, 'message' => "Error: $errstr"]);
        exit;
    });
}

// ============================================================
// 2. SESSION & DATABASE CONNECTION
// ============================================================
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}

if (!$conn) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    } else {
        die('Database connection failed.');
    }
}

// ----- Helper to fetch settings (with error suppression) -----
function getMarketplaceSetting($key, $default = '') {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
        if (!$stmt) {
            return $default; // table might not exist
        }
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// ----- Color Settings -----
$body_bg_color = getMarketplaceSetting('body_bg_color', '#f7faf8');
$text_primary_color = getMarketplaceSetting('text_primary_color', '#1a1a1a');
$primary_btn_bg = getMarketplaceSetting('primary_btn_bg', '#27a85a');
$primary_btn_text = getMarketplaceSetting('primary_btn_text', '#ffffff');
$card_bg_color = getMarketplaceSetting('card_bg_color', '#ffffff');
$sidebar_bg_color = getMarketplaceSetting('sidebar_bg_color', '#ffffff');
$sidebar_text_color = getMarketplaceSetting('sidebar_text_color', '#555');

function darkenHex($hex, $factor = 0.7) {
    if (preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
        $r = hexdec($m[1][0] . $m[1][1]) * $factor;
        $g = hexdec($m[1][2] . $m[1][3]) * $factor;
        $b = hexdec($m[1][4] . $m[1][5]) * $factor;
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    return $hex;
}
$btn_bg_dark = darkenHex($primary_btn_bg, 0.7);
$btn_bg_darker = darkenHex($primary_btn_bg, 0.5);

// ----- Fetch Shipping & Tax settings -----
$tax_rate = floatval(getMarketplaceSetting('tax_rate', '0'));
$shipping_default = floatval(getMarketplaceSetting('shipping_default', '0'));
$shipping_states_raw = getMarketplaceSetting('shipping_states', '');
$shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];

// List of Nigerian states
$nigeria_states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
    'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
    'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
    'Yobe', 'Zamfara'
];

$conn->close();

// ============================================================
// 3. PDO SETUP & PAYMENT KEYS (use .env credentials — never hardcode root)
// ============================================================
$dbHost = function_exists('rdv_env') ? rdv_env('DB_HOST', 'localhost') : 'localhost';
$dbUser = function_exists('rdv_env') ? rdv_env('DB_USER', 'root') : 'root';
$dbPass = function_exists('rdv_env') ? rdv_env('DB_PASS', '') : '';
$dbName = function_exists('rdv_env') ? rdv_env('DB_NAME', 'rdvendora_db') : 'rdvendora_db';
$dbPort = (int) (function_exists('rdv_env') ? rdv_env('DB_PORT', 3306) : 3306);

$payKeys = function_exists('rdv_payment_keys') ? rdv_payment_keys() : [];
if (!defined('PAYSTACK_SECRET_KEY')) {
    define('PAYSTACK_SECRET_KEY', $payKeys['paystack_secret'] ?? '');
}
if (!defined('PAYSTACK_PUBLIC_KEY')) {
    define('PAYSTACK_PUBLIC_KEY', $payKeys['paystack_public'] ?? '');
}
if (!defined('FLUTTERWAVE_SECRET_KEY')) {
    define('FLUTTERWAVE_SECRET_KEY', $payKeys['flutterwave_secret'] ?? '');
}
if (!defined('FLUTTERWAVE_PUBLIC_KEY')) {
    define('FLUTTERWAVE_PUBLIC_KEY', $payKeys['flutterwave_public'] ?? '');
}
if (!defined('FLUTTERWAVE_ENCRYPTION_KEY')) {
    define('FLUTTERWAVE_ENCRYPTION_KEY', $payKeys['flutterwave_encryption'] ?? '');
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbHost,
        $dbPort,
        $dbName,
        function_exists('rdv_env') ? rdv_env('DB_CHARSET', 'utf8mb4') : 'utf8mb4'
    );
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
        CREATE TABLE `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `store_id` INT NOT NULL,
            `order_ref` VARCHAR(100) NOT NULL UNIQUE,
            `transaction_ref` VARCHAR(100) NOT NULL,
            `user_name` VARCHAR(100) NOT NULL,
            `user_email` VARCHAR(100) NOT NULL,
            `user_phone` VARCHAR(50) NOT NULL,
            `user_address` TEXT NOT NULL,
            `user_state` VARCHAR(100) NOT NULL,
            `subtotal` DECIMAL(10,2) NOT NULL,
            `shipping_fee` DECIMAL(10,2) NOT NULL,
            `tax_amount` DECIMAL(10,2) NOT NULL,
            `total_amount` DECIMAL(10,2) NOT NULL,
            `payment_method` VARCHAR(50) NOT NULL,
            `status` VARCHAR(50) DEFAULT 'pending',
            `payment_response` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("
        CREATE TABLE `order_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `store_id` INT NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `quantity` INT NOT NULL,
            `image_url` VARCHAR(500),
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
        )");
    } else {
        // Add missing columns if needed
        try {
            $pdo->exec("ALTER TABLE orders DROP INDEX transaction_ref");
        } catch (PDOException $e) {
        }
        $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'store_id'");
        if ($colCheck->rowCount() == 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN store_id INT NOT NULL AFTER id");
        }
        $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'user_state'");
        if ($colCheck->rowCount() == 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN user_state VARCHAR(100) NOT NULL AFTER user_address");
        }
        $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'subtotal'");
        if ($colCheck->rowCount() == 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) NOT NULL AFTER user_state");
        }
        $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'shipping_fee'");
        if ($colCheck->rowCount() == 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL AFTER subtotal");
        }
        $colCheck = $pdo->query("SHOW COLUMNS FROM orders LIKE 'tax_amount'");
        if ($colCheck->rowCount() == 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN tax_amount DECIMAL(10,2) NOT NULL AFTER shipping_fee");
        }
    }
} catch (PDOException $e) {
    error_log('marketplacecheckout PDO: ' . $e->getMessage());
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database setup failed. Please try again later.']);
        exit;
    } else {
        die('Checkout is temporarily unavailable. Please try again shortly.');
    }
}

function generateReference($prefix = 'ORD') {
    try {
        return $prefix . '_' . time() . '_' . bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return $prefix . '_' . time() . '_' . uniqid();
    }
}

// ============================================================
// 4. AJAX HANDLING
// ============================================================
if ($isAjax) {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];

    try {
        if ($action === 'create_order') {
            $cart = json_decode($_POST['cart'], true);
            $userName = trim($_POST['user_name'] ?? '');
            $userEmail = trim($_POST['user_email'] ?? '');
            $userPhone = trim($_POST['user_phone'] ?? '');
            $userAddress = trim($_POST['user_address'] ?? '');
            $userState = trim($_POST['user_state'] ?? '');
            $paymentMethod = trim($_POST['payment_method'] ?? '');

            if (empty($cart) || !is_array($cart)) {
                throw new Exception('Cart is empty');
            }
            if (empty($userName) || empty($userEmail) || empty($userPhone) || empty($userAddress) || empty($userState)) {
                throw new Exception('Please fill in all required fields');
            }
            if (!in_array($paymentMethod, ['paystack', 'flutterwave'])) {
                throw new Exception('Invalid payment method');
            }
            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            // Re-fetch shipping & tax settings using PDO
            $stmt = $pdo->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = 'tax_rate'");
            $stmt->execute();
            $tax_rate = floatval($stmt->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = 'shipping_default'");
            $stmt->execute();
            $shipping_default = floatval($stmt->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = 'shipping_states'");
            $stmt->execute();
            $shipping_states_raw = $stmt->fetchColumn() ?: '';
            $shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];
            $shipping_fee = isset($shipping_states[$userState]) ? floatval($shipping_states[$userState]) : $shipping_default;

            // Group cart by store
            $storeGroups = [];
            foreach ($cart as $item) {
                $sid = $item['store_id'];
                if (!isset($storeGroups[$sid])) {
                    $storeGroups[$sid] = [
                        'store_id' => $sid,
                        'store_name' => $item['store_name'] ?? 'Store',
                        'items' => [],
                        'total' => 0
                    ];
                }
                $storeGroups[$sid]['items'][] = $item;
                $storeGroups[$sid]['total'] += floatval($item['price']) * intval($item['quantity']);
            }
            if (empty($storeGroups)) {
                throw new Exception('No items to order');
            }

            $subtotal = array_sum(array_column($storeGroups, 'total'));
            $tax_amount = $subtotal * ($tax_rate / 100);
            $grandTotal = $subtotal + $shipping_fee + $tax_amount;

            $pdo->beginTransaction();
            $transactionRef = generateReference('TXN');
            $createdOrders = [];

            foreach ($storeGroups as $group) {
                $orderRef = generateReference('ORD');
                $stmt = $pdo->prepare("INSERT INTO orders (store_id, order_ref, transaction_ref, user_name, user_email, user_phone, user_address, user_state, subtotal, shipping_fee, tax_amount, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([
                    $group['store_id'],
                    $orderRef,
                    $transactionRef,
                    $userName,
                    $userEmail,
                    $userPhone,
                    $userAddress,
                    $userState,
                    $group['total'],
                    $shipping_fee,
                    $tax_amount,
                    $grandTotal,
                    $paymentMethod
                ]);
                $orderId = $pdo->lastInsertId();
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, store_id, product_name, price, quantity, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($group['items'] as $item) {
                    $itemStmt->execute([
                        $orderId,
                        $item['product_id'],
                        $item['store_id'],
                        $item['name'],
                        $item['price'],
                        $item['quantity'],
                        $item['image'] ?? ''
                    ]);
                }
                $createdOrders[] = [
                    'order_id' => $orderId,
                    'order_ref' => $orderRef,
                    'store_id' => $group['store_id'],
                    'total' => $grandTotal
                ];
            }
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'orders' => $createdOrders,
                'transaction_ref' => $transactionRef,
                'amount' => $grandTotal,
                'user_email' => $userEmail,
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping_fee,
                'tax_amount' => $tax_amount
            ]);
            exit;
        } elseif ($action === 'verify_payment') {
            $transactionRef = $_POST['transaction_ref'] ?? '';
            $paymentMethod = $_POST['payment_method'] ?? '';
            $orderIds = json_decode($_POST['order_ids'] ?? '[]', true);

            if (empty($transactionRef) || empty($paymentMethod) || empty($orderIds)) {
                throw new Exception('Missing parameters');
            }

            $stmt = $pdo->prepare("SELECT * FROM orders WHERE transaction_ref = ?");
            $stmt->execute([$transactionRef]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($orders)) {
                throw new Exception('Orders not found');
            }

            $allCompleted = true;
            foreach ($orders as $order) {
                if ($order['status'] !== 'completed') {
                    $allCompleted = false;
                }
            }
            if ($allCompleted) {
                // If already completed, redirect using ref
                echo json_encode(['success' => true, 'redirect' => 'order_success_store.php?ref=' . $orders[0]['order_ref']]);
                exit;
            }

            $verificationStatus = false;
            $paymentData = null;

            if ($paymentMethod === 'paystack') {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $transactionRef,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer " . PAYSTACK_SECRET_KEY, "Cache-Control: no-cache"]
                ]);
                $result = json_decode(curl_exec($curl), true);
                curl_close($curl);
                if ($result && $result['status'] && $result['data']['status'] === 'success') {
                    $verificationStatus = true;
                    $paymentData = json_encode($result['data']);
                    $paidAmount = floatval($result['data']['amount']) / 100;
                    $expectedTotal = array_sum(array_column($orders, 'total_amount'));
                    if (abs($paidAmount - $expectedTotal) > 0.01) {
                        $verificationStatus = false;
                    }
                }
            } elseif ($paymentMethod === 'flutterwave') {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/verify_by_reference/" . $transactionRef,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer " . FLUTTERWAVE_SECRET_KEY, "Content-Type: application/json"]
                ]);
                $result = json_decode(curl_exec($curl), true);
                curl_close($curl);
                if ($result && $result['status'] === 'success' && $result['data']['status'] === 'successful') {
                    $verificationStatus = true;
                    $paymentData = json_encode($result['data']);
                    $paidAmount = floatval($result['data']['amount']);
                    $expectedTotal = array_sum(array_column($orders, 'total_amount'));
                    if (abs($paidAmount - $expectedTotal) > 0.01) {
                        $verificationStatus = false;
                    }
                }
            }

            if ($verificationStatus) {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'completed', payment_response = ? WHERE transaction_ref = ?");
                $stmt->execute([$paymentData, $transactionRef]);

                // FIXED: redirect using order_ref (not order_id)
                echo json_encode(['success' => true, 'redirect' => 'order_success_store.php?ref=' . $orders[0]['order_ref']]);
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'failed', payment_response = ? WHERE transaction_ref = ?");
                $stmt->execute([$paymentData ?: 'Verification failed', $transactionRef]);
                throw new Exception('Payment verification failed – amount mismatch or gateway error');
            }
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ============================================================
// 5. NOT AJAX – OUTPUT HTML
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <style>
        /* ── DYNAMIC COLORS ── */
        :root {
            --body-bg: <?= htmlspecialchars($body_bg_color) ?>;
            --text-primary: <?= htmlspecialchars($text_primary_color) ?>;
            --btn-bg: <?= htmlspecialchars($primary_btn_bg) ?>;
            --btn-text: <?= htmlspecialchars($primary_btn_text) ?>;
            --card-bg: <?= htmlspecialchars($card_bg_color) ?>;
            --sidebar-bg: <?= htmlspecialchars($sidebar_bg_color) ?>;
            --sidebar-text: <?= htmlspecialchars($sidebar_text_color) ?>;
            --btn-bg-dark: <?= $btn_bg_dark ?>;
            --btn-bg-darker: <?= $btn_bg_darker ?>;
            --orange: #f97316;
        }

        /* ── BASE ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ── TOP STRIP ── */
        .top-strip {
            background: var(--btn-bg-dark);
            color: var(--btn-text);
            font-size: 12px;
            text-align: center;
            padding: 6px 16px;
            letter-spacing: .4px;
            font-weight: 500;
        }

        /* ── HEADER ── */
        header {
            background: var(--btn-bg);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            backdrop-filter: blur(2px);
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--btn-text);
            white-space: nowrap;
            letter-spacing: -0.5px;
            flex: 0 0 auto;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logo .rdv-brand-logo {
            height: 44px;
            width: auto;
            max-width: 170px;
            object-fit: contain;
            background: #fff;
            border-radius: 8px;
            padding: 2px 6px;
            display: block;
        }
        .logo span { color: #b8f5d0; }
        .logo i { color: var(--btn-text); font-size: 22px; }
        .search-bar {
            flex: 0 1 auto;
            margin: 0 auto;
            max-width: 560px;
            width: 100%;
            display: flex;
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.15);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .search-bar:focus-within {
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 4px rgba(255,255,255,0.08);
        }
        .search-bar input {
            flex: 1;
            padding: 10px 16px;
            border: none;
            font-size: 14px;
            outline: none;
            background: transparent;
            color: var(--btn-text);
            min-width: 0;
        }
        .search-bar input::placeholder { color: rgba(255,255,255,0.7); }
        .search-bar button {
            background: var(--btn-bg-dark);
            border: none;
            padding: 0 18px;
            color: var(--btn-text);
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .search-bar button:hover { background: var(--btn-bg-darker); }

        .header-actions {
            display: flex;
            gap: 16px;
            align-items: center;
            color: var(--btn-text);
            font-size: 13px;
            flex: 0 0 auto;
            margin-left: auto;
        }
        .header-actions a {
            color: var(--btn-text);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            cursor: pointer;
            transition: transform 0.2s, opacity 0.2s;
            position: relative;
        }
        .header-actions a:hover { transform: translateY(-2px); opacity: 0.85; }
        .header-actions a i { font-size: 22px; }
        .header-actions a span {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .cart-badge { position: relative; }
        .cart-badge .badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: var(--orange);
            color: #fff;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(249,115,22,0.4);
        }

        /* ── MAIN LAYOUT ── */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }

        /* ── PROGRESS STEPS ── */
        .progress-steps {
            display: flex;
            justify-content: center;
            gap: 0;
            margin: 2rem 0 1.5rem;
            position: relative;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--sidebar-text);
            padding: 0 20px;
            position: relative;
            opacity: 0.6;
        }
        .step .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--sidebar-bg);
            border: 2px solid var(--sidebar-text);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--sidebar-text);
            transition: all 0.3s;
        }
        .step.active {
            opacity: 1;
            color: var(--text-primary);
        }
        .step.active .step-number {
            background: var(--btn-bg);
            border-color: var(--btn-bg);
            color: var(--btn-text);
            box-shadow: 0 4px 12px rgba(39,168,90,0.3);
        }
        .step.done .step-number {
            background: var(--btn-bg);
            border-color: var(--btn-bg);
            color: var(--btn-text);
        }
        .step:not(:last-child)::after {
            content: '';
            flex: 1;
            height: 2px;
            background: var(--sidebar-bg);
            min-width: 30px;
            margin-left: 16px;
        }

        /* ── CHECKOUT GRID ── */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 2rem;
            margin: 1.5rem 0 3rem;
        }

        /* ── ORDER SUMMARY ── */
        .order-summary, .checkout-form {
            background: var(--card-bg);
            border-radius: 1.2rem;
            padding: 1.8rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.03);
            transition: box-shadow 0.3s;
        }
        .order-summary:hover, .checkout-form:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.06);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            padding-bottom: 0.6rem;
            border-bottom: 2px solid var(--sidebar-bg);
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        .section-title i { color: var(--btn-bg); }

        /* ── STORE GROUPS ── */
        .store-group {
            margin-bottom: 1.2rem;
            border: 1px solid var(--sidebar-bg);
            border-radius: 0.8rem;
            overflow: hidden;
        }
        .store-header {
            background: var(--sidebar-bg);
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            color: var(--text-primary);
        }
        .store-header i { color: var(--btn-bg); }

        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 0.8rem 1rem;
            border-bottom: 1px solid var(--sidebar-bg);
            align-items: center;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 0.5rem;
            background: var(--body-bg);
            flex-shrink: 0;
        }
        .cart-item-details {
            flex: 1;
            min-width: 0;
        }
        .cart-item-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cart-item-price {
            font-weight: 700;
            color: var(--btn-bg);
            font-size: 0.9rem;
        }
        .cart-item-quantity {
            font-size: 0.8rem;
            color: var(--sidebar-text);
        }

        /* ── SUMMARY TOTALS ── */
        .summary-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--sidebar-bg);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        .summary-row.shipping-row, .summary-row.tax-row {
            color: var(--sidebar-text);
        }
        .summary-total {
            font-weight: 800;
            font-size: 1.2rem;
            border-top: 1px solid var(--sidebar-bg);
            margin-top: 0.5rem;
            padding-top: 0.8rem;
        }
        .summary-total .amount {
            color: var(--btn-bg);
        }

        /* ── FORM ── */
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        .form-group label .required {
            color: #ef4444;
            margin-left: 2px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1.5px solid var(--sidebar-bg);
            border-radius: 0.7rem;
            font-family: inherit;
            font-size: 0.95rem;
            background: var(--body-bg);
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--btn-bg);
            box-shadow: 0 0 0 3px rgba(39,168,90,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .payment-methods {
            margin: 1.5rem 0;
        }
        .payment-methods h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            color: var(--text-primary);
        }
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 1rem;
            border: 2px solid var(--sidebar-bg);
            border-radius: 0.7rem;
            margin-bottom: 0.7rem;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--body-bg);
        }
        .payment-method:hover {
            border-color: var(--btn-bg);
        }
        .payment-method.selected {
            border-color: var(--btn-bg);
            background: rgba(39,168,90,0.05);
            box-shadow: 0 0 0 3px rgba(39,168,90,0.08);
        }
        .payment-method input {
            width: auto;
            margin: 0;
            accent-color: var(--btn-bg);
        }
        .payment-method label {
            flex: 1;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .payment-method .method-icon {
            font-size: 1.2rem;
            width: 28px;
            text-align: center;
        }

        /* ── PLACE ORDER BUTTON ── */
        .place-order-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--btn-bg), var(--btn-bg-dark));
            color: var(--btn-text);
            padding: 0.9rem;
            border: none;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1.05rem;
            cursor: pointer;
            margin-top: 1.2rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(39,168,90,0.25);
            letter-spacing: 0.3px;
        }
        .place-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(39,168,90,0.35);
        }
        .place-order-btn:active { transform: translateY(0); }
        .place-order-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        /* ── EMPTY CART ── */
        .empty-cart {
            text-align: center;
            padding: 2rem 1rem;
        }
        .empty-cart i {
            font-size: 3rem;
            color: var(--sidebar-text);
            opacity: 0.3;
        }
        .empty-cart p {
            margin: 0.8rem 0 1rem;
            color: var(--sidebar-text);
        }
        .empty-cart a {
            color: var(--btn-bg);
            text-decoration: none;
            font-weight: 600;
            border: 2px solid var(--btn-bg);
            padding: 0.4rem 1.2rem;
            border-radius: 2rem;
            transition: all 0.2s;
        }
        .empty-cart a:hover {
            background: var(--btn-bg);
            color: var(--btn-text);
        }

        /* ── TOAST ── */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #10b981;
            color: white;
            padding: 14px 24px;
            border-radius: 50px;
            z-index: 1000;
            font-weight: 600;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            animation: slideUp 0.3s ease;
        }
        .toast.error { background: #ef4444; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── FOOTER ── */
        footer {
            background: var(--btn-bg-dark);
            color: rgba(255,255,255,.85);
            padding: 40px 20px 20px;
            margin-top: 3rem;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        .footer-col h4 {
            color: #fff;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
            border-bottom: 2px solid var(--btn-bg);
            padding-bottom: 6px;
        }
        .footer-col a {
            display: block;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 6px;
            transition: color .2s;
        }
        .footer-col a:hover { color: #fff; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,.1);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .social-links { display: flex; gap: 14px; }
        .social-links a {
            color: rgba(255,255,255,.6);
            font-size: 18px;
            transition: color .2s;
            text-decoration: none;
        }
        .social-links a:hover { color: #fff; }

        /* ── RESPONSIVE ── */
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
        @media (max-width: 768px) {
            .container { padding: 0 16px; }
            header { flex-wrap: wrap; gap: 10px; }
            .search-bar { order: 3; flex-basis: 100%; max-width: 100%; margin: 0; }
            .header-actions { gap: 12px; }
            .progress-steps { flex-wrap: wrap; gap: 10px; justify-content: center; }
            .step { padding: 0 10px; }
            .step:not(:last-child)::after { display: none; }
            .order-summary, .checkout-form { padding: 1.2rem; }
            .cart-item img { width: 44px; height: 44px; }
        }
        @media (max-width: 480px) {
            .logo { font-size: 20px; }
            .step .step-number { width: 28px; height: 28px; font-size: 12px; }
            .step span:not(.step-number) { display: none; }
        }
    </style>
</head>
<body>

<!-- TOP STRIP -->
<div class="top-strip">🚚 Free delivery on orders above ₦10,000 &nbsp;|&nbsp; ✅ 100% Genuine Products &nbsp;|&nbsp; 🔄 Easy Returns</div>

<!-- HEADER -->
<header>
    <a href="marketplace" class="logo"><img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span></a>
    <div class="search-bar">
        <form method="get" action="marketplace" style="display:flex; flex:1; width:100%;">
            <input type="text" name="q" placeholder="Search products, brands and categories…" />
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="header-actions">
        <a href="marketplaceaddtocart">
            <div class="cart-badge">
                <i class="fas fa-shopping-cart"></i>
                <span class="badge" id="cartCount">0</span>
            </div>
            <span>Cart</span>
        </a>
    </div>
</header>

<div class="container">
    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step active">
            <span class="step-number">1</span>
            <span>Shipping</span>
        </div>
        <div class="step">
            <span class="step-number">2</span>
            <span>Payment</span>
        </div>
        <div class="step">
            <span class="step-number">3</span>
            <span>Confirm</span>
        </div>
    </div>

    <div class="checkout-container">
        <!-- Order Summary -->
        <div class="order-summary">
            <div class="section-title"><i class="fas fa-shopping-bag"></i> Order Summary</div>
            <div id="cartSummary" class="cart-items-list"><div class="empty-cart">Loading...</div></div>
            <div id="totalSummary" class="summary-details"></div>
        </div>

        <!-- Checkout Form -->
        <div class="checkout-form">
            <div class="section-title"><i class="fas fa-info-circle"></i> Shipping Information</div>
            <form id="checkoutForm">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="user_name" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" id="user_email" placeholder="john@example.com" required>
                </div>
                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" id="user_phone" placeholder="08012345678" required>
                </div>
                <div class="form-group">
                    <label>Delivery Address <span class="required">*</span></label>
                    <textarea id="user_address" rows="3" placeholder="123 Street, City, State, Nigeria" required></textarea>
                </div>

                <div class="form-group">
                    <label>State <span class="required">*</span></label>
                    <select id="user_state" required>
                        <option value="">Select your state</option>
                        <?php foreach ($nigeria_states as $state): ?>
                            <option value="<?= htmlspecialchars($state) ?>"><?= htmlspecialchars($state) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="payment-methods">
                    <h3><i class="fas fa-credit-card"></i> Choose Payment Method</h3>
                    <div class="payment-method" data-method="paystack">
                        <input type="radio" name="payment_method" value="paystack" id="paystack" checked>
                        <label for="paystack">
                            <span class="method-icon"><i class="fas fa-bolt" style="color:#4f46e5;"></i></span>
                            Pay with Paystack
                        </label>
                    </div>
                    <div class="payment-method" data-method="flutterwave">
                        <input type="radio" name="payment_method" value="flutterwave" id="flutterwave">
                        <label for="flutterwave">
                            <span class="method-icon"><i class="fas fa-water" style="color:#0ea5e9;"></i></span>
                            Pay with Flutterwave
                        </label>
                    </div>
                </div>

                <button type="submit" class="place-order-btn" id="placeOrderBtn">
                    <i class="fas fa-lock"></i> Place Order & Pay
                </button>
            </form>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>RD Vendora</h4>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="#">Press</a>
                <a href="#">Contact Us</a>
                <a href="#">Affiliates</a>
            </div>
            <div class="footer-col">
                <h4>Help</h4>
                <a href="#">FAQ</a>
                <a href="#">Track Order</a>
                <a href="#">Returns</a>
                <a href="#">Report a Product</a>
            </div>
            <div class="footer-col">
                <h4>Sell on RD Vendora</h4>
                <a href="#">Become a Seller</a>
                <a href="#">Seller Center</a>
                <a href="#">Flash Sales</a>
                <a href="#">Advertise</a>
            </div>
            <div class="footer-col">
                <h4>Payment</h4>
                <a href="#">RD Pay</a>
                <a href="#">Cards Accepted</a>
                <a href="#">Bank Transfer</a>
                <a href="#">Pay on Delivery</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> RD Vendora. All rights reserved.</span>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-whatsapp"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
</footer>

<script>
// ── CART KEY ──
const CART_KEY = "greenshop_cart";

function getCart() {
    const c = localStorage.getItem(CART_KEY);
    return c ? JSON.parse(c) : [];
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, m => m==='&'?'&amp;':m==='<'?'&lt;':'&gt;');
}

function showToast(msg, type='success') {
    const toast = document.createElement('div');
    toast.className = 'toast' + (type === 'error' ? ' error' : '');
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${msg}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

function updateCartCount() {
    const cart = getCart();
    const total = cart.reduce((s, i) => s + i.quantity, 0);
    const badge = document.getElementById('cartCount');
    if (badge) badge.innerText = total;
}

// ── SHIPPING & TAX CALCULATION ──
const taxRate = <?= json_encode($tax_rate) ?>;
const shippingDefault = <?= json_encode($shipping_default) ?>;
const shippingStates = <?= json_encode($shipping_states) ?>;

function getShippingFee(state) {
    if (!state) return shippingDefault;
    return shippingStates[state] ?? shippingDefault;
}

function calculateTotals(cart, state) {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const shipping = parseFloat(getShippingFee(state));
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + shipping + tax;
    return { subtotal, shipping, tax, total };
}

function renderOrderSummary() {
    const cart = getCart();
    const summaryDiv = document.getElementById('cartSummary');
    const totalDiv = document.getElementById('totalSummary');
    const state = document.getElementById('user_state')?.value || '';

    if (cart.length === 0) {
        summaryDiv.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Your cart is empty.</p>
                <a href="marketplace">Continue Shopping</a>
            </div>
        `;
        totalDiv.innerHTML = '';
        document.getElementById('placeOrderBtn').disabled = true;
        return;
    }

    // Group by store
    const grouped = {};
    cart.forEach(item => {
        const sid = item.store_id || 0;
        if (!grouped[sid]) {
            grouped[sid] = {
                store_name: item.store_name || `Store ${sid}`,
                items: []
            };
        }
        grouped[sid].items.push(item);
    });

    let html = '';
    for (let sid in grouped) {
        const g = grouped[sid];
        html += `<div class="store-group">
            <div class="store-header"><i class="fas fa-store"></i> ${escapeHtml(g.store_name)}</div>`;
        g.items.forEach(item => {
            html += `
                <div class="cart-item">
                    <img src="${escapeHtml(item.image)}" onerror="this.src='https://placehold.co/400x400?text=No+Image'">
                    <div class="cart-item-details">
                        <h4>${escapeHtml(item.name)}</h4>
                        <div class="cart-item-price">₦${parseFloat(item.price).toFixed(2)}</div>
                        <div class="cart-item-quantity">Quantity: ${item.quantity}</div>
                    </div>
                </div>
            `;
        });
        html += `</div>`;
    }
    summaryDiv.innerHTML = html;

    const totals = calculateTotals(cart, state);
    totalDiv.innerHTML = `
        <div class="summary-row"><span>Subtotal</span><span>₦${totals.subtotal.toFixed(2)}</span></div>
        <div class="summary-row shipping-row"><span>Shipping</span><span>₦${totals.shipping.toFixed(2)}</span></div>
        <div class="summary-row tax-row"><span>Tax (${taxRate}%)</span><span>₦${totals.tax.toFixed(2)}</span></div>
        <div class="summary-row summary-total"><span>Total</span><span class="amount">₦${totals.total.toFixed(2)}</span></div>
    `;
    document.getElementById('placeOrderBtn').disabled = false;
}

document.getElementById('user_state').addEventListener('change', renderOrderSummary);

document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cart = getCart();
    if (cart.length === 0) { showToast('Your cart is empty', 'error'); return; }
    
    const userName = document.getElementById('user_name').value.trim();
    const userEmail = document.getElementById('user_email').value.trim();
    const userPhone = document.getElementById('user_phone').value.trim();
    const userAddress = document.getElementById('user_address').value.trim();
    const userState = document.getElementById('user_state').value.trim();
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (!userName || !userEmail || !userPhone || !userAddress || !userState) {
        showToast('Please fill all required fields', 'error');
        return;
    }
    if (!/^\S+@\S+\.\S+$/.test(userEmail)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }
    
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating orders...';
    
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'create_order');
        formData.append('cart', JSON.stringify(cart));
        formData.append('user_name', userName);
        formData.append('user_email', userEmail);
        formData.append('user_phone', userPhone);
        formData.append('user_address', userAddress);
        formData.append('user_state', userState);
        formData.append('payment_method', paymentMethod);
        formData.append('ajax', '1');  // tells server it's AJAX
        
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        
        const { orders, transaction_ref, amount, user_email } = data;
        const orderIds = orders.map(o => o.order_id);
        
        if (paymentMethod === 'paystack') {
            const handler = PaystackPop.setup({
                key: '<?= PAYSTACK_PUBLIC_KEY ?>',
                email: user_email,
                amount: Math.round(amount * 100),
                ref: transaction_ref,
                currency: 'NGN',
                callback: () => verifyPayment(transaction_ref, 'paystack', orderIds),
                onClose: () => {
                    showToast('Transaction cancelled', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Place Order & Pay';
                }
            });
            handler.openIframe();
        } else if (paymentMethod === 'flutterwave') {
            FlutterwaveCheckout({
                public_key: '<?= FLUTTERWAVE_PUBLIC_KEY ?>',
                tx_ref: transaction_ref,
                amount: amount,
                currency: 'NGN',
                payment_options: 'card,ussd,banktransfer,mobilemoney',
                customer: { email: user_email, phone_number: userPhone, name: userName },
                customizations: { title: 'RD Vendora', description: `Multi‑store order #${transaction_ref}` },
                callback: (resp) => {
                    if (resp.status === 'successful') {
                        verifyPayment(transaction_ref, 'flutterwave', orderIds);
                    } else {
                        showToast('Payment failed or cancelled', 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-lock"></i> Place Order & Pay';
                    }
                },
                onclose: () => {
                    showToast('Payment modal closed', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Place Order & Pay';
                }
            });
        }
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Place Order & Pay';
    }
});

async function verifyPayment(transactionRef, paymentMethod, orderIds) {
    const btn = document.getElementById('placeOrderBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying payment...';
    btn.disabled = true;
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'verify_payment');
        formData.append('transaction_ref', transactionRef);
        formData.append('payment_method', paymentMethod);
        formData.append('order_ids', JSON.stringify(orderIds));
        formData.append('ajax', '1');
        
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        if (data.success) {
            showToast('Payment successful! Redirecting...', 'success');
            localStorage.removeItem(CART_KEY);
            window.location.href = data.redirect;
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        showToast('Verification failed: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Place Order & Pay';
    }
}

// Payment method highlight
document.querySelectorAll('.payment-method').forEach(m => {
    m.addEventListener('click', function() {
        const radio = this.querySelector('input');
        radio.checked = true;
        document.querySelectorAll('.payment-method').forEach(m2 => m2.classList.remove('selected'));
        this.classList.add('selected');
        radio.dispatchEvent(new Event('change'));
    });
});
document.querySelector('.payment-method input:checked')?.closest('.payment-method')?.classList.add('selected');

document.addEventListener('DOMContentLoaded', () => {
    renderOrderSummary();
    updateCartCount();
});
</script>
</body>
</html>