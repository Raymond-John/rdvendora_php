<?php
$rdvPageTitle = 'Privacy Policy | RD Vendora';
$rdvPageDescription = 'How RD Vendora collects, uses, and stores personal information for accounts, stores, orders, contact forms, and the newsletter.';
$rdvPagePath = 'privacy.php';
$rdvActiveNav = 'about.php';
require __DIR__ . '/includes/public_layout_start.php';
$updated = '17 August 2026';
?>
<section class="section">
  <div class="container rdv-legal">
    <nav class="rdv-crumbs" aria-label="Breadcrumb"><a href="./">Home</a> / Privacy Policy</nav>
    <h1>Privacy Policy</h1>
    <p class="rdv-updated">Last updated <?= htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') ?>. This policy describes RD Vendora (rdvendora.com), operated in connection with RD NEXA TECH. It is not legal advice.</p>

    <h2>Who we are</h2>
    <p>RD Vendora is a multi-vendor eCommerce platform. People can create an account, open a store, list products, take orders, and (where enabled) accept payment through providers such as Paystack and Flutterwave. Platform administrators review stores, documents, and some user-submitted content.</p>

    <h2>Information we collect</h2>
    <ul>
      <li><strong>Account data</strong> such as name, email address, password (stored as a hash), and optional profile details you provide.</li>
      <li><strong>Store and commerce data</strong> such as store settings, product listings, orders, customer checkout details that sellers or buyers submit, and payment references returned by payment providers.</li>
      <li><strong>Communications</strong> including contact-form messages, support chats, and newsletter subscription records.</li>
      <li><strong>Technical data</strong> such as IP address (sometimes stored as a hash), browser type, and session cookies needed to keep you signed in. If you accept analytics cookies, Google Analytics 4 may also collect page URLs, approximate location, and device information under Google’s terms.</li>
      <li><strong>User-generated content</strong> such as product text, images you upload, testimonials you submit for approval, and company documents uploaded for review.</li>
    </ul>
    <p>We do not ask for your payment-card PAN to be typed into RD Vendora checkout itself. Card details are handled by the payment provider you choose (for example Paystack or Flutterwave) under that provider’s terms.</p>

    <h2>How we use information</h2>
    <p>We use personal data to operate the service: create accounts, run stores, process orders, send transactional email (password reset, order notices, newsletter confirmation), respond to contact messages, moderate content, prevent abuse, and improve reliability. We use newsletter addresses only after you subscribe and, where confirmation is required, after you confirm.</p>

    <h2>Legal bases (where applicable)</h2>
    <p>Depending on where you live, we may rely on: performing a contract (providing the platform), legitimate interests (security, service improvement), consent (newsletter, optional cookies, some marketing), and legal obligation (tax or law-enforcement requests we are required to meet).</p>

    <h2>Sharing</h2>
    <p>We share data with:</p>
    <ul>
      <li>Infrastructure and email providers needed to host the site and send mail.</li>
      <li>Payment processors when you or a customer complete a payment.</li>
      <li>Google services you choose to use (for example Google sign-in, and later Google AdSense or Analytics if you consent and those products are enabled).</li>
    </ul>
    <p>We do not sell your personal information. Store owners may see customer information related to orders placed with their store.</p>

    <h2>Cookies</h2>
    <p>Necessary cookies keep sessions working. Optional analytics and advertising cookies are described in the <a href="cookies">Cookie Policy</a> and are not set for those purposes until you make a choice in the cookie banner.</p>

    <h2>Retention</h2>
    <p>We keep account and store records while the account is active and for a reasonable period afterward for security, dispute, and legal reasons. Newsletter records are kept so we can honour unsubscribe requests. Contact messages are kept for support history until an administrator deletes them.</p>

    <h2>Your choices</h2>
    <p>You can update profile information in your account settings, unsubscribe from the newsletter at <a href="newsletter-unsubscribe">newsletter-unsubscribe.php</a>, and request access or deletion by contacting us via the <a href="contact">contact form</a>. We may need to verify that the request comes from the account holder. Some records may be retained where we have a legal reason to keep them.</p>

    <h2>Children</h2>
    <p>RD Vendora is intended for people who can form a contract to run or buy from an online store. It is not directed at children under 13 (or the equivalent age in your country). If you believe a child has submitted personal data, contact us so we can delete it.</p>

    <h2>Security</h2>
    <p>We use hashed passwords, HTTPS in production, prepared database statements for many operations, and access controls on the admin area. No website can guarantee absolute security. Please use a unique password and keep your login private.</p>

    <h2>International visitors</h2>
    <p>The service may be hosted in a different country from yours. If you use RD Vendora, your information may be processed in the country where the servers are located.</p>

    <h2>Changes</h2>
    <p>We may update this policy when the product or the law changes. The “last updated” date will change. Continued use after an update means you have had a chance to read the new version.</p>

    <h2>Contact</h2>
    <p>Privacy questions can be sent through the <a href="contact">Contact</a> page. Do not send passwords or full payment-card numbers by email.</p>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
