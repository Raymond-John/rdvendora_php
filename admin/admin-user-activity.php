<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/log_activity.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!$conn) {
    die('Database connection failed.');
}

if (!adminHasPermission('dashboard', $conn) && !adminHasPermission('users', $conn)) {
    rdv_require_admin_page($conn, 'dashboard');
} else {
    rdv_hydrate_admin_session($conn);
    if (!rdv_admin_flag_is_set()) {
        header('Location: admin_login');
        exit;
    }
}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        page VARCHAR(255) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (action),
        INDEX (created_at),
        INDEX (page)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    error_log('user_activity_log table: ' . $e->getMessage());
}

logUserActivity((int) ($_SESSION['user_id'] ?? 0), 'admin_activity_view', 'admin-user-activity.php', 'Viewed user activity log');

$userCols = [];
try {
    $colRes = $conn->query('SHOW COLUMNS FROM users');
    if ($colRes) {
        while ($col = $colRes->fetch_assoc()) {
            $userCols[$col['Field']] = true;
        }
    }
} catch (Throwable $e) {
    $userCols = [];
}

$nameParts = [];
foreach (['fullname', 'full_name', 'name', 'username'] as $candidate) {
    if (!empty($userCols[$candidate])) {
        $nameParts[] = "NULLIF(u.$candidate, '')";
    }
}
$nameExpr = $nameParts ? ('COALESCE(' . implode(', ', $nameParts) . ", u.email, 'Unknown')") : "COALESCE(u.email, 'Unknown')";
$userListName = $nameParts ? str_replace('u.', '', $nameParts[0]) : 'email';
$userListName = preg_replace('/^NULLIF\((.+), \'\'\)$/', '$1', $userListName);

$limit = 50;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$start_date = trim((string) ($_GET['start_date'] ?? ''));
$end_date = trim((string) ($_GET['end_date'] ?? ''));
$user_filter = (int) ($_GET['user_id'] ?? 0);
$action_filter = trim((string) ($_GET['action'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($start_date !== '' && $end_date !== '') {
    $where[] = 'DATE(l.created_at) BETWEEN ? AND ?';
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
} elseif ($start_date !== '') {
    $where[] = 'DATE(l.created_at) >= ?';
    $params[] = $start_date;
    $types .= 's';
} elseif ($end_date !== '') {
    $where[] = 'DATE(l.created_at) <= ?';
    $params[] = $end_date;
    $types .= 's';
}
if ($user_filter > 0) {
    $where[] = 'l.user_id = ?';
    $params[] = $user_filter;
    $types .= 'i';
}
if ($action_filter !== '' && $action_filter !== 'all') {
    $where[] = 'l.action = ?';
    $params[] = $action_filter;
    $types .= 's';
}
if ($q !== '') {
    $where[] = '(l.action LIKE ? OR l.page LIKE ? OR l.details LIKE ? OR l.ip_address LIKE ? OR u.email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$run = static function (mysqli $conn, string $sql, string $types = '', array $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        if ($types !== '' && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        error_log('User activity query failed: ' . $e->getMessage());
        return null;
    }
};

$countRows = $run($conn, "SELECT COUNT(*) AS total FROM user_activity_log l LEFT JOIN users u ON u.id = l.user_id $where_sql", $types, $params) ?: [['total' => 0]];
$total = (int) ($countRows[0]['total'] ?? 0);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRows = $run(
        $conn,
        "SELECT l.created_at, l.user_id, $nameExpr AS user_name, u.email AS user_email, l.action, l.page, l.details, l.ip_address
         FROM user_activity_log l
         LEFT JOIN users u ON u.id = l.user_id
         $where_sql
         ORDER BY l.created_at DESC
         LIMIT 5000",
        $types,
        $params
    ) ?: [];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="user-activity.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['created_at', 'user_id', 'user_name', 'email', 'action', 'page', 'details', 'ip_address']);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row['created_at'] ?? '',
            $row['user_id'] ?? '',
            $row['user_name'] ?? '',
            $row['user_email'] ?? '',
            $row['action'] ?? '',
            $row['page'] ?? '',
            $row['details'] ?? '',
            $row['ip_address'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$listTypes = $types . 'ii';
$listParams = array_merge($params, [$limit, $offset]);
$logs = $run(
    $conn,
    "SELECT l.*, $nameExpr AS user_name, u.email AS user_email
     FROM user_activity_log l
     LEFT JOIN users u ON u.id = l.user_id
     $where_sql
     ORDER BY l.created_at DESC
     LIMIT ? OFFSET ?",
    $listTypes,
    $listParams
) ?: [];

$actions = [];
try {
    $actRes = $conn->query('SELECT DISTINCT action FROM user_activity_log ORDER BY action');
    if ($actRes) {
        $actions = $actRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $actions = [];
}

$users = [];
try {
    $userSql = "SELECT id, email";
    if (!empty($userCols['fullname'])) {
        $userSql .= ', fullname';
    } elseif (!empty($userCols['full_name'])) {
        $userSql .= ', full_name';
    }
    $userSql .= ' FROM users ORDER BY id DESC LIMIT 500';
    $userRes = $conn->query($userSql);
    if ($userRes) {
        $users = $userRes->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $users = [];
}

$day_labels = [];
$day_counts = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $day_labels[] = date('M d', strtotime($day));
    $row = $run($conn, 'SELECT COUNT(*) AS cnt FROM user_activity_log WHERE DATE(created_at) = ?', 's', [$day]) ?: [['cnt' => 0]];
    $day_counts[] = (int) (($row[0]['cnt'] ?? 0));
}

$action_counts = [];
try {
    $ac = $conn->query('SELECT action, COUNT(*) AS cnt FROM user_activity_log GROUP BY action ORDER BY cnt DESC LIMIT 10');
    if ($ac) {
        $action_counts = $ac->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    $action_counts = [];
}
$action_labels = array_column($action_counts, 'action');
$action_values = array_map('intval', array_column($action_counts, 'cnt'));

$todayCount = (int) (($run($conn, "SELECT COUNT(*) AS cnt FROM user_activity_log WHERE DATE(created_at) = CURDATE()") ?: [['cnt' => 0]])[0]['cnt'] ?? 0);
$weekCount = (int) (($run($conn, "SELECT COUNT(*) AS cnt FROM user_activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)") ?: [['cnt' => 0]])[0]['cnt'] ?? 0);
$uniqueUsers = (int) (($run($conn, "SELECT COUNT(DISTINCT user_id) AS cnt FROM user_activity_log WHERE user_id IS NOT NULL AND user_id > 0") ?: [['cnt' => 0]])[0]['cnt'] ?? 0);
$allCount = (int) (($run($conn, "SELECT COUNT(*) AS cnt FROM user_activity_log") ?: [['cnt' => 0]])[0]['cnt'] ?? 0);

$total_pages = max(1, (int) ceil($total / $limit));
$query_params = $_GET;
unset($query_params['page'], $query_params['export']);
$base_url = 'admin-user-activity' . ($query_params ? ('?' . http_build_query($query_params)) : '');
$page_join = strpos($base_url, '?') === false ? '?' : '&';

$adminPageTitle = 'User Activity - Admin';
$adminPageHeading = 'User Activity';
$adminPageSubtitle = 'Live log of logins, page views, and admin actions';
$adminSearchPlaceholder = 'Search actions, pages, IP...';
$adminShowHeader = true;
$adminHeadExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
$adminPageStyles = '
.filter-bar { display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end; background:var(--bg-secondary); border:1px solid var(--border-primary); border-radius:var(--radius-xl); padding:1rem 1.25rem; }
.filter-group label { margin-bottom:0.35rem; }
.filter-group input, .filter-group select { min-width:140px; }
.badge-login, .badge-admin_login { background:var(--primary-light); color:var(--primary); }
.badge-dashboard_view, .badge-admin_activity_view { background:var(--info-light); color:var(--info); }
.badge-analytics_view { background:var(--success-light); color:var(--success); }
.current-page { padding:0.4rem 0.75rem; border-radius:var(--radius); background:var(--primary); color:#fff; }
';
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-title">Today</div>
        <div class="stat-value"><?= number_format($todayCount) ?></div>
        <div class="stat-change">events logged today</div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Last 7 days</div>
        <div class="stat-value"><?= number_format($weekCount) ?></div>
        <div class="stat-change">platform activity</div>
    </div>
    <div class="stat-card">
        <div class="stat-title">Unique users</div>
        <div class="stat-value"><?= number_format($uniqueUsers) ?></div>
        <div class="stat-change">with recorded actions</div>
    </div>
    <div class="stat-card">
        <div class="stat-title">All time</div>
        <div class="stat-value"><?= number_format($allCount) ?></div>
        <div class="stat-change"><?= number_format($total) ?> match this filter</div>
    </div>
</div>

<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-header"><h3 class="chart-title">Activity trend (last 7 days)</h3></div>
        <div class="chart-container"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3 class="chart-title">Top actions</h3></div>
        <div class="chart-container"><canvas id="actionChart"></canvas></div>
    </div>
</div>

<div class="page-content">
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label>Search</label>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Action, page, IP, email">
        </div>
        <div class="filter-group">
            <label>Start date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="filter-group">
            <label>End date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="filter-group">
            <label>User</label>
            <select name="user_id">
                <option value="0">All users</option>
                <?php foreach ($users as $user): ?>
                    <?php
                    $labelName = $user['fullname'] ?? $user['full_name'] ?? $user['email'] ?? ('User #' . $user['id']);
                    ?>
                    <option value="<?= (int) $user['id'] ?>" <?= $user_filter === (int) $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($labelName . ' (' . ($user['email'] ?? '') . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Action</label>
            <select name="action">
                <option value="all">All actions</option>
                <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act['action']) ?>" <?= $action_filter === $act['action'] ? 'selected' : '' ?>><?= htmlspecialchars($act['action']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <button type="submit" class="btn">Filter</button>
            <a class="btn ghost" href="admin-user-activity">Reset</a>
            <a class="btn ghost" href="<?= htmlspecialchars($base_url . $page_join . 'export=csv') ?>">Export CSV</a>
        </div>
    </form>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Page</th>
                <th>Details</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="empty-state">No activity yet. Vendor logins, dashboard views, and this page now write to the log.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $displayName = (string) ($log['user_name'] ?? '');
                    if ($displayName === '' || $displayName === 'Unknown') {
                        $displayName = !empty($log['user_id']) ? ('User #' . (int) $log['user_id']) : 'Guest';
                    }
                    $badge = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', (string) $log['action']));
                    ?>
                    <tr>
                        <td style="white-space:nowrap"><?= htmlspecialchars((string) $log['created_at']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($displayName) ?></strong>
                            <?php if (!empty($log['user_email'])): ?>
                                <div style="color:var(--text-muted);font-size:0.75rem"><?= htmlspecialchars($log['user_email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= htmlspecialchars($badge) ?>"><?= htmlspecialchars((string) $log['action']) ?></span></td>
                        <td><?= htmlspecialchars((string) ($log['page'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($log['details'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($total > $limit): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= htmlspecialchars($base_url . $page_join . 'page=' . ($page - 1)) ?>">Previous</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current-page"><?= $i ?></span>
        <?php else: ?>
            <a class="page-btn" href="<?= htmlspecialchars($base_url . $page_join . 'page=' . $i) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
        <a class="page-btn" href="<?= htmlspecialchars($base_url . $page_join . 'page=' . ($page + 1)) ?>">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    const trendLabels = <?= json_encode($day_labels) ?>;
    const trendData = <?= json_encode($day_counts) ?>;
    const actionLabels = <?= json_encode($action_labels) ?>;
    const actionData = <?= json_encode($action_values) ?>;
    let trendChart, actionChart;
    function createActivityCharts() {
        const ctxTrend = document.getElementById('trendChart')?.getContext('2d');
        const ctxAction = document.getElementById('actionChart')?.getContext('2d');
        if (ctxTrend) {
            if (trendChart) trendChart.destroy();
            trendChart = new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Events',
                        data: trendData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.12)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
        if (ctxAction) {
            if (actionChart) actionChart.destroy();
            actionChart = new Chart(ctxAction, {
                type: 'bar',
                data: {
                    labels: actionLabels.length ? actionLabels : ['No data'],
                    datasets: [{
                        label: 'Occurrences',
                        data: actionData.length ? actionData : [0],
                        backgroundColor: '#8b5cf6',
                        borderRadius: 8
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
            });
        }
    }
    window.rdvAdminOnThemeChange = createActivityCharts;
    document.addEventListener('DOMContentLoaded', createActivityCharts);
    const adminSearch = document.getElementById('adminSearchInput');
    if (adminSearch && !<?= json_encode($q !== '') ?>) {
        adminSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const url = new URL(window.location.href);
                url.searchParams.set('q', this.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }
        });
    }
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
