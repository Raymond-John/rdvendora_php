<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/public_site.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$content = [];
try {
    $result = $conn->query("SELECT section_key, content FROM about_content");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $content[$row['section_key']] = $row['content'];
        }
    }
} catch (Throwable $e) {
    error_log('about.php about_content: ' . $e->getMessage());
}

$team_members = [];
try {
    $teamResult = $conn->query("SELECT * FROM team_members WHERE status = 'active' ORDER BY display_order ASC");
    if ($teamResult) {
        $team_members = $teamResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('about.php team_members: ' . $e->getMessage());
}

$hero_title = $content['hero_title'] ?? 'Helping people sell <span class="gradient-text">online</span>';
$hero_subtitle = $content['hero_subtitle'] ?? 'RD Vendora is a multi-vendor eCommerce platform. We build software so independent sellers and small businesses can create a store, list products, take orders, and get paid through supported payment providers.';
$story_title = $content['story_title'] ?? 'Why RD Vendora exists';
$story_text = $content['story_text'] ?? "Starting an online shop usually means stitching together hosting, a catalogue, checkout, and a way to talk to customers. RD Vendora puts those pieces in one product: a store dashboard, public storefronts, a marketplace, and admin tools for the people who run the platform.\n\nThe goal is practical, not theatrical. We want a seller who has never launched a site before to register, create a store, and take a real order. We want buyers to see honest listings and a checkout that uses established processors (Paystack and Flutterwave) instead of a homemade card form.\n\nWe do not publish invented user counts, revenue figures, or awards. If you see numbers on this page, they were entered by the site administrator in the about-page editor.";
$stat1_number = $content['stat1_number'] ?? '';
$stat1_label = $content['stat1_label'] ?? '';
$stat2_number = $content['stat2_number'] ?? '';
$stat2_label = $content['stat2_label'] ?? '';
$stat3_number = $content['stat3_number'] ?? '';
$stat3_label = $content['stat3_label'] ?? '';
$stat4_number = $content['stat4_number'] ?? '';
$stat4_label = $content['stat4_label'] ?? '';
$fakeNumbers = ['15K+', '$2.5M', '50+', '99.9%'];
if (in_array($stat1_number, $fakeNumbers, true)) { $stat1_number = ''; }
if (in_array($stat2_number, $fakeNumbers, true)) { $stat2_number = ''; }
if (in_array($stat3_number, $fakeNumbers, true)) { $stat3_number = ''; }
if (in_array($stat4_number, $fakeNumbers, true)) { $stat4_number = ''; }
$showAboutStats = ($stat1_number !== '' || $stat2_number !== '' || $stat3_number !== '' || $stat4_number !== '');

$rdvPageTitle = 'About RD Vendora';
$rdvPageDescription = 'RD Vendora helps independent sellers run an online store: catalogue, orders, marketplace, and checkout with Paystack or Flutterwave.';
$rdvPagePath = 'about.php';
$rdvActiveNav = 'about.php';
$rdvBodyClass = 'mk-marketing about-page';
$rdvHeaderAds = false;
require __DIR__ . '/includes/public_layout_start.php';
?>

<section class="mk-hero mk-hero--compact mk-page-hero">
  <div class="container">
    <div class="mk-kicker">About</div>
    <h1><?= $hero_title ?></h1>
    <p class="lead"><?= nl2br(htmlspecialchars($hero_subtitle)) ?></p>
  </div>
</section>

<section class="mk-section">
  <div class="container">
    <div class="mk-split">
      <div class="mk-prose reveal">
        <h2><?= htmlspecialchars($story_title) ?></h2>
        <?php foreach (explode("\n\n", $story_text) as $para): ?>
          <p><?= nl2br(htmlspecialchars($para)) ?></p>
        <?php endforeach; ?>
      </div>
      <?php if ($showAboutStats): ?>
      <div class="mk-stat-grid reveal">
        <?php if ($stat1_number !== ''): ?><div class="mk-stat"><b><?= htmlspecialchars($stat1_number) ?></b><span><?= htmlspecialchars($stat1_label) ?></span></div><?php endif; ?>
        <?php if ($stat2_number !== ''): ?><div class="mk-stat"><b><?= htmlspecialchars($stat2_number) ?></b><span><?= htmlspecialchars($stat2_label) ?></span></div><?php endif; ?>
        <?php if ($stat3_number !== ''): ?><div class="mk-stat"><b><?= htmlspecialchars($stat3_number) ?></b><span><?= htmlspecialchars($stat3_label) ?></span></div><?php endif; ?>
        <?php if ($stat4_number !== ''): ?><div class="mk-stat"><b><?= htmlspecialchars($stat4_number) ?></b><span><?= htmlspecialchars($stat4_label) ?></span></div><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="mk-card mk-aside-card reveal">
        <h3>Built for real shops</h3>
        <p>We focus on a working storefront, honest listings, and checkout through established payment providers — not inflated metrics.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="mk-section alt">
  <div class="container">
    <div class="mk-section-head">
      <div class="section-label">How we work</div>
      <h2>Mission, vision, and who we serve</h2>
    </div>
    <div class="mk-values-grid">
      <article class="mk-card mk-value-card reveal">
        <div class="mk-value-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h3>Mission</h3>
        <p>Make it realistic for a small business to run an online store: catalogue, orders, and supported checkout in one place.</p>
      </article>
      <article class="mk-card mk-value-card reveal">
        <div class="mk-value-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h3>Vision</h3>
        <p>A marketplace where independent sellers can be found, paid, and managed without pretending the platform is bigger than it is.</p>
      </article>
      <article class="mk-card mk-value-card reveal">
        <div class="mk-value-icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <h3>Who we serve</h3>
        <p>Sellers who need a storefront, buyers who want to purchase from those stores, and the operators who moderate the platform. Start from <a href="register.php">create an account</a> or <a href="contact.php">contact us</a>.</p>
      </article>
    </div>
  </div>
</section>

<?php if (!empty($team_members)): ?>
<section class="mk-section">
  <div class="container">
    <div class="mk-section-head">
      <div class="section-label">Team</div>
      <h2>People behind RD Vendora</h2>
      <p>The people listed here are published from the admin team editor.</p>
    </div>
    <div class="mk-team-grid">
      <?php foreach ($team_members as $member):
        $bgColor = 'var(--primary-light)'; $textColor = 'var(--primary)';
        switch ($member['avatar_color'] ?? '') {
            case 'success': $bgColor = 'var(--success-light)'; $textColor = 'var(--success-dark)'; break;
            case 'warning': $bgColor = 'var(--warning-light)'; $textColor = 'var(--warning-dark)'; break;
            case 'error':   $bgColor = 'var(--error-light)';   $textColor = 'var(--error-dark)';   break;
        }
        $initials = $member['initials'] ?: strtoupper(substr($member['name'], 0, 2));
      ?>
      <article class="mk-card mk-team-card reveal">
        <?php if (!empty($member['avatar'])): ?>
        <img src="<?= htmlspecialchars($member['avatar']) ?>" alt="<?= htmlspecialchars($member['name']) ?>" width="84" height="84">
        <?php else: ?>
        <div class="mk-avatar" style="background:<?= $bgColor ?>;color:<?= $textColor ?>;"><?= htmlspecialchars($initials) ?></div>
        <?php endif; ?>
        <h3><?= htmlspecialchars($member['name']) ?></h3>
        <p class="mk-team-role"><?= htmlspecialchars($member['role']) ?></p>
        <p class="mk-team-bio"><?= nl2br(htmlspecialchars($member['bio'])) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="container">
  <div class="mk-cta-band">
    <h2>Work with us</h2>
    <p>Questions about the product, partnership, or your store? Send a message.</p>
    <a href="contact.php" class="btn btn-white btn-lg">Contact RD Vendora</a>
  </div>
</div>

<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
