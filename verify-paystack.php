<?php
header('Content-Type: application/json');
require_once 'includes/connection.php';

// Use the existing connection from connection.php
if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed.']);
    exit;
}

// Your Paystack secret key (test/live)
$paystackSecretKey = rdv_env('PAYSTACK_SECRET_KEY', '');

$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference'] ?? '';
$orderId = (int)($input['order_id'] ?? 0);
$storeId = (int)($input['store_id'] ?? 0);

if (!$reference || !$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing reference or order ID']);
    exit;
}

// Verify with Paystack API
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $paystackSecretKey",
        "Cache-Control: no-cache"
    ]
]);
$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    echo json_encode(['success' => false, 'message' => 'cURL error: ' . $err]);
    exit;
}

$result = json_decode($response, true);
if ($httpCode === 200 && $result['status'] && $result['data']['status'] == 'success') {
    // Update order status to 'paid'
    $paymentDetails = json_encode($result['data']);
    
    // Check if store_id column exists before using it
    $updateSql = "UPDATE orders SET status = 'paid', payment_details = ? WHERE id = ?";
    $params = [$paymentDetails, $orderId];
    $types = "si";
    
    // If store_id column exists and you want to use it
    $checkColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'store_id'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $updateSql .= " AND store_id = ?";
        $params[] = $storeId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
        exit;
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified',
            'order_id' => $orderId,
            'transaction_ref' => $reference
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Order not found or already updated'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Payment verification failed: ' . ($result['message'] ?? 'Unknown error')
    ]);
}
?>