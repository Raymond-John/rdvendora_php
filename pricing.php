<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/public_site.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$activePlans = [];
try {
    $plansQuery = $conn->query("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price ASC");
    if ($plansQuery && $plansQuery->num_rows > 0) {
        $activePlans = $plansQuery->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('pricing.php subscription_plans: ' . $e->getMessage());
}

$rdvPageTitle = 'Pricing — RD Vendora';
$rdvPageDescription = 'Transparent RD Vendora plans. Start with a store and upgrade when you are ready.';
$rdvPagePath = 'pricing.php';
$rdvActiveNav = 'pricing.php';
$rdvBodyClass = 'mk-marketing';
$rdvHeaderAds = false;
$rdvFooterExtra = '<script src="assets/js/marketing.js" defer></script>';
require __DIR__ . '/includes/public_layout_start.php';
?>

<section class="mk-hero mk-hero--compact mk-page-hero">
  <div class="container">
    <div class="mk-kicker">Pricing</div>
    <h1>Simple, transparent <span class="gradient-text">pricing</span></h1>
    <p class="lead">Choose the plan that fits your store. Amounts come from the live plan list in the admin.</p>
    <div class="mk-tabs" style="margin-top:1.25rem;">
      <button type="button" class="tab-btn active" onclick="switchBilling(this, 'monthly')">Monthly</button>
      <button type="button" class="tab-btn" onclick="switchBilling(this, 'annual')">Annual</button>
    </div>
  </div>
</section>

<section class="mk-section">
  <div class="container">
    <div class="pricing-grid">
      <?php if (empty($activePlans)): ?>
        <div class="pricing-card" style="grid-column:1/-1;text-align:center;padding:2rem;">No active subscription plans are listed. <a href="contact">Contact us</a> for access.</div>
      <?php else: ?>
        <?php
        $planCount = count($activePlans);
        foreach ($activePlans as $index => $plan):
          $isPopular = ($index === 1 && $planCount > 2);
          $planName = htmlspecialchars($plan['name']);
          $planDesc = !empty($plan['description']) ? htmlspecialchars($plan['description']) : (($plan['duration'] ?? '') === 'monthly' ? 'Billed monthly' : 'Billed yearly');
          $basePrice = floatval($plan['price']);
          if (($plan['duration'] ?? '') === 'monthly') {
            $monthlyPrice = $basePrice;
            $annualPrice = round($basePrice * 12 * 0.8, 2);
          } else {
            $monthlyPrice = $basePrice > 0 ? round($basePrice / 12, 2) : 0;
            $annualPrice = $basePrice;
          }
          $features = json_decode($plan['features'] ?? '[]', true);
          if (!is_array($features)) $features = [];
          $planNameLower = strtolower(trim($plan['name']));
        ?>
        <div class="pricing-card reveal <?= $isPopular ? 'popular' : '' ?>">
          <?php if ($isPopular): ?><div class="pricing-badge">Most chosen</div><?php endif; ?>
          <div class="pricing-name"><?= $planName ?></div>
          <p class="pricing-desc"><?= $planDesc ?></p>
          <div class="pricing-price">
            <span class="monthly-price">
              <span class="pricing-amount"><?= $monthlyPrice == 0 ? 'Free' : '₦' . number_format($monthlyPrice, 0) ?></span>
              <span class="pricing-period"><?= $monthlyPrice == 0 ? '' : '/month' ?></span>
            </span>
            <span class="annual-price hidden">
              <span class="pricing-amount"><?= $annualPrice == 0 ? 'Free' : '₦' . number_format($annualPrice, 0) ?></span>
              <span class="pricing-period"><?= $annualPrice == 0 ? '' : '/year' ?></span>
            </span>
          </div>
          <div class="pricing-features">
            <?php if (empty($features)): ?>
              <div class="pricing-feature included">Standard features included</div>
            <?php else: foreach ($features as $feature): ?>
              <div class="pricing-feature included"><?= htmlspecialchars((string) $feature) ?></div>
            <?php endforeach; endif; ?>
          </div>
          <?php if ($planNameLower === 'empire'): ?>
            <a href="contact" class="btn btn-outline w-full" style="justify-content:center;">Contact sales</a>
          <?php else: ?>
            <a href="register" class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?> w-full" style="justify-content:center;">Get started</a>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<section class="mk-section alt">
  <div class="container">
    <div class="mk-section-head">
      <h2>Questions about billing</h2>
      <p>Short answers. If you need a specific quote, use the contact form.</p>
    </div>
    <div class="mk-faq">
      <div class="rdv-faq-item">
        <h2><button type="button" aria-expanded="false">Can I change plans later?</button></h2>
        <div class="rdv-faq-a"><p>Yes. You can move between plans from billing when you are logged in. Timing and proration follow what you see at checkout.</p></div>
      </div>
      <div class="rdv-faq-item">
        <h2><button type="button" aria-expanded="false">Is there a free option?</button></h2>
        <div class="rdv-faq-a"><p>When a free or trial plan is active in the list above, you can start without paying. Paid plans are billed as shown.</p></div>
      </div>
      <div class="rdv-faq-item">
        <h2><button type="button" aria-expanded="false">How do customers pay in my store?</button></h2>
        <div class="rdv-faq-a"><p>Store checkout can use Paystack and Flutterwave. Those providers support cards and other local methods in countries they serve.</p></div>
      </div>
      <div class="rdv-faq-item">
        <h2><button type="button" aria-expanded="false">Need help picking a plan?</button></h2>
        <div class="rdv-faq-a"><p>Write to us from the <a href="contact">contact page</a> with how many products you sell and we will point you to the right tier.</p></div>
      </div>
    </div>
  </div>
</section>

<div class="container">
  <div class="mk-cta-band">
    <h2>Start with a store, not a sales call</h2>
    <p>Register, add products, and upgrade when you need more capacity.</p>
    <a href="register" class="btn btn-white btn-lg" style="background:#fff;color:#12305f;">Create your store</a>
  </div>
</div>

<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
