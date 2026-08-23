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
            var submitter = e.submitter;
            if (submitter && submitter.name && !form.querySelector('input[name="' + submitter.name + '"]')) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value || '1';
                form.appendChild(hidden);
            }
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
    function isMobileNav() {
        return window.matchMedia('(max-width: 768px)').matches;
    }
    function closeMobile() {
        if (!sidebar) return;
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('is-visible');
        overlay.style.display = 'none';
        document.body.style.overflow = '';
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
    }
    function openMobile() {
        if (!sidebar) return;
        sidebar.classList.add('mobile-open');
        overlay.classList.add('is-visible');
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
        if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
    }
    function toggleMobile() {
        if (sidebar && sidebar.classList.contains('mobile-open')) closeMobile();
        else openMobile();
    }
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            if (isMobileNav()) toggleMobile();
            else sidebar.classList.toggle('collapsed');
        });
    }
    if (mobileToggle) {
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileToggle.addEventListener('click', toggleMobile);
    }
    overlay.addEventListener('click', closeMobile);
    if (sidebar) {
        sidebar.querySelectorAll('a.sidebar-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (isMobileNav()) closeMobile();
            });
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('mobile-open')) {
            closeMobile();
        }
    });
    window.addEventListener('resize', function () {
        if (!isMobileNav()) {
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
