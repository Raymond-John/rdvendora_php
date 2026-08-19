<?php
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function rdv_newsletter_json($payload, $code = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/includes/connection.php';
    require_once __DIR__ . '/includes/public_site.php';
} catch (Throwable $e) {
    error_log('Newsletter bootstrap failed: ' . $e->getMessage());
    rdv_newsletter_json(['success' => false, 'message' => 'The newsletter is temporarily unavailable.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rdv_newsletter_json(['success' => false, 'message' => 'Please submit the newsletter form.'], 405);
}

if (!rdv_rate_limit('newsletter', 8, 600)) {
    rdv_newsletter_json(['success' => false, 'message' => 'Too many attempts. Please wait a few minutes.'], 429);
}

if (!empty($_POST['website'])) {
    rdv_newsletter_json(['success' => true, 'message' => 'Check your inbox and click the confirmation link to finish subscribing.']);
}

if (!rdv_csrf_verify()) {
    rdv_newsletter_json(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 400);
}

$conn = $conn ?? $connect ?? null;
if (!$conn) {
    rdv_newsletter_json(['success' => false, 'message' => 'The newsletter is temporarily unavailable.'], 500);
}

$email = trim((string) ($_POST['email'] ?? ''));
$consent = isset($_POST['consent']) && $_POST['consent'] !== '' && $_POST['consent'] !== '0';

try {
    $result = rdv_newsletter_subscribe($conn, $email, '', $consent);
    rdv_newsletter_json(['success' => !empty($result['ok']), 'message' => $result['message'] ?? 'Done.']);
} catch (Throwable $e) {
    error_log('Newsletter subscribe failed: ' . $e->getMessage());
    rdv_newsletter_json(['success' => false, 'message' => 'Could not complete the subscription. Please try again.'], 500);
}
