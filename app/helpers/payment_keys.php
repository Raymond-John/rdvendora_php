<?php
/**
 * Paystack / Flutterwave keys from .env, overlaid by Admin → Settings.
 */
if (!function_exists('rdv_payment_keys')) {
    function rdv_payment_keys() {
        $cfg = [
            'paystack_public' => trim((string) rdv_env('PAYSTACK_PUBLIC_KEY', '')),
            'paystack_secret' => trim((string) rdv_env('PAYSTACK_SECRET_KEY', '')),
            'flutterwave_public' => trim((string) rdv_env('FLUTTERWAVE_PUBLIC_KEY', '')),
            'flutterwave_secret' => trim((string) rdv_env('FLUTTERWAVE_SECRET_KEY', '')),
            'flutterwave_encryption' => trim((string) rdv_env('FLUTTERWAVE_ENCRYPTION_KEY', '')),
        ];
        $db = $GLOBALS['conn'] ?? $GLOBALS['connect'] ?? null;
        if ($db instanceof mysqli) {
            $map = [
                'paystack_public_key' => 'paystack_public',
                'paystack_secret_key' => 'paystack_secret',
                'flutterwave_public_key' => 'flutterwave_public',
                'flutterwave_secret_key' => 'flutterwave_secret',
                'flutterwave_encryption_key' => 'flutterwave_encryption',
            ];
            foreach ($map as $settingKey => $cfgKey) {
                try {
                    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                    if (!$stmt) {
                        continue;
                    }
                    $stmt->bind_param('s', $settingKey);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $val = trim((string) ($row['setting_value'] ?? ''));
                    if ($val !== '') {
                        $cfg[$cfgKey] = $val;
                    }
                } catch (Throwable $e) {
                    error_log('rdv_payment_keys: ' . $e->getMessage());
                    break;
                }
            }
        }
        return $cfg;
    }
}
