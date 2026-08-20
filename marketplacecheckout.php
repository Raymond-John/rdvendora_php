<?php
// ============================================================
// 1. AJAX DETECTION â€“ MUST BE FIRST (before any output)
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
    // Suppress all PHP errors/warnings for AJAX â€“ we handle them ourselves
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
                throw new Exception('Payment verification failed â€“ amount mismatch or gateway error');
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
// 5. NOT AJAX â€“ OUTPUT HTML
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Checkout â€” RD Vendora Marketplace</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/css/marketplace.css') : 'assets/css/marketplace.css', ENT_QUOTES, 'UTF-8') ?>">
  <script src="https://js.paystack.co/v1/inline.js"></script>
  <script src="https://checkout.flutterwave.com/v3.js"></script>
  <style>
    :root {
      --body-bg: <?= htmlspecialchars($body_bg_color) ?>;
      --text-primary: <?= htmlspecialchars($text_primary_color) ?>;
      --btn-bg: <?= htmlspecialchars($primary_btn_bg) ?>;
      --btn-text: <?= htmlspecialchars($primary_btn_text) ?>;
      --card-bg: <?= htmlspecialchars($card_bg_color) ?>;
      --sidebar-bg: <?= htmlspecialchars($sidebar_bg_color) ?>;
      --sidebar-text: <?= htmlspecialchars($sidebar_text_color) ?>;
      --btn-bg-dark: <?= htmlspecialchars($btn_bg_dark) ?>;
      --btn-bg-darker: <?= htmlspecialchars($btn_bg_darker) ?>;
    }
  </style>
</head>
<body class="mp-page">
<?php
$mpActive = 'checkout';
$mpSearch = '';
$mpCategories = [];
$mpSelectedCategory = '';
$mpShowCategories = false;
require __DIR__ . '/includes/marketplace_header.php';
?>

<div class="mp-page-title">
  <h1>Checkout</h1>
</div>

<div class="mp-checkout-layout">
  <section class="mp-panel">
    <div class="mp-guest-note">
      Guest checkout is available. Enter your delivery details below â€” you do not need an RD Vendora account to complete this order.
    </div>
    <form id="checkoutForm" novalidate>
      <h2 class="section-title" style="margin:0 0 1rem;font-size:1.05rem;">Delivery details</h2>
      <div class="mp-form-grid two">
        <div class="mp-field">
          <label for="user_name">Full name <span class="req">*</span></label>
          <input type="text" id="user_name" name="user_name" autocomplete="name" required>
        </div>
        <div class="mp-field">
          <label for="user_email">Email <span class="req">*</span></label>
          <input type="email" id="user_email" name="user_email" autocomplete="email" inputmode="email" required>
        </div>
        <div class="mp-field">
          <label for="user_phone">Phone <span class="req">*</span></label>
          <input type="tel" id="user_phone" name="user_phone" autocomplete="tel" inputmode="tel" required>
        </div>
        <div class="mp-field">
          <label for="user_state">State <span class="req">*</span></label>
          <select id="user_state" name="user_state" required>
            <option value="">Select state</option>
            <?php foreach ($nigeria_states as $state): ?>
              <option value="<?= htmlspecialchars($state) ?>"><?= htmlspecialchars($state) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mp-field" style="margin-top:0.85rem">
        <label for="user_address">Delivery address <span class="req">*</span></label>
        <textarea id="user_address" name="user_address" autocomplete="street-address" required placeholder="Street, city, landmark"></textarea>
      </div>

      <h2 style="margin:1.4rem 0 0.75rem;font-size:1.05rem;">Payment method</h2>
      <div class="payment-methods">
        <label class="mp-pay-option selected">
          <input type="radio" name="payment_method" value="paystack" checked>
          <span><strong>Paystack</strong><br><small style="color:var(--mp-muted)">Cards, bank &amp; USSD</small></span>
        </label>
        <label class="mp-pay-option">
          <input type="radio" name="payment_method" value="flutterwave">
          <span><strong>Flutterwave</strong><br><small style="color:var(--mp-muted)">Cards &amp; transfers</small></span>
        </label>
      </div>

      <button type="submit" class="mp-btn mp-btn-primary mp-btn-block" id="placeOrderBtn">
        Continue to Payment
      </button>
    </form>
  </section>

  <aside class="mp-panel">
    <button type="button" class="mp-summary-toggle" id="mpSummaryToggle" aria-expanded="false">
      <span>Order summary</span>
      <strong id="summaryToggleTotal">â‚¦0.00</strong>
    </button>
    <div class="mp-order-panel-body" id="mpOrderPanelBody" hidden>
      <div id="cartSummary"></div>
      <div class="summary-details" id="totalSummary"></div>
    </div>
    <div class="mp-order-panel-body mp-order-desktop" id="mpOrderDesktop">
      <h2 style="margin:0 0 0.75rem;font-size:1.05rem;">Order summary</h2>
      <div id="cartSummaryDesktop"></div>
      <div class="summary-details" id="totalSummaryDesktop"></div>
    </div>
  </aside>
</div>

<style>
@media (max-width: 899px) {
  .mp-order-desktop { display: none !important; }
}
@media (min-width: 900px) {
  #mpSummaryToggle { display: none; }
  #mpOrderPanelBody { display: none !important; }
}
.mp-summary-row { display:flex; justify-content:space-between; padding:0.4rem 0; }
.mp-summary-total, .summary-total { font-weight:800; border-top:1px solid var(--mp-line); margin-top:0.5rem; padding-top:0.75rem; }
</style>

<?php require __DIR__ . '/includes/marketplace_footer.php'; ?>
<script>
const CART_KEY = "greenshop_cart";
const taxRate = <?= json_encode($tax_rate) ?>;
const shippingDefault = <?= json_encode($shipping_default) ?>;
const shippingStates = <?= json_encode($shipping_states) ?>;

function getCart() {
  try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch (e) { return []; }
}
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}
function showToast(msg, type) {
  if (window.MPCart) return window.MPCart.showToast(msg, type || 'success');
  alert(msg);
}
function getShippingFee(state) {
  if (!state) return shippingDefault;
  return shippingStates[state] ?? shippingDefault;
}
function calculateTotals(cart, state) {
  const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const shipping = parseFloat(getShippingFee(state));
  const tax = subtotal * (taxRate / 100);
  return { subtotal, shipping, tax, total: subtotal + shipping + tax };
}
function renderOrderSummary() {
  const cart = getCart();
  const state = document.getElementById('user_state')?.value || '';
  const totals = calculateTotals(cart, state);
  const toggleTotal = document.getElementById('summaryToggleTotal');
  if (toggleTotal) toggleTotal.textContent = 'â‚¦' + totals.total.toFixed(2);

  const targets = [
    { list: 'cartSummary', totals: 'totalSummary' },
    { list: 'cartSummaryDesktop', totals: 'totalSummaryDesktop' }
  ];

  targets.forEach(({ list, totals: totId }) => {
    const summaryDiv = document.getElementById(list);
    const totalDiv = document.getElementById(totId);
    if (!summaryDiv || !totalDiv) return;
    if (!cart.length) {
      summaryDiv.innerHTML = `<div class="mp-empty-cart"><p>Your cart is empty.</p><a class="mp-btn mp-btn-outline" href="${(window.MP_URLS&&window.MP_URLS.home)||'marketplace'}">Start Shopping</a></div>`;
      totalDiv.innerHTML = '';
      document.getElementById('placeOrderBtn').disabled = true;
      return;
    }
    document.getElementById('placeOrderBtn').disabled = false;
    let html = '';
    cart.forEach(item => {
      html += `<div class="mp-cart-item" style="grid-template-columns:56px 1fr">
        <img src="${escapeHtml(item.image||'')}" alt="" onerror="this.src='assets/brand-logo.png'">
        <div>
          <h3>${escapeHtml(item.name)}</h3>
          <div class="mp-cart-meta">${escapeHtml(item.store_name||'')} Â· Qty ${item.quantity}</div>
          <strong>â‚¦${(parseFloat(item.price)*item.quantity).toFixed(2)}</strong>
        </div>
      </div>`;
    });
    summaryDiv.innerHTML = html;
    totalDiv.innerHTML = `
      <div class="mp-summary-row"><span>Subtotal</span><span>â‚¦${totals.subtotal.toFixed(2)}</span></div>
      <div class="mp-summary-row"><span>Shipping</span><span>â‚¦${totals.shipping.toFixed(2)}</span></div>
      <div class="mp-summary-row"><span>Tax (${taxRate}%)</span><span>â‚¦${totals.tax.toFixed(2)}</span></div>
      <div class="mp-summary-row mp-summary-total"><span>Total</span><strong>â‚¦${totals.total.toFixed(2)}</strong></div>`;
  });
}

document.getElementById('mpSummaryToggle')?.addEventListener('click', function () {
  const body = document.getElementById('mpOrderPanelBody');
  const open = body.hasAttribute('hidden');
  if (open) body.removeAttribute('hidden'); else body.setAttribute('hidden', '');
  this.setAttribute('aria-expanded', open ? 'true' : 'false');
});

document.getElementById('user_state').addEventListener('change', renderOrderSummary);

document.querySelectorAll('.mp-pay-option').forEach(m => {
  m.addEventListener('click', function () {
    this.querySelector('input').checked = true;
    document.querySelectorAll('.mp-pay-option').forEach(x => x.classList.remove('selected'));
    this.classList.add('selected');
  });
});

document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const cart = getCart();
  if (!cart.length) { showToast('Your cart is empty', 'error'); return; }

  const userName = document.getElementById('user_name').value.trim();
  const userEmail = document.getElementById('user_email').value.trim();
  const userPhone = document.getElementById('user_phone').value.trim();
  const userAddress = document.getElementById('user_address').value.trim();
  const userState = document.getElementById('user_state').value.trim();
  const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

  if (!userName || !userEmail || !userPhone || !userAddress || !userState) {
    showToast('Please fill all required fields', 'error'); return;
  }
  if (!/^\S+@\S+\.\S+$/.test(userEmail)) {
    showToast('Please enter a valid email address', 'error'); return;
  }

  const btn = document.getElementById('placeOrderBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating orderâ€¦';

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
    formData.append('ajax', '1');

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
          btn.innerHTML = 'Continue to Payment';
        }
      });
      handler.openIframe();
    } else {
      FlutterwaveCheckout({
        public_key: '<?= FLUTTERWAVE_PUBLIC_KEY ?>',
        tx_ref: transaction_ref,
        amount: amount,
        currency: 'NGN',
        payment_options: 'card,ussd,banktransfer,mobilemoney',
        customer: { email: user_email, phone_number: userPhone, name: userName },
        customizations: { title: 'RD Vendora', description: `Marketplace order #${transaction_ref}` },
        callback: (resp) => {
          if (resp.status === 'successful') verifyPayment(transaction_ref, 'flutterwave', orderIds);
          else {
            showToast('Payment failed or cancelled', 'error');
            btn.disabled = false;
            btn.innerHTML = 'Continue to Payment';
          }
        },
        onclose: () => {
          showToast('Payment modal closed', 'error');
          btn.disabled = false;
          btn.innerHTML = 'Continue to Payment';
        }
      });
    }
  } catch (err) {
    showToast(err.message, 'error');
    btn.disabled = false;
    btn.innerHTML = 'Continue to Payment';
  }
});

async function verifyPayment(transactionRef, paymentMethod, orderIds) {
  const btn = document.getElementById('placeOrderBtn');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying paymentâ€¦';
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
      showToast('Payment successful! Redirectingâ€¦', 'success');
      localStorage.removeItem(CART_KEY);
      window.location.href = data.redirect;
    } else throw new Error(data.message);
  } catch (err) {
    showToast('Verification failed: ' + err.message, 'error');
    btn.disabled = false;
    btn.innerHTML = 'Continue to Payment';
  }
}

document.addEventListener('DOMContentLoaded', function () {
  renderOrderSummary();
  if (window.MPCart) window.MPCart.updateCartCount();
});
</script>
</body>
</html>

