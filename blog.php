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
$rdvExtraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">';

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
$perPage = 8;
$filters = ['category' => $category, 'q' => $q];

$lead = [];
$secondary = [];
$feed = [];
$total = 0;
$latestAside = [];
$ticker = [];

if ($conn instanceof mysqli) {
    $ticker = rdv_blog_latest($conn, 6);
    $isFiltered = ($category !== '' || $q !== '');
    if (!$isFiltered && $page === 1) {
        $top = rdv_blog_featured($conn, 3);
        $lead = $top[0] ?? null;
        $secondary = array_slice($top, 1, 2);
        $exclude = array_column($top, 'id');
        $latest = rdv_blog_list($conn, ['limit' => 24]);
        foreach ($latest as $row) {
            if (in_array($row['id'], $exclude, true)) {
                continue;
            }
            $feed[] = $row;
            if (count($feed) >= $perPage) {
                break;
            }
        }
        $total = max(0, rdv_blog_count($conn) - count($exclude));
    } else {
        $offset = ($page - 1) * $perPage;
        $total = rdv_blog_count($conn, $filters);
        $feed = rdv_blog_list($conn, $filters + ['limit' => $perPage, 'offset' => $offset]);
        $lead = $feed[0] ?? null;
        if ($lead && $page === 1 && $q === '') {
            $secondary = array_slice($feed, 1, 2);
            $feed = array_slice($feed, 3);
        }
    }
    $latestAside = rdv_blog_latest($conn, 5, (int) (($lead['id'] ?? 0)));
}

$pages = max(1, (int) ceil($total / $perPage));
$todayLabel = date('l j F Y');
?>
<section class="rdv-news">
  <header class="rdv-news-masthead">
    <div>
      <p class="rdv-news-kicker">RD Vendora</p>
      <h1 class="rdv-news-brand">News</h1>
    </div>
    <p class="rdv-news-dateline">
      <strong><?= rdv_blog_h($todayLabel) ?></strong>
      Platform news and practical notes for stores
    </p>
  </header>

  <?php if ($ticker): ?>
    <div class="rdv-news-ticker" aria-label="Latest headlines">
      <?php foreach ($ticker as $item): ?>
        <a href="<?= rdv_blog_h(rdv_blog_url($item['slug'])) ?>">
          <span><?= rdv_blog_h(rdv_blog_category_label($item['category'])) ?></span>
          <?= rdv_blog_h($item['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <nav class="rdv-news-sections" aria-label="News sections">
    <a href="blog" class="<?= $category === '' && $q === '' ? 'is-on' : '' ?>">Latest</a>
    <?php foreach (rdv_blog_categories() as $key => $label): ?>
      <a href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $key])) ?>" class="<?= $category === $key ? 'is-on' : '' ?>"><?= rdv_blog_h($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($q !== ''): ?>
    <p class="rdv-article-meta">Search results for “<?= rdv_blog_h($q) ?>” · <?= (int) $total ?> <?= $total === 1 ? 'story' : 'stories' ?></p>
  <?php elseif ($category !== ''): ?>
    <p class="rdv-article-meta"><?= rdv_blog_h(rdv_blog_category_label($category)) ?> · <?= (int) $total ?> <?= $total === 1 ? 'story' : 'stories' ?></p>
  <?php endif; ?>

  <?php if ($lead && $page === 1): ?>
    <div class="rdv-news-lead-grid">
      <a class="rdv-news-lead" href="<?= rdv_blog_h(rdv_blog_url($lead['slug'])) ?>">
        <?= rdv_blog_thumb_html($lead) ?>
        <span class="rdv-news-cat"><?= rdv_blog_h(rdv_blog_category_label($lead['category'])) ?></span>
        <h2><?= rdv_blog_h($lead['title']) ?></h2>
        <p><?= rdv_blog_h(rdv_blog_excerpt($lead, 36)) ?></p>
        <p class="rdv-news-meta rdv-news-meta--with-views">
          <?= rdv_blog_h(rdv_blog_story_meta($lead, ['views' => false])) ?>
          · <?= rdv_blog_views_markup($lead) ?>
        </p>
      </a>
      <div class="rdv-news-secondary">
        <?php foreach ($secondary as $story): ?>
          <a class="rdv-news-side-story" href="<?= rdv_blog_h(rdv_blog_url($story['slug'])) ?>">
            <?= rdv_blog_thumb_html($story, false) ?>
            <div>
              <span class="rdv-news-cat"><?= rdv_blog_h(rdv_blog_category_label($story['category'])) ?></span>
              <h3><?= rdv_blog_h($story['title']) ?></h3>
              <p class="rdv-news-meta rdv-news-meta--with-views">
                <?= rdv_blog_h(rdv_blog_story_meta($story, ['reading' => false, 'views' => false])) ?>
                · <?= rdv_blog_views_markup($story) ?>
              </p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="rdv-news-layout">
    <div class="rdv-news-feed">
      <h2 class="rdv-news-block-title"><?= $q !== '' ? 'Results' : ($category !== '' ? rdv_blog_h(rdv_blog_category_label($category)) : 'More stories') ?></h2>
      <?= rdv_render_ad_slot('content') ?>
      <?php if (!$feed && !$lead): ?>
        <p class="rdv-news-empty">No stories match this filter yet. Check Latest, or subscribe in the footer for updates.</p>
      <?php endif; ?>
      <?php foreach ($feed as $story): ?>
        <a class="rdv-news-row" href="<?= rdv_blog_h(rdv_blog_url($story['slug'])) ?>">
          <?= rdv_blog_thumb_html($story, false) ?>
          <div>
            <span class="rdv-news-cat"><?= rdv_blog_h(rdv_blog_category_label($story['category'])) ?></span>
            <h3><?= rdv_blog_h($story['title']) ?></h3>
            <p><?= rdv_blog_h(rdv_blog_excerpt($story, 22)) ?></p>
            <p class="rdv-news-meta rdv-news-meta--with-views">
              <?= rdv_blog_h(rdv_blog_story_meta($story, ['views' => false])) ?>
              · <?= rdv_blog_views_markup($story) ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if ($pages > 1): ?>
        <nav class="rdv-news-pager" aria-label="Pagination">
          <?php if ($page > 1): ?>
            <a class="btn btn-outline btn-sm" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $page - 1])) ?>">Previous</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="btn btn-outline btn-sm" href="<?= rdv_blog_h(rdv_blog_index_url(['cat' => $category, 'q' => $q, 'page' => $page + 1])) ?>">Next</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
    <aside class="rdv-news-aside">
      <form class="rdv-news-search" method="get" action="blog" role="search">
        <?php if ($category !== ''): ?><input type="hidden" name="cat" value="<?= rdv_blog_h($category) ?>"><?php endif; ?>
        <label class="rdv-sr-only" for="rdv-news-q">Search news</label>
        <input id="rdv-news-q" type="search" name="q" value="<?= rdv_blog_h($q) ?>" placeholder="Search news" maxlength="80">
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
      </form>
      <h2 class="rdv-news-block-title">Most recent</h2>
      <?php foreach ($latestAside as $item): ?>
        <a class="rdv-mini" href="<?= rdv_blog_h(rdv_blog_url($item['slug'])) ?>">
          <h3><?= rdv_blog_h($item['title']) ?></h3>
          <p class="rdv-news-meta rdv-news-meta--with-views">
            <?= rdv_blog_h(rdv_blog_story_meta($item, ['reading' => false, 'views' => false])) ?>
            · <?= rdv_blog_views_markup($item) ?>
          </p>
        </a>
      <?php endforeach; ?>
      <?= rdv_render_ad_slot('sidebar') ?>
      <div class="rdv-footer-newsletter" style="margin-top:24px">
        <h2 class="rdv-news-block-title">Newsletter</h2>
        <p>Get platform news and store resources by email.</p>
        <?= rdv_newsletter_form('news') ?>
      </div>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/public_layout_end.php'; ?>
