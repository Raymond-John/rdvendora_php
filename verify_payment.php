<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// ---------- CONFIGURATION ----------
$payKeys = function_exists('rdv_payment_keys') ? rdv_payment_keys() : [];
$paystackSecretKey    = $payKeys['paystack_secret'] ?? '';
$flutterwaveSecretKey = $payKeys['flutterwave_secret'] ?? '';

// ---------- DETECT REQUEST TYPE ----------
$gateway = '';
$reference = '';
$orderId = 0;
$storeId = 0;

$input = json_decode(file_get_contents('php://input'), true);
if ($input && is_array($input)) {
    // AJAX call (Paystack)
    $gateway   = $input['gateway'] ?? '';
    $reference = $input['reference'] ?? '';
    $orderId   = (int)($input['order_id'] ?? 0);
    $storeId   = (int)($input['store_id'] ?? 0);
} else {
    // GET request (Flutterwave redirect)
    $gateway   = 'flutterwave';
    $reference = $_GET['transaction_id'] ?? $_GET['reference'] ?? '';
    $orderId   = (int)($_GET['order_id'] ?? 0);
    $storeId   = (int)($_GET['store_id'] ?? 0);
}

if (empty($reference) || $orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing reference or order ID']);
    exit;
}

// ---------- VERIFICATION ----------
$verified = false;
$paymentData = null;

if ($gateway === 'paystack') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $paystackSecretKey"]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['status'] && $result['data']['status'] === 'success') {
            $verified = true;
            $paymentData = $result['data'];
        }
    }
} else {
    // Flutterwave
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/$reference/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $flutterwaveSecretKey",
            "Content-Type: application/json"
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['status'] === 'success' && $result['data']['status'] === 'successful') {
            $verified = true;
            $paymentData = $result['data'];
        }
    }
}

// ---------- UPDATE DATABASE ON SUCCESS ----------
if ($verified && $paymentData) {
    // Ensure payment_details column exists (optional, for logging)
    $check = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_details'");
    if (!$check || $check->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_details TEXT");
    }
    
    // Update order status to 'completed' (which is now allowed in the enum)
    $updateSql = "UPDATE orders SET status = 'completed', payment_details = ? WHERE id = ?";
    $types = "si";
    $params = [json_encode($paymentData), $orderId];
    
    // If store_id column exists and we have a storeId, add condition for safety
    $checkStore = $conn->query("SHOW COLUMNS FROM orders LIKE 'store_id'");
    if ($checkStore && $checkStore->num_rows > 0 && $storeId > 0) {
        $updateSql .= " AND store_id = ?";
        $types .= "i";
        $params[] = $storeId;
    }
    
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rowsAffected = $stmt->affected_rows;
        $stmt->close();
        
        if ($rowsAffected === 0) {
            // Fallback: update without store_id condition
            $fallbackSql = "UPDATE orders SET status = 'completed', payment_details = ? WHERE id = ?";
            $fallbackStmt = $conn->prepare($fallbackSql);
            $fallbackStmt->bind_param("si", json_encode($paymentData), $orderId);
            $fallbackStmt->execute();
            $fallbackStmt->close();
        }
    }
    
    // Determine response type
    if ($input && is_array($input)) {
        // AJAX request (Paystack) → return JSON
        echo json_encode(['success' => true, 'order_id' => $orderId]);
        exit;
    } else {
        // Browser redirect (Flutterwave) → redirect to success page
        header("Location: order_success?order_id=" . $orderId);
        exit;
    }
} else {
    $errorMsg = $result['message'] ?? 'Verification failed';
    echo json_encode(['success' => false, 'message' => "Verification failed: $errorMsg"]);
    exit;
}
?>