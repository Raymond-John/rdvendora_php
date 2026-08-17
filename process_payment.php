<?php
session_start();
require_once 'includes/connection.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Yabacon\Paystack;
use StarfolkSoftware\Flutterwave\Client as FlutterwaveClient;

if (class_exists('Dotenv\\Dotenv')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: marketplace.php');
    exit;
}

$payment_method = $_POST['payment_method'] ?? '';
$order_id = $_POST['order_id'] ?? 0;

if (!$payment_method || !$order_id) {
    die('Invalid payment request.');
}

// Fetch order
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die('Order not found.');
}

$tx_ref = 'ORDER_' . $order_id . '_' . time();

// Paystack
if ($payment_method === 'paystack') {
    $paystack = new Paystack($_ENV['PAYSTACK_SECRET_KEY']);
    $amount_in_kobo = $order['total_amount'] * 100;
    $callback_url = 'https://YOUR_DOMAIN.com/payment_callback.php?gateway=paystack&order_id=' . $order_id;
    try {
        $transaction = $paystack->transaction->initialize([
            'amount' => $amount_in_kobo,
            'email' => $order['customer_email'],
            'reference' => $tx_ref,
            'callback_url' => $callback_url,
            'metadata' => json_encode(['order_id' => $order_id, 'customer_name' => $order['customer_name']])
        ]);
        if ($transaction && $transaction->status) {
            $update = $conn->prepare("UPDATE orders SET transaction_ref = ? WHERE id = ?");
            $update->bind_param("si", $tx_ref, $order_id);
            $update->execute();
            $update->close();
            header('Location: ' . $transaction->data->authorization_url);
            exit;
        } else {
            die('Paystack init failed: ' . ($transaction->message ?? 'Unknown'));
        }
    } catch (Exception $e) {
        die('Paystack error: ' . $e->getMessage());
    }
}
// Flutterwave
elseif ($payment_method === 'flutterwave') {
    $flutterwave = new FlutterwaveClient(['secretKey' => $_ENV['FLUTTERWAVE_SECRET_KEY']]);
    $callback_url = 'https://YOUR_DOMAIN.com/payment_callback.php?gateway=flutterwave&order_id=' . $order_id;
    $payload = [
        'tx_ref' => $tx_ref,
        'amount' => $order['total_amount'],
        'currency' => 'NGN',
        'redirect_url' => $callback_url,
        'customer' => ['email' => $order['customer_email'], 'name' => $order['customer_name']],
        'customizations' => ['title' => 'RD Vendora - Order #' . $order_id, 'logo' => 'https://YOUR_DOMAIN.com/assets/logo.png']
    ];
    try {
        $response = $flutterwave->transactions->initialize($payload);
        if ($response && isset($response['data']['link'])) {
            $update = $conn->prepare("UPDATE orders SET transaction_ref = ? WHERE id = ?");
            $update->bind_param("si", $tx_ref, $order_id);
            $update->execute();
            $update->close();
            header('Location: ' . $response['data']['link']);
            exit;
        } else {
            die('Flutterwave init failed: ' . ($response['message'] ?? 'Unknown'));
        }
    } catch (Exception $e) {
        die('Flutterwave error: ' . $e->getMessage());
    }
} else {
    die('Invalid payment method.');
}
?>