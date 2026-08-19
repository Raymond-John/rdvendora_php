<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

rdv_require_admin_page($conn, 'dashboard');

// ---------- STATS FROM DATABASE (LIVE) ----------
$totalUsers = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM users");
$totalStores = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM stores");
$totalProducts = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM products");
$totalOrder = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM orders");
$totalTransportOrder = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM transport_notifications");

$totalRevenue = rdv_admin_count($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");

// ---------- CHART DATA (LAST 7 DAYS) ----------
$chartLabels = [];
$chartRevenue = [];
$chartUsers = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $displayDate = date('M d', strtotime($date));
    $chartLabels[] = $displayDate;
    
    // Daily revenue from completed orders
    $dailyRevenue = 0;
    $dailyUsers = 0;
    try {
    $revStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as daily_total FROM orders WHERE status = 'completed' AND DATE(created_at) = ?");
        if ($revStmt) {
    $revStmt->bind_param("s", $date);
    $revStmt->execute();
    $revRow = $revStmt->get_result()->fetch_assoc();
    $dailyRevenue = $revRow['daily_total'] ?? 0;
    $revStmt->close();
        }
    $userStmt = $conn->prepare("SELECT COUNT(*) as daily_count FROM users WHERE DATE(created_at) = ?");
        if ($userStmt) {
    $userStmt->bind_param("s", $date);
    $userStmt->execute();
    $userRow = $userStmt->get_result()->fetch_assoc();
    $dailyUsers = $userRow['daily_count'] ?? 0;
    $userStmt->close();
        }
    } catch (Throwable $e) {
        error_log('Admin chart query failed: ' . $e->getMessage());
    }
    $chartRevenue[] = $dailyRevenue;
    $chartUsers[] = $dailyUsers;
}

// Calculate percentage changes (vs previous month)
$lastMonthRevenue = rdv_admin_count($conn, "SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed' AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
$revenueChange = $lastMonthRevenue > 0 ? round(($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue * 100) : 0;

$lastMonthUsers = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM users WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
$userChange = $lastMonthUsers > 0 ? round(($totalUsers - $lastMonthUsers) / $lastMonthUsers * 100) : 0;

$lastMonthStores = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM stores WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
$storeChange = $lastMonthStores > 0 ? round(($totalStores - $lastMonthStores) / $lastMonthStores * 100) : 0;

$lastMonthProducts = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM products WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
$productChange = $lastMonthProducts > 0 ? round(($totalProducts - $lastMonthProducts) / $lastMonthProducts * 100) : 0;

$lastMonthOrder = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM orders WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
$OrderChange = $lastMonthOrder > 0 ? round(($totalOrder - $lastMonthOrder) / $lastMonthOrder * 100) : 0;

$lastMonthTransportOrder = (int) rdv_admin_count($conn, "SELECT COUNT(*) as count FROM transport_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)");
$TransportOrderChange = $lastMonthTransportOrder > 0 ? round(($totalTransportOrder - $lastMonthTransportOrder) / $lastMonthTransportOrder * 100) : 0;
$adminPageTitle = 'Admin Dashboard - RD Vendora';
$adminPageHeading = 'Platform Dashboard';
$adminPageSubtitle = 'Overview of the entire RD Vendora ecosystem';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
$adminHeadExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Total Users</div>
            <div class="stat-value"><?= number_format($totalUsers) ?></div>
            <div class="stat-change <?= $userChange >= 0 ? 'up' : 'down' ?>">
                <?= $userChange >= 0 ? '↑' : '↓' ?> <?= abs($userChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Active Stores</div>
            <div class="stat-value"><?= number_format($totalStores) ?></div>
            <div class="stat-change <?= $storeChange >= 0 ? 'up' : 'down' ?>">
                <?= $storeChange >= 0 ? '↑' : '↓' ?> <?= abs($storeChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Products</div>
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-change <?= $productChange >= 0 ? 'up' : 'down' ?>">
                <?= $productChange >= 0 ? '↑' : '↓' ?> <?= abs($productChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Orders</div>
            <div class="stat-value"><?= number_format($totalOrder) ?></div>
            <div class="stat-change <?= $productChange >= 0 ? 'up' : 'down' ?>">
                <?= $productChange >= 0 ? '↑' : '↓' ?> <?= abs($productChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Order Transport</div>
            <div class="stat-value"><?= number_format($totalTransportOrder) ?></div>
            <div class="stat-change <?= $productChange >= 0 ? 'up' : 'down' ?>">
                <?= $productChange >= 0 ? '↑' : '↓' ?> <?= abs($productChange) ?>% vs last month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Platform Revenue</div>
            <div class="stat-value">₦<?= number_format($totalRevenue, 2) ?></div>
            <div class="stat-change <?= $revenueChange >= 0 ? 'up' : 'down' ?>">
                <?= $revenueChange >= 0 ? '↑' : '↓' ?> <?= abs($revenueChange) ?>% vs last month
            </div>
        </div>
        
    </div>

    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Revenue Trend (Last 7 days)</h3>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">User Acquisition (Last 7 days)</h3>
            </div>
            <div class="chart-container">
                <canvas id="userGrowthChart"></canvas>
            </div>
        </div>
    </div>
<script>
    // Live data passed from PHP
    const revenueData = <?= json_encode($chartRevenue) ?>;
    const userData = <?= json_encode($chartUsers) ?>;
    const labels = <?= json_encode($chartLabels) ?>;
    // Chart creation function (reusable for theme change)
    let revenueChart, userChart;
    function createCharts() {
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        const userCtx = document.getElementById('userGrowthChart')?.getContext('2d');
        if (revenueCtx) {
            if (revenueChart) revenueChart.destroy();
            revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (₦)',
                        data: revenueData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => '₦' + v.toLocaleString() } }
                    }
                }
            });
        }
        if (userCtx) {
            if (userChart) userChart.destroy();
            userChart = new Chart(userCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'New Users',
                        data: userData,
                        backgroundColor: '#10b981',
                        borderRadius: 8,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    }

    createCharts();
    window.rdvAdminOnThemeChange = createCharts;


</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
