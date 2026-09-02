<?php
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

$conn = $conn ?? $connect ?? null;
$slug = rdv_blog_slugify((string) ($_GET['slug'] ?? ''));
$preview = isset($_GET['preview']) && $_GET['preview'] === '1';
$post = null;
if ($conn instanceof mysqli && $slug !== '') {
    $includeDraft = $preview && !empty($_SESSION['is_admin']);
    $post = rdv_blog_get_by_slug($conn, $slug, $includeDraft);
}

$isPreviewRequest = $preview && !empty($_SESSION['is_admin']);
if ($post && $conn instanceof mysqli && !$isPreviewRequest && ($post['status'] ?? '') === 'published') {
    $post['view_count'] = rdv_blog_record_view($conn, (int) $post['id']);
}

if (!$post) {
    http_response_code(404);
    $rdvPageTitle = 'Story not found | RD Vendora News';
    $rdvPageDescription = 'That news story is not available.';
    $rdvPagePath = 'blog.php';
    $rdvActiveNav = 'blog.php';
    $rdvBodyClass = 'rdv-article-page';
    require __DIR__ . '/includes/public_layout_start.php';
    echo '<article class="rdv-article"><div class="rdv-article-inner">';
    echo '<nav class="rdv-crumbs" aria-label="Breadcrumb"><a href="' . rdv_blog_h(rdv_url('index')) . '">Home</a> / <a href="' . rdv_blog_h(rdv_url('blog')) . '">News</a></nav>';
    echo '<h1>Story not found</h1><p>This article is unpublished, was removed, or the link is incomplete. <a href="' . rdv_blog_h(rdv_url('blog')) . '">Back to News</a>.</p></div></article>';
    require __DIR__ . '/includes/public_layout_end.php';
    exit;
}

$related = rdv_blog_related($conn, $post, 3);
$bodyHtml = rdv_blog_prepare_body($post['body']);
$toc = rdv_blog_toc($bodyHtml);
$hasHeroImage = trim((string) ($post['image_url'] ?? '')) !== '';
$stepCount = preg_match_all('/class="rdv-step-title"/', $bodyHtml);
$shareUrl = rdv_canonical_url(rdv_blog_url($post['slug']));
$rdvPageTitle = $post['title'] . ' | RD Vendora News';
$rdvPageDescription = rdv_blog_excerpt($post, 36);
$rdvPagePath = rdv_blog_url($post['slug']);
$rdvActiveNav = 'blog.php';
$rdvOgType = 'article';
$rdvBodyClass = 'rdv-article-page';
$rdvExtraHead = rdv_blog_article_schema($post)
    . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap">';
if (!empty($post['image_url'])) {
    $img = rdv_blog_media_url($post['image_url']);
    $rdvExtraHead .= "\n  <meta property=\"og:image\" content=\"" . rdv_blog_h($img) . "\">";
}

require __DIR__ . '/includes/public_layout_start.php';
?>
<article class="rdv-article">
  <header class="rdv-article-hero">
    <div class="rdv-article-inner">
      <nav class="rdv-crumbs" aria-label="Breadcrumb">
        <a href="<?= rdv_blog_h(rdv_url('index')) ?>">Home</a>
        <span aria-hidden="true">/</span>
        <a href="<?= rdv_blog_h(rdv_url('blog')) ?>">News</a>
        <span aria-hidden="true">/</span>
        <span><?= rdv_blog_h(rdv_blog_category_label($post['category'])) ?></span>
      </nav>
      <div class="rdv-article-pills">
        <a class="rdv-news-cat" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $post['category']])) ?>"><?= rdv_blog_h(rdv_blog_category_label($post['category'])) ?></a>
        <span class="rdv-article-pill">Complete guide</span>
        <span class="rdv-article-pill"><?= (int) rdv_blog_reading_minutes($post) ?> min read</span>
        <span class="rdv-article-pill"><?= rdv_blog_h(rdv_blog_format_views((int) ($post['view_count'] ?? 0))) ?></span>
        <?php if ($stepCount > 0): ?><span class="rdv-article-pill"><?= (int) $stepCount ?> steps</span><?php endif; ?>
      </div>
      <h1><?= rdv_blog_h($post['title']) ?></h1>
      <p class="rdv-news-standfirst"><?= rdv_blog_h(rdv_blog_excerpt($post, 48)) ?></p>
      <div class="rdv-article-byline">
        <div class="rdv-article-byline-avatar" aria-hidden="true"><?= rdv_blog_h(strtoupper(substr($post['author'] ?: 'R', 0, 1))) ?></div>
        <div>
          <p class="rdv-article-byline-name"><?= rdv_blog_h($post['author']) ?></p>
          <p class="rdv-news-meta">
            <?= rdv_blog_h(rdv_blog_format_date($post['published_at'], true)) ?>
            · <?= (int) rdv_blog_reading_minutes($post) ?> min read
            · <?= rdv_blog_h(rdv_blog_format_views((int) ($post['view_count'] ?? 0))) ?>
            <?php if ($post['status'] !== 'published'): ?> · Draft preview<?php endif; ?>
          </p>
        </div>
      </div>
      <div class="rdv-news-share">
        <button type="button" id="rdv-copy-link">Copy link</button>
        <a href="https://twitter.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($post['title']) ?>" rel="noopener noreferrer" target="_blank">Share on X</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" rel="noopener noreferrer" target="_blank">Share on Facebook</a>
      </div>
    </div>
  </header>

  <div class="rdv-article-inner">
    <?php if ($hasHeroImage): ?>
      <div class="rdv-article-hero-media">
        <?= rdv_blog_thumb_html($post) ?>
      </div>
    <?php endif; ?>

    <div class="rdv-article-layout">
      <div class="rdv-article-main">
        <?= rdv_render_ad_slot('article') ?>
        <div class="rdv-news-body">
          <?= $bodyHtml ?>
        </div>
        <aside class="rdv-article-cta">
          <p class="rdv-article-cta-kicker">Build on RD Vendora</p>
          <h2>Ready to open your store?</h2>
          <p>Create a storefront, list products, and accept payments with Paystack or Flutterwave.</p>
          <a class="btn btn-primary" href="<?= rdv_blog_h(rdv_url('register')) ?>">Get started</a>
          <a class="btn btn-outline" href="<?= rdv_blog_h(rdv_url('blog/open-a-store')) ?>">How to open a store</a>
        </aside>
        <?php if ($related): ?>
          <aside class="rdv-news-related">
            <h2>Related stories</h2>
            <div class="rdv-article-related-grid">
              <?php foreach ($related as $item): ?>
                <a class="rdv-article-related-card" href="<?= rdv_blog_h(rdv_blog_url($item['slug'])) ?>">
                  <?= rdv_blog_thumb_html($item, false) ?>
                  <span class="rdv-news-cat"><?= rdv_blog_h(rdv_blog_category_label($item['category'])) ?></span>
                  <h3><?= rdv_blog_h($item['title']) ?></h3>
                  <p class="rdv-news-meta"><?= rdv_blog_h(rdv_blog_story_meta($item, ['reading' => false])) ?></p>
                </a>
              <?php endforeach; ?>
            </div>
          </aside>
        <?php endif; ?>
      </div>

      <aside class="rdv-article-aside">
        <?php if (count($toc) >= 3): ?>
          <nav class="rdv-article-toc" aria-label="On this page">
            <h2>On this page</h2>
            <ol>
              <?php foreach ($toc as $item): ?>
                <li><a href="#<?= rdv_blog_h($item['id']) ?>"><?= rdv_blog_h($item['text']) ?></a></li>
              <?php endforeach; ?>
            </ol>
          </nav>
        <?php endif; ?>
        <div class="rdv-article-aside-card">
          <h2>Newsletter</h2>
          <p>Platform news and practical notes for stores, by email.</p>
          <?= rdv_newsletter_form('article') ?>
        </div>
      </aside>
    </div>
  </div>
</article>
<script>
  (function () {
    var btn = document.getElementById('rdv-copy-link');
    if (btn && navigator.clipboard) {
      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(<?= json_encode($shareUrl) ?>).then(function () {
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = 'Copy link'; }, 1600);
        });
      });
    }
    var toc = document.querySelector('.rdv-article-toc');
    if (!toc) return;
    toc.addEventListener('click', function (e) {
      var link = e.target.closest('a');
      if (!link) return;
      toc.querySelectorAll('a').forEach(function (a) { a.classList.remove('is-on'); });
      link.classList.add('is-on');
    });
  })();
</script>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
