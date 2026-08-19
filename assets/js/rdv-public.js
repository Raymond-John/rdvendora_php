(function () {
  var CONSENT_KEY = 'rdv_cookie_consent';
  var FOOTER_HTML = null;

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function getConsent() {
    try {
      var raw = localStorage.getItem(CONSENT_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      return parsed;
    } catch (e) { return null; }
  }

  function saveConsent(prefs) {
    var payload = {
      necessary: true,
      analytics: !!prefs.analytics,
      advertising: !!prefs.advertising,
      updatedAt: new Date().toISOString()
    };
    localStorage.setItem(CONSENT_KEY, JSON.stringify(payload));
    localStorage.removeItem('RD Vendora_cookies_accepted');
    maybeLoadAds(payload);
    maybeLoadAnalytics(payload);
    return payload;
  }

  function maybeLoadAds(consent) {
    consent = consent || getConsent();
    if (!consent || !consent.advertising) {
      document.cookie = 'rdv_ad_consent=0; path=/; max-age=31536000; SameSite=Lax';
      return;
    }
    document.cookie = 'rdv_ad_consent=1; path=/; max-age=31536000; SameSite=Lax';
    if (document.querySelector('script[src*="pagead2.googlesyndication.com"]')) {
      qsa('ins.adsbygoogle').forEach(function () {
        try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {}
      });
      return;
    }
    if (!window.rdvAdsenseClient) return;
    if (document.getElementById('rdv-adsense-script')) {
      try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {}
      return;
    }
    var s = document.createElement('script');
    s.id = 'rdv-adsense-script';
    s.async = true;
    s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(window.rdvAdsenseClient);
    s.crossOrigin = 'anonymous';
    s.onload = function () {
      qsa('ins.adsbygoogle').forEach(function () {
        try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (err) {}
      });
    };
    document.head.appendChild(s);
  }

  function maybeLoadAnalytics(consent) {
    consent = consent || getConsent();
    if (!window.rdvGaId) return;
    if (!consent || !consent.analytics) {
      document.cookie = 'rdv_ga_consent=0; path=/; max-age=31536000; SameSite=Lax';
      return;
    }
    document.cookie = 'rdv_ga_consent=1; path=/; max-age=31536000; SameSite=Lax';
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { dataLayer.push(arguments); };
    if (document.getElementById('rdv-ga-script') || document.querySelector('script[src*="googletagmanager.com/gtag/js"]')) {
      gtag('event', 'page_view');
      return;
    }
    var s = document.createElement('script');
    s.id = 'rdv-ga-script';
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.rdvGaId);
    s.onload = function () {
      gtag('js', new Date());
      gtag('config', window.rdvGaId, { anonymize_ip: true });
    };
    document.head.appendChild(s);
  }

  function cookieUi() {
    if (qs('#rdv-cookie-banner')) return;
    var root = qs('#rdv-cookie-root') || document.body;
    var existing = getConsent();
    var manage = document.createElement('button');
    manage.type = 'button';
    manage.className = 'btn btn-ghost btn-sm rdv-manage-cookies';
    manage.textContent = 'Cookie settings';
    manage.addEventListener('click', function () { showBanner(true); });
    if (!qs('.rdv-manage-cookies')) document.body.appendChild(manage);
    if (existing) {
      maybeLoadAds(existing);
      maybeLoadAnalytics(existing);
      return;
    }
    showBanner(false);
  }

  function showBanner(forcePrefs) {
    var old = qs('#rdv-cookie-banner');
    if (old) old.remove();
    var wrap = document.createElement('div');
    wrap.id = 'rdv-cookie-banner';
    wrap.className = 'rdv-cookie';
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-labelledby', 'rdv-cookie-title');
    wrap.innerHTML = '<h2 id="rdv-cookie-title">Cookies on RD Vendora</h2>' +
      '<p>We use necessary cookies to run the site (for example sign-in sessions). Optional cookies are used only if you choose them: analytics (Google Analytics, to understand which public pages are used) and advertising (Google AdSense). Read the <a href="cookies.php">Cookie Policy</a> and <a href="privacy.php">Privacy Policy</a>.</p>' +
      '<div class="rdv-cookie-actions">' +
      '<button type="button" class="btn btn-primary" data-act="accept">Accept optional cookies</button>' +
      '<button type="button" class="btn btn-outline" data-act="reject">Necessary only</button>' +
      '<button type="button" class="btn btn-ghost" data-act="manage">Manage choices</button>' +
      '</div>' +
      '<div class="rdv-cookie-prefs" hidden>' +
      '<label><input type="checkbox" checked disabled> Necessary (always on)</label>' +
      '<label><input type="checkbox" name="analytics"> Analytics</label>' +
      '<label><input type="checkbox" name="advertising"> Advertising</label>' +
      '<button type="button" class="btn btn-primary" data-act="save">Save choices</button>' +
      '</div>';
    (qs('#rdv-cookie-root') || document.body).appendChild(wrap);
    wrap.addEventListener('click', function (e) {
      var act = e.target.getAttribute('data-act');
      if (act === 'accept') { saveConsent({ analytics: true, advertising: true }); wrap.remove(); }
      if (act === 'reject') { saveConsent({ analytics: false, advertising: false }); wrap.remove(); }
      if (act === 'manage') { qs('.rdv-cookie-prefs', wrap).hidden = false; }
      if (act === 'save') {
        saveConsent({
          analytics: !!qs('input[name="analytics"]', wrap).checked,
          advertising: !!qs('input[name="advertising"]', wrap).checked
        });
        wrap.remove();
      }
    });
    if (forcePrefs) qs('.rdv-cookie-prefs', wrap).hidden = false;
  }

    function parseJsonResponse(r) {
      return r.text().then(function (text) {
        try {
          return JSON.parse(text);
        } catch (err) {
          return { success: false, message: 'Could not subscribe right now. Please refresh and try again.' };
        }
      });
    }

    function newsletterUrl(form, file) {
      if (form && form.action) return form.action.replace(/[^/]+$/, file);
      try {
        return new URL(file, window.location.href).href;
      } catch (err) {
        return file;
      }
    }

    function bindNewsletter(form) {
    if (!form || form.dataset.bound) return;
    form.dataset.bound = '1';
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var status = qs('.rdv-newsletter-status', form);
      var btn = qs('button[type="submit"]', form);
      if (status) { status.textContent = 'Sending…'; status.className = 'rdv-newsletter-status'; }
      if (btn) btn.disabled = true;
      var send = function (token) {
        var data = new FormData(form);
        if (token && !data.get('csrf_token')) data.append('csrf_token', token);
        fetch(newsletterUrl(form, 'newsletter-subscribe.php'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(parseJsonResponse)
          .then(function (json) {
            if (status) {
              status.textContent = (json && json.message) || 'Done.';
              status.classList.add(json && json.success ? 'is-ok' : 'is-err');
            }
            if (json && json.success) form.reset();
          })
          .catch(function () {
            if (status) { status.textContent = 'Could not reach the server. Please try again.'; status.classList.add('is-err'); }
          })
          .then(function () { if (btn) btn.disabled = false; });
      };
      if (form.querySelector('input[name="csrf_token"]')) {
        send(null);
      } else {
        fetch(newsletterUrl(form, 'csrf-token.php'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(parseJsonResponse)
          .then(function (j) { send(j && j.csrf_token ? j.csrf_token : null); })
          .catch(function () { send(null); });
      }
    });
  }

  function enhanceNav() {
    qsa('#navbar-nav').forEach(function (nav) {
      if (nav.querySelector('a[href="blog.php"]')) return;
      var about = nav.querySelector('a[href="about.php"]');
      var link = document.createElement('a');
      link.href = 'blog.php';
      link.className = 'nav-link';
      link.textContent = 'News';
      if (about) nav.insertBefore(link, about);
      else nav.appendChild(link);
    });
  }

  function enhanceFooter() {
    qsa('#footer .footer-links a, .mobile-menu-footer a').forEach(function (a) {
      var t = (a.textContent || '').trim().toLowerCase();
      if (a.getAttribute('href') === '#' || t === 'privacy') a.setAttribute('href', 'privacy.php');
      if (t === 'terms' || t === 'terms of service') a.setAttribute('href', 'terms.php');
      if (t === 'cookies') a.setAttribute('href', 'cookies.php');
      if (t === 'security') a.setAttribute('href', 'disclaimer.php');
      if (t === 'careers' || t === 'changelog') a.setAttribute('href', 'about.php');
    });
    var legal = qs('#footer .footer-column:last-child .footer-links');
    if (legal && !legal.querySelector('a[href="community-guidelines.php"]')) {
      ['privacy.php|Privacy Policy', 'terms.php|Terms', 'cookies.php|Cookie Policy', 'disclaimer.php|Disclaimer', 'community-guidelines.php|Community Guidelines', 'sitemap.php|Sitemap'].forEach(function (pair) {
        var parts = pair.split('|');
        if (!legal.querySelector('a[href="' + parts[0] + '"]')) {
          var a = document.createElement('a');
          a.href = parts[0];
          a.textContent = parts[1];
          legal.appendChild(a);
        }
      });
    }
    var company = qsa('#footer .footer-column')[2];
    if (company && !qs('a[href="blog.php"]', company)) {
      var links = qs('.footer-links', company);
      if (links) {
        var b = document.createElement('a');
        b.href = 'blog.php';
        b.textContent = 'News';
        links.appendChild(b);
      }
    }
    if (!qs('#footer .rdv-newsletter-form')) {
      var brand = qs('#footer .footer-brand');
      if (brand) {
        var box = document.createElement('div');
        box.className = 'rdv-footer-newsletter';
        box.innerHTML = '<h2 class="rdv-footer-heading">Newsletter</h2><p>Subscribe to the RD Vendora newsletter to receive updates, useful business resources, platform news, and other relevant information.</p>' +
          '<form class="rdv-newsletter-form" method="post" action="newsletter-subscribe.php"><input type="text" name="website" class="rdv-hp" tabindex="-1" autocomplete="off" aria-hidden="true"><label class="rdv-sr-only" for="rdv-js-nl-email">Email</label><input id="rdv-js-nl-email" name="email" type="email" required placeholder="Email address"><label class="rdv-consent"><input type="checkbox" name="consent" value="1" required><span>I want emails about RD Vendora news and resources. I can unsubscribe anytime.</span></label><button type="submit" class="btn btn-primary">Subscribe</button><p class="rdv-newsletter-status" role="status"></p></form>';
        brand.appendChild(box);
      }
    }
  }

  function mobileMenu() {
    var toggle = qs('#mobile-menu-toggle');
    var overlay = qs('#rdv-mobile-overlay');
    if (!toggle || !overlay) return;
    overlay.hidden = false;
    overlay.classList.add('mobile-overlay');
    toggle.addEventListener('click', function () {
      var open = overlay.classList.toggle('active');
      toggle.classList.toggle('active', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
  }

  function faq() {
    qsa('.rdv-faq-item button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var item = btn.closest('.rdv-faq-item');
        var open = !item.classList.contains('is-open');
        qsa('.rdv-faq-item').forEach(function (el) { el.classList.remove('is-open'); qs('button', el).setAttribute('aria-expanded', 'false'); });
        if (open) { item.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true'); }
      });
    });
  }

  function hideOldCookieBanners() {
    qsa('#cookie-banner').forEach(function (el) { el.remove(); });
  }

  function init() {
    hideOldCookieBanners();
    enhanceNav();
    enhanceFooter();
    qsa('.rdv-newsletter-form').forEach(bindNewsletter);
    cookieUi();
    mobileMenu();
    faq();
    maybeLoadAds();
    maybeLoadAnalytics();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); setTimeout(init, 80); });
  } else {
    init();
    setTimeout(init, 80);
  }
})();
