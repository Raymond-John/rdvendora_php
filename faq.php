<?php
$rdvPageTitle = 'FAQ | RD Vendora';
$rdvPageDescription = 'Answers about RD Vendora accounts, stores, payments with Paystack and Flutterwave, and how to get support.';
$rdvPagePath = 'faq.php';
$rdvActiveNav = 'faq.php';
$rdvExtraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        ['@type' => 'Question', 'name' => 'What is RD Vendora?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'RD Vendora is a multi-vendor eCommerce platform. You can create an account, open a store, list products, take orders, and manage day-to-day selling from a dashboard.']],
        ['@type' => 'Question', 'name' => 'How do I get started?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Register on the site, then create a store, add products, and share your storefront. Paid plans, if you choose one, are listed on the pricing page.']],
        ['@type' => 'Question', 'name' => 'Which payments can stores accept?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Checkout on RD Vendora can use Paystack and Flutterwave, which support cards and other local methods those providers offer in supported countries.']],
        ['@type' => 'Question', 'name' => 'How do I contact support?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Use the contact form on the Contact page. Include your account email and, if relevant, an order or store name.']],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
require __DIR__ . '/includes/public_layout_start.php';

$faqs = [
    ['What is RD Vendora?', 'RD Vendora is a multi-vendor eCommerce platform. People can create an account, open a store, list products, receive orders, and use vendor tools such as dashboards, notifications, and (where enabled) transport-related order flows.'],
    ['Who is it for?', 'It is built for independent sellers and small teams who want an online storefront without assembling every piece of software themselves. Buyers use public store and marketplace pages to browse and check out.'],
    ['How do I get started?', 'Create an account from the register page, then create a store and add products. The exact steps appear in your dashboard after you sign in. Subscription options, when you need them, are shown on the pricing page.'],
    ['Which payment methods can a store accept?', 'Where checkout is enabled, RD Vendora integrates Paystack and Flutterwave. Those providers can accept cards and other methods they support in your country. RD Vendora does not currently advertise Stripe, PayPal, Apple Pay, or Square as built-in processors.'],
    ['Does RD Vendora charge a fee on every sale?', 'Payment providers charge their own processing fees. Any RD Vendora subscription or platform fee is the one shown when you subscribe or in your billing screen—not a hidden extra invented here. If a screen does not list a platform commission, do not assume there is none; check your plan details in the dashboard.'],
    ['Can more than one vendor sell on the same marketplace?', 'Yes. The platform is designed so multiple stores can exist, and public marketplace pages can list products from those stores when that feature is turned on.'],
    ['How do I cancel a subscription?', 'If you have a paid plan, use the billing or account area in your dashboard. Access typically continues until the end of the period you already paid for, unless that screen states something different.'],
    ['How do refunds work?', 'Refunds for products you sold are between you and your customer, plus any rules of Paystack or Flutterwave. Refunds for an RD Vendora subscription, if offered, will be stated at purchase or in a written policy—not as an automatic “always 30 days” promise on this FAQ.'],
    ['How do I get help?', 'Use the <a href="contact">contact form</a>. For store-specific issues, include the store name and your account email. You can also read the <a href="blog">News</a> section for setup guidance.'],
    ['Do you show advertisements?', 'Public marketing and article pages may include clearly labelled advertisement areas. Live Google ads appear only if the site owner configures AdSense and a visitor accepts advertising cookies. We do not ask anyone to click ads.'],
];
?>
<section class="section">
  <div class="container" style="max-width:720px">
    <nav class="rdv-crumbs" aria-label="Breadcrumb"><a href="./">Home</a> / FAQ</nav>
    <div class="section-header">
      <div class="section-label">FAQ</div>
      <h1 class="section-title">Frequently asked questions</h1>
      <p class="section-description">Practical answers about the RD Vendora platform as it exists today. If a dashboard screen differs from this page, the screen you are logged into is the source of truth for your account.</p>
    </div>
    <?php if (!empty($rdvShowAds)): ?>
      <?= rdv_render_ad_slot('content') ?>
    <?php endif; ?>
    <div class="rdv-faq-list">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="rdv-faq-item">
          <h2>
            <button type="button" aria-expanded="false" aria-controls="faq-a-<?= (int) $i ?>">
              <?= htmlspecialchars($faq[0], ENT_QUOTES, 'UTF-8') ?>
            </button>
          </h2>
          <div class="rdv-faq-a" id="faq-a-<?= (int) $i ?>"><?= $faq[1] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
