/**
 * RD Vendora - Admin Panel JavaScript
 * Platform admin dashboard with analytics and user management
 */

document.addEventListener('DOMContentLoaded', () => {
  if (!isAuthenticated()) {
    window.location.href = 'login.php';
    return;
  }

  renderAdminStats();
  renderUsersTable();
  renderSubscriptionsTable();
  initAdminCharts();
});

/**
 * Render admin stats
 */
function renderAdminStats() {
  const subscribers = DataStore.get('subscribers') || [];
  const totalUsers = subscribers.length + 120;
  const totalStores = subscribers.reduce((s, u) => s + u.stores, 0) + 45;
  const totalRevenue = subscribers.reduce((s, u) => s + u.revenue, 0) + 45000;
  const activeSubs = subscribers.filter(s => s.status === 'active').length + 98;

  const stats = [
    { label: 'Total Users', value: totalUsers.toLocaleString(), change: '+12%', up: true, icon: 'primary', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' },
    { label: 'Total Stores', value: totalStores.toLocaleString(), change: '+8%', up: true, icon: 'success', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' },
    { label: 'Platform Revenue', value: '$' + totalRevenue.toLocaleString(), change: '+24%', up: true, icon: 'warning', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
    { label: 'Active Subscriptions', value: activeSubs.toString(), change: '+5%', up: true, icon: 'info', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>' }
  ];

  const grid = document.getElementById('adminStats');
  if (grid) {
    grid.innerHTML = stats.map(stat => `
      <div class="stat-card">
        <div class="stat-header">
          <div>
            <div class="stat-label">${stat.label}</div>
            <div class="stat-value">${stat.value}</div>
            <div class="stat-change ${stat.up ? 'up' : 'down'}">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="${stat.up ? '23 6 13.5 15.5 8.5 10.5 1 18' : '23 18 13.5 8.5 8.5 13.5 1 6'}"/></svg>
              ${stat.change}
            </div>
          </div>
          <div class="stat-icon ${stat.icon}">${stat.iconSvg}</div>
        </div>
      </div>
    `).join('');
  }
}

/**
 * Render users table
 */
function renderUsersTable() {
  const tbody = document.querySelector('#usersTable tbody');
  if (!tbody) return;

  const subscribers = DataStore.get('subscribers') || [];

  tbody.innerHTML = subscribers.map(user => `
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:0.75rem">
          <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;color:white;font-size:13px;font-weight:600">
            ${user.name.split(' ').map(n => n[0]).join('')}
          </div>
          <div>
            <div style="font-weight:500;font-size:0.875rem">${user.name}</div>
            <div style="font-size:0.75rem;color:var(--text-muted)">${user.email}</div>
          </div>
        </div>
      </td>
      <td><span class="badge badge-${getPlanColor(user.plan)}">${user.plan}</span></td>
      <td>${user.stores}</td>
      <td><strong>$${user.revenue.toLocaleString()}</strong></td>
      <td><span class="admin-badge ${user.status}">${user.status}</span></td>
      <td style="font-size:0.8125rem;color:var(--text-muted)">${user.joined}</td>
      <td>
        <div class="table-actions">
          <button class="btn btn-icon btn-sm btn-ghost" onclick="Toast.info('Edit user')" title="Edit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="btn btn-icon btn-sm btn-ghost" onclick="Toast.info('View details')" title="View">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function getPlanColor(plan) {
  const colors = { 'Launch': 'info', 'Growth': 'success', 'Scale': 'primary', 'Empire': 'warning' };
  return colors[plan] || 'info';
}

/**
 * Render subscriptions table
 */
function renderSubscriptionsTable() {
  const tbody = document.querySelector('#subscriptionsTable tbody');
  if (!tbody) return;

  const subscribers = DataStore.get('subscribers') || [];
  const planPrices = { 'Launch': 19, 'Growth': 49, 'Scale': 99, 'Empire': 299 };

  tbody.innerHTML = subscribers.map(user => `
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:0.75rem">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:12px;font-weight:600">
            ${user.name.split(' ').map(n => n[0]).join('')}
          </div>
          <span style="font-weight:500;font-size:0.875rem">${user.name}</span>
        </div>
      </td>
      <td><span class="badge badge-${getPlanColor(user.plan)}">${user.plan}</span></td>
      <td>$${planPrices[user.plan]}/mo</td>
      <td><span class="admin-badge ${user.status}">${user.status}</span></td>
      <td style="font-size:0.8125rem;color:var(--text-muted)">Feb 15, 2025</td>
      <td>
        <div class="table-actions">
          <button class="btn btn-icon btn-sm btn-ghost" title="Manage" onclick="Toast.info('Manage subscription')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

/**
 * Switch tab
 */
function switchTab(tab) {
  document.getElementById('usersTab').classList.toggle('hidden', tab !== 'users');
  document.getElementById('storesTab').classList.toggle('hidden', tab !== 'stores');
  document.getElementById('subscriptionsTab').classList.toggle('hidden', tab !== 'subscriptions');

  document.querySelectorAll('.tabs-nav button').forEach(btn => {
    btn.classList.toggle('active', btn.textContent.toLowerCase().includes(tab));
  });
}

/**
 * Initialize admin charts
 */
function initAdminCharts() {
  // Platform Revenue Chart
  const revCtx = document.getElementById('adminRevenueChart');
  if (revCtx) {
    const grad = revCtx.getContext('2d').createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
    grad.addColorStop(1, 'rgba(16, 185, 129, 0)');

    new Chart(revCtx, {
      type: 'line',
      data: {
        labels: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
        datasets: [{
          label: 'Revenue',
          data: [28000, 32000, 35000, 31000, 42000, 48000, 52000],
          borderColor: '#10b981',
          backgroundColor: grad,
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointBackgroundColor: '#10b981',
          pointBorderColor: '#fff',
          pointBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleColor: '#fff',
            bodyColor: '#fff',
            padding: 12,
            cornerRadius: 8,
            displayColors: false,
            callbacks: { label: (ctx) => '$' + ctx.parsed.y.toLocaleString() }
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: 'var(--text-muted)', font: { size: 11 } } },
          y: { border: { display: false }, grid: { color: 'var(--border-color)' }, ticks: { color: 'var(--text-muted)', font: { size: 11 }, callback: (val) => '$' + (val / 1000) + 'k' } }
        }
      }
    });
  }

  // User Growth Chart
  const userCtx = document.getElementById('userGrowthChart');
  if (userCtx) {
    new Chart(userCtx, {
      type: 'bar',
      data: {
        labels: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
        datasets: [{
          label: 'New Users',
          data: [18, 22, 28, 32, 45, 52, 38],
          backgroundColor: '#6366f1',
          borderRadius: 6
        }, {
          label: 'Churned',
          data: [2, 3, 1, 4, 2, 3, 2],
          backgroundColor: '#ef4444',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: 'var(--text-secondary)', font: { size: 11 }, padding: 16, usePointStyle: true, pointStyle: 'circle' }
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: 'var(--text-muted)', font: { size: 11 } } },
          y: { border: { display: false }, grid: { color: 'var(--border-color)' }, ticks: { color: 'var(--text-muted)', font: { size: 11 } } }
        }
      }
    });
  }
}
