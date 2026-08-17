/**
 * RD Vendora - Dashboard JavaScript
 * Charts, stats, recent orders, and dashboard-specific functionality
 */

document.addEventListener('DOMContentLoaded', () => {
  // Check authentication
  if (!isAuthenticated()) {
    window.location.href = 'login.php';
    return;
  }

  // Load user data
  loadUserData();

  // Render dashboard components
  renderStats();
  renderNotifications();
  renderRecentOrders();
  initCharts();

  // Chart period buttons
  document.querySelectorAll('.chart-period button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.chart-period button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      updateRevenueChart(btn.textContent);
    });
  });
});

/**
 * Load user data into UI
 */
function loadUserData() {
  const user = DataStore.get('user');
  if (user) {
    const nameEl = document.getElementById('userName');
    const avatarEl = document.getElementById('userAvatar');
    if (nameEl) nameEl.textContent = user.name;
    if (avatarEl) avatarEl.src = user.avatar;
  }
}

/**
 * Render stats cards
 */
function renderStats() {
  const statsGrid = document.getElementById('statsGrid');
  if (!statsGrid) return;

  const orders = DataStore.get('orders') || [];
  const products = DataStore.get('products') || [];
  const customers = DataStore.get('customers') || [];

  const totalRevenue = orders.reduce((sum, o) => sum + o.total, 0);
  const totalOrders = orders.length;
  const totalProducts = products.length;
  const totalCustomers = customers.length;

  const stats = [
    { label: 'Total Revenue', value: `$${totalRevenue.toLocaleString()}`, change: '+12.5%', up: true, icon: 'primary', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
    { label: 'Total Orders', value: totalOrders.toString(), change: '+8.2%', up: true, icon: 'success', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>' },
    { label: 'Products', value: totalProducts.toString(), change: '+3', up: true, icon: 'warning', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>' },
    { label: 'Customers', value: totalCustomers.toString(), change: '+5.1%', up: true, icon: 'info', iconSvg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' }
  ];

  statsGrid.innerHTML = stats.map(stat => `
    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">${stat.label}</div>
          <div class="stat-value">${stat.value}</div>
          <div class="stat-change ${stat.up ? 'up' : 'down'}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              ${stat.up ? '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>' : '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>'}
            </svg>
            ${stat.change}
          </div>
        </div>
        <div class="stat-icon ${stat.icon}">${stat.iconSvg}</div>
      </div>
    </div>
  `).join('');
}

/**
 * Render notifications
 */
function renderNotifications() {
  const list = document.getElementById('notificationList');
  if (!list) return;

  const notifications = DataStore.get('notifications') || [];

  list.innerHTML = notifications.map(n => `
    <div class="notification-item ${n.read ? '' : 'unread'}" data-id="${n.id}">
      <div class="notification-icon ${n.type}">
        ${getNotificationIcon(n.type)}
      </div>
      <div class="notification-content">
        <div class="notification-title">${n.title}</div>
        <div class="notification-text">${n.message}</div>
        <div class="notification-time">${n.time}</div>
      </div>
    </div>
  `).join('');
}

function getNotificationIcon(type) {
  const icons = {
    order: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    alert: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    customer: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    payment: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>',
    review: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
  };
  return icons[type] || icons.alert;
}

function markAllRead() {
  const notifications = DataStore.get('notifications') || [];
  notifications.forEach(n => n.read = true);
  DataStore.set('notifications', notifications);
  renderNotifications();
  Toast.success('All notifications marked as read');
}

/**
 * Render recent orders table
 */
function renderRecentOrders() {
  const tbody = document.querySelector('#ordersTable tbody');
  if (!tbody) return;

  const orders = (DataStore.get('orders') || []).slice(0, 8);

  tbody.innerHTML = orders.map(order => `
    <tr>
      <td><strong>${order.id}</strong></td>
      <td>
        <div style="display:flex;align-items:center;gap:0.5rem">
          <div style="width:28px;height:28px;border-radius:50%;background:var(--primary-gradient);display:flex;align-items:center;justify-content:center;color:white;font-size:11px;font-weight:600">
            ${order.customer.split(' ').map(n => n[0]).join('')}
          </div>
          ${order.customer}
        </div>
      </td>
      <td>${order.date}</td>
      <td>${order.items}</td>
      <td><strong>$${order.total.toFixed(2)}</strong></td>
      <td><span class="badge badge-${getStatusColor(order.status)}">${order.status}</span></td>
      <td>
        <div class="table-actions">
          <button class="btn btn-icon btn-sm btn-ghost" onclick="Toast.info('View order details')" title="View">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function getStatusColor(status) {
  const colors = {
    completed: 'success',
    processing: 'warning',
    pending: 'info',
    shipped: 'primary',
    cancelled: 'danger'
  };
  return colors[status] || 'info';
}

/**
 * Initialize Charts
 */
let revenueChartInstance = null;
let categoryChartInstance = null;

function initCharts() {
  initRevenueChart();
  initCategoryChart();
}

function initRevenueChart() {
  const ctx = document.getElementById('revenueChart');
  if (!ctx) return;

  const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
  gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
  gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

  revenueChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [{
        label: 'Revenue',
        data: [1250, 1890, 1500, 2200, 1850, 2800, 2450],
        borderColor: '#6366f1',
        backgroundColor: gradient,
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#6366f1',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 6
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
          callbacks: {
            label: (ctx) => `$${ctx.parsed.y.toLocaleString()}`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: 'var(--text-muted)', font: { size: 11 } }
        },
        y: {
          border: { display: false },
          grid: { color: 'var(--border-color)' },
          ticks: {
            color: 'var(--text-muted)',
            font: { size: 11 },
            callback: (val) => '$' + (val / 1000) + 'k'
          }
        }
      }
    }
  });
}

function initCategoryChart() {
  const ctx = document.getElementById('categoryChart');
  if (!ctx) return;

  categoryChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Electronics', 'Fashion', 'Home', 'Beauty', 'Sports'],
      datasets: [{
        data: [35, 25, 18, 12, 10],
        backgroundColor: [
          '#6366f1',
          '#10b981',
          '#f59e0b',
          '#ef4444',
          '#06b6d4'
        ],
        borderWidth: 0,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: 'var(--text-secondary)',
            font: { size: 11 },
            padding: 16,
            usePointStyle: true,
            pointStyle: 'circle'
          }
        },
        tooltip: {
          backgroundColor: 'rgba(15, 23, 42, 0.9)',
          titleColor: '#fff',
          bodyColor: '#fff',
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: (ctx) => ` ${ctx.label}: ${ctx.parsed}%`
          }
        }
      }
    }
  });
}

function updateRevenueChart(period) {
  if (!revenueChartInstance) return;

  const dataMap = {
    '7D': [1250, 1890, 1500, 2200, 1850, 2800, 2450],
    '30D': [8200, 9500, 11200, 8900, 12400, 10800, 13500],
    '90D': [35000, 42000, 38000, 51000, 48000, 56000, 62000]
  };

  const labelsMap = {
    '7D': ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    '30D': ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
    '90D': ['Month 1', 'Month 2', 'Month 3']
  };

  revenueChartInstance.data.labels = labelsMap[period] || labelsMap['7D'];
  revenueChartInstance.data.datasets[0].data = dataMap[period] || dataMap['7D'];
  revenueChartInstance.update();
}

/**
 * Send team invite
 */
function sendInvite() {
  const email = document.getElementById('inviteEmail')?.value;
  if (!email) {
    Toast.error('Please enter an email address');
    return;
  }
  Modal.close('inviteModal');
  Toast.success(`Invitation sent to ${email}`);
  document.getElementById('inviteEmail').value = '';
}
