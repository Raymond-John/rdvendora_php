<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

$rdvPageTitle = 'Confirm newsletter subscription | RD Vendora';
$rdvPageDescription = 'Confirm your RD Vendora newsletter subscription.';
$rdvPagePath = 'newsletter-confirm.php';
$rdvActiveNav = 'blog.php';
$rdvShowAds = false;
$message = 'This confirmation link is missing or incomplete.';
$ok = false;

$token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_GET['token'] ?? '')));
$conn = $conn ?? $connect ?? null;
if ($conn && strlen($token) === 64) {
    rdv_ensure_newsletter_table($conn);
    $stmt = $conn->prepare('SELECT id, status FROM newsletter_subscribers WHERE verification_token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        if ($row['status'] === 'verified') {
            $ok = true;
            $message = 'This email is already confirmed. Thank you for subscribing.';
        } elseif ($row['status'] === 'unsubscribed') {
            $message = 'This email was unsubscribed. Submit the newsletter form again if you want to re-subscribe.';
        } else {
            $id = (int) $row['id'];
            $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'verified', verified_at = NOW(), subscribed_at = COALESCE(subscribed_at, NOW()) WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $ok = true;
            $message = 'Your subscription is confirmed. You will receive RD Vendora newsletter emails when we send them.';
        }
    } else {
        $message = 'This confirmation link is invalid or has already been used.';
    }
}

require __DIR__ . '/includes/public_layout_start.php';
?>
<section class="section">
  <div class="container rdv-legal">
    <nav class="rdv-crumbs" aria-label="Breadcrumb"><a href="./">Home</a> / Newsletter</nav>
    <h1><?= $ok ? 'Subscription confirmed' : 'We could not confirm this subscription' ?></h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="./" class="btn btn-primary">Back to home</a></p>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
