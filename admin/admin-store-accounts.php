<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/send_approval_email.php';
require_once __DIR__ . '/../app/helpers/store_account_details.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    die('Database connection failed.');
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

if (!adminHasPermission('stores', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage store accounts.</p><a href="admin">Go to Dashboard</a></div>');
}

rdv_ensure_store_account_details_table($conn);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $storeId = (int) ($_POST['store_id'] ?? 0);
    $action = (string) $_POST['action'];

    if ($action === 'approve_store' && $storeId > 0) {
        $stmt = $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?");
        $stmt->bind_param('i', $storeId);
        if ($stmt->execute()) {
            $infoStmt = $conn->prepare('SELECT user_id, store_name FROM stores WHERE id = ?');
            $infoStmt->bind_param('i', $storeId);
            $infoStmt->execute();
            $storeInfo = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();
            if ($storeInfo) {
                sendStoreApprovalEmail((int) $storeInfo['user_id'], $storeInfo['store_name']);
            }
            $reviewStmt = $conn->prepare("UPDATE store_account_details SET status = 'reviewed' WHERE store_id = ?");
            $reviewStmt->bind_param('i', $storeId);
            $reviewStmt->execute();
            $reviewStmt->close();
            $message = 'Store approved successfully. The owner has been notified.';
            $messageType = 'success';
        } else {
            $message = 'Could not approve the store.';
            $messageType = 'error';
        }
        $stmt->close();
    } elseif ($action === 'mark_reviewed' && $storeId > 0) {
        $stmt = $conn->prepare("UPDATE store_account_details SET status = 'reviewed' WHERE store_id = ?");
        $stmt->bind_param('i', $storeId);
        if ($stmt->execute()) {
            $message = 'Account details marked as reviewed.';
            $messageType = 'success';
        } else {
            $message = 'Could not update review status.';
            $messageType = 'error';
        }
        $stmt->close();
    }

    if ($message !== '') {
        $qs = http_build_query([
            'message' => $message,
            'type' => $messageType,
            'filter' => $_GET['filter'] ?? 'pending_store',
            'q' => $_GET['q'] ?? '',
        ]);
        header('Location: admin-store-accounts?' . $qs);
        exit;
    }
}

$message = (string) ($_GET['message'] ?? $message);
$messageType = (string) ($_GET['type'] ?? $messageType);
$filter = (string) ($_GET['filter'] ?? 'pending_store');
$searchQ = trim((string) ($_GET['q'] ?? ''));
$rows = rdv_store_account_details_list_for_admin($conn, ['filter' => $filter, 'q' => $searchQ, 'limit' => 100]);
$total = rdv_store_account_details_count_for_admin($conn, ['filter' => $filter, 'q' => $searchQ]);

$adminPageTitle = 'Store Account Details - Admin';
$adminPageHeading = 'Store Account Details';
$adminPageSubtitle = $total . ' submission' . ($total === 1 ? '' : 's');
$adminSearchPlaceholder = 'Search store, business, or email...';
$adminShowHeader = true;
$adminPageStyles = '
.store-account-filters { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem; }
.store-account-filters a { padding:0.45rem 0.85rem; border-radius:999px; border:1px solid var(--border-primary); text-decoration:none; color:var(--text-secondary); font-size:0.85rem; }
.store-account-filters a.is-on { background:var(--primary-light); color:var(--primary); border-color:transparent; }
.store-account-card { background:var(--bg-primary); border:1px solid var(--border-primary); border-radius:12px; padding:1.25rem; margin-bottom:1rem; }
.store-account-card h3 { margin:0 0 0.35rem; font-size:1.05rem; }
.store-account-meta { color:var(--text-secondary); font-size:0.88rem; margin-bottom:1rem; }
.store-account-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0.75rem 1.25rem; }
.store-account-grid .label { font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--text-muted); margin-bottom:0.15rem; }
.store-account-grid .value { font-size:0.92rem; color:var(--text-primary); word-break:break-word; }
.store-account-actions { display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border-primary); }
.badge-pending { background:var(--warning-light,#fef3c7); color:#92400e; }
.badge-active { background:var(--success-light); color:var(--success); }
.badge-inactive { background:#fee2e2; color:#991b1b; }
@media (max-width: 768px) { .store-account-grid { grid-template-columns:1fr; } }
';
require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="get" class="dash-search" style="max-width:420px;margin-bottom:1rem;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="search" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Search stores or account details">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
    </form>

    <div class="store-account-filters">
        <?php
        $filters = [
            'pending_store' => 'Pending approval',
            'active_store' => 'Approved stores',
            'all' => 'All submissions',
        ];
        foreach ($filters as $key => $label):
            $qs = http_build_query(array_filter(['filter' => $key, 'q' => $searchQ !== '' ? $searchQ : null]));
        ?>
            <a href="admin-store-accounts?<?= htmlspecialchars($qs) ?>" class="<?= $filter === $key ? 'is-on' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state">
            <h3>No account details yet</h3>
            <p>Submissions will appear here after vendors complete the store account details form.</p>
        </div>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <article class="store-account-card">
                <h3><?= htmlspecialchars($row['store_name']) ?></h3>
                <p class="store-account-meta">
                    Owner: <?= htmlspecialchars($row['owner_name'] ?? 'Unknown') ?> · <?= htmlspecialchars($row['owner_email'] ?? '') ?>
                    · Submitted <?= htmlspecialchars(date('M j, Y g:ia', strtotime($row['submitted_at'] ?? 'now'))) ?>
                </p>
                <p class="store-account-meta">
                    Store status:
                    <span class="badge badge-<?= htmlspecialchars($row['store_status'] === 'active' ? 'active' : ($row['store_status'] === 'pending' ? 'pending' : 'inactive')) ?>">
                        <?= htmlspecialchars(ucfirst((string) $row['store_status'])) ?>
                    </span>
                    · Details status:
                    <span class="badge badge-<?= ($row['status'] ?? '') === 'reviewed' ? 'active' : 'pending' ?>">
                        <?= htmlspecialchars(ucfirst((string) ($row['status'] ?? 'pending'))) ?>
                    </span>
                </p>

                <div class="store-account-grid">
                    <div><div class="label">Business name</div><div class="value"><?= htmlspecialchars($row['business_name']) ?></div></div>
                    <div><div class="label">Contact phone</div><div class="value"><?= htmlspecialchars($row['contact_phone']) ?></div></div>
                    <div><div class="label">Contact email</div><div class="value"><?= htmlspecialchars($row['contact_email']) ?></div></div>
                    <div style="grid-column:1/-1;"><div class="label">Business address</div><div class="value"><?= htmlspecialchars($row['business_address']) ?>, <?= htmlspecialchars($row['city']) ?>, <?= htmlspecialchars($row['state_region']) ?>, <?= htmlspecialchars($row['country']) ?></div></div>
                    <div><div class="label">Bank</div><div class="value"><?= htmlspecialchars($row['bank_name']) ?></div></div>
                    <div><div class="label">Account type</div><div class="value"><?= htmlspecialchars(ucfirst((string) $row['account_type'])) ?></div></div>
                    <div><div class="label">Account name</div><div class="value"><?= htmlspecialchars($row['account_name']) ?></div></div>
                    <div><div class="label">Account number</div><div class="value"><?= htmlspecialchars($row['account_number']) ?></div></div>
                    <?php if (!empty($row['notes'])): ?>
                        <div style="grid-column:1/-1;"><div class="label">Notes</div><div class="value"><?= nl2br(htmlspecialchars($row['notes'])) ?></div></div>
                    <?php endif; ?>
                </div>

                <div class="store-account-actions">
                    <?php if (($row['store_status'] ?? '') === 'pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="store_id" value="<?= (int) $row['store_id'] ?>">
                            <input type="hidden" name="action" value="approve_store">
                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Approve this store and notify the owner?')">Approve store</button>
                        </form>
                    <?php endif; ?>
                    <?php if (($row['status'] ?? '') !== 'reviewed'): ?>
                        <form method="POST">
                            <input type="hidden" name="store_id" value="<?= (int) $row['store_id'] ?>">
                            <input type="hidden" name="action" value="mark_reviewed">
                            <button type="submit" class="btn btn-secondary btn-sm">Mark reviewed</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-ghost btn-sm" href="admin-stores?filter=pending&q=<?= urlencode($row['store_name']) ?>">Open in Stores</a>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
