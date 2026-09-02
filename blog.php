<?php
// If /blog/{slug} was mapped onto blog.php (PATH_INFO), show the article.
$pathInfo = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
if ($pathInfo !== '' && strpos($pathInfo, '/') === false) {
    $_GET['slug'] = $pathInfo;
    require __DIR__ . '/blog-post.php';
    exit;
}

require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

$conn = $conn ?? $connect ?? ($GLOBALS['conn'] ?? null);
if ($conn instanceof mysqli) {
    rdv_ensure_blog_table($conn);
    rdv_blog_ensure_view_count_column($conn);
}

$rdvPageTitle = 'News | RD Vendora';
$rdvPageDescription = 'Platform news, store guides, and payment notes from the RD Vendora team.';
$rdvPagePath = 'blog.php';
$rdvActiveNav = 'blog.php';
$rdvOgType = 'website';
$rdvBodyClass = 'rdv-blog-journal-page';
$rdvExtraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Open+Sans:wght@400;600;700&display=swap">';

require __DIR__ . '/includes/public_layout_start.php';

$category = trim((string) ($_GET['cat'] ?? ''));
if ($category !== '' && !isset(rdv_blog_categories()[$category])) {
    $category = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
if (strlen($q) > 80) {
    $q = substr($q, 0, 80);
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$filters = ['category' => $category, 'q' => $q];

$feed = [];
$total = 0;
$popularAside = [];

if ($conn instanceof mysqli) {
    $offset = ($page - 1) * $perPage;
    $total = rdv_blog_count($conn, $filters);
    $feed = rdv_blog_list($conn, $filters + ['limit' => $perPage, 'offset' => $offset]);
    $popularAside = rdv_blog_popular($conn, 5);
}

$pages = max(1, (int) ceil($total / $perPage));
$showFrom = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$showTo = min($page * $perPage, $total);
$homeUrl = function_exists('rdv_url') ? rdv_url('') : './';
?>
<div class="rdv-blog-page-header">
  <div class="rdv-blog-page-header-inner">
    <h1>News &amp; Store Guides</h1>
    <nav aria-label="Breadcrumb">
      <ol class="rdv-blog-breadcrumb">
        <li><a href="<?= rdv_blog_h($homeUrl) ?>">Home</a></li>
        <li aria-current="page">News &amp; Store Guides</li>
      </ol>
    </nav>
  </div>
</div>

<section class="rdv-blog-journal">
  <div class="rdv-blog-journal-inner">
    <div class="rdv-blog-intro">
      <p class="rdv-blog-section-title">RD Vendora Journal</p>
      <h2>Practical advice for stores, payments, and growing online</h2>
    </div>

    <div class="blog-search-wrap">
      <form action="blog" method="get" class="blog-search-form" role="search" autocomplete="off">
        <?php if ($category !== ''): ?><input type="hidden" name="cat" value="<?= rdv_blog_h($category) ?>"><?php endif; ?>
        <label class="rdv-sr-only" for="blogSearchInput">Search articles</label>
        <input id="blogSearchInput" type="search" name="q" value="<?= rdv_blog_h($q) ?>" placeholder="Search store guides, payments, marketplace tips…" maxlength="80">
        <button type="submit" class="btn btn-primary">Search</button>
      </form>
      <?php if ($q !== ''): ?>
        <p class="blog-search-hint"><?= (int) $total ?> <?= $total === 1 ? 'result' : 'results' ?> for “<?= rdv_blog_h($q) ?>”</p>
      <?php elseif ($category !== ''): ?>
        <p class="blog-search-hint"><?= rdv_blog_h(rdv_blog_category_label($category)) ?> · <?= (int) $total ?> <?= $total === 1 ? 'article' : 'articles' ?></p>
      <?php endif; ?>
    </div>

    <div class="rdv-blog-main-grid">
      <div class="rdv-blog-feed-col">
        <?= rdv_render_ad_slot('content') ?>

        <?php if (!$feed): ?>
          <p class="rdv-blog-empty">No articles match this search yet. Try another term or browse the latest stories.</p>
        <?php else: ?>
          <div class="rdv-blog-card-grid">
            <?php foreach ($feed as $story): ?>
              <article class="blog-card">
                <?= rdv_blog_card_media_html($story) ?>
                <div class="blog-card-body">
                  <p class="blog-card-meta"><?= rdv_blog_h(rdv_blog_card_meta($story)) ?></p>
                  <h3 class="blog-card-title">
                    <a href="<?= rdv_blog_h(rdv_blog_url($story['slug'])) ?>"><?= rdv_blog_h($story['title']) ?></a>
                  </h3>
                  <p class="blog-card-excerpt"><?= rdv_blog_h(rdv_blog_excerpt($story, 32)) ?></p>
                  <a class="btn btn-secondary" href="<?= rdv_blog_h(rdv_blog_url($story['slug'])) ?>">Read more</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
          <nav class="blog-index-pager" aria-label="Blog pages">
            <div class="blog-index-pager-mobile">
              <ul class="blog-pagination">
                <li class="<?= $page <= 1 ? 'is-disabled' : '' ?>">
                  <?php if ($page > 1): ?>
                    <a class="blog-page-link" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $page - 1])) ?>">&laquo; Previous</a>
                  <?php else: ?>
                    <span class="blog-page-link">&laquo; Previous</span>
                  <?php endif; ?>
                </li>
                <li class="<?= $page >= $pages ? 'is-disabled' : '' ?>">
                  <?php if ($page < $pages): ?>
                    <a class="blog-page-link" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $page + 1])) ?>" rel="next">Next &raquo;</a>
                  <?php else: ?>
                    <span class="blog-page-link">Next &raquo;</span>
                  <?php endif; ?>
                </li>
              </ul>
            </div>

            <div class="blog-index-pager-desktop">
              <p class="blog-index-pager-summary">
                Showing <strong><?= (int) $showFrom ?></strong> to <strong><?= (int) $showTo ?></strong> of <strong><?= (int) $total ?></strong> results
              </p>
              <ul class="blog-pagination">
                <li class="<?= $page <= 1 ? 'is-disabled' : '' ?>">
                  <?php if ($page > 1): ?>
                    <a class="blog-page-link" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $page - 1])) ?>" aria-label="Previous">&lsaquo;</a>
                  <?php else: ?>
                    <span class="blog-page-link" aria-hidden="true">&lsaquo;</span>
                  <?php endif; ?>
                </li>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                  <li class="<?= $i === $page ? 'is-active' : '' ?>">
                    <?php if ($i === $page): ?>
                      <span class="blog-page-link" aria-current="page"><?= (int) $i ?></span>
                    <?php else: ?>
                      <a class="blog-page-link" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $i])) ?>"><?= (int) $i ?></a>
                    <?php endif; ?>
                  </li>
                <?php endfor; ?>
                <li class="<?= $page >= $pages ? 'is-disabled' : '' ?>">
                  <?php if ($page < $pages): ?>
                    <a class="blog-page-link" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $page + 1])) ?>" rel="next" aria-label="Next">&rsaquo;</a>
                  <?php else: ?>
                    <span class="blog-page-link" aria-hidden="true">&rsaquo;</span>
                  <?php endif; ?>
                </li>
              </ul>
            </div>
          </nav>
        <?php endif; ?>
      </div>

      <aside class="rdv-blog-aside-col">
        <div class="surface-panel sticky-panel">
          <h2 class="blog-side-title">Popular content</h2>
          <?php if ($popularAside): ?>
            <ul class="blog-mini-list">
              <?php foreach ($popularAside as $item): ?>
                <li>
                  <a href="<?= rdv_blog_h(rdv_blog_url($item['slug'])) ?>"><?= rdv_blog_h($item['title']) ?></a>
                  <span><?= rdv_blog_h(rdv_blog_mini_meta($item)) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="blog-search-hint">Popular articles will appear here as readers visit stories.</p>
          <?php endif; ?>
          <?= rdv_render_ad_slot('sidebar') ?>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
