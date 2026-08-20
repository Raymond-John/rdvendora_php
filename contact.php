<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';
$conn = $conn ?? $connect ?? null;
$contactEmail = rdv_site_contact_email($conn);
$contactPhone = $conn ? rdv_site_setting($conn, 'site_phone') : '';
$contactAddress = $conn ? rdv_site_setting($conn, 'site_address') : '';

$rdvPageTitle = 'Contact RD Vendora';
$rdvPageDescription = 'Send a message to the RD Vendora team about accounts, stores, or the platform.';
$rdvPagePath = 'contact.php';
$rdvActiveNav = 'contact.php';
$rdvBodyClass = 'mk-marketing';
$rdvHeaderAds = false;
require __DIR__ . '/includes/public_layout_start.php';
?>

<section class="mk-hero mk-hero--compact mk-page-hero">
  <div class="container">
    <div class="mk-kicker">Contact</div>
    <h1>Let’s <span class="gradient-text">talk</span></h1>
    <p class="lead">Questions about your store, billing, or the platform? Send a message and the team will follow up.</p>
    </div>
  </section>

<section class="mk-section">
  <div class="container">
    <div class="mk-contact-grid">
      <aside class="mk-contact-card reveal">
        <div class="mk-contact-item">
          <h2>How to reach us</h2>
          <p>Use the form. Messages are stored for the RD Vendora team and, when email is configured, forwarded to the site contact address.</p>
          </div>
        <?php if ($contactEmail !== ''): ?>
        <div class="mk-contact-item">
          <h3>Email</h3>
          <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></a>
          </div>
        <?php endif; ?>
        <?php if ($contactPhone !== ''): ?>
        <div class="mk-contact-item">
          <h3>Phone</h3>
          <p><?= htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php endif; ?>
        <?php if ($contactAddress !== ''): ?>
        <div class="mk-contact-item">
          <h3>Address</h3>
          <p><?= nl2br(htmlspecialchars($contactAddress, ENT_QUOTES, 'UTF-8')) ?></p>
          </div>
        <?php endif; ?>
        <div class="mk-contact-item" style="margin-bottom:0;">
          <h3>Also useful</h3>
          <p><a href="faq">FAQ</a> · <a href="pricing">Pricing</a> · <a href="register">Create a store</a></p>
        </div>
      </aside>
      <div class="mk-form-card reveal">
        <form id="contact-form" method="post" action="submit-contact" novalidate>
          <?= rdv_csrf_field() ?>
          <input type="text" name="website" class="rdv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="form-group">
            <label class="form-label" for="contact_name">Name</label>
            <input type="text" id="contact_name" name="name" class="form-input" placeholder="Your name" required maxlength="120" autocomplete="name">
            </div>
            <div class="form-group">
            <label class="form-label" for="contact_email">Email</label>
            <input type="email" id="contact_email" name="email" class="form-input" placeholder="you@example.com" required maxlength="190" autocomplete="email">
            </div>
            <div class="form-group">
            <label class="form-label" for="contact_subject">Subject</label>
            <select id="contact_subject" name="subject" class="form-input" required>
              <option value="General inquiry">General inquiry</option>
              <option value="Sales">Sales</option>
              <option value="Support">Support</option>
              <option value="Partnership">Partnership</option>
              <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
            <label class="form-label" for="contact_message">Message</label>
            <textarea id="contact_message" name="message" class="form-input" rows="5" placeholder="How can we help?" required minlength="10" maxlength="5000"></textarea>
            </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Send message</button>
          </form>
        </div>
      </div>
    </div>
  </section>

<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
