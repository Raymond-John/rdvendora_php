<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
$conn = $conn ?? $connect ?? null;
if (!$conn) { die('Database connection failed.'); }
rdv_require_admin_page($conn, 'dashboard');
$adminPageTitle = 'Admin Analytics - RD Vendora';
$adminPageHeading = 'Admin Analytics';
$adminPageSubtitle = 'Platform-wide data and insights.';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
$adminHeadExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content">
      
      <div class="stats-grid"><div class="stat-card reveal"><div class="stat-value" style="color: var(--primary);">$173k</div><div class="stat-label">Total Revenue</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--success);">15K</div><div class="stat-label">Total Users</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--warning);">2.5M</div><div class="stat-label">Products Sold</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--info);">99.9%</div><div class="stat-label">Uptime</div></div></div>
      <div class="dashboard-grid dashboard-grid-2"><div class="chart-container reveal"><div class="chart-header"><h3 class="chart-title">Monthly Revenue</h3></div><div class="chart-wrapper"><canvas id="monthlyRev"></canvas></div></div><div class="chart-container reveal"><div class="chart-header"><h3 class="chart-title">Plan Distribution</h3></div><div class="chart-wrapper chart-wrapper-sm"><canvas id="planDist"></canvas></div></div></div>
      <div class="chart-container reveal"><div class="chart-header"><h3 class="chart-title">User Signups</h3></div><div class="chart-wrapper"><canvas id="userSignups"></canvas></div></div>
    </div>
<script>
    new Chart(document.getElementById('monthlyRev').getContext('2d'), { type: 'bar', data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], datasets: [{ label: 'Revenue', data: [8,12,15,14,18,22,20,25,28,32,38,45], backgroundColor: '#4f46e5', borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#8b95a5', font: { size: 11 } } }, y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#8b95a5', font: { size: 11 }, callback: v => '$' + v + 'k' } } } } });
    new Chart(document.getElementById('planDist').getContext('2d'), { type: 'doughnut', data: { labels: ['Launch','Growth','Scale','Empire'], datasets: [{ data: [60,25,10,5], backgroundColor: ['#8b95a5','#4f46e5','#f59e0b','#ec4899'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 11 } } } } } });
    new Chart(document.getElementById('userSignups').getContext('2d'), { type: 'line', data: { labels: ['W1','W2','W3','W4'], datasets: [{ label: 'Signups', data: [320,480,560,420], borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 4 }, { label: 'Activations', data: [280,400,480,380], borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.08)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 11 } } } }, scales: { x: { grid: { display: false }, ticks: { color: '#8b95a5', font: { size: 11 } } }, y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#8b95a5', font: { size: 11 } } } } } });
  </script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
