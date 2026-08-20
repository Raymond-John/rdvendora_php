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
if (!adminHasPermission('newsletter', $conn) && !adminHasPermission('contacts', $conn)) {
    die('<div style="text-align:center;padding:3rem"><h1>Access Denied</h1><p>You do not have permission to manage the newsletter.</p><a href="admin">Dashboard</a></div>');
}

rdv_ensure_newsletter_table($conn);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['unsubscribe', 'delete'], true)) {
        if ($action === 'unsubscribe') {
            $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Subscriber marked unsubscribed.';
        } else {
            $stmt = $conn->prepare('DELETE FROM newsletter_subscribers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Subscriber removed.';
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="newsletter-subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'first_name', 'status', 'consent', 'subscribed_at', 'verified_at', 'unsubscribed_at']);
    $res = $conn->query('SELECT email, first_name, status, consent, subscribed_at, verified_at, unsubscribed_at FROM newsletter_subscribers ORDER BY id DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}

$status = $_GET['status'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT * FROM newsletter_subscribers WHERE 1=1';
$types = '';
$params = [];
if (in_array($status, ['pending', 'verified', 'unsubscribed'], true)) {
    $sql .= ' AND status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($q !== '') {
    $sql .= ' AND (email LIKE ? OR first_name LIKE ?)';
    $types .= 'ss';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY created_at DESC LIMIT 300';
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$adminPageTitle = 'Newsletter subscribers | Admin';
$adminPageHeading = 'Newsletter subscribers';
$adminPageSubtitle = 'Only administrators can see this list. Export does not include verification tokens.';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content"><?php if ($message): ?><p class="ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form class="toolbar" method="get">
      <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search email or name">
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['pending','verified','unsubscribed'] as $st): ?>
          <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Filter</button>
      <a class="btn ghost" href="admin-newsletter?export=csv">Export CSV</a>
    </form>
    <table>
      <thead>
        <tr><th>Email</th><th>Name</th><th>Status</th><th>Consent</th><th>Subscribed</th><th>Verified</th><th>Unsubscribed</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8">No subscribers match this filter.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="badge <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= ((int) $row['consent'] === 1) ? 'Yes' : 'No' ?></td>
            <td><?= htmlspecialchars((string) $row['subscribed_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($row['verified_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($row['unsubscribed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if ($row['status'] !== 'unsubscribed'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Unsubscribe this address?')">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="action" value="unsubscribe">
                  <button type="submit">Unsubscribe</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Permanently delete this row?')">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
