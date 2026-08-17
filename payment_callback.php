<?php
session_start();
require_once 'includes/connection.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Yabacon\Paystack;
use StarfolkSoftware\Flutterwave\Client as FlutterwaveClient;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$gateway = $_GET['gateway'] ?? '';
$order_id = $_GET['order_id'] ?? 0;

if (!$gateway || !$order_id) {
    die('Invalid callback.');
}

if ($gateway === 'paystack') {
    $reference = $_GET['reference'] ?? '';
    if (!$reference) die('No reference');
    $paystack = new Paystack($_ENV['PAYSTACK_SECRET_KEY']);
    $verification = $paystack->transaction->verify(['reference' => $reference]);
    if ($verification && $verification->data->status === 'success') {
        $conn->query("UPDATE orders SET status = 'completed', payment_status = 'paid' WHERE id = $order_id");
        echo "<script>localStorage.removeItem('marketplace_cart'); window.location.href='order-confirmation.php?id=$order_id&payment=success';</script>";
    } else {
        $conn->query("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = $order_id");
        header('Location: order-confirmation.php?id=' . $order_id . '&payment=failed');
    }
}
elseif ($gateway === 'flutterwave') {
    $transaction_id = $_GET['transaction_id'] ?? '';
    if (!$transaction_id) die('No transaction ID');
    $flutterwave = new FlutterwaveClient(['secretKey' => $_ENV['FLUTTERWAVE_SECRET_KEY']]);
    $verification = $flutterwave->transactions->verify($transaction_id);
    if ($verification && isset($verification['data']['status']) && $verification['data']['status'] === 'successful') {
        $conn->query("UPDATE orders SET status = 'completed', payment_status = 'paid' WHERE id = $order_id");
        echo "<script>localStorage.removeItem('marketplace_cart'); window.location.href='order-confirmation.php?id=$order_id&payment=success';</script>";
    } else {
        $conn->query("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = $order_id");
        header('Location: order-confirmation.php?id=' . $order_id . '&payment=failed');
    }
} else {
    die('Invalid gateway');
}
?>