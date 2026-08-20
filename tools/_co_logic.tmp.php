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
require_once __DIR__ . '/app/helpers/marketplace_urls.php';

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
$body_bg_color = getMarketplaceSetting('body_bg_color', '#f3f5f9');
$text_primary_color = getMarketplaceSetting('text_primary_color', '#0f172a');
$primary_btn_bg = getMarketplaceSetting('primary_btn_bg', '#0A3D91');
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
// 3. PDO SETUP & PAYMENT KEYS
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'rdvendora_db');
define('DB_USER', 'root');
define('DB_PASS', '');

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
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`");
    $pdo->exec("USE `" . DB_NAME . "`");

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
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database setup failed: ' . $e->getMessage()]);
        exit;
    } else {
        die("Database setup failed: " . $e->getMessage());
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
