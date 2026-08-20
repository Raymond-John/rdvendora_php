<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/public_site.php';

$conn = $conn ?? $connect ?? null;
if (!$conn) {
    die('Database connection failed.');
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('<div style="text-align:center;padding:3rem"><h1>Access Denied</h1><a href="admin">Dashboard</a></div>');
}
if (!adminHasPermission('blog', $conn) && !adminHasPermission('about', $conn) && !adminHasPermission('newsletter', $conn)) {
    die('<div style="text-align:center;padding:3rem"><h1>Access Denied</h1><p>You do not have permission to manage News.</p><a href="admin">Dashboard</a></div>');
}

rdv_ensure_blog_table($conn);

$message = '';
$error = '';
$edit = null;
$editId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rdv_csrf_verify()) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0 && rdv_blog_delete($conn, $id)) {
                header('Location: admin-blog?ok=1');
                exit;
            }
            $error = 'Could not delete that story.';
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
            $uploadDir = dirname(__DIR__) . '/uploads/blog/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                $ext = strtolower(pathinfo((string) $_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) && ($_FILES['image']['size'] ?? 0) <= 2 * 1024 * 1024) {
                    $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $name)) {
                        $imageUrl = 'uploads/blog/' . $name;
                    } else {
                        $error = 'Image upload failed.';
                    }
                } else {
                    $error = 'Use a JPG, PNG, WEBP, or GIF image under 2 MB.';
                }
            }
            if ($error === '') {
                $result = rdv_blog_save($conn, [
                    'title' => $_POST['title'] ?? '',
                    'slug' => $_POST['slug'] ?? '',
                    'excerpt' => $_POST['excerpt'] ?? '',
                    'body' => $_POST['body'] ?? '',
                    'category' => $_POST['category'] ?? 'platform',
                    'author' => $_POST['author'] ?? '',
                    'image_url' => $imageUrl,
                    'status' => $_POST['status'] ?? 'draft',
                    'is_featured' => !empty($_POST['is_featured']),
                    'published_at' => $_POST['published_at'] ?? '',
                ], $id);
                if ($result['ok']) {
                    header('Location: admin-blog?id=' . (int) $result['id'] . '&ok=1');
                    exit;
                }
                $error = $result['message'];
                $editId = $id;
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $message = 'Saved.';
}

if ($editId > 0) {
    $edit = rdv_blog_get_by_id($conn, $editId);
}

$statusFilter = (string) ($_GET['status'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$rows = rdv_blog_admin_list($conn, ['status' => $statusFilter, 'q' => $q]);
$isNew = isset($_GET['new']);
$form = $edit ?: [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'body' => '',
    'category' => 'platform',
    'author' => 'RD Vendora team',
    'image_url' => '',
    'status' => 'draft',
    'is_featured' => 0,
    'published_at' => date('Y-m-d\TH:i'),
];
if (!empty($form['published_at']) && strpos((string) $form['published_at'], 'T') === false) {
    $form['published_at'] = date('Y-m-d\TH:i', strtotime((string) $form['published_at']));
}

$adminPageTitle = 'News desk | Admin';
$adminPageHeading = 'News desk';
$adminPageSubtitle = 'Publish stories that appear on the public News page.';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content"><?php if ($message): ?><p class="ok"><?= rdv_blog_h($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="err"><?= rdv_blog_h($error) ?></p><?php endif; ?>

    <form class="toolbar" method="get">
      <input type="search" name="q" value="<?= rdv_blog_h($q) ?>" placeholder="Search headline or slug">
      <select name="status">
        <option value="">All statuses</option>
        <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
      </select>
      <button type="submit">Filter</button>
      <a class="btn" href="admin-blog?new=1">New story</a>
    </form>

    <?php if (!$isNew && !$edit): ?>
    <table>
      <thead>
        <tr><th>Headline</th><th>Section</th><th>Status</th><th>Published</th><th>Featured</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6">No stories yet. <a href="admin-blog?new=1">Write the first one</a>.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= rdv_blog_h($row['title']) ?></td>
            <td><?= rdv_blog_h(rdv_blog_category_label($row['category'])) ?></td>
            <td><span class="badge <?= rdv_blog_h($row['status']) ?>"><?= rdv_blog_h($row['status']) ?></span></td>
            <td><?= rdv_blog_h((string) $row['published_at']) ?></td>
            <td><?= ((int) $row['is_featured'] === 1) ? 'Yes' : '' ?></td>
            <td>
              <a class="btn ghost" href="admin-blog?id=<?= (int) $row['id'] ?>">Edit</a>
              <?php if ($row['status'] === 'published'): ?>
                <a class="btn ghost" href="../<?= rdv_blog_h(rdv_blog_url($row['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <?php else: ?>
                <a class="btn ghost" href="../<?= rdv_blog_h(rdv_blog_url($row['slug'])) ?>&preview=1" target="_blank" rel="noopener">Preview</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <?php if ($isNew || $edit): ?>
    <div class="card">
      <h2><?= $edit ? 'Edit story' : 'New story' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= rdv_csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="grid">
          <div class="full">
            <label for="title">Headline</label>
            <input id="title" name="title" required maxlength="220" value="<?= rdv_blog_h($form['title']) ?>">
          </div>
          <div>
            <label for="slug">Slug (optional)</label>
            <input id="slug" name="slug" maxlength="150" value="<?= rdv_blog_h($form['slug']) ?>" placeholder="generated-from-headline">
          </div>
          <div>
            <label for="category">Section</label>
            <select id="category" name="category">
              <?php foreach (rdv_blog_categories() as $key => $label): ?>
                <option value="<?= rdv_blog_h($key) ?>" <?= ($form['category'] === $key) ? 'selected' : '' ?>><?= rdv_blog_h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="author">Byline</label>
            <input id="author" name="author" maxlength="120" value="<?= rdv_blog_h($form['author']) ?>">
          </div>
          <div>
            <label for="published_at">Publish time</label>
            <input id="published_at" type="datetime-local" name="published_at" value="<?= rdv_blog_h($form['published_at']) ?>">
          </div>
          <div>
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="draft" <?= $form['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
              <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
          </div>
          <div>
            <label><input type="checkbox" name="is_featured" value="1" <?= !empty($form['is_featured']) ? 'checked' : '' ?>> Feature on the News front page</label>
          </div>
          <div class="full">
            <label for="excerpt">Standfirst / summary</label>
            <textarea id="excerpt" name="excerpt" style="min-height:80px"><?= rdv_blog_h($form['excerpt']) ?></textarea>
          </div>
          <div class="full">
            <label for="body">Body (HTML: p, h2, ul, li, a, strong, em)</label>
            <textarea id="body" name="body" required><?= rdv_blog_h($form['body']) ?></textarea>
          </div>
          <div>
            <label for="image_url">Image URL or uploaded path</label>
            <input id="image_url" name="image_url" maxlength="500" value="<?= rdv_blog_h($form['image_url']) ?>">
          </div>
          <div>
            <label for="image">Upload image (optional)</label>
            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
          </div>
        </div>
        <p class="toolbar">
          <button type="submit">Save</button>
          <a class="btn ghost" href="admin-blog">Back to list</a>
        </p>
      </form>
      <?php if ($edit): ?>
        <form method="post" onsubmit="return confirm('Delete this story permanently?')">
          <?= rdv_csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
          <button class="danger" type="submit">Delete</button>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
