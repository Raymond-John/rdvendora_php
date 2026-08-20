<?php
$rdvPageTitle = 'Sitemap | RD Vendora';
$rdvPageDescription = 'A human-readable map of RD Vendora’s public pages.';
$rdvPagePath = 'sitemap';
$rdvActiveNav = 'about';
$rdvShowAds = false;
require __DIR__ . '/includes/public_layout_start.php';
$conn = $conn ?? $connect ?? null;
$newsLinks = ['blog' => 'News'];
if ($conn instanceof mysqli) {
    foreach (rdv_blog_list($conn, ['limit' => 40]) as $post) {
        $newsLinks[rdv_blog_url($post['slug'])] = $post['title'];
    }
}
$groups = [
    'Learn' => [
        'index' => 'Home',
        'about' => 'About',
        'features' => 'Features',
        'pricing' => 'Pricing',
        'faq' => 'FAQ',
    ],
    'News' => $newsLinks,
    'Use the platform' => [
        'contact' => 'Contact',
        'register' => 'Create an account',
        'login' => 'Log in',
        'marketplace' => 'Marketplace',
    ],
    'Legal' => [
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms and Conditions',
        'cookies' => 'Cookie Policy',
        'disclaimer' => 'Disclaimer',
        'community-guidelines' => 'Community Guidelines',
        'newsletter-unsubscribe' => 'Unsubscribe from newsletter',
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
          <?php
            $link = (str_starts_with((string) $href, 'http://') || str_starts_with((string) $href, 'https://') || str_contains((string) $href, '/'))
              ? $href
              : (function_exists('rdv_url') ? rdv_url($href) : $href);
          ?>
          <li><a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
