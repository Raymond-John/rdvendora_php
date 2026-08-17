<?php
session_start();
require_once 'includes/connection.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ----- Get store (same logic as cart.php) -----
$storeId = isset($_GET['store']) ? (int)$_GET['store'] : 0;
$storeSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if ($storeId == 0 && empty($storeSlug) && isset($_SESSION['user_id'])) {
    $storeId = $_SESSION['user_id'];
}

$store = null;
if ($storeId > 0) {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("i", $storeId);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (!empty($storeSlug)) {
    $stmt = $conn->prepare("SELECT * FROM stores WHERE store_slug = ?");
    $stmt->bind_param("s", $storeSlug);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$store) {
    die('<div style="text-align:center; padding:3rem;"><h1>Store Not Found</h1><p>The store you are looking for does not exist.</p><a href="index.php">Go Home</a></div>');
}

// ----- Ensure missing colour columns exist (auto-fix) -----
$missingCols = [];
$checkNav = $conn->query("SHOW COLUMNS FROM stores LIKE 'nav_color'");
if ($checkNav->num_rows == 0) $missingCols[] = "ADD COLUMN nav_color VARCHAR(7) DEFAULT '#ffffff' AFTER brand_color";
$checkBody = $conn->query("SHOW COLUMNS FROM stores LIKE 'body_bg_color'");
if ($checkBody->num_rows == 0) $missingCols[] = "ADD COLUMN body_bg_color VARCHAR(7) DEFAULT '#f9fafb' AFTER nav_color";
$checkFooter = $conn->query("SHOW COLUMNS FROM stores LIKE 'footer_bg_color'");
if ($checkFooter->num_rows == 0) $missingCols[] = "ADD COLUMN footer_bg_color VARCHAR(7) DEFAULT '#111827' AFTER body_bg_color";

if (!empty($missingCols)) {
    $alterSQL = "ALTER TABLE stores " . implode(", ", $missingCols);
    $conn->query($alterSQL);
    // Refresh store data after altering
    $stmt = $conn->prepare("SELECT * FROM stores WHERE id = ?");
    $stmt->bind_param("i", $store['id']);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ----- Dynamic colors from store settings -----
$brandColor = $store['brand_color'] ?? '#1a56db';
$navColor = $store['nav_color'] ?? '#ffffff';
$bodyBgColor = $store['body_bg_color'] ?? '#f9fafb';
$footerBgColor = $store['footer_bg_color'] ?? '#111827';

function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    $r = max(0, min(255, $r + $percent));
    $g = max(0, min(255, $g + $percent));
    $b = max(0, min(255, $b + $percent));
    return "#".str_pad(dechex($r),2,'0',STR_PAD_LEFT).str_pad(dechex($g),2,'0',STR_PAD_LEFT).str_pad(dechex($b),2,'0',STR_PAD_LEFT);
}

$brandColorDark = adjustBrightness($brandColor, -20);
$brandColorLight = adjustBrightness($brandColor, 60);
$gradientPrimary = "linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%)";

function getTextColor($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);
    return $luminance > 128 ? '#1f2937' : '#ffffff';
}

$navTextColor = getTextColor($navColor);
$footerTextColor = getTextColor($footerBgColor);

$r = hexdec(substr($brandColor,1,2));
$g = hexdec(substr($brandColor,3,2));
$b = hexdec(substr($brandColor,5,2));

// ----- Payment Gateway Keys -----
$paystackPublicKey = rdv_env('PAYSTACK_PUBLIC_KEY', '');
$flutterwavePublicKey = rdv_env('FLUTTERWAVE_PUBLIC_KEY', '');

// ========== FETCH SHIPPING & TAX SETTINGS ==========
function getMarketplaceSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM marketplace_settings WHERE setting_key = ?");
    if (!$stmt) return $default;
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

$tax_rate = floatval(getMarketplaceSetting('tax_rate', '5'));      // default 5%
$shipping_default = floatval(getMarketplaceSetting('shipping_default', '3000'));
$shipping_states_raw = getMarketplaceSetting('shipping_states', '');
$shipping_states = !empty($shipping_states_raw) ? json_decode($shipping_states_raw, true) : [];

$nigeria_states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
    'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
    'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
    'Yobe', 'Zamfara'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($store['store_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== (All existing styles remain unchanged) ========== */
        * { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --primary: <?= $brandColor ?>;
            --primary-dark: <?= $brandColorDark ?>;
            --primary-light: <?= $brandColorLight ?>;
            --gradient-primary: <?= $gradientPrimary ?>;
            --nav-bg: <?= $navColor ?>;
            --nav-text: <?= $navTextColor ?>;
            --body-bg: <?= $bodyBgColor ?>;
            --footer-bg: <?= $footerBgColor ?>;
            --footer-text: <?= $footerTextColor ?>;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --radius: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--body-bg);
            color: var(--gray-900);
            line-height: 1.5;
            min-height: 100vh;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; }
        
        .navbar {
            background: var(--nav-bg);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--gray-200);
        }
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
            gap: 1.5rem;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 800;
            color: var(--nav-text);
        }
        .logo span {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .logo-icon {
            width: 36px;
            height: 36px;
            background: var(--gradient-primary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        .logo img {
            height: 40px;
            width: auto;
            object-fit: contain;
        }
        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--nav-text);
        }
        .btn-outline:hover {
            background: rgba(0, 0, 0, 0.05);
            border-color: var(--gray-400);
        }
        
        .checkout-page { 
            max-width: 1280px; 
            margin: 2rem auto; 
            padding: 0 1.5rem; 
        }
        
        .checkout-header {
            margin-bottom: 2rem;
        }
        
        .checkout-header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        
        .checkout-grid { 
            display: grid; 
            grid-template-columns: 1fr 380px; 
            gap: 2rem; 
        }
        
        @media (max-width: 768px) { 
            .checkout-grid { 
                grid-template-columns: 1fr; 
            }
            .nav-container {
                padding: 0 1rem;
            }
            .logo span {
                font-size: 1rem;
            }
        }
        
        .checkout-section {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }
        
        .checkout-section:hover {
            box-shadow: var(--shadow-sm);
        }
        
        .checkout-section h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-800);
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8125rem;
            font-weight: 700;
        }
        
        .form-group { margin-bottom: 1.125rem; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.375rem;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--gray-900);
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(<?= $r ?>, <?= $g ?>, <?= $b ?>, 0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        
        .payment-methods { display: flex; flex-direction: column; gap: 0.75rem; }
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
        }
        .payment-method:hover {
            border-color: var(--gray-300);
            background: var(--gray-50);
        }
        .payment-method.active { 
            border-color: var(--primary); 
            background: rgba(<?= $r ?>, <?= $g ?>, <?= $b ?>, 0.05); 
        }
        .payment-method input { 
            width: 18px; 
            height: 18px; 
            accent-color: var(--primary);
            cursor: pointer;
        }
        .payment-method-label { 
            flex: 1; 
            font-size: 0.875rem; 
            font-weight: 500;
            color: var(--gray-700);
        }
        .payment-method-icon {
            font-size: 1.25rem;
        }
        
        .order-summary {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 90px;
            box-shadow: var(--shadow);
        }
        
        .order-summary h3 {
            font-size: 1.125rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--primary-light);
            color: var(--gray-800);
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item img {
            width: 52px;
            height: 52px;
            border-radius: var(--radius);
            object-fit: cover;
            background: var(--gray-100);
        }
        .order-item-info { flex: 1; min-width: 0; }
        .order-item-name { 
            font-size: 0.8125rem; 
            font-weight: 600; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            color: var(--gray-800);
        }
        .order-item-qty { 
            font-size: 0.7rem; 
            color: var(--gray-500); 
            margin-top: 0.25rem;
        }
        .order-item-price { 
            font-size: 0.875rem; 
            font-weight: 700;
            color: var(--primary);
        }
        
        .summary-divider { 
            height: 1px; 
            background: var(--gray-200); 
            margin: 0.75rem 0; 
        }
        .summary-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 0.5rem 0; 
            font-size: 0.875rem; 
            color: var(--gray-600); 
        }
        .summary-row.total { 
            font-size: 1.125rem; 
            font-weight: 800; 
            color: var(--gray-900); 
            border-top: 2px solid var(--gray-200); 
            margin-top: 0.5rem; 
            padding-top: 1rem; 
        }
        .summary-row.total span:last-child {
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .place-order-btn {
            background: var(--gradient-primary);
            color: white;
            width: 100%;
            padding: 0.875rem;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 0.9375rem;
            margin-top: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition);
            position: relative;
        }
        .place-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .place-order-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .footer {
            background: var(--footer-bg);
            color: var(--footer-text);
            padding: 2rem 1.5rem;
            margin-top: 4rem;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .footer p {
            opacity: 0.8;
            font-size: 0.85rem;
        }
        
        .alert { 
            padding: 0.875rem 1rem; 
            border-radius: var(--radius-lg); 
            margin-bottom: 1rem; 
            font-size: 0.875rem; 
            font-weight: 500;
        }
        .alert-error { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626; 
            border: 1px solid #fca5a5; 
        }
        .alert-success { 
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #059669; 
            border: 1px solid #86efac; 
        }
        
        .place-order-btn.loading {
            color: transparent;
        }
        .place-order-btn.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-200);
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        
        [data-theme="dark"] {
            --gray-50: #1a1d28;
            --gray-100: #1e2130;
            --gray-200: #2d3139;
            --gray-300: #3a3f4a;
            --gray-400: #6b7280;
            --gray-500: #9ca3b0;
            --gray-600: #d1d5db;
            --gray-700: #e5e7eb;
            --gray-800: #f3f4f6;
            --gray-900: #f9fafb;
            --white: #1a1d28;
        }
        
        [data-theme="dark"] .checkout-section,
        [data-theme="dark"] .order-summary {
            background: #1e2130;
        }
        
        [data-theme="dark"] .form-input,
        [data-theme="dark"] .form-select {
            background: #2d3139;
            border-color: #3a3f4a;
            color: #e5e7eb;
        }
        
        [data-theme="dark"] .payment-method {
            border-color: #3a3f4a;
        }
        
        [data-theme="dark"] .payment-method:hover {
            background: #2d3139;
        }
        
        [data-theme="dark"] .order-item {
            border-bottom-color: #2d3139;
        }
    </style>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script src="https://checkout.flutterwave.com/v3.js"></script>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="storefront.php?store=<?= $store['id'] ?>" class="logo">
            <?php if (!empty($store['logo_path'])): ?>
                <img src="<?= htmlspecialchars($store['logo_path']) ?>" alt="<?= htmlspecialchars($store['store_name']) ?>">
            <?php else: ?>
                <div class="logo-icon">🛍️</div>
            <?php endif; ?>
            <span><?= htmlspecialchars($store['store_name']) ?></span>
        </a>
        <div class="store-actions">
            <a href="cart.php?store=<?= $store['id'] ?>" class="btn btn-outline">
                ← Back to Cart
            </a>
        </div>
    </div>
</nav>

<div class="checkout-page">
    <div class="checkout-header">
        <h1>Secure Checkout</h1>
    </div>
    <div id="alertContainer"></div>
    <div class="checkout-grid">
        <div>
            <div class="checkout-section">
                <h3><span class="step-number">1</span> Contact Information</h3>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" class="form-input" placeholder="you@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <input type="tel" id="phone" class="form-input" placeholder="+234 812 345 6789">
                </div>
            </div>
            <div class="checkout-section">
                <h3><span class="step-number">2</span> Shipping Address</h3>
                <div class="form-group">
                    <label class="form-label" for="fullName">Full Name *</label>
                    <input type="text" id="fullName" class="form-input" placeholder="John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label" for="address">Street Address *</label>
                    <input type="text" id="address" class="form-input" placeholder="123 Main Street">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="city">City *</label>
                        <input type="text" id="city" class="form-input" placeholder="Lagos">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="postal">Postal Code</label>
                        <input type="text" id="postal" class="form-input" placeholder="100001">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="country">Country *</label>
                        <select id="country" class="form-select">
                            <option value="NG">🇳🇬 Nigeria</option>
                            <option value="US">🇺🇸 United States</option>
                            <option value="UK">🇬🇧 United Kingdom</option>
                            <option value="GH">🇬🇭 Ghana</option>
                            <option value="KE">🇰🇪 Kenya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="state">State/Province *</label>
                        <!-- REPLACED text input with dropdown of Nigerian states -->
                        <select id="state" class="form-select">
                            <option value="">Select State</option>
                            <?php foreach ($nigeria_states as $state): ?>
                                <option value="<?= htmlspecialchars($state) ?>"><?= htmlspecialchars($state) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="checkout-section">
                <h3><span class="step-number">3</span> Payment Method</h3>
                <div class="payment-methods">
                    <label class="payment-method active">
                        <input type="radio" name="payment" value="paystack" checked>
                        <span class="payment-method-label">Paystack (Card / Bank / USSD)</span>
                        <span class="payment-method-icon">💳</span>
                    </label>
                    <label class="payment-method">
                        <input type="radio" name="payment" value="flutterwave">
                        <span class="payment-method-label">Flutterwave (Card / Mobile Money / Bank)</span>
                        <span class="payment-method-icon">🌊</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <div id="orderItems"></div>
            <div class="summary-divider"></div>
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotal">₦0.00</span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span id="shipping">₦0.00</span>
            </div>
            <div class="summary-row">
                <span>Tax (<?= $tax_rate ?>%)</span>
                <span id="tax">₦0.00</span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="total">₦0.00</span>
            </div>
            <button class="place-order-btn" id="placeOrderBtn">
                Place Order
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
            <div class="secure-badge">
                <span>🔒</span> Secure SSL Encrypted Checkout
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>. All rights reserved | Developed by RD Nexa Tech</p>
</footer>

<script>
    // ========== Store & Cart ==========
    const STORE_ID = <?= $store['id'] ?>;
    const CART_KEY = `cart_${STORE_ID}`;
    const PAYSTACK_PUBLIC_KEY = "<?= $paystackPublicKey ?>";
    const FLUTTERWAVE_PUBLIC_KEY = "<?= $flutterwavePublicKey ?>";

    // ========== Shipping & Tax from server ==========
    const TAX_RATE = <?= $tax_rate ?>;                       // e.g., 5
    const SHIPPING_DEFAULT = <?= $shipping_default ?>;      // e.g., 3000
    const SHIPPING_STATES = <?= json_encode($shipping_states) ?>; // { "Lagos": 5000, ... }

    function getCart() {
        const cart = localStorage.getItem(CART_KEY);
        return cart ? JSON.parse(cart) : [];
    }

    function getShippingFee(state) {
        if (!state) return SHIPPING_DEFAULT;
        return SHIPPING_STATES[state] ?? SHIPPING_DEFAULT;
    }

    function renderOrderSummary() {
        const cart = getCart();
        const container = document.getElementById('orderItems');
        if (!container) return;
        if (cart.length === 0) {
            window.location.href = `cart.php?store=${STORE_ID}`;
            return;
        }
        let itemsHtml = '';
        let subtotal = 0;
        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            itemsHtml += `
                <div class="order-item">
                    <img src="${item.image || 'https://placehold.co/52x52'}" alt="${escapeHtml(item.name)}">
                    <div class="order-item-info">
                        <div class="order-item-name">${escapeHtml(item.name)}</div>
                        <div class="order-item-qty">Quantity: ${item.quantity}</div>
                    </div>
                    <div class="order-item-price">₦${itemTotal.toLocaleString()}</div>
                </div>
            `;
        });
        container.innerHTML = itemsHtml;

        // Get selected state
        const stateSelect = document.getElementById('state');
        const selectedState = stateSelect.value;

        const shipping = getShippingFee(selectedState);
        const tax = subtotal * (TAX_RATE / 100);
        const total = subtotal + shipping + tax;

        document.getElementById('subtotal').innerText = `₦${subtotal.toLocaleString()}`;
        document.getElementById('shipping').innerText = shipping === 0 ? 'Free' : `₦${shipping.toLocaleString()}`;
        document.getElementById('tax').innerText = `₦${tax.toLocaleString()}`;
        document.getElementById('total').innerText = `₦${total.toLocaleString()}`;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function showAlert(message, type = 'error') {
        const alertDiv = document.getElementById('alertContainer');
        alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        setTimeout(() => alertDiv.innerHTML = '', 5000);
    }

    function getCustomerData() {
        return {
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            fullName: document.getElementById('fullName').value.trim(),
            address: document.getElementById('address').value.trim(),
            city: document.getElementById('city').value.trim(),
            postal: document.getElementById('postal').value.trim(),
            country: document.getElementById('country').value,
            state: document.getElementById('state').value.trim()
        };
    }

    function validateForm() {
        const data = getCustomerData();
        if (!data.email || !data.phone || !data.fullName || !data.address || !data.city || !data.country || !data.state) {
            showAlert('Please fill in all required fields.', 'error');
            return false;
        }
        if (!data.email.includes('@')) {
            showAlert('Enter a valid email address.', 'error');
            return false;
        }
        if (!data.phone.match(/^[\+]?[0-9]{10,15}$/)) {
            showAlert('Enter a valid phone number (10-15 digits).', 'error');
            return false;
        }
        return true;
    }

    // Payment method styling
    document.querySelectorAll('.payment-method').forEach(method => {
        method.addEventListener('click', function() {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
            this.classList.add('active');
            this.querySelector('input').checked = true;
        });
    });

    // Recalculate totals when state changes
    document.getElementById('state').addEventListener('change', renderOrderSummary);

    document.getElementById('placeOrderBtn').addEventListener('click', async function() {
        if (!validateForm()) return;

        const cart = getCart();
        if (cart.length === 0) {
            showAlert('Your cart is empty.', 'error');
            return;
        }

        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const state = document.getElementById('state').value;
        const shipping = getShippingFee(state);
        const tax = subtotal * (TAX_RATE / 100);
        const total = subtotal + shipping + tax;
        const paymentMethod = document.querySelector('input[name="payment"]:checked').value;
        const customer = getCustomerData();
        const btn = this;

        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const response = await fetch('process_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    store_id: STORE_ID,
                    cart: cart,
                    customer: customer,
                    subtotal: subtotal,
                    shipping: shipping,
                    tax: tax,
                    total: total,
                    payment_method: paymentMethod
                })
            });

            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error("Server response (non-JSON):", text);
                throw new Error("Server returned an invalid response. Please check error logs.");
            }

            if (!result.success) throw new Error(result.message || 'Failed to initialize order');

            if (paymentMethod === 'paystack') {
                payWithPaystack(result, total, customer);
            } else {
                payWithFlutterwave(result, total, customer);
            }
        } catch (err) {
            console.error("Error:", err);
            showAlert(err.message, 'error');
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    });

    function payWithPaystack(orderData, amount, customer) {
        const handler = PaystackPop.setup({
            key: PAYSTACK_PUBLIC_KEY,
            email: customer.email,
            amount: Math.round(amount * 100),
            currency: 'NGN',
            ref: orderData.transaction_ref,
            firstname: customer.fullName.split(' ')[0],
            lastname: customer.fullName.split(' ').slice(1).join(' ') || '',
            phone: customer.phone,
            metadata: {
                custom_fields: [
                    { display_name: "Store ID", variable_name: "store_id", value: STORE_ID },
                    { display_name: "Order ID", variable_name: "order_id", value: orderData.order_id }
                ]
            },
            callback: function(response) {
                verifyPayment('paystack', response.reference, orderData.order_id);
            },
            onClose: function() {
                const btn = document.getElementById('placeOrderBtn');
                btn.disabled = false;
                btn.classList.remove('loading');
                showAlert('Payment window closed.', 'error');
            }
        });
        handler.openIframe();
    }

    function payWithFlutterwave(orderData, amount, customer) {
        FlutterwaveCheckout({
            public_key: FLUTTERWAVE_PUBLIC_KEY,
            tx_ref: orderData.transaction_ref,
            amount: amount,
            currency: "NGN",
            payment_options: "card,ussd,banktransfer,mobilemoney",
            redirect_url: window.location.origin + "/RD Vendora/verify_payment.php?order_id=" + orderData.order_id + "&store_id=" + STORE_ID,
            customer: { email: customer.email, phone_number: customer.phone, name: customer.fullName },
            customizations: { title: `Order #${orderData.order_id}`, description: `Payment for order ${orderData.transaction_ref}` },
            onclose: function() {
                const btn = document.getElementById('placeOrderBtn');
                btn.disabled = false;
                btn.classList.remove('loading');
                showAlert('Payment modal closed.', 'error');
            }
        });
    }

    function verifyPayment(gateway, transactionId, orderId) {
        fetch('verify_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                gateway: gateway,
                reference: transactionId,
                order_id: orderId,
                store_id: STORE_ID
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                localStorage.removeItem(CART_KEY);
                window.location.href = 'order_success.php?order_id=' + data.order_id;
            } else {
                showAlert('Verification error: ' + data.message, 'error');
                const btn = document.getElementById('placeOrderBtn');
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showAlert('Invalid verification response - ' + error.message, 'error');
            const btn = document.getElementById('placeOrderBtn');
            btn.disabled = false;
            btn.classList.remove('loading');
        });
    }

    // Initial render
    renderOrderSummary();
</script>
</body>
</html>