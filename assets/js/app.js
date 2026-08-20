// ============================================================
// RD Vendora – MAIN APPLICATION SCRIPT (repaired)
// ============================================================

// ---------- GLOBAL CONFIG ----------
const APP_CONFIG = {
    version: '1.0',
    debug: true
};

// ---------- AUTHENTICATION (Client‑side – for UI only) ----------
// Note: Real authentication is handled by PHP sessions.
// This client‑side auth is only used to show/hide login/logout buttons, etc.
const Auth = {
    // Check if user is logged in (by reading session cookie or localStorage)
    // Since PHP manages session, we can try to read a flag set by PHP.
    // For simplicity, we assume that if the page loads without a login redirect, the user is logged in.
    // But we can also check a meta tag or a global JS variable from PHP.
    isLoggedIn() {
        // You can set a JS variable from PHP in your layout: <script>window.userId = <?= $_SESSION['user_id'] ?? 0 ?>;</script>
        // For now, we check if the body has a data attribute or a global var.
        return window.userId && window.userId > 0;
    },

    // This function is called on pages that require authentication.
    // Instead of redirecting, we let the PHP backend handle it.
    requireAuth() {
        // Optional: show a message if not logged in, but do not redirect (PHP already does).
        if (!this.isLoggedIn()) {
            console.warn('User not logged in (client‑side). PHP will handle redirect.');
        }
    },

    logout() {
        window.location.href='logout';
    }
};

// ---------- UI HELPERS (Sidebar, Topbar) ----------
const UI = {
    // Inject sidebar HTML
    injectSidebar(activePage = '') {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        // Define sidebar links (adjust URLs to match your project)
        const links = [
            { href: 'dashboard', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>', text: 'Dashboard', section: 'Main' },
            { href: 'products', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>', text: 'Products', section: 'Main' },
            { href: 'orders', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>', text: 'Orders', section: 'Main' },
            { href: 'customers', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', text: 'Customers', section: 'Main' },
            { href: 'storefront', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>', text: 'Storefront', section: 'Store' },
            { href: 'store-settings', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>', text: 'Store Settings', section: 'Store' },
            { href: 'settings', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>', text: 'Account Settings', section: 'Account' },
            { href: '#', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>', text: 'Logout', section: 'Account', onclick: 'Auth.logout()' }
        ];

        let currentSection = '';
        let html = '<div class="sidebar-header">\n' +
                   '  <a href="dashboard" class="sidebar-brand">\n' +
                   '    <div class="sidebar-brand-icon"><img src="assets/brand-logo.png" alt="RD Vendora" style="width:100%;height:100%;object-fit:contain;background:#fff;border-radius:6px;"></div>\n' +
                   '    RD Vendora\n' +
                   '  </a>\n' +
                   '  <button class="sidebar-toggle" id="sidebarToggle">◀</button>\n' +
                   '</div>\n' +
                   '<nav class="sidebar-nav">\n';

        for (const link of links) {
            if (link.section !== currentSection) {
                currentSection = link.section;
                html += `<div class="sidebar-section-title">${currentSection}</div>\n`;
            }
            const activeClass = (link.text.toLowerCase() === activePage.toLowerCase()) ? 'active' : '';
            if (link.onclick) {
                html += `<a href="#" class="sidebar-link ${activeClass}" onclick="${link.onclick}">${link.icon}<span class="sidebar-link-text">${link.text}</span></a>\n`;
            } else {
                html += `<a href="${link.href}" class="sidebar-link ${activeClass}">${link.icon}<span class="sidebar-link-text">${link.text}</span></a>\n`;
            }
        }
        html += '</nav>\n' +
                '<div class="sidebar-footer">\n' +
                '  <div class="sidebar-user">\n' +
                '    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" class="sidebar-user-avatar">\n' +
                '    <div>\n' +
                '      <div class="sidebar-user-name">Vendor</div>\n' +
                '      <div class="sidebar-user-role">Store Owner</div>\n' +
                '    </div>\n' +
                '  </div>\n' +
                '</div>';

        sidebar.innerHTML = html;
    },

    // Inject topbar HTML
    injectTopbar() {
        const topbar = document.getElementById('topbar');
        if (!topbar) return;

        topbar.innerHTML = `
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">☰</button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="globalSearch" placeholder="Search...">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggleBtn">🌓</button>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <div class="topbar-user-name">Vendor</div>
                            <div class="topbar-user-role">Store Owner</div>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="profile" class="dropdown-item">Profile</a>
                        <a href="#" class="dropdown-item" onclick="Auth.logout()">Logout</a>
                    </div>
                </div>
            </div>
        `;
    }
};

// ---------- MOCK DATABASE (localStorage) ----------
// Used for demo purposes. Replace with real API calls.
const DB = {
    key: 'rdvendora_db',
    version: '1.0',

    init() {
        if (!localStorage.getItem(this.key)) {
            this.seed();
        }
    },

    get() {
        try {
            return JSON.parse(localStorage.getItem(this.key)) || {};
        } catch {
            return {};
        }
    },

    set(data) {
        localStorage.setItem(this.key, JSON.stringify(data));
    },

    getAll(collection) {
        const data = this.get();
        return data[collection] || [];
    },

    getById(collection, id) {
        const items = this.getAll(collection);
        return items.find(i => i.id == id);
    },

    create(collection, item) {
        const data = this.get();
        if (!data[collection]) data[collection] = [];
        item.id = item.id || this.generateId();
        data[collection].push(item);
        this.set(data);
        return item;
    },

    update(collection, id, updates) {
        const data = this.get();
        const items = data[collection] || [];
        const index = items.findIndex(i => i.id == id);
        if (index !== -1) {
            items[index] = { ...items[index], ...updates };
            this.set(data);
            return items[index];
        }
        return null;
    },

    delete(collection, id) {
        const data = this.get();
        const items = data[collection] || [];
        const filtered = items.filter(i => i.id != id);
        if (filtered.length !== items.length) {
            data[collection] = filtered;
            this.set(data);
            return true;
        }
        return false;
    },

    generateId() {
        return '_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
    },

    seed() {
        const sampleUsers = [
            { id: 1, name: 'Admin', email: 'admin@RD Vendora.com', password: 'admin123', role: 'admin', initials: 'AD', status: 'active' }
        ];
        const sampleOrders = [
            { id: 1, order_number: 'ORD-1001', customer_name: 'John Doe', customer_email: 'john@example.com', total: 245.00, items: [{name: 'Headphones', qty:2, price:99.99}], status: 'delivered', payment_status: 'paid', created_at: '2024-11-25' }
        ];
        const data = {
            version: this.version,
            users: sampleUsers,
            orders: sampleOrders,
            products: [],
            customers: [],
            stores: [],
            carts: {}
        };
        this.set(data);
    }
};

// ---------- TOAST NOTIFICATIONS ----------
const Toast = {
    container: null,

    getContainer() {
        if (!this.container) {
            this.container = document.getElementById('toastContainer');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainer';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        }
        return this.container;
    },

    show(message, type = 'info') {
        const container = this.getContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<div class="toast-content">${message}</div>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    info(message) { this.show(message, 'info'); }
};

// ---------- MODAL DIALOG ----------
const Modal = {
    open(content, options = {}) {
        let modal = document.getElementById('dynamicModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'dynamicModal';
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal">
                    <div class="modal-header">
                        <h3 class="modal-title">${options.title || 'Modal'}</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button class="btn btn-ghost close-btn">Close</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        const modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = content;
        if (options.title) modal.querySelector('.modal-title').innerText = options.title;
        modal.classList.add('active');
        const closeBtn = modal.querySelector('.modal-close');
        const closeFooter = modal.querySelector('.close-btn');
        const closeModal = () => {
            modal.classList.remove('active');
        };
        closeBtn.onclick = closeModal;
        closeFooter.onclick = closeModal;
        modal.onclick = (e) => { if (e.target === modal) closeModal(); };
        document.body.style.overflow = 'hidden';
    },

    close() {
        const modal = document.getElementById('dynamicModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
};

// ---------- INITIALISATION ----------
document.addEventListener('DOMContentLoaded', () => {
    DB.init();
    // If the page has a topbar, inject it (unless it's a login/register page)
    if (document.getElementById('topbar') && !document.body.classList.contains('auth-page')) {
        UI.injectTopbar();
        UI.injectSidebar();
    }
    // Global theme toggle (if present)
    const themeToggle = document.getElementById('themeToggleBtn');
    if (themeToggle) {
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeToggle.innerHTML = savedTheme === 'light' ? '🌙' : '☀️';
        themeToggle.addEventListener('click', () => {
            const cur = html.getAttribute('data-theme');
            const next = cur === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('RD Vendora-theme', next);
            themeToggle.innerHTML = next === 'light' ? '🌙' : '☀️';
        });
    }
    // Global dropdown toggle
    document.addEventListener('click', (e) => {
        const userDropdown = document.getElementById('userDropdown');
        if (userDropdown && !userDropdown.contains(e.target)) {
            userDropdown.classList.remove('open');
        } else if (userDropdown && e.target.closest('.dropdown-trigger')) {
            userDropdown.classList.toggle('open');
        }
    });
});