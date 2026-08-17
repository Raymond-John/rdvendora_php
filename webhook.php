<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input && isset($input['event']) && $input['event'] === 'charge.completed') {
    $transaction_id = $input['data']['id'];
    $status = $input['data']['status'];
    
    if ($status === 'successful') {
        // Update order status in your database
        // You'll need to map transaction_id to order_id
    }
}

http_response_code(200);
?>