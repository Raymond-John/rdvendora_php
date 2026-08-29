<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/email_functions.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
$token = trim((string) ($_GET['token'] ?? ''));

$result = ['ok' => false, 'already' => false, 'message' => 'Invalid request.'];
if ($orderId > 0 && $token !== '') {
    $result = rdv_confirm_order_received($orderId, $token, $conn);
}

$pageTitle = $result['ok'] ? 'Order Received' : 'Confirmation Failed';
$isSuccess = !empty($result['ok']);
$isAlready = !empty($result['already']);
$message = (string) ($result['message'] ?? '');
$orderRef = '';
if (!empty($result['order'])) {
    $orderRef = (string) ($result['order']['order_ref'] ?? ('#' . ($result['order']['id'] ?? '')));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(180deg, #f5f7fb 0%, #eef2f7 100%);
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .card-header {
            background: #0a3d91;
            border-bottom: 6px solid #d4af37;
            color: #fff;
            padding: 22px 28px;
            font-size: 20px;
            font-weight: 700;
        }
        .card-body { padding: 32px 28px 28px; text-align: center; }
        .icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            margin-bottom: 18px;
        }
        .icon.success { background: #dcfce7; color: #16a34a; }
        .icon.info { background: #dbeafe; color: #1a56db; }
        .icon.error { background: #fee2e2; color: #dc2626; }
        h1 { font-size: 24px; margin-bottom: 10px; }
        p { color: #64748b; line-height: 1.7; margin-bottom: 24px; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
        }
        .btn-primary { background: #1a56db; color: #fff; }
        .btn-gold { background: #d4af37; color: #0a3d91; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">RD Vendora</div>
        <div class="card-body">
            <?php if ($isSuccess): ?>
                <div class="icon <?= $isAlready ? 'info' : 'success' ?>"><?= $isAlready ? 'ℹ️' : '✅' ?></div>
                <h1><?= $isAlready ? 'Already Confirmed' : 'Delivery Confirmed' ?></h1>
                <p><?= htmlspecialchars($message) ?><?php if ($orderRef !== ''): ?><br><strong>Order <?= htmlspecialchars($orderRef) ?></strong><?php endif; ?></p>
            <?php else: ?>
                <div class="icon error">⚠️</div>
                <h1>Unable to Confirm</h1>
                <p><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>
            <div class="actions">
                <a class="btn btn-primary" href="<?= htmlspecialchars(rdv_url('marketplace')) ?>">Continue</a>
                <?php if ($isSuccess): ?>
                    <a class="btn btn-gold" href="<?= htmlspecialchars(rdv_url('customer-profile')) ?>">My Account</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
