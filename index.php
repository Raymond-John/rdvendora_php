<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/public_site.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$maintenanceMode = rdv_site_setting($conn, 'maintenance_mode');
if ($maintenanceMode === '') {
    $maintenanceMode = '0';
}
if ($maintenanceMode == '1') {
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        header('Location: maintenance.php');
        exit;
    }
}

$table_check = $conn->query("SHOW TABLES LIKE 'testimonials'");
if ($table_check && $table_check->num_rows === 0) {
    $conn->query("CREATE TABLE testimonials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        rating TINYINT(1) DEFAULT 5,
        review TEXT NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status),
        INDEX (user_id)
    )");
}

$activePlans = [];
try {
    $plansQuery = $conn->query("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price ASC");
    if ($plansQuery && $plansQuery->num_rows > 0) {
        $activePlans = $plansQuery->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('index.php subscription_plans: ' . $e->getMessage());
}

$testimonials = [];
try {
    $testimonialQuery = $conn->query("SELECT name, rating, review, created_at FROM testimonials WHERE status = 'approved' ORDER BY created_at DESC LIMIT 6");
    if ($testimonialQuery && $testimonialQuery->num_rows > 0) {
        $testimonials = $testimonialQuery->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('index.php testimonials: ' . $e->getMessage());
}

$rdvPageTitle = 'RD Vendora — Build your online store';
$rdvPageDescription = 'Create a store, list products, take orders, and check out with Paystack or Flutterwave on RD Vendora.';
$rdvPagePath = 'index.php';
$rdvActiveNav = 'index.php';
$rdvBodyClass = 'mk-marketing';
$rdvHeaderAds = false;
$rdvFooterExtra = '<script src="assets/js/marketing.js" defer></script>';
require __DIR__ . '/includes/public_layout_start.php';
?>

<section class="mk-hero">
  <video id="hero-bg-video" class="mk-hero-video" autoplay muted playsinline poster="https://images.pexels.com/photos/3184291/pexels-photo-3184291.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2"></video>
  <div class="mk-hero-overlay"></div>
  <div class="container">
    <div class="mk-hero-grid">
      <div>
        <div class="mk-kicker">Multi-vendor commerce, in one place</div>
        <h1>Launch an online store that can actually take orders</h1>
        <p class="lead">RD Vendora gives independent sellers a storefront, catalogue, orders, and checkout through Paystack or Flutterwave — without stitching five tools together.</p>
        <div class="mk-actions">
          <a href="register.php" class="btn btn-primary btn-lg">Create your store</a>
          <a href="marketplace.php" class="btn btn-outline-white btn-lg">Browse the marketplace</a>
        </div>
        <div class="mk-proof">
          <div class="mk-proof-item"><strong>Store dashboard</strong><span>Products, orders, and customers</span></div>
          <div class="mk-proof-item"><strong>Paystack &amp; Flutterwave</strong><span>Checkout with supported methods</span></div>
          <div class="mk-proof-item"><strong>Marketplace</strong><span>Be found by more buyers</span></div>
        </div>
      </div>
      <div class="mk-preview reveal">
        <div class="mk-preview-bar">
          <span class="mk-preview-dot"></span><span class="mk-preview-dot"></span><span class="mk-preview-dot"></span>
          <span>Seller dashboard</span>
        </div>
        <div class="mk-preview-body">
          <div class="mk-preview-stats">
            <div class="mk-preview-stat"><small>Today</small><b>Orders</b></div>
            <div class="mk-preview-stat"><small>Catalogue</small><b>Products</b></div>
            <div class="mk-preview-stat"><small>Checkout</small><b>Live</b></div>
          </div>
          <div class="mk-preview-row"><span>New order</span><strong>Paid</strong></div>
          <div class="mk-preview-row"><span>Inventory</span><strong>In stock</strong></div>
          <div class="mk-preview-row"><span>Payouts</span><strong>Via provider</strong></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mk-section alt">
  <div class="container">
    <div class="mk-section-head reveal">
      <div class="section-label">Platform</div>
      <h2>What you get from day one</h2>
      <p>The tools a small shop actually needs: a public store, product listings, order handling, and a supported checkout.</p>
    </div>
    <div class="feature-grid">
      <article class="feature-card reveal">
        <div class="feature-icon purple"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></div>
        <h3 class="feature-title">Storefronts</h3>
        <p class="feature-description">A public shop page for your brand, products, and checkout — ready to share with customers.</p>
      </article>
      <article class="feature-card reveal">
        <div class="feature-icon green"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
        <h3 class="feature-title">Catalogue</h3>
        <p class="feature-description">Add products, prices, and stock so buyers see what you actually sell.</p>
      </article>
      <article class="feature-card reveal">
        <div class="feature-icon amber"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <h3 class="feature-title">Payments</h3>
        <p class="feature-description">Checkout can use Paystack and Flutterwave for cards and other methods those providers support.</p>
      </article>
      <article class="feature-card reveal">
        <div class="feature-icon red"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg></div>
        <h3 class="feature-title">Orders &amp; analytics</h3>
        <p class="feature-description">See incoming orders and basic sales activity from your seller dashboard.</p>
      </article>
      <article class="feature-card reveal">
        <div class="feature-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <h3 class="feature-title">Customer contact</h3>
        <p class="feature-description">Keep conversations with buyers in one place instead of chasing them across apps.</p>
      </article>
      <article class="feature-card reveal">
        <div class="feature-icon purple"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
        <h3 class="feature-title">Marketplace</h3>
        <p class="feature-description">Sellers can appear in the RD Vendora marketplace so buyers can discover more than one shop.</p>
      </article>
    </div>
  </div>
</section>

<section class="mk-section">
  <div class="container">
    <div class="mk-section-head reveal">
      <div class="section-label">How it works</div>
      <h2>Three steps to a live shop</h2>
      <p>Register, set up your store, and start taking orders. No custom code required.</p>
    </div>
    <div class="mk-steps">
      <div class="mk-step reveal"><div class="mk-step-num">1</div><h3 class="feature-title">Create an account</h3><p class="feature-description">Sign up and open your seller dashboard.</p></div>
      <div class="mk-step reveal"><div class="mk-step-num">2</div><h3 class="feature-title">Add products</h3><p class="feature-description">Upload listings, set prices, and keep stock current.</p></div>
      <div class="mk-step reveal"><div class="mk-step-num">3</div><h3 class="feature-title">Share and sell</h3><p class="feature-description">Send your store link or list in the marketplace.</p></div>
    </div>
  </div>
</section>

<section class="mk-market">
  <video autoplay muted loop playsinline poster="https://images.pexels.com/photos/3184291/pexels-photo-3184291.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2">
    <source src="https://videos.pexels.com/video-files/3129670/3129670-uhd_2732_1440_25fps.mp4" type="video/mp4">
    <source src="pinterest_video_1780670597 (1).mp4" type="video/mp4">
  </video>
  <div class="container">
    <h2>Shop the marketplace</h2>
    <p>See live stores and products from sellers already on RD Vendora.</p>
    <a href="marketplace.php" class="btn btn-primary btn-lg">Visit marketplace</a>
  </div>
</section>

<section class="mk-section alt" id="pricing">
  <div class="container">
    <div class="mk-section-head reveal">
      <div class="section-label">Pricing</div>
      <h2>Plans that match how you sell</h2>
      <p>Start on a free trial where available, then pick the plan that fits. Full details on the pricing page.</p>
    </div>
    <div class="pricing-grid">
      <?php if (empty($activePlans)): ?>
        <div class="pricing-card" style="grid-column:1/-1;text-align:center;padding:2rem;">No active plans are listed right now. <a href="contact.php">Contact us</a> for access.</div>
      <?php else: ?>
        <?php
        $planCount = count($activePlans);
        foreach ($activePlans as $index => $plan):
          $isPopular = ($index === 1 && $planCount > 2);
          $planName = htmlspecialchars($plan['name']);
          $price = floatval($plan['price']);
          $durationLabel = ($plan['duration'] ?? '') === 'monthly' ? '/month' : '/year';
          $features = json_decode($plan['features'] ?? '[]', true);
          if (!is_array($features)) $features = [];
        ?>
        <div class="pricing-card reveal <?= $isPopular ? 'popular' : '' ?>">
          <?php if ($isPopular): ?><div class="pricing-badge">Most chosen</div><?php endif; ?>
          <div class="pricing-name"><?= $planName ?></div>
          <p class="pricing-desc"><?= ($plan['duration'] ?? '') === 'monthly' ? 'Billed monthly' : 'Billed yearly' ?></p>
          <div class="pricing-price">
            <span class="pricing-amount"><?= $price == 0 ? 'Free' : '₦' . number_format($price, 0) ?></span>
            <span class="pricing-period"><?= $price == 0 ? '' : $durationLabel ?></span>
          </div>
          <div class="pricing-features">
            <?php if (empty($features)): ?>
              <div class="pricing-feature included">Standard plan features</div>
            <?php else: foreach ($features as $feature): ?>
              <div class="pricing-feature included"><?= htmlspecialchars((string) $feature) ?></div>
            <?php endforeach; endif; ?>
          </div>
          <a href="register.php" class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?> w-full" style="justify-content:center;">Get started</a>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <p class="text-center" style="margin-top:1.5rem;"><a href="pricing.php">Compare plans in detail →</a></p>
  </div>
</section>

<section class="mk-section" id="testimonials">
  <div class="container">
    <div class="mk-section-head reveal">
      <div class="section-label">Reviews</div>
      <h2>From people using the platform</h2>
      <p>Approved reviews from store owners. We only show what was submitted here.</p>
    </div>
    <?php if (!empty($_SESSION['testimonial_message'])): ?>
      <div class="alert alert-success" style="margin-bottom:1.5rem;"><?= htmlspecialchars($_SESSION['testimonial_message']) ?></div>
      <?php unset($_SESSION['testimonial_message']); ?>
    <?php elseif (!empty($_SESSION['testimonial_error'])): ?>
      <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= htmlspecialchars($_SESSION['testimonial_error']) ?></div>
      <?php unset($_SESSION['testimonial_error']); ?>
    <?php endif; ?>
    <div class="testimonial-grid">
      <?php if (empty($testimonials)): ?>
        <div class="testimonial-card" style="grid-column:1/-1;text-align:center;">No reviews yet. If you run a store, you can leave one.</div>
      <?php else: foreach ($testimonials as $t): ?>
        <article class="testimonial-card reveal">
          <div class="testimonial-stars" style="color:var(--warning);"><?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']) ?></div>
          <p class="testimonial-text">“<?= htmlspecialchars($t['review']) ?>”</p>
          <div class="testimonial-author">
            <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;"><?= strtoupper(substr($t['name'], 0, 2)) ?></div>
            <div>
              <div class="testimonial-name"><?= htmlspecialchars($t['name']) ?></div>
              <div class="testimonial-role"><?= date('F j, Y', strtotime($t['created_at'])) ?></div>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>
    <div style="text-align:center;margin-top:2rem;">
      <button type="button" class="btn btn-primary" id="writeReviewBtn">Write a review</button>
    </div>
  </div>
</section>

<div class="container">
  <div class="mk-cta-band">
    <h2>Ready to open your store?</h2>
    <p>Create an account, add products, and share your shop link. Talk to us if you need a hand getting started.</p>
    <div class="mk-actions" style="justify-content:center;">
      <a href="register.php" class="btn btn-white btn-lg" style="background:#fff;color:#12305f;">Get started</a>
      <a href="contact.php" class="btn btn-outline-white btn-lg">Contact</a>
    </div>
  </div>
</div>

<div id="reviewModal" class="mk-review-modal" role="dialog" aria-modal="true" aria-labelledby="review-title">
  <div class="mk-review-dialog">
    <button type="button" id="closeModalBtn" class="modal-close" style="position:absolute;top:12px;right:16px;font-size:1.5rem;background:none;border:0;cursor:pointer;" aria-label="Close">&times;</button>
    <h3 id="review-title" style="margin-bottom:1rem;">Share your experience</h3>
    <form action="submit-testimonial.php" method="POST">
      <?= rdv_csrf_field() ?>
      <div class="form-group"><label>Your name</label><input class="form-input" type="text" name="name" required maxlength="100"></div>
      <div class="form-group"><label>Email</label><input class="form-input" type="email" name="email" required maxlength="100"></div>
      <div class="form-group">
        <label>Rating</label>
        <div id="ratingStars" style="display:flex;gap:8px;font-size:1.6rem;cursor:pointer;margin:8px 0;">
          <span data-val="1">★</span><span data-val="2">★</span><span data-val="3">★</span><span data-val="4">★</span><span data-val="5">★</span>
        </div>
        <input type="hidden" name="rating" id="ratingValue" value="5">
      </div>
      <div class="form-group"><label>Review</label><textarea class="form-input" name="review" rows="4" required></textarea></div>
      <div class="mk-actions" style="justify-content:flex-end;margin-top:1rem;">
        <button type="button" id="cancelModalBtn" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
