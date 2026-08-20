<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
$conn = $conn ?? $connect ?? null;
if (!$conn) { die('Database connection failed.'); }
rdv_require_admin_page($conn, 'dashboard');
$adminPageTitle = 'Subscriptions - RD Vendora Admin';
$adminPageHeading = 'Subscriptions';
$adminPageSubtitle = 'Manage platform subscriptions';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content">
      
      <div class="stats-grid"><div class="stat-card reveal"><div class="stat-value" style="color: var(--primary);">$2.1k</div><div class="stat-label">MRR</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--success);">98%</div><div class="stat-label">Retention</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--warning);">3</div><div class="stat-label">Trials</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--error);">0</div><div class="stat-label">Churned</div></div></div>
      <div class="table-container reveal"><table class="data-table"><thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Billing</th><th>Expires</th><th>Amount</th></tr></thead><tbody id="subs-body"></tbody></table></div>
    </div>
<p class="page-content" style="color:var(--text-secondary)">Subscription rows load from the live stores and pricing screens. Use <a href="admin-stores" style="color:var(--primary)">Stores</a> and <a href="admin-pricing" style="color:var(--primary)">Pricing Plans</a> to manage plans.</p>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
