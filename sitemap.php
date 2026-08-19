<?php
$rdvPageTitle = 'Sitemap | RD Vendora';
$rdvPageDescription = 'A human-readable map of RD Vendora’s public pages.';
$rdvPagePath = 'sitemap.php';
$rdvActiveNav = 'about.php';
$rdvShowAds = false;
require __DIR__ . '/includes/public_layout_start.php';
$conn = $conn ?? $connect ?? null;
$newsLinks = ['blog.php' => 'News'];
if ($conn instanceof mysqli) {
    foreach (rdv_blog_list($conn, ['limit' => 40]) as $post) {
        $newsLinks[rdv_blog_url($post['slug'])] = $post['title'];
    }
}
$groups = [
    'Learn' => [
        'index.php' => 'Home',
        'about.php' => 'About',
        'features.php' => 'Features',
        'pricing.php' => 'Pricing',
        'faq.php' => 'FAQ',
    ],
    'News' => $newsLinks,
    'Use the platform' => [
        'contact.php' => 'Contact',
        'register.php' => 'Create an account',
        'login.php' => 'Log in',
        'marketplace.php' => 'Marketplace',
    ],
    'Legal' => [
        'privacy.php' => 'Privacy Policy',
        'terms.php' => 'Terms and Conditions',
        'cookies.php' => 'Cookie Policy',
        'disclaimer.php' => 'Disclaimer',
        'community-guidelines.php' => 'Community Guidelines',
        'newsletter-unsubscribe.php' => 'Unsubscribe from newsletter',
    ],
];
?>
<section class="section">
  <div class="container rdv-legal">
    <h1>Sitemap</h1>
    <p>Public pages on RD Vendora. Account dashboards and the admin area are not listed because they require a login.</p>
    <?php foreach ($groups as $title => $links): ?>
      <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
      <ul>
        <?php foreach ($links as $href => $label): ?>
          <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
