<?php
/**
 * Google Sign-In config from .env, overlaid by Admin → Settings.
 */
if (!function_exists('rdv_google_callback_uri_for_host')) {
    function rdv_google_callback_uri_for_host() {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        if ($host === 'rdvendora.com' || $host === 'www.rdvendora.com') {
            return 'https://rdvendora.com/oauth2callback.php';
        }

        $https = function_exists('rdv_request_is_https') && rdv_request_is_https();
        $scheme = $https ? 'https' : 'http';
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/oauth2callback.php'));
        $base = rtrim(dirname($script), '/');
        if (substr($base, -9) === '/includes') {
            $base = dirname($base);
        }
        if (substr($base, -6) === '/admin') {
            $base = dirname($base);
        }
        if ($base === '/' || $base === '\\' || $base === '.' || $base === '') {
            $base = '';
        }
        return $scheme . '://' . $host . $base . '/oauth2callback.php';
    }
}

if (!function_exists('rdv_google_oauth_config')) {
    function rdv_google_oauth_config($conn = null) {
        $clientId = defined('GOOGLE_CLIENT_ID') ? trim((string) GOOGLE_CLIENT_ID) : '';
        $clientSecret = defined('GOOGLE_CLIENT_SECRET') ? trim((string) GOOGLE_CLIENT_SECRET) : '';
        $redirectUri = defined('GOOGLE_REDIRECT_URI') ? trim((string) GOOGLE_REDIRECT_URI) : '';

        $db = $conn instanceof mysqli ? $conn : ($GLOBALS['conn'] ?? $GLOBALS['connect'] ?? null);
        if ($db instanceof mysqli) {
            $keys = [
                'google_client_id' => 'clientId',
                'google_client_secret' => 'clientSecret',
                'google_redirect_uri' => 'redirectUri',
            ];
            foreach ($keys as $settingKey => $local) {
                $val = '';
                if (function_exists('rdv_site_setting')) {
                    $val = rdv_site_setting($db, $settingKey);
                } else {
                    try {
                        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                        if ($stmt) {
                            $stmt->bind_param('s', $settingKey);
                            $stmt->execute();
                            $row = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            $val = trim((string) ($row['setting_value'] ?? ''));
                        }
                    } catch (Throwable $e) {
                        break;
                    }
                }
                if ($val !== '') {
                    if ($local === 'clientId') {
                        $clientId = $val;
                    } elseif ($local === 'clientSecret') {
                        $clientSecret = $val;
                    } else {
                        $redirectUri = $val;
                    }
                }
            }
        }

        $computed = rdv_google_callback_uri_for_host();
        $currentHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $configuredHost = strtolower((string) (parse_url($redirectUri, PHP_URL_HOST) ?? ''));
        $liveHost = ($currentHost === 'rdvendora.com' || $currentHost === 'www.rdvendora.com');

        if ($liveHost) {
            $redirectUri = 'https://rdvendora.com/oauth2callback.php';
        } elseif ($redirectUri === '' || $configuredHost === '' || ($configuredHost !== $currentHost && $configuredHost !== 'www.' . $currentHost && 'www.' . $configuredHost !== $currentHost)) {
            $redirectUri = $computed;
        }

        $placeholder = (
            $clientId === '' ||
            $clientSecret === '' ||
            stripos($clientId, 'YOUR_CLIENT_ID') !== false ||
            $clientSecret === 'YOUR_CLIENT_SECRET'
        );

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'configured' => !$placeholder,
        ];
    }
}

if (!function_exists('rdv_google_oauth_fail')) {
    function rdv_google_oauth_fail($message) {
        error_log('Google OAuth: ' . $message);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['login_error'] = $message;
        header('Location: login.php');
        exit;
    }
}
