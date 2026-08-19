<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

$rdvPageTitle = 'Unsubscribe from the RD Vendora newsletter';
$rdvPageDescription = 'Stop receiving RD Vendora newsletter emails.';
$rdvPagePath = 'newsletter-unsubscribe.php';
$rdvActiveNav = 'contact.php';
$rdvShowAds = false;

$conn = $conn ?? $connect ?? null;
$done = false;
$error = '';
$emailPrefill = strtolower(trim((string) ($_GET['email'] ?? $_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rdv_csrf_verify()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!rdv_rate_limit('newsletter_unsub', 8, 600)) {
        $error = 'Too many attempts. Please wait a few minutes.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter the email address you used to subscribe.';
        } elseif ($conn) {
            rdv_ensure_newsletter_table($conn);
            $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE email = ? AND status <> 'unsubscribed'");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();
            $done = true;
        } else {
            $error = 'Unsubscribe is temporarily unavailable.';
        }
    }
}

require __DIR__ . '/includes/public_layout_start.php';
?>
<section class="section">
  <div class="container rdv-legal">
    <nav class="rdv-crumbs" aria-label="Breadcrumb"><a href="index.php">Home</a> / Unsubscribe</nav>
    <h1>Unsubscribe</h1>
    <?php if ($done): ?>
      <p>If that address was on our newsletter list, it is now unsubscribed. You will not receive future RD Vendora newsletter emails unless you subscribe again and confirm.</p>
    <?php else: ?>
      <p>Enter the email address you used to subscribe. We will stop sending newsletter messages to it. Transactional messages (such as order or account emails) are separate and are not controlled here.</p>
      <?php if ($error): ?><p class="rdv-newsletter-status is-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post" class="rdv-newsletter-form" style="max-width:420px">
        <?= rdv_csrf_field() ?>
        <label for="unsub-email">Email address</label>
        <input id="unsub-email" type="email" name="email" required value="<?= htmlspecialchars($emailPrefill, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-primary">Unsubscribe</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
