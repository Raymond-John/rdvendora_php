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

if (!$post) {
    http_response_code(404);
    $rdvPageTitle = 'Story not found | RD Vendora News';
    $rdvPageDescription = 'That news story is not available.';
    $rdvPagePath = 'blog.php';
    $rdvActiveNav = 'blog.php';
    require __DIR__ . '/includes/public_layout_start.php';
    echo '<section class="rdv-news-article"><nav class="rdv-crumbs" aria-label="Breadcrumb"><a href="./">Home</a> / <a href="blog">News</a></nav>';
    echo '<h1>Story not found</h1><p>This article is unpublished, was removed, or the link is incomplete. <a href="blog">Back to News</a>.</p></section>';
    require __DIR__ . '/includes/public_layout_end.php';
    exit;
}

$related = rdv_blog_related($conn, $post, 3);
$shareUrl = rdv_canonical_url(rdv_blog_url($post['slug']));
$rdvPageTitle = $post['title'] . ' | RD Vendora News';
$rdvPageDescription = rdv_blog_excerpt($post, 36);
$rdvPagePath = rdv_blog_url($post['slug']);
$rdvActiveNav = 'blog.php';
$rdvOgType = 'article';
$rdvExtraHead = rdv_blog_article_schema($post)
    . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">';
if (!empty($post['image_url'])) {
    $img = $post['image_url'];
    if (strpos($img, 'http') !== 0) {
        $img = rtrim(APP_URL, '/') . '/' . ltrim($img, '/');
    }
    $rdvExtraHead .= "\n  <meta property=\"og:image\" content=\"" . rdv_blog_h($img) . "\">";
}

require __DIR__ . '/includes/public_layout_start.php';
?>
<article class="rdv-news-article">
  <nav class="rdv-crumbs" aria-label="Breadcrumb">
    <a href="./">Home</a> / <a href="blog">News</a> / <?= rdv_blog_h(rdv_blog_category_label($post['category'])) ?>
  </nav>
  <a class="rdv-news-cat" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $post['category']])) ?>"><?= rdv_blog_h(rdv_blog_category_label($post['category'])) ?></a>
  <h1><?= rdv_blog_h($post['title']) ?></h1>
  <p class="rdv-news-standfirst"><?= rdv_blog_h(rdv_blog_excerpt($post, 48)) ?></p>
  <p class="rdv-news-meta">
    By <?= rdv_blog_h($post['author']) ?>
    · <?= rdv_blog_h(rdv_blog_format_date($post['published_at'], true)) ?>
    · <?= (int) rdv_blog_reading_minutes($post) ?> min read
    <?php if ($post['status'] !== 'published'): ?> · Draft preview<?php endif; ?>
  </p>
  <div class="rdv-news-share">
    <button type="button" id="rdv-copy-link">Copy link</button>
    <a href="https://twitter.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($post['title']) ?>" rel="noopener noreferrer" target="_blank">Share on X</a>
    <a href="https://www.facebook.com/sharer/sharer?u=<?= rawurlencode($shareUrl) ?>" rel="noopener noreferrer" target="_blank">Share on Facebook</a>
  </div>
  <div class="rdv-news-hero">
    <?= rdv_blog_thumb_html($post) ?>
  </div>
  <?= rdv_render_ad_slot('article') ?>
  <div class="rdv-news-body">
    <?= rdv_blog_sanitize_html($post['body']) ?>
  </div>
  <?php if ($related): ?>
    <aside class="rdv-news-related">
      <h2>Related stories</h2>
      <ul>
        <?php foreach ($related as $item): ?>
          <li>
            <a href="<?= rdv_blog_h(rdv_blog_url($item['slug'])) ?>"><?= rdv_blog_h($item['title']) ?></a>
            <span class="rdv-news-meta"> · <?= rdv_blog_h(rdv_blog_category_label($item['category'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </aside>
  <?php endif; ?>
</article>
<script>
  (function () {
    var btn = document.getElementById('rdv-copy-link');
    if (!btn || !navigator.clipboard) return;
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(<?= json_encode($shareUrl) ?>).then(function () {
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = 'Copy link'; }, 1600);
      });
    });
  })();
</script>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
