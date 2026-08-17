<?php
session_start(); // needed if you want to store messages
require_once 'includes/connection.php';

// Use existing connection
if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    die('Database connection failed.');
}

$flutterwaveSecretKey = rdv_env('FLUTTERWAVE_SECRET_KEY', '');

// --- Read input from either GET (Flutterwave redirect) or POST (AJAX) ---
$transactionId = '';
$orderId = 0;
$storeId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Flutterwave redirects with query parameters
    $transactionId = $_GET['transaction_id'] ?? $_GET['reference'] ?? '';
    $orderId = (int)($_GET['order_id'] ?? 0);
    $storeId = (int)($_GET['store_id'] ?? 0);
} else {
    // AJAX POST with JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $transactionId = $input['transaction_id'] ?? $input['reference'] ?? '';
    $orderId = (int)($input['order_id'] ?? 0);
    $storeId = (int)($input['store_id'] ?? 0);
}

if (!$transactionId || !$orderId) {
    die('Missing transaction ID or order ID. Please go back and try again.');
}

// --- Verify with Flutterwave API ---
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/$transactionId/verify",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $flutterwaveSecretKey",
        "Content-Type: application/json"
    ]
]);
$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    die('Network error: ' . $err);
}

$result = json_decode($response, true);

// --- Check verification result ---
if ($httpCode === 200 && isset($result['status']) && $result['status'] === 'success' 
    && isset($result['data']['status']) && $result['data']['status'] === 'successful') {
    
    // Ensure payment_details column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_details'");
    if (!$checkColumn || $checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_details TEXT");
    }
    
    // Update order status
    $updateSql = "UPDATE orders SET status = 'paid', payment_details = ? WHERE id = ?";
    $types = "si";
    $params = [json_encode($result['data']), $orderId];
    
    $checkStoreCol = $conn->query("SHOW COLUMNS FROM orders LIKE 'store_id'");
    if ($checkStoreCol && $checkStoreCol->num_rows > 0 && $storeId > 0) {
        $updateSql .= " AND store_id = ?";
        $types .= "i";
        $params[] = $storeId;
    }
    
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
    
    // --- Redirect to order success page ---
    header("Location: order_success.php?order_id=" . $orderId);
    exit;
    
} else {
    // Verification failed – show error message
    $errorMsg = $result['message'] ?? 'Payment verification failed';
    die("Payment verification failed: $errorMsg. Please contact support.");
}
?>