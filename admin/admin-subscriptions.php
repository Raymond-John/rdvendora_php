<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Subscriptions - RD Vendora Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/animations.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="dashboard-layout">
  <aside class="sidebar" id="sidebar"></aside>
  <div class="sidebar-overlay" id="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('mobile-open');this.classList.remove('active')"></div>
  <div class="main-content">
    <header class="topbar" id="topbar"></header>
    <main class="dashboard-content page-enter">
      <div class="dashboard-header"><div class="dashboard-header-row"><div><h1 class="dashboard-title">Subscriptions</h1><p class="dashboard-subtitle">Manage platform subscriptions.</p></div></div></div>
      <div class="stats-grid"><div class="stat-card reveal"><div class="stat-value" style="color: var(--primary);">$2.1k</div><div class="stat-label">MRR</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--success);">98%</div><div class="stat-label">Retention</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--warning);">3</div><div class="stat-label">Trials</div></div><div class="stat-card reveal"><div class="stat-value" style="color: var(--error);">0</div><div class="stat-label">Churned</div></div></div>
      <div class="table-container reveal"><table class="data-table"><thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Billing</th><th>Expires</th><th>Amount</th></tr></thead><tbody id="subs-body"></tbody></table></div>
    </main>
  </div>
  <script src="../assets/js/app.js"></script>
  <script>
    if (!Auth.isAdmin()) window.location.href = '../dashboard.php';
    UI.injectSidebar('admin-subscriptions'); UI.injectTopbar();
    const users = DB.getAll('users');
    const planPrices = { launch: 0, growth: 49, scale: 149, empire: 399 };
    document.getElementById('subs-body').innerHTML = users.filter(u => u.subscription).map(u => `<tr><td><div style="display: flex; align-items: center; gap: 10px;"><div class="avatar avatar-sm" style="background: var(--primary-light); color: var(--primary);">${u.initials}</div><span style="font-weight: 500;">${u.name}</span></div></td><td><span class="badge ${u.subscription.plan === 'empire' ? 'badge-error' : u.subscription.plan === 'scale' ? 'badge-warning' : 'badge-info'}" style="text-transform: capitalize;">${u.subscription.plan}</span></td><td><span class="badge ${u.subscription.status === 'active' ? 'badge-success' : 'badge-error'}">${u.subscription.status}</span></td><td style="text-transform: capitalize;">${u.subscription.billing_cycle || 'monthly'}</td><td>${u.subscription.expires_at ? new Date(u.subscription.expires_at).toLocaleDateString() : 'N/A'}</td><td style="font-weight: 600;">$${planPrices[u.subscription.plan] || 0}/mo</td></tr>`).join('');
  </script>
</body>
</html>
