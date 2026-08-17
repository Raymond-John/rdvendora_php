<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!$conn) {
    die(json_encode(['error' => 'DB connection failed']));
}

$testData = [
    'cart' => [
        [
            'store_id' => 1,
            'product_id' => 1,
            'name' => 'Test Product',
            'price' => 100,
            'quantity' => 1,
            'image' => ''
        ]
    ],
    'customer' => [
        'fullName' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'address' => '123 Test St',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'country' => 'NG',
        'postal' => '100001'
    ],
    'total' => 100,
    'payment_method' => 'paystack'
];

// Manually call the order creation logic
$sql = "INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, customer_address, payment_method, total_amount) 
        VALUES (NULL, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssd", $testData['customer']['fullName'], $testData['customer']['email'], $testData['customer']['phone'], 
                  $testData['customer']['address'] . ', ' . $testData['customer']['city'] . ', ' . $testData['customer']['state'] . ', ' . $testData['customer']['country'],
                  $testData['payment_method'], $testData['total']);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'order_id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
?>