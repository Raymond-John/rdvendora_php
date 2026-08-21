(function () {
    var nativeAlert = window.alert.bind(window);
    var nativeConfirm = window.confirm.bind(window);
    var modal = document.getElementById('rdvAdminModal');
    var titleEl = document.getElementById('rdvAdminModalTitle');
    var bodyEl = document.getElementById('rdvAdminModalBody');
    var okBtn = document.getElementById('rdvAdminModalOk');
    var cancelBtn = document.getElementById('rdvAdminModalCancel');
    var modalResolver = null;

    function closeAdminModal(result) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('hidden', '');
        var resolve = modalResolver;
        modalResolver = null;
        if (typeof resolve === 'function') resolve(result);
    }

    function showAdminModal(opts) {
        opts = opts || {};
        if (!modal || !titleEl || !bodyEl || !okBtn) {
            if (opts.mode === 'confirm') return Promise.resolve(nativeConfirm(opts.message || ''));
            nativeAlert(opts.message || '');
            return Promise.resolve(true);
        }
        titleEl.textContent = opts.title || (opts.mode === 'confirm' ? 'Please confirm' : 'Notice');
        bodyEl.textContent = opts.message || '';
        okBtn.textContent = opts.okText || 'OK';
        if (cancelBtn) cancelBtn.textContent = opts.cancelText || 'Cancel';
        modal.dataset.mode = opts.mode || 'alert';
        modal.classList.add('is-open');
        modal.removeAttribute('hidden');
        okBtn.focus();
        return new Promise(function (resolve) {
            modalResolver = resolve;
        });
    }

    if (okBtn) okBtn.addEventListener('click', function () { closeAdminModal(true); });
    if (cancelBtn) cancelBtn.addEventListener('click', function () { closeAdminModal(false); });
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeAdminModal(false);
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeAdminModal(false);
        }
    });

    window.rdvAdminAlert = function (message, title) {
        return showAdminModal({ mode: 'alert', message: String(message || ''), title: title || 'Notice' });
    };
    window.rdvAdminConfirm = function (message, title) {
        return showAdminModal({ mode: 'confirm', message: String(message || ''), title: title || 'Please confirm' });
    };

    if (window.RDV_ADMIN_LOGIN_ALERT && window.RDV_ADMIN_LOGIN_ALERT.message) {
        showAdminModal({
            mode: 'alert',
            title: window.RDV_ADMIN_LOGIN_ALERT.title || 'Login alert',
            message: window.RDV_ADMIN_LOGIN_ALERT.message
        });
    }

    function confirmMessageFromAttr(value) {
        if (!value) return null;
        var match = String(value).match(/confirm\s*\(\s*(['"`])([\s\S]*?)\1\s*\)/);
        return match ? match[2].replace(/\\n/g, '\n') : null;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.rdvConfirmed === '1') return;
        var message = confirmMessageFromAttr(form.getAttribute('onsubmit'));
        if (!message) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        showAdminModal({ mode: 'confirm', message: message }).then(function (ok) {
            if (!ok) return;
            form.dataset.rdvConfirmed = '1';
            form.removeAttribute('onsubmit');
            HTMLFormElement.prototype.submit.call(form);
        });
    }, true);

    document.addEventListener('click', function (e) {
        var el = e.target.closest('[onclick]');
        if (!el || el.dataset.rdvConfirmed === '1') return;
        var handler = el.getAttribute('onclick') || '';
        var message = confirmMessageFromAttr(handler);
        if (!message) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        showAdminModal({ mode: 'confirm', message: message }).then(function (ok) {
            if (!ok) return;
            el.dataset.rdvConfirmed = '1';
            el.setAttribute('onclick', handler.replace(/return\s+confirm\s*\(\s*(['"`])([\s\S]*?)\1\s*\)\s*;?/, 'return true;'));
            el.click();
        });
    }, true);

    window.alert = function (message) {
        showAdminModal({ mode: 'alert', message: String(message == null ? '' : message), title: 'Notice' });
    };

    function logout() {
        showAdminModal({
            mode: 'confirm',
            title: 'Sign out',
            message: 'Logout from the admin panel?'
        }).then(function (ok) {
            if (ok) window.location.href = 'admin_logout';
        });
    }
    window.logout = logout;

    const html = document.documentElement;
    const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.innerHTML = savedTheme === 'light' ? '🌙' : '☀️';
        themeToggle.addEventListener('click', function () {
            const newTheme = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('RD Vendora-theme', newTheme);
            themeToggle.innerHTML = newTheme === 'light' ? '🌙' : '☀️';
            if (typeof window.rdvAdminOnThemeChange === 'function') {
                window.rdvAdminOnThemeChange();
            }
        });
    }

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    function closeMobile() {
        if (!sidebar) return;
        sidebar.classList.remove('mobile-open');
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }
    function openMobile() {
        if (!sidebar) return;
        sidebar.classList.add('mobile-open');
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('mobile-open')) closeMobile();
                else openMobile();
            } else {
                sidebar.classList.toggle('collapsed');
      }
    });
  }
    if (mobileToggle) mobileToggle.addEventListener('click', openMobile);
    overlay.addEventListener('click', closeMobile);
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeMobile();
            if (sidebar) sidebar.classList.remove('collapsed');
        }
    });

    const searchInput = document.getElementById('adminSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.data-table tbody tr, #usersTableBody tr').forEach(function (row) {
                if (!row.querySelector('td')) return;
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    const userDD = document.getElementById('userDropdown');
    if (userDD) {
        const trigger = userDD.querySelector('.dropdown-trigger');
        if (trigger) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                userDD.classList.toggle('open');
            });
        }
        document.addEventListener('click', function () {
            userDD.classList.remove('open');
        });
    }
})();
