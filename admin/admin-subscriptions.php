<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

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

if (!adminHasPermission('pricing', $conn) && !adminHasPermission('stores', $conn) && !adminHasPermission('dashboard', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view subscriptions.</p><a href="admin">Go to Dashboard</a></div>');
}

// Keep expired rows accurate
$conn->query("UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND end_date IS NOT NULL AND end_date <= NOW()");

// Resolve user name column differences
$userCols = [];
$colResult = $conn->query('SHOW COLUMNS FROM users');
if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        $userCols[$col['Field']] = true;
    }
}
$ownerNameParts = [];
foreach (['fullname', 'full_name', 'name', 'username'] as $candidate) {
    if (!empty($userCols[$candidate])) {
        $ownerNameParts[] = "NULLIF(u.$candidate, '')";
    }
}
$ownerNameExpr = $ownerNameParts
    ? ('COALESCE(' . implode(', ', $ownerNameParts) . ", u.email, 'Unknown')")
    : "COALESCE(u.email, 'Unknown')";

$filter = trim((string) ($_GET['filter'] ?? 'all'));
$searchQ = trim((string) ($_GET['q'] ?? ''));
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['1=1'];
$types = '';
$params = [];

if ($filter === 'active') {
    $where[] = "sub.status = 'active' AND (sub.end_date IS NULL OR sub.end_date > NOW())";
} elseif ($filter === 'trial') {
    $where[] = "sub.status = 'active' AND sub.amount = 0 AND (sub.end_date IS NULL OR sub.end_date > NOW())";
} elseif ($filter === 'expired') {
    $where[] = "sub.status = 'expired'";
} elseif ($filter === 'cancelled') {
    $where[] = "sub.status = 'cancelled'";
} elseif ($filter === 'pending') {
    $where[] = "sub.status = 'pending'";
}

if ($searchQ !== '') {
    $like = '%' . $searchQ . '%';
    $where[] = "(u.email LIKE ? OR s.store_name LIKE ? OR sub.plan LIKE ? OR sub.payment_ref LIKE ? OR $ownerNameExpr LIKE ?)";
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

// Stats from live data
$stats = [
    'mrr' => 0.0,
    'active' => 0,
    'trials' => 0,
    'expired' => 0,
    'cancelled' => 0,
    'total' => 0,
];

$activeRes = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(CASE WHEN billing_cycle = 'yearly' THEN amount / 12 ELSE amount END), 0) AS mrr
    FROM subscriptions
    WHERE status = 'active' AND amount > 0 AND (end_date IS NULL OR end_date > NOW())");
if ($activeRes && ($row = $activeRes->fetch_assoc())) {
    $stats['active'] = (int) ($row['cnt'] ?? 0);
    $stats['mrr'] = (float) ($row['mrr'] ?? 0);
}

$trialRes = $conn->query("SELECT COUNT(*) AS cnt FROM subscriptions WHERE status = 'active' AND amount = 0 AND (end_date IS NULL OR end_date > NOW())");
if ($trialRes && ($row = $trialRes->fetch_assoc())) {
    $stats['trials'] = (int) ($row['cnt'] ?? 0);
}

$expiredRes = $conn->query("SELECT COUNT(*) AS cnt FROM subscriptions WHERE status = 'expired'");
if ($expiredRes && ($row = $expiredRes->fetch_assoc())) {
    $stats['expired'] = (int) ($row['cnt'] ?? 0);
}

$cancelledRes = $conn->query("SELECT COUNT(*) AS cnt FROM subscriptions WHERE status = 'cancelled'");
if ($cancelledRes && ($row = $cancelledRes->fetch_assoc())) {
    $stats['cancelled'] = (int) ($row['cnt'] ?? 0);
}

$totalRes = $conn->query('SELECT COUNT(*) AS cnt FROM subscriptions');
if ($totalRes && ($row = $totalRes->fetch_assoc())) {
    $stats['total'] = (int) ($row['cnt'] ?? 0);
}

$countSql = "SELECT COUNT(*) AS cnt
    FROM subscriptions sub
    INNER JOIN users u ON u.id = sub.user_id
    LEFT JOIN stores s ON s.user_id = sub.user_id
    WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
$totalFiltered = 0;
if ($countStmt) {
    if ($types !== '') {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalFiltered = (int) ($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $countStmt->close();
}

$pages = max(1, (int) ceil($totalFiltered / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$listSql = "SELECT sub.*,
        $ownerNameExpr AS owner_name,
        u.email AS owner_email,
        s.store_name,
        s.store_slug,
        s.status AS store_status
    FROM subscriptions sub
    INNER JOIN users u ON u.id = sub.user_id
    LEFT JOIN stores s ON s.user_id = sub.user_id
    WHERE $whereSql
    ORDER BY sub.created_at DESC, sub.id DESC
    LIMIT ? OFFSET ?";

$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$subscriptions = [];
$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listStmt->bind_param($listTypes, ...$listParams);
    $listStmt->execute();
    $subscriptions = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $listStmt->close();
}

$filterQs = static function (array $overrides = []) use ($filter, $searchQ, $page) {
    $qs = array_merge([
        'filter' => $filter,
        'q' => $searchQ,
        'page' => $page,
    ], $overrides);
    $qs = array_filter($qs, static function ($v) {
        return $v !== '' && $v !== null;
    });
    if (isset($qs['page']) && (int) $qs['page'] <= 1) {
        unset($qs['page']);
    }
    return $qs ? ('?' . http_build_query($qs)) : '';
};

$formatMoney = static function ($amount) {
    return '₦' . number_format((float) $amount, 2);
};

$adminPageTitle = 'Subscriptions - RD Vendora Admin';
$adminPageHeading = 'Subscriptions';
$adminPageSubtitle = $totalFiltered . ' record' . ($totalFiltered === 1 ? '' : 's');
$adminSearchPlaceholder = 'Search users, stores, plans...';
$adminShowHeader = true;
$adminPageStyles = '
.sub-filters { display:flex; flex-wrap:wrap; gap:0.5rem; margin:1rem 0; }
.sub-filters a { padding:0.45rem 0.85rem; border-radius:999px; border:1px solid var(--border-primary); text-decoration:none; color:var(--text-secondary); font-size:0.85rem; }
.sub-filters a.is-on { background:var(--primary-light); color:var(--primary); border-color:transparent; }
.sub-search { display:flex; gap:0.5rem; max-width:420px; margin-bottom:1rem; }
.sub-search input { flex:1; }
.badge-active { background:var(--success-light); color:var(--success); }
.badge-expired { background:#fee2e2; color:#991b1b; }
.badge-cancelled { background:var(--bg-tertiary,#f3f4f6); color:var(--text-muted); }
.badge-pending, .badge-trial { background:var(--warning-light,#fef3c7); color:#92400e; }
.sub-meta { color:var(--text-secondary); font-size:0.82rem; }
.sub-pager { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-top:1rem; flex-wrap:wrap; }
';
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content">
    <div class="stats-grid">
        <div class="stat-card reveal">
            <div class="stat-value" style="color: var(--primary);"><?= htmlspecialchars($formatMoney($stats['mrr'])) ?></div>
            <div class="stat-label">Est. MRR</div>
        </div>
        <div class="stat-card reveal">
            <div class="stat-value" style="color: var(--success);"><?= (int) $stats['active'] ?></div>
            <div class="stat-label">Active paid</div>
        </div>
        <div class="stat-card reveal">
            <div class="stat-value" style="color: var(--warning);"><?= (int) $stats['trials'] ?></div>
            <div class="stat-label">Free trials</div>
        </div>
        <div class="stat-card reveal">
            <div class="stat-value" style="color: var(--error);"><?= (int) $stats['expired'] ?></div>
            <div class="stat-label">Expired</div>
        </div>
    </div>

    <form method="get" class="sub-search">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <input type="search" name="q" class="form-input" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Search user, store, plan, or payment ref">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
    </form>

    <div class="sub-filters">
        <?php
        $filters = [
            'all' => 'All (' . $stats['total'] . ')',
            'active' => 'Active paid (' . $stats['active'] . ')',
            'trial' => 'Trials (' . $stats['trials'] . ')',
            'expired' => 'Expired (' . $stats['expired'] . ')',
            'cancelled' => 'Cancelled (' . $stats['cancelled'] . ')',
            'pending' => 'Pending',
        ];
        foreach ($filters as $key => $label):
        ?>
            <a href="admin-subscriptions<?= htmlspecialchars($filterQs(['filter' => $key, 'page' => 1])) ?>" class="<?= $filter === $key ? 'is-on' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="table-container reveal">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User / Store</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Billing</th>
                    <th>Started</th>
                    <th>Expires</th>
                    <th>Amount</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$subscriptions): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-secondary);">
                            No subscription records found<?= $searchQ !== '' || $filter !== 'all' ? ' for this filter.' : ' yet.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscriptions as $sub): ?>
                        <?php
                        $status = (string) ($sub['status'] ?? 'pending');
                        $isTrial = ($status === 'active' && (float) ($sub['amount'] ?? 0) == 0.0);
                        $badgeClass = 'badge-pending';
                        $statusLabel = ucfirst($status);
                        if ($isTrial) {
                            $badgeClass = 'badge-trial';
                            $statusLabel = 'Trial';
                        } elseif ($status === 'active') {
                            $badgeClass = 'badge-active';
                        } elseif ($status === 'expired') {
                            $badgeClass = 'badge-expired';
                        } elseif ($status === 'cancelled') {
                            $badgeClass = 'badge-cancelled';
                        }
                        $startLabel = !empty($sub['start_date']) ? date('M j, Y', strtotime($sub['start_date'])) : '—';
                        $endLabel = !empty($sub['end_date']) ? date('M j, Y', strtotime($sub['end_date'])) : '—';
                        ?>
                        <tr>
                            <td>
                                <div><strong><?= htmlspecialchars($sub['owner_name'] ?? 'Unknown') ?></strong></div>
                                <div class="sub-meta"><?= htmlspecialchars($sub['owner_email'] ?? '') ?></div>
                                <?php if (!empty($sub['store_name'])): ?>
                                    <div class="sub-meta">Store: <?= htmlspecialchars($sub['store_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($sub['plan'] ?? '—') ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                            <td><?= htmlspecialchars(ucfirst((string) ($sub['billing_cycle'] ?? '—'))) ?></td>
                            <td><?= htmlspecialchars($startLabel) ?></td>
                            <td><?= htmlspecialchars($endLabel) ?></td>
                            <td><?= htmlspecialchars($formatMoney($sub['amount'] ?? 0)) ?></td>
                            <td>
                                <div class="sub-meta"><?= htmlspecialchars($sub['payment_method'] ?? '—') ?></div>
                                <?php if (!empty($sub['payment_ref'])): ?>
                                    <div class="sub-meta"><?= htmlspecialchars($sub['payment_ref']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="sub-pager">
            <div class="sub-meta">Page <?= (int) $page ?> of <?= (int) $pages ?></div>
            <div style="display:flex;gap:0.5rem;">
                <?php if ($page > 1): ?>
                    <a class="btn btn-ghost btn-sm" href="admin-subscriptions<?= htmlspecialchars($filterQs(['page' => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-ghost btn-sm" href="admin-subscriptions<?= htmlspecialchars($filterQs(['page' => $page + 1])) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <p style="margin-top:1.5rem;color:var(--text-secondary);font-size:0.9rem;">
        Manage plan prices in <a href="admin-pricing" style="color:var(--primary)">Pricing Plans</a>.
        Approve or review stores in <a href="admin-stores" style="color:var(--primary)">Stores</a>.
    </p>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
