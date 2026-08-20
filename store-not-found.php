<?php
if (!defined('RDV_BOOTSTRAPPED')) {
    require_once __DIR__ . '/includes/connection.php';
}
$home = rtrim((string) (defined('APP_URL') ? APP_URL : 'https://rdvendora.com'), '/');
$message = isset($rdvStoreNotFoundMessage) && is_string($rdvStoreNotFoundMessage) && $rdvStoreNotFoundMessage !== ''
    ? $rdvStoreNotFoundMessage
    : 'This store could not be found or may no longer be available.';
if (!headers_sent()) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Store Not Found | RD Vendora</title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/brand-logo.png') : 'assets/brand-logo.png', ENT_QUOTES, 'UTF-8') ?>" type="image/png">
  <link rel="stylesheet" href="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/css/style.css') : 'assets/css/style.css', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(function_exists('rdv_asset') ? rdv_asset('assets/css/public-extras.css') : 'assets/css/public-extras.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
  <div class="error-page">
    <div>
      <div class="error-code">404</div>
      <h1 class="error-title">Store Not Found</h1>
      <p class="error-text"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <p>
        <a class="btn btn-primary" href="<?= htmlspecialchars($home . '/', ENT_QUOTES, 'UTF-8') ?>">Go to RD Vendora</a>
        <a class="btn btn-outline" href="<?= htmlspecialchars($home . '/marketplace.php', ENT_QUOTES, 'UTF-8') ?>">Browse marketplace</a>
      </p>
    </div>
  </div>
</body>
</html>
