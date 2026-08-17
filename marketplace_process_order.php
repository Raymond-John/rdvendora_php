<?php
// Only JSON output – no HTML errors
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/includes/connection.php';

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Create tables if missing (safe to run repeatedly)
$conn->query("CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(50) NULL,
    `customer_address` TEXT NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'paystack',
    `total_amount` DECIMAL(10,2) NOT NULL,
    `transaction_ref` VARCHAR(255) NULL,
    `status` ENUM('pending','processing','completed','cancelled') DEFAULT 'pending',
    `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure missing columns (MySQL 5.6 compatible)
$columns = ['user_id', 'transaction_ref', 'payment_status'];
foreach ($columns as $col) {
    $check = $conn->query("SHOW COLUMNS FROM orders LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        if ($col == 'user_id') $conn->query("ALTER TABLE orders ADD COLUMN user_id INT NULL AFTER id");
        if ($col == 'transaction_ref') $conn->query("ALTER TABLE orders ADD COLUMN transaction_ref VARCHAR(255) NULL AFTER total_amount");
        if ($col == 'payment_status') $conn->query("ALTER TABLE orders ADD COLUMN payment_status ENUM('pending','paid','failed') DEFAULT 'pending' AFTER status");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `store_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
)");

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$cart = $data['cart'] ?? [];
$customer = $data['customer'] ?? [];
$total = $data['total'] ?? 0;
$payment_method = $data['payment_method'] ?? 'paystack';

$name = trim($customer['fullName'] ?? '');
$email = trim($customer['email'] ?? '');
$phone = trim($customer['phone'] ?? '');
$address = trim($customer['address'] ?? '');
$city = trim($customer['city'] ?? '');
$state = trim($customer['state'] ?? '');
$country = trim($customer['country'] ?? '');
$postal = trim($customer['postal'] ?? '');
$full_address = "$address, $city, $state, $country" . ($postal ? ", $postal" : "");

if (empty($name) || empty($email) || empty($full_address)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}
if (empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Cart empty']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$stmt = $conn->prepare("INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, customer_address, payment_method, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("isssssd", $user_id, $name, $email, $phone, $full_address, $payment_method, $total);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    exit;
}
$order_id = $stmt->insert_id;
$stmt->close();

$item_stmt = $conn->prepare("INSERT INTO order_items (order_id, store_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?, ?)");
if ($item_stmt) {
    foreach ($cart as $item) {
        $item_stmt->bind_param("iiissi", $order_id, $item['store_id'], $item['product_id'], $item['name'], $item['price'], $item['quantity']);
        $item_stmt->execute();
    }
    $item_stmt->close();
}

$tx_ref = 'ORDER_' . $order_id . '_' . time();
$conn->query("UPDATE orders SET transaction_ref = '$tx_ref' WHERE id = $order_id");

echo json_encode(['success' => true, 'order_id' => $order_id, 'transaction_ref' => $tx_ref]);
?>