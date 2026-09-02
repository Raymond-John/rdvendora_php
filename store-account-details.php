<?php
session_start();

require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/store_account_details.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    die('Database connection failed.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$userId = (int) $_SESSION['user_id'];
rdv_ensure_store_account_details_table($conn);

$stmt = $conn->prepare('SELECT id, store_name, store_slug, status FROM stores WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$store) {
    header('Location: create-store');
    exit;
}

$storeId = (int) $store['id'];
$_SESSION['store_id'] = $storeId;
$_SESSION['store_name'] = $store['store_name'];
$_SESSION['store_slug'] = $store['store_slug'];

$existing = rdv_store_account_details_get($conn, $storeId);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rdv_csrf_verify()) {
        $error = 'Please refresh the page and try again.';
    } else {
        $result = rdv_store_account_details_save($conn, $storeId, $userId, $_POST);
        if ($result['ok']) {
            header('Location: dashboard?account_details=1');
            exit;
        }
        $error = $result['message'];
        $existing = array_merge($existing ?: [], $_POST);
    }
}

$defaults = [
    'business_name' => $existing['business_name'] ?? ($store['store_name'] ?? ''),
    'contact_phone' => $existing['contact_phone'] ?? '',
    'contact_email' => $existing['contact_email'] ?? ($_SESSION['email'] ?? ''),
    'business_address' => $existing['business_address'] ?? '',
    'city' => $existing['city'] ?? '',
    'state_region' => $existing['state_region'] ?? '',
    'country' => $existing['country'] ?? 'Nigeria',
    'bank_name' => $existing['bank_name'] ?? '',
    'account_name' => $existing['account_name'] ?? '',
    'account_number' => $existing['account_number'] ?? '',
    'account_type' => $existing['account_type'] ?? 'savings',
    'notes' => $existing['notes'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store account details - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/auth.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(rdv_asset('assets/css/responsive.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .account-wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        .account-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 1.75rem; }
        .account-header { margin-bottom: 1.5rem; }
        .account-header h1 { font-size: 1.6rem; margin: 0 0 0.5rem; }
        .account-header p { color: var(--text-secondary); margin: 0; line-height: 1.6; }
        .account-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .account-grid .full { grid-column: 1 / -1; }
        .account-note { margin-top: 1rem; padding: 0.85rem 1rem; border-radius: var(--radius-lg); background: rgba(99,102,241,0.08); color: var(--text-secondary); font-size: 0.9rem; }
        .error-message { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; padding: 0.75rem 1rem; border-radius: var(--radius-lg); margin-bottom: 1rem; font-size: 0.875rem; }
        @media (max-width: 640px) { .account-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="auth-page">
    <div class="auth-bg"></div>
    <div class="account-wrap">
        <div class="auth-logo" style="margin-bottom:1.25rem;display:flex;align-items:center;gap:0.6rem;">
            <img class="rdv-brand-logo" src="<?= htmlspecialchars(rdv_asset('assets/brand-logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="" style="height:40px;width:auto;max-width:120px;object-fit:contain;background:#fff;border-radius:8px;padding:4px 8px;">
            <span class="rdv-brand-name">RD Vendora</span>
        </div>

        <div class="account-card">
            <div class="account-header">
                <h1>Store account details</h1>
                <p>Complete your payout and business information for <strong><?= htmlspecialchars($store['store_name']) ?></strong>. An administrator will review your store after you submit this form.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= rdv_csrf_field() ?>
                <div class="account-grid">
                    <div class="form-group full">
                        <label class="form-label" for="business_name">Business / legal name *</label>
                        <input class="form-input" type="text" id="business_name" name="business_name" required maxlength="200" value="<?= htmlspecialchars($defaults['business_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_phone">Phone number *</label>
                        <input class="form-input" type="tel" id="contact_phone" name="contact_phone" required maxlength="30" value="<?= htmlspecialchars($defaults['contact_phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="contact_email">Contact email *</label>
                        <input class="form-input" type="email" id="contact_email" name="contact_email" required maxlength="191" value="<?= htmlspecialchars($defaults['contact_email']) ?>">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="business_address">Business address *</label>
                        <textarea class="form-input" id="business_address" name="business_address" rows="2" required><?= htmlspecialchars($defaults['business_address']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="city">City *</label>
                        <input class="form-input" type="text" id="city" name="city" required maxlength="100" value="<?= htmlspecialchars($defaults['city']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="state_region">State / region *</label>
                        <input class="form-input" type="text" id="state_region" name="state_region" required maxlength="100" value="<?= htmlspecialchars($defaults['state_region']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="country">Country</label>
                        <input class="form-input" type="text" id="country" name="country" maxlength="100" value="<?= htmlspecialchars($defaults['country']) ?>">
                    </div>
                    <div class="form-group full" style="margin-top:0.5rem;">
                        <h2 style="font-size:1rem;margin:0 0 0.75rem;">Payout account</h2>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="bank_name">Bank name *</label>
                        <input class="form-input" type="text" id="bank_name" name="bank_name" required maxlength="120" value="<?= htmlspecialchars($defaults['bank_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="account_type">Account type *</label>
                        <select class="form-input" id="account_type" name="account_type" required>
                            <option value="savings"<?= $defaults['account_type'] === 'savings' ? ' selected' : '' ?>>Savings</option>
                            <option value="current"<?= $defaults['account_type'] === 'current' ? ' selected' : '' ?>>Current</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="account_name">Account name *</label>
                        <input class="form-input" type="text" id="account_name" name="account_name" required maxlength="120" value="<?= htmlspecialchars($defaults['account_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="account_number">Account number *</label>
                        <input class="form-input" type="text" id="account_number" name="account_number" required maxlength="10" pattern="[0-9]{10}" inputmode="numeric" value="<?= htmlspecialchars($defaults['account_number']) ?>">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="notes">Additional notes</label>
                        <textarea class="form-input" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Optional information for the admin team"><?= htmlspecialchars($defaults['notes']) ?></textarea>
                    </div>
                </div>

                <div class="account-note">
                    Your store status is <strong><?= htmlspecialchars(ucfirst((string) $store['status'])) ?></strong>. Features stay locked until an administrator approves your store.
                </div>

                <div style="display:flex;justify-content:flex-end;margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary">Submit account details</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
