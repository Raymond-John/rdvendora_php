<?php
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
try {
    require_once __DIR__ . '/includes/connection.php';
    require_once __DIR__ . '/includes/public_site.php';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['csrf_token' => rdv_csrf_token()]);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['csrf_token' => '', 'message' => 'Could not start a secure session.']);
}
