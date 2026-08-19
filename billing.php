<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <?php require __DIR__ . '/includes/adsense_head.php'; ?>
  <title>Pricing - RD Vendora</title>
  <meta name="description" content="Simple, transparent pricing for RD Vendora. Start free and scale as you grow.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/animations.css">
  <link rel="stylesheet" href="assets/css/public-extras.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <style>
    /* ============================================================
       RD Vendora - Responsive Styles (same as index)
       ============================================================ */
    @media (max-width: 1024px) {
      .container { padding: 0 var(--space-4); }
      .hero { padding: 120px 0 80px; }
      .hero-title { font-size: var(--text-4xl); }
      .feature-grid { grid-template-columns: repeat(2, 1fr); }
      .pricing-grid { grid-template-columns: repeat(2, 1fr); }
      .testimonial-grid { grid-template-columns: repeat(2, 1fr); }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
      .dashboard-grid-4 { grid-template-columns: repeat(2, 1fr); }
      .footer-grid { grid-template-columns: repeat(2, 1fr); gap: var(--space-8); }
      .sidebar { transform: translateX(-100%); }
      .sidebar.mobile-open { transform: translateX(0); }
      .main-content { margin-left: 0 !important; }
      .topbar-search { display: none; }
    }
    @media (max-width: 767px) {
      :root { --text-5xl: 2.5rem; --text-4xl: 2rem; --text-3xl: 1.75rem; --text-2xl: 1.5rem; }
      .container { padding: 0 var(--space-4); }
      .navbar-inner { padding: 0 var(--space-4); }
      .navbar-nav { display: none !important; }
      .mobile-menu-toggle { display: flex !important; }
      .nav-link { padding: var(--space-3) var(--space-4); width: 100%; }
      .navbar-actions .btn { display: none; }
      .navbar-actions .btn-icon { display: inline-flex; }
      .hero { padding: 100px 0 60px; }
      .hero-title { font-size: var(--text-3xl); }
      .hero-description { font-size: var(--text-base); }
      .hero-actions { flex-direction: column; align-items: stretch; }
      .hero-actions .btn { width: 100%; justify-content: center; }
      .hero-stats { flex-direction: column; gap: var(--space-6); align-items: center; text-align: center; }
      .hero-stat { text-align: center; }
      .feature-grid, .pricing-grid, .testimonial-grid, .theme-grid, .stats-grid, .dashboard-grid, .dashboard-grid-2, .dashboard-grid-4 { grid-template-columns: 1fr; }
      .pricing-card.popular { transform: none; }
      .section { padding: var(--space-12) 0; }
      .section-title { font-size: var(--text-2xl); }
      .cta-content { padding: var(--space-8) var(--space-4); }
      .cta-title { font-size: var(--text-2xl); }
      .cta-actions { flex-direction: column; }
      .footer-grid { grid-template-columns: 1fr; text-align: center; }
      .footer-brand { max-width: 100%; }
      .footer-bottom { flex-direction: column; gap: var(--space-4); text-align: center; }
      .dashboard-content { padding: var(--space-4); }
      .dashboard-header-row, .filters-bar, .filters-left, .filters-right, .quick-actions { flex-direction: column; align-items: stretch; }
      .search-box, .quick-action-btn { width: 100%; }
      .chart-container { padding: var(--space-4); }
      .chart-header { flex-direction: column; align-items: flex-start; }
      .chart-wrapper { height: 220px; }
      .data-table { font-size: var(--text-xs); }
      .data-table thead th, .data-table tbody td { padding: var(--space-2) var(--space-3); }
      .auth-visual { display: none; }
      .auth-form-side { padding: var(--space-6) var(--space-4); }
      .product-grid { grid-template-columns: repeat(2, 1fr) !important; gap: var(--space-3) !important; }
      .cart-drawer { width: 100%; }
      .search-overlay { padding-top: 80px; }
      .search-modal { margin: 0 var(--space-4); }
      .stepper { overflow-x: auto; padding-bottom: var(--space-2); }
      .step-label { display: none; }
      .step-connector { width: 20px; }
      .onboarding-header, .onboarding-footer { padding: var(--space-4); }
      .onboarding-step { padding: var(--space-8) var(--space-4); }
      .onboarding-footer { flex-direction: column; gap: var(--space-3); }
      .onboarding-footer .btn { width: 100%; justify-content: center; }
      .topbar { padding: 0 var(--space-4); }
      .topbar-user-name, .topbar-user-role { display: none; }
      .store-hero { padding: 80px 0 40px !important; }
      .product-detail-grid { grid-template-columns: 1fr !important; }
      .tabs { overflow-x: auto; width: 100%; }
      .tab-btn { white-space: nowrap; }
      .campaign-stats { grid-template-columns: 1fr; }
      .campaign-stat { border-right: none; border-bottom: 1px solid var(--border-primary); }
      .campaign-stat:last-child { border-bottom: none; }
      .error-code { font-size: 80px; }
      .success-card { padding: var(--space-8) var(--space-4); }
      .modal-container { margin: var(--space-4); max-height: calc(100vh - var(--space-8)); }
      .breadcrumb { flex-wrap: wrap; }
      .hero-grid { grid-template-columns: 1fr !important; gap: 32px !important; }
      .hero-grid > :first-child { order: -1; }
      .hero-dashboard-preview { transform: none !important; margin: 0 auto; }
      .floating-card-left, .floating-card-right { position: relative !important; left: auto !important; right: auto !important; top: auto !important; bottom: auto !important; margin: 10px 0; }
    }
    @media (max-width: 480px) {
      :root { --text-5xl: 2rem; --text-4xl: 1.75rem; --text-3xl: 1.5rem; --text-2xl: 1.375rem; }
      .hero { padding: 80px 0 40px !important; }
      .hero-title { font-size: 1.8rem !important; }
      .hero-actions .btn { padding: 12px 20px; font-size: 0.875rem; }
      .hero-stats { gap: var(--space-4); }
      .brand-logos { gap: 24px !important; }
      .brand-logo-item { font-size: 16px !important; font-weight: 600; }
      .pricing-grid, .testimonial-grid, .feature-grid { gap: var(--space-4); }
      .cta-title { font-size: 1.6rem; }
    }
    @media (min-width: 768px) {
      .mobile-menu-toggle { display: none !important; }
      .navbar-nav { display: flex !important; }
    }
    @media (min-width: 1281px) {
      .container { max-width: var(--container-wide); }
      .stats-grid { grid-template-columns: repeat(4, 1fr); }
      .dashboard-grid-4 { grid-template-columns: repeat(4, 1fr); }
    }
    @media print {
      .sidebar, .topbar, .navbar, .footer, .btn, .no-print { display: none !important; }
      .main-content { margin-left: 0 !important; }
      .dashboard-content { padding: 0; }
      body { background: white; color: black; }
      .card, .chart-container, .stat-card { border: 1px solid #ddd; box-shadow: none; break-inside: avoid; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
      html { scroll-behavior: auto; }
      .reveal, .reveal-left, .reveal-right, .reveal-scale { opacity: 1 !important; transform: none !important; }
    }

    /* ============================================================
       RD Vendora - Core Design System (Blue, Gold, White, Black)
       ============================================================ */
    :root {
      --bg-primary: #ffffff; --bg-secondary: #f9fafb; --bg-tertiary: #f3f4f6;
      --bg-elevated: #ffffff; --bg-hover: #e5e7eb; --bg-active: #d1d5db;
      --surface-primary: #ffffff; --surface-secondary: #f9fafb; --surface-tertiary: #f3f4f6;
      --text-primary: #111827; --text-secondary: #4b5563; --text-muted: #6b7280;
      --text-inverse: #ffffff; --border-primary: #e5e7eb; --border-secondary: #d1d5db;
      --border-focus: #2563eb; --primary: #2563eb; --primary-hover: #1d4ed8;
      --primary-light: #dbeafe; --primary-dark: #1e3a8a;
      --success: #10b981; --success-light: #d1fae5; --success-dark: #047857;
      --warning: #f59e0b; --warning-light: #fef3c7; --warning-dark: #b45309;
      --error: #ef4444; --error-light: #fee2e2; --error-dark: #b91c1c;
      --info: #3b82f6; --info-light: #dbeafe; --info-dark: #1d4ed8;
      --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      --gradient-hero: linear-gradient(135deg, #1e3a8a 0%, #f59e0b 100%);
      --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --text-xs: 0.75rem; --text-sm: 0.8125rem; --text-base: 0.9375rem;
      --text-lg: 1.125rem; --text-xl: 1.25rem; --text-2xl: 1.5rem;
      --text-3xl: 2rem; --text-4xl: 2.5rem; --text-5xl: 3.5rem;
      --font-normal: 400; --font-medium: 500; --font-semibold: 600; --font-bold: 700;
      --space-0: 0; --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem;
      --space-4: 1rem; --space-5: 1.25rem; --space-6: 1.5rem; --space-8: 2rem;
      --space-10: 2.5rem; --space-12: 3rem; --space-16: 4rem; --space-20: 5rem; --space-24: 6rem;
      --radius-sm: 6px; --radius-md: 10px; --radius-lg: 16px; --radius-xl: 20px; --radius-2xl: 28px; --radius-full: 9999px;
      --shadow-xs: 0 1px 2px rgba(0,0,0,0.04); --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);
      --shadow-lg: 0 8px 24px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
      --shadow-xl: 0 16px 48px rgba(0,0,0,0.10), 0 8px 16px rgba(0,0,0,0.04);
      --shadow-glow: 0 0 40px rgba(37,99,235,0.15); --shadow-glow-sm: 0 0 20px rgba(37,99,235,0.10);
      --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
      --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
      --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
      --topbar-height: 64px; --container-max: 1280px; --container-wide: 1440px;
    }
    [data-theme="dark"] {
      --bg-primary: #0c0e14; --bg-secondary: #14161f; --bg-tertiary: #1a1d28;
      --bg-elevated: #1e2130; --bg-hover: #242838; --bg-active: #2a2e40;
      --surface-primary: #14161f; --surface-secondary: #1a1d28; --surface-tertiary: #1e2130;
      --text-primary: #e8eaf0; --text-secondary: #9ca3b0; --text-muted: #6b7280;
      --text-inverse: #1a1d23; --border-primary: #2d3139; --border-secondary: #3a3f4a;
      --primary: #3b82f6; --primary-hover: #60a5fa; --primary-light: rgba(59,130,246,0.15); --primary-dark: #1e3a8a;
      --warning: #f59e0b; --warning-light: rgba(245,158,11,0.15); --warning-dark: #b45309;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; }
    body { font-family: var(--font-sans); font-size: var(--text-base); line-height: 1.5; color: var(--text-primary); background: var(--bg-primary); min-height: 100vh; overflow-x: hidden; }
    img { max-width: 100%; height: auto; display: block; }
    a { color: inherit; text-decoration: none; transition: color var(--transition-fast); }
    button { cursor: pointer; border: none; background: none; font-family: inherit; font-size: inherit; }
    input, textarea, select { font-family: inherit; font-size: inherit; color: inherit; }
    ul, ol { list-style: none; }

    /* Utility classes */
    .container { width: 100%; max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); }
    .flex { display: flex; } .grid { display: grid; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: var(--space-2); padding: var(--space-3) var(--space-5); font-size: var(--text-sm); font-weight: var(--font-medium); border-radius: var(--radius-md); transition: all var(--transition-fast); cursor: pointer; border: 1px solid transparent; }
    .btn-primary { background: var(--gradient-primary); color: var(--text-inverse); box-shadow: 0 2px 8px rgba(37,99,235,0.25); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(37,99,235,0.35); }
    .btn-lg { padding: var(--space-4) var(--space-8); font-size: var(--text-base); }
    .gradient-text { background: var(--gradient-hero); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .card { background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-lg); }

    /* ========== GLASSMORPHIC FOOTER ========== */
    .footer-glass { background: rgba(255,255,255,0.65) !important; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); border-top: 1px solid rgba(0,0,0,0.08); color: var(--text-primary) !important; padding: var(--space-16) 0 var(--space-8); position: relative; z-index: 1; }
    [data-theme="dark"] .footer-glass { background: rgba(20,22,31,0.7) !important; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); border-top: 1px solid rgba(255,255,255,0.06); }
    .footer-glass .footer-grid { margin-bottom: var(--space-8); }
    .footer-glass .footer-brand-desc { color: var(--text-secondary); max-width: 320px; }
    .footer-glass .footer-column h4 { font-size: var(--text-sm); font-weight: var(--font-semibold); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--space-4); color: var(--text-primary); }
    .footer-glass .footer-links a { display: block; padding: 4px 0; font-size: var(--text-sm); color: var(--text-secondary); transition: color var(--transition-fast); }
    .footer-glass .footer-links a:hover { color: var(--primary); }
    .footer-glass .footer-bottom { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-primary); padding-top: var(--space-6); font-size: var(--text-xs); color: var(--text-muted); }
    .footer-glass .footer-social a { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: var(--radius-full); background: var(--bg-tertiary); color: var(--text-secondary); font-weight: var(--font-medium); transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease; margin-left: var(--space-4); }
    .footer-glass .footer-social a:hover { background: var(--primary); color: #fff; transform: translateY(-2px); }
    .footer-glass .footer-social a svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
    @media (max-width: 767px) { .footer-glass .footer-bottom { flex-direction: column; gap: var(--space-4); text-align: center; } .footer-glass .footer-social { margin-top: var(--space-4); } }

    /* ========== WORLD-CLASS MOBILE MENU OVERLAY ========== */
    .mobile-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.92); backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%); z-index: 9998; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 0.4s ease, visibility 0.4s ease; }
    [data-theme="dark"] .mobile-overlay { background: rgba(14,16,22,0.92); }
    .mobile-overlay.active { opacity: 1; visibility: visible; }
    .mobile-overlay .menu-close { position: absolute; top: 24px; right: 24px; width: 48px; height: 48px; border-radius: var(--radius-full); background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; transition: transform 0.3s ease, background 0.2s; z-index: 2; }
    .mobile-overlay .menu-close:hover { transform: rotate(90deg); background: var(--bg-hover); }
    .mobile-overlay .menu-close svg { width: 20px; height: 20px; stroke: var(--text-primary); stroke-width: 2.5; }
    .mobile-menu-brand { position: absolute; top: 32px; left: 24px; display: flex; align-items: center; gap: 8px; font-weight: var(--font-bold); font-size: var(--text-lg); color: var(--text-primary); z-index: 2; }
    .mobile-menu-brand .brand-icon { width: 24px; height: 24px; }
    .mobile-nav-links { display: flex; flex-direction: column; align-items: center; gap: var(--space-8); margin-top: -40px; }
    .mobile-nav-link { font-size: 2.5rem; font-weight: var(--font-bold); color: var(--text-primary); opacity: 0; transform: translateY(20px); transition: opacity 0.4s ease, transform 0.4s ease, color 0.2s; }
    .mobile-overlay.active .mobile-nav-link { opacity: 1; transform: translateY(0); }
    .mobile-nav-link:hover { color: var(--primary); }
    .mobile-nav-link:nth-child(1) { transition-delay: 0.1s; } .mobile-nav-link:nth-child(2) { transition-delay: 0.2s; } .mobile-nav-link:nth-child(3) { transition-delay: 0.3s; } .mobile-nav-link:nth-child(4) { transition-delay: 0.4s; } .mobile-nav-link:nth-child(5) { transition-delay: 0.5s; } .mobile-nav-link:nth-child(6) { transition-delay: 0.6s; }
    .mobile-menu-footer { position: absolute; bottom: 40px; display: flex; gap: var(--space-6); opacity: 0; transition: opacity 0.5s ease 0.6s; }
    .mobile-overlay.active .mobile-menu-footer { opacity: 1; }
    .mobile-menu-footer a { font-size: var(--text-sm); color: var(--text-muted); transition: color 0.2s; }
    .mobile-menu-footer a:hover { color: var(--primary); }
    .mobile-menu-toggle span { display: block; width: 20px; height: 2px; background: var(--text-primary); transition: transform 0.3s ease, opacity 0.2s ease; }
    .mobile-menu-toggle.active span:nth-child(1) { transform: translateY(6px) rotate(45deg); }
    .mobile-menu-toggle.active span:nth-child(2) { opacity: 0; }
    .mobile-menu-toggle.active span:nth-child(3) { transform: translateY(-6px) rotate(-45deg); }

    /* ========== SCROLL TO TOP BUTTON ========== */
    #scroll-to-top { position: fixed; bottom: 90px; right: 20px; width: 50px; height: 50px; border-radius: var(--radius-full); background: var(--bg-secondary); border: 1px solid var(--border-primary); box-shadow: var(--shadow-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 998; opacity: 0; visibility: hidden; transform: translateY(10px); transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease; }
    #scroll-to-top.visible { opacity: 1; visibility: visible; transform: translateY(0); }
    #scroll-to-top:hover { border-color: var(--primary); box-shadow: var(--shadow-glow); transform: translateY(-3px) scale(1.05); }
    .progress-ring-container { position: relative; width: 100%; height: 100%; }
    .progress-ring { transform: rotate(-90deg); width: 100%; height: 100%; }
    .progress-ring-bg { fill: none; stroke: var(--border-primary); stroke-width: 3; }
    .progress-ring-fill { fill: none; stroke: url(#scrollGradient); stroke-width: 3; stroke-linecap: round; transition: stroke-dashoffset 0.1s linear; }
    .progress-percentage { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: var(--text-xs); font-weight: var(--font-bold); color: var(--primary); line-height: 1; }
    @media (max-width: 480px) { #scroll-to-top { bottom: 130px; right: 12px; width: 42px; height: 42px; } .progress-percentage { font-size: 0.65rem; } }

    /* ========== AI CHATBOT WIDGET ========== */
    .chatbot-toggle { position: fixed; bottom: 10px; right: 20px; width: 50px; height: 50px; border-radius: 50%; background: var(--gradient-primary); color: #fff; box-shadow: var(--shadow-lg); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 997; transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .chatbot-toggle:hover { transform: scale(1.1); box-shadow: var(--shadow-glow); }
    .chatbot-toggle svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 2; }
    .chatbot-panel { position: fixed; bottom: 60px; right: 20px; width: 360px; max-width: calc(100vw - 40px); height: 500px; max-height: calc(100vh - 160px); background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); display: flex; flex-direction: column; z-index: 996; opacity: 0; visibility: hidden; transform: translateY(20px); transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease; }
    .chatbot-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }
    .chatbot-header { padding: var(--space-4); border-bottom: 1px solid var(--border-primary); display: flex; align-items: center; justify-content: space-between; font-weight: var(--font-semibold); color: var(--text-primary); background: var(--bg-primary); border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
    .chatbot-header button { width: 28px; height: 28px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .chatbot-header button:hover { background: var(--bg-hover); }
    .chatbot-messages { flex: 1; overflow-y: auto; padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3); }
    .chat-message { max-width: 85%; padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: var(--text-sm); line-height: 1.5; word-wrap: break-word; }
    .chat-message.bot { background: var(--bg-tertiary); color: var(--text-primary); align-self: flex-start; border-bottom-left-radius: 4px; }
    .chat-message.user { background: var(--gradient-primary); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
    .chatbot-input-area { padding: var(--space-3) var(--space-4); border-top: 1px solid var(--border-primary); display: flex; gap: var(--space-2); background: var(--bg-primary); border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
    .chatbot-input-area input { flex: 1; border: 1px solid var(--border-primary); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: var(--text-sm); outline: none; background: var(--bg-secondary); color: var(--text-primary); transition: border-color 0.2s; }
    .chatbot-input-area input:focus { border-color: var(--primary); }
    .chatbot-input-area button { background: var(--gradient-primary); color: #fff; border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-weight: var(--font-medium); font-size: var(--text-sm); transition: opacity 0.2s; }
    .chatbot-input-area button:hover { opacity: 0.9; }
    @media (max-width: 480px) { .chatbot-toggle { bottom: 70px; right: 12px; width: 42px; height: 42px; } .chatbot-panel { bottom: 50px; right: 12px; width: calc(100vw - 24px); height: 420px; max-height: calc(100vh - 140px); } }

    /* ========== COOKIE CONSENT BANNER ========== */
    .cookie-banner { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--bg-secondary); border-top: 1px solid var(--border-primary); box-shadow: var(--shadow-xl); padding: var(--space-4) var(--space-6); display: flex; align-items: center; justify-content: space-between; gap: var(--space-4); z-index: 990; transform: translateY(100%); transition: transform 0.4s ease; font-size: var(--text-sm); color: var(--text-primary); }
    .cookie-banner.visible { transform: translateY(0); }
    .cookie-banner p { flex: 1; margin: 0; line-height: 1.5; }
    .cookie-banner a { color: var(--primary); font-weight: var(--font-medium); text-decoration: underline; }
    .cookie-banner .btn-accept { background: var(--gradient-primary); color: #fff; border: none; padding: var(--space-2) var(--space-4); border-radius: var(--radius-md); font-weight: var(--font-medium); font-size: var(--text-sm); cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
    .cookie-banner .btn-accept:hover { opacity: 0.9; }
    @media (max-width: 767px) { .cookie-banner { flex-direction: column; align-items: flex-start; text-align: center; } .cookie-banner .btn-accept { width: 100%; } }

    /* ========== REMITA PAYMENT MODAL STYLES (Enhanced Responsive) ========== */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
      padding: var(--space-4);
    }
    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .payment-modal {
      background: var(--bg-secondary);
      border-radius: var(--radius-xl);
      max-width: 500px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: var(--shadow-xl);
      border: 1px solid var(--border-primary);
      transform: scale(0.95);
      transition: transform 0.3s ease;
    }
    .modal-overlay.active .payment-modal {
      transform: scale(1);
    }
    .modal-header {
      padding: var(--space-5) var(--space-6);
      border-bottom: 1px solid var(--border-primary);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      background: var(--bg-secondary);
      z-index: 1;
    }
    .modal-header h3 {
      font-size: var(--text-xl);
      font-weight: var(--font-bold);
    }
    .modal-close {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--bg-tertiary);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.2s;
    }
    .modal-close:hover {
      background: var(--bg-hover);
    }
    .modal-body {
      padding: var(--space-6);
    }
    .form-group {
      margin-bottom: var(--space-4);
    }
    .form-group label {
      display: block;
      margin-bottom: var(--space-2);
      font-weight: var(--font-medium);
      font-size: var(--text-sm);
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: var(--space-3);
      border: 1px solid var(--border-primary);
      border-radius: var(--radius-md);
      background: var(--bg-primary);
      color: var(--text-primary);
      font-size: var(--text-base);
      transition: border-color 0.2s;
    }
    .form-group input:focus, .form-group select:focus {
      outline: none;
      border-color: var(--primary);
    }
    .plan-summary {
      background: var(--bg-tertiary);
      padding: var(--space-4);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-6);
      font-size: var(--text-sm);
    }
    .plan-summary p {
      margin: var(--space-1) 0;
    }
    .remita-note {
      font-size: var(--text-xs);
      color: var(--text-muted);
      margin-top: var(--space-4);
      text-align: center;
    }
    .btn-pay {
      width: 100%;
      background: var(--gradient-primary);
      color: white;
      padding: var(--space-3);
      font-weight: var(--font-semibold);
      margin-top: var(--space-2);
      font-size: var(--text-base);
    }
    .btn-pay:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .loader-spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 0.6s linear infinite;
      margin-right: 8px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Additional responsive refinements for modal on small screens */
    @media (max-width: 480px) {
      .payment-modal {
        max-width: 100%;
        border-radius: var(--radius-lg);
      }
      .modal-header {
        padding: var(--space-4);
      }
      .modal-header h3 {
        font-size: var(--text-lg);
      }
      .modal-body {
        padding: var(--space-4);
      }
      .form-group input, .form-group select {
        padding: 12px;
        font-size: 16px; /* Prevents zoom on iOS */
      }
      .modal-close {
        width: 40px;
        height: 40px;
      }
      .btn-pay {
        padding: 14px;
        font-size: 16px;
      }
      .plan-summary {
        padding: var(--space-3);
      }
    }

    /* Improve pricing card buttons on mobile */
    @media (max-width: 767px) {
      .pricing-card .btn {
        width: 100%;
        padding: 12px;
        font-size: 1rem;
      }
      .tabs {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 8px;
        -webkit-overflow-scrolling: touch;
      }
      .tab-btn {
        flex-shrink: 0;
        padding: 10px 20px;
      }
      .section-title {
        font-size: 1.8rem;
      }
    }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <header class="navbar glass" id="navbar"></header>

  <!-- PRICING CONTENT -->
  <section style="padding: 140px 0 80px;">
    <div class="container">
      <div class="section-header reveal">
        <div class="section-label">Pricing</div>
        <h1 class="section-title" style="font-size: var(--text-4xl);">Simple, transparent <span class="gradient-text">pricing</span></h1>
        <p class="section-description">Choose the plan that fits your business. All plans include a 14-day free trial.</p>
        <div style="margin-top: 24px;">
          <div class="tabs" style="margin: 0 auto;">
            <button class="tab-btn active" data-billing="monthly">Monthly</button>
            <button class="tab-btn" data-billing="annual">Annual <span class="badge badge-success" style="margin-left: 4px;">-20%</span></button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top: 0;">
    <div class="container">
      <div class="pricing-grid stagger-children">
        <!-- Launch Plan (Free) -->
        <div class="pricing-card reveal" data-plan="Launch" data-price-monthly="0" data-price-annual="0" data-free="true">
          <div class="pricing-name">Launch</div>
          <p class="pricing-desc">Perfect for getting started and testing the waters.</p>
          <div class="pricing-price"><span class="pricing-amount monthly-price">$0</span><span class="pricing-amount annual-price hidden">$0</span><span class="pricing-period">/month</span></div>
          <div class="pricing-features">
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Up to 10 products</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Basic analytics</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>RD Vendora subdomain</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Email support</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Custom domain</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Discount codes</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Multi-vendor</div>
          </div>
          <button class="btn btn-outline w-full plan-btn" style="justify-content: center;">Get started</button>
        </div>
        <!-- Growth Plan -->
        <div class="pricing-card popular reveal" data-plan="Growth" data-price-monthly="49" data-price-annual="39" data-free="false">
          <div class="pricing-badge">Most Popular</div>
          <div class="pricing-name">Growth</div>
          <p class="pricing-desc">For growing businesses ready to scale.</p>
          <div class="pricing-price"><span class="pricing-amount monthly-price">$49</span><span class="pricing-amount annual-price hidden">$39</span><span class="pricing-period">/month</span></div>
          <div class="pricing-features">
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Unlimited products</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Advanced analytics</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Custom domain</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Discount codes</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Priority support</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Multi-vendor</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>API access</div>
          </div>
          <button class="btn btn-primary w-full plan-btn" style="justify-content: center;">Start free trial</button>
        </div>
        <!-- Scale Plan -->
        <div class="pricing-card reveal" data-plan="Scale" data-price-monthly="149" data-price-annual="119" data-free="false">
          <div class="pricing-name">Scale</div>
          <p class="pricing-desc">For scaling brands with advanced needs.</p>
          <div class="pricing-price"><span class="pricing-amount monthly-price">$149</span><span class="pricing-amount annual-price hidden">$119</span><span class="pricing-period">/month</span></div>
          <div class="pricing-features">
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Everything in Growth</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Multi-vendor (5)</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>API access</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Custom integrations</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Advanced reports</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Dedicated manager</div>
            <div class="pricing-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>SLA guarantee</div>
          </div>
          <button class="btn btn-outline w-full plan-btn" style="justify-content: center;">Start free trial</button>
        </div>
        <!-- Empire Plan -->
        <div class="pricing-card reveal" data-plan="Empire" data-price-monthly="399" data-price-annual="319" data-free="false">
          <div class="pricing-name">Empire</div>
          <p class="pricing-desc">For enterprises with custom requirements.</p>
          <div class="pricing-price"><span class="pricing-amount monthly-price">$399</span><span class="pricing-amount annual-price hidden">$319</span><span class="pricing-period">/month</span></div>
          <div class="pricing-features">
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Everything in Scale</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Unlimited vendors</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Dedicated manager</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Custom development</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>SLA guarantee</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>On-premise option</div>
            <div class="pricing-feature included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Training & onboarding</div>
          </div>
          <button class="btn btn-outline w-full plan-btn" style="justify-content: center;">Contact sales</button>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container" style="max-width: 800px;">
      <h2 class="section-title text-center reveal" style="margin-bottom: 40px;">Frequently Asked Questions</h2>
      <div class="faq-list">
        <div class="faq-item reveal">
          <div class="faq-question" onclick="this.parentElement.classList.toggle('active')">Can I change plans later?<svg class="faq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
          <div class="faq-answer"><p>Yes! You can upgrade or downgrade your plan at any time. Changes take effect immediately, and we will prorate any difference.</p></div>
        </div>
        <div class="faq-item reveal">
          <div class="faq-question" onclick="this.parentElement.classList.toggle('active')">Is there a free plan?<svg class="faq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
          <div class="faq-answer"><p>Yes, our Launch plan is completely free forever. It is perfect for testing and small stores with up to 10 products.</p></div>
        </div>
        <div class="faq-item reveal">
          <div class="faq-question" onclick="this.parentElement.classList.toggle('active')">Do I need a credit card to start?<svg class="faq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
          <div class="faq-answer"><p>No credit card required for the Launch plan or to start your 14-day free trial of paid plans.</p></div>
        </div>
        <div class="faq-item reveal">
          <div class="faq-question" onclick="this.parentElement.classList.toggle('active')">What payment methods do you accept?<svg class="faq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
          <div class="faq-answer"><p>We accept all major credit cards (Visa, Mastercard, Amex), PayPal, and bank transfers for annual plans.</p></div>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer footer-glass" id="footer"></footer>

  <!-- SCROLL TO TOP BUTTON -->
  <div id="scroll-to-top">
    <div class="progress-ring-container">
      <svg class="progress-ring" width="50" height="50" viewBox="0 0 50 50">
        <defs>
          <linearGradient id="scrollGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="var(--primary)" />
            <stop offset="100%" stop-color="var(--warning)" />
          </linearGradient>
        </defs>
        <circle class="progress-ring-bg" cx="25" cy="25" r="21" />
        <circle class="progress-ring-fill" cx="25" cy="25" r="21" stroke-dasharray="131.95" stroke-dashoffset="131.95" />
      </svg>
      <span class="progress-percentage">0%</span>
    </div>
  </div>

  <!-- CHATBOT WIDGET -->
  <div class="chatbot-toggle" id="chatbot-toggle" title="Chat with us!">
    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
  </div>
  <div class="chatbot-panel" id="chatbot-panel">
    <div class="chatbot-header">
      <span>💬 RD Vendora Assistant</span>
      <button id="chatbot-close" aria-label="Close chat">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="chatbot-messages" id="chatbot-messages">
      <div class="chat-message bot">
        Hello! 👋 I'm the RD Vendora virtual assistant. How can I help you today?
      </div>
    </div>
    <div class="chatbot-input-area">
      <input type="text" id="chatbot-input" placeholder="Type a message..." autocomplete="off">
      <button id="chatbot-send">Send</button>
    </div>
  </div>

  <!-- COOKIE CONSENT BANNER -->
  <div class="cookie-banner" id="cookie-banner">
    <p>
      We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic.
      By clicking "Accept All", you consent to our use of cookies.
      <a href="privacy.php">Learn more</a>
    </p>
    <button class="btn-accept" id="accept-cookies">Accept All</button>
  </div>

  <!-- REMITA PAYMENT MODAL -->
  <div id="paymentModal" class="modal-overlay">
    <div class="payment-modal">
      <div class="modal-header">
        <h3>Complete Payment with Remita</h3>
        <div class="modal-close" id="closeModalBtn">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
      </div>
      <div class="modal-body">
        <div class="plan-summary" id="planSummary">
          <p><strong>Plan:</strong> <span id="selectedPlan">-</span></p>
          <p><strong>Billing:</strong> <span id="selectedBilling">Monthly</span></p>
          <p><strong>Amount:</strong> $<span id="selectedAmount">0</span></p>
        </div>
        <form id="paymentForm">
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" id="fullName" required placeholder="John Doe">
          </div>
          <div class="form-group">
            <label>Email Address *</label>
            <input type="email" id="email" required placeholder="john@example.com">
          </div>
          <div class="form-group">
            <label>Phone Number *</label>
            <input type="tel" id="phone" required placeholder="08012345678">
          </div>
          <div class="form-group">
            <label>Remita Payment Method</label>
            <select id="remitaMethod">
              <option value="card">Card Payment</option>
              <option value="bank">Bank Transfer</option>
              <option value="ussd">USSD</option>
              <option value="wallet">Wallet</option>
            </select>
          </div>
          <button type="submit" class="btn btn-pay" id="payNowBtn">Pay with Remita</button>
        </form>
        <div class="remita-note">
          🔒 Test mode: No real charge. After payment simulation, you'll be redirected to registration.
        </div>
      </div>
    </div>
  </div>

  <script>
    // ======================= DATABASE & AUTH =======================
    const DB = {
      key: 'rdvendora_db', version: '1.0',
      init() { if (!localStorage.getItem(this.key)) this.seed(); },
      get() { try { return JSON.parse(localStorage.getItem(this.key)) || {}; } catch { return {}; } },
      set(data) { localStorage.setItem(this.key, JSON.stringify(data)); },
      getAll(collection) { return this.get()[collection] || []; },
      getById(collection, id) { return this.getAll(collection).find(i => i.id === id); },
      create(collection, item) { const data = this.get(); if (!data[collection]) data[collection] = []; item.id = item.id || this.generateId(); data[collection].push(item); this.set(data); return item; },
      update(collection, id, updates) { const data = this.get(); const items = data[collection] || []; const idx = items.findIndex(i => i.id === id); if (idx !== -1) { items[idx] = { ...items[idx], ...updates, updated_at: new Date().toISOString() }; data[collection] = items; this.set(data); return items[idx]; } return null; },
      query(collection, fn) { return this.getAll(collection).filter(fn); },
      generateId() { return '_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36); },
      seed() { const now = new Date().toISOString(); const data = { version: this.version, users: [{ id: 'user_admin', name: 'Admin', email: 'admin@RD Vendora.com', password: 'admin123', role: 'admin', initials: 'AD', status: 'active' }], stores: [], products: [], orders: [], customers: [], carts: {}, notifications: [] }; this.set(data); }
    };
    const Auth = {
      sessionKey: 'RD Vendora_session',
      login(email, password) { const user = DB.getAll('users').find(u => u.email === email && u.password === password); if (user) { localStorage.setItem(this.sessionKey, JSON.stringify({ userId: user.id, email: user.email, name: user.name, role: user.role, initials: user.initials, expiresAt: Date.now() + 86400000 })); return { success: true }; } return { success: false }; },
      logout() { localStorage.removeItem(this.sessionKey); window.location.href = 'index.php'; },
      getSession() { try { const s = JSON.parse(localStorage.getItem(this.sessionKey)); if (s && s.expiresAt > Date.now()) return s; localStorage.removeItem(this.sessionKey); return null; } catch { return null; } },
      isLoggedIn() { return !!this.getSession(); }
    };
    const Theme = {
      init() { const saved = localStorage.getItem('RD Vendora_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); this.set(saved); },
      set(theme) { document.documentElement.setAttribute('data-theme', theme); localStorage.setItem('RD Vendora_theme', theme); },
      toggle() { const cur = document.documentElement.getAttribute('data-theme'); const next = cur === 'dark' ? 'light' : 'dark'; this.set(next); return next; }
    };
    const UI = {
      injectNavbar() {
        const nav = document.getElementById('navbar');
        if (!nav) return;
        const session = Auth.getSession();
        nav.innerHTML = `
          <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">
              <img src="assets/brand-logo.png" alt="" class="rdv-brand-logo" style="height:28px;width:auto;max-width:100px;object-fit:contain;background:#fff;border-radius:6px;padding:1px 4px;display:block;">
              <span class="rdv-brand-name">RD Vendora</span>
            </a>
            <nav class="navbar-nav" id="navbar-nav">
              <a href="index.php" class="nav-link">Home</a>
              <a href="features.php" class="nav-link">Features</a>
              <a href="pricing.php" class="nav-link active">Pricing</a>
              <a href="about.php" class="nav-link">About</a>
              <a href="contact.php" class="nav-link">Contact</a>
            </nav>
            <div class="navbar-actions">
              ${session ? `<a href="${session.role === 'admin' ? 'admin/admin.php' : 'dashboard.php'}" class="btn btn-primary btn-sm">Dashboard</a>` : `<a href="login.php" class="btn btn-ghost btn-sm">Log in</a><a href="register.php" class="btn btn-primary btn-sm">Get Started</a>`}
              <button class="btn-icon mobile-menu-toggle" id="mobile-menu-toggle">
                <span></span><span></span><span></span>
              </button>
            </div>
          </div>`;
      },
      injectFooter() {
        const footer = document.getElementById('footer');
        if (!footer) return;
        footer.innerHTML = `
          <div class="container">
            <div class="footer-grid">
              <div class="footer-brand">
                <a href="index.php" class="navbar-brand" style="margin-bottom:16px;">
                  <img src="assets/brand-logo.png" alt="" class="rdv-brand-logo" style="height:28px;width:auto;max-width:100px;object-fit:contain;background:#fff;border-radius:6px;padding:1px 4px;display:block;">
                  <span class="rdv-brand-name">RD Vendora</span>
                </a>
                <p class="footer-brand-desc">The complete multi-vendor eCommerce platform. Build, manage, and scale your online business with powerful tools.</p>
              </div>
              <div class="footer-column">
                <h4>Product</h4>
                <div class="footer-links">
                  <a href="features.php">Features</a><a href="pricing.php">Pricing</a><a href="storefront.php">Store Demo</a><a href="#">Changelog</a>
                </div>
              </div>
              <div class="footer-column">
                <h4>Company</h4>
                <div class="footer-links">
                  <a href="about.php">About</a><a href="contact.php">Contact</a><a href="faq.php">FAQ</a><a href="#">Careers</a>
                </div>
              </div>
              <div class="footer-column">
                <h4>Legal</h4>
                <div class="footer-links">
                  <a href="#">Privacy</a><a href="#">Terms</a><a href="#">Security</a><a href="#">Cookies</a>
                </div>
              </div>
            </div>
            <div class="footer-bottom">
              <p class="footer-copyright">© 2024 RD Vendora. All rights reserved.</p>
              <div class="footer-social">
                <a href="#"><svg viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg> Twitter</a>
                <a href="#"><svg viewBox="0 0 24 24"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg> GitHub</a>
                <a href="#"><svg viewBox="0 0 24 24"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2zM4 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg> LinkedIn</a>
              </div>
            </div>
          </div>`;
      },
      initScrollReveal() {
        const reveals = document.querySelectorAll('[class*="reveal"]');
        const obs = new IntersectionObserver((entries) => {
          entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('revealed'); });
        }, { threshold: 0.1 });
        reveals.forEach(r => obs.observe(r));
      }
    };

    // ======================= REMITA PAYMENT MODAL LOGIC =======================
    let currentPlan = null;
    let currentAmount = 0;
    let currentBilling = 'monthly';

    const planButtons = document.querySelectorAll('.plan-btn');
    const modalOverlay = document.getElementById('paymentModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const paymentForm = document.getElementById('paymentForm');
    const selectedPlanSpan = document.getElementById('selectedPlan');
    const selectedBillingSpan = document.getElementById('selectedBilling');
    const selectedAmountSpan = document.getElementById('selectedAmount');

    let activeBilling = 'monthly';
    const billingBtns = document.querySelectorAll('.tab-btn');
    function updateBillingVisibility() {
      document.querySelectorAll('.monthly-price').forEach(el => el.classList.toggle('hidden', activeBilling !== 'monthly'));
      document.querySelectorAll('.annual-price').forEach(el => el.classList.toggle('hidden', activeBilling !== 'annual'));
    }
    billingBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        billingBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeBilling = this.getAttribute('data-billing');
        updateBillingVisibility();
      });
    });

    function openPaymentModal(planCard) {
      const planName = planCard.getAttribute('data-plan');
      const isFree = planCard.getAttribute('data-free') === 'true';
      let price = activeBilling === 'monthly' 
        ? parseInt(planCard.getAttribute('data-price-monthly')) 
        : parseInt(planCard.getAttribute('data-price-annual'));
      
      if (isFree && price === 0) {
        window.location.href = `register.php?plan=${encodeURIComponent(planName)}&amount=0&billing=${activeBilling}`;
        return;
      }
      
      currentPlan = planName;
      currentAmount = price;
      currentBilling = activeBilling;
      
      selectedPlanSpan.textContent = currentPlan;
      selectedBillingSpan.textContent = currentBilling === 'monthly' ? 'Monthly' : 'Annual (20% off)';
      selectedAmountSpan.textContent = currentAmount;
      
      modalOverlay.classList.add('active');
    }

    planButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const planCard = btn.closest('.pricing-card');
        openPaymentModal(planCard);
      });
    });

    function closeModal() {
      modalOverlay.classList.remove('active');
      paymentForm.reset();
    }
    closeModalBtn.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) closeModal();
    });

    paymentForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fullName = document.getElementById('fullName').value.trim();
      const email = document.getElementById('email').value.trim();
      const phone = document.getElementById('phone').value.trim();
      
      if (!fullName || !email || !phone) {
        alert('Please fill in all required fields.');
        return;
      }
      
      const payBtn = document.getElementById('payNowBtn');
      const originalText = payBtn.innerHTML;
      payBtn.innerHTML = '<span class="loader-spinner"></span> Processing...';
      payBtn.disabled = true;
      
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      const paymentReference = 'REMITA_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
      const redirectUrl = `register.php?plan=${encodeURIComponent(currentPlan)}&amount=${currentAmount}&billing=${currentBilling}&payment_ref=${paymentReference}&name=${encodeURIComponent(fullName)}&email=${encodeURIComponent(email)}`;
      window.location.href = redirectUrl;
    });

    document.addEventListener('DOMContentLoaded', () => {
      DB.init(); Theme.init(); UI.injectNavbar(); UI.injectFooter(); UI.initScrollReveal();

      const navbar = document.getElementById('navbar');
      window.addEventListener('scroll', () => {
        if (window.scrollY > 50) navbar.classList.add('scrolled', 'glass');
        else navbar.classList.remove('scrolled', 'glass');
      });

      // Mobile menu overlay
      const toggle = document.getElementById('mobile-menu-toggle');
      if (toggle) {
        const overlay = document.createElement('div');
        overlay.className = 'mobile-overlay';
        overlay.innerHTML = `
          <div class="mobile-menu-brand">
            <img src="assets/brand-logo.png" alt="" style="height:28px;width:auto;max-width:100px;object-fit:contain;background:#fff;border-radius:6px;padding:1px 4px;">
            <span class="rdv-brand-name">RD Vendora</span>
          </div>
          <button class="menu-close" id="menu-close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
          <nav class="mobile-nav-links">
            <a href="index.php" class="mobile-nav-link">Home</a>
            <a href="features.php" class="mobile-nav-link">Features</a>
            <a href="pricing.php" class="mobile-nav-link">Pricing</a>
            <a href="about.php" class="mobile-nav-link">About</a>
            <a href="contact.php" class="mobile-nav-link">Contact</a>
            <a href="register.php" class="mobile-nav-link">Get Started</a>
          </nav>
          <div class="mobile-menu-footer">
            <a href="#">Privacy</a><a href="#">Terms</a><a href="#">Security</a>
          </div>
        `;
        document.body.appendChild(overlay);
        const closeBtn = document.getElementById('menu-close');
        const openMenu = () => { overlay.classList.add('active'); toggle.classList.add('active'); document.body.style.overflow = 'hidden'; };
        const closeMenu = () => { overlay.classList.remove('active'); toggle.classList.remove('active'); document.body.style.overflow = ''; };
        toggle.addEventListener('click', openMenu);
        closeBtn.addEventListener('click', closeMenu);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeMenu(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && overlay.classList.contains('active')) closeMenu(); });
        overlay.querySelectorAll('.mobile-nav-link').forEach(link => link.addEventListener('click', closeMenu));
      }

      // Scroll to top button
      const scrollBtn = document.getElementById('scroll-to-top');
      const progressFill = document.querySelector('.progress-ring-fill');
      const progressText = document.querySelector('.progress-percentage');
      const circumference = 2 * Math.PI * 21;
      function updateScrollProgress() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrolled = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        progressFill.style.strokeDashoffset = circumference - (scrolled / 100) * circumference;
        progressText.textContent = Math.round(scrolled) + '%';
        if (scrollTop > 300) scrollBtn.classList.add('visible');
        else scrollBtn.classList.remove('visible');
      }
      window.addEventListener('scroll', updateScrollProgress, { passive: true });
      window.addEventListener('resize', updateScrollProgress);
      scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

      // Chatbot logic
      const chatbotMessages = document.getElementById('chatbot-messages');
      const chatbotInput = document.getElementById('chatbot-input');
      const chatbotSend = document.getElementById('chatbot-send');
      const chatbotToggle = document.getElementById('chatbot-toggle');
      const chatbotPanel = document.getElementById('chatbot-panel');
      const chatbotClose = document.getElementById('chatbot-close');
      function appendMessage(text, sender) {
        const msg = document.createElement('div');
        msg.className = `chat-message ${sender}`;
        msg.textContent = text;
        chatbotMessages.appendChild(msg);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
      }
      function getBotResponse(userMessage) {
        const msg = userMessage.toLowerCase().trim();
        if (msg.includes('hello') || msg.includes('hi')) return "Hi there! How can I assist you with RD Vendora today?";
        if (msg.includes('price') || msg.includes('plan')) return "We have plans starting from $0/month (Launch), $49/month (Growth), $149/month (Scale), and $399/month (Empire).";
        if (msg.includes('support')) return "You can reach our support team at support@RD Vendora.com. We're here 24/7!";
        return "Thanks for your message! I'm here to help with questions about RD Vendora's features, pricing, or anything else.";
      }
      function handleSend() {
        const text = chatbotInput.value.trim();
        if (!text) return;
        appendMessage(text, 'user');
        chatbotInput.value = '';
        setTimeout(() => { const reply = getBotResponse(text); appendMessage(reply, 'bot'); }, 600);
      }
      chatbotSend.addEventListener('click', handleSend);
      chatbotInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSend(); });
      chatbotToggle.addEventListener('click', () => chatbotPanel.classList.add('open'));
      chatbotClose.addEventListener('click', () => chatbotPanel.classList.remove('open'));

      // Cookie consent
      const cookieBanner = document.getElementById('cookie-banner');
      const acceptBtn = document.getElementById('accept-cookies');
      const COOKIE_CONSENT_KEY = 'RD Vendora_cookies_accepted';
      if (!localStorage.getItem(COOKIE_CONSENT_KEY)) { setTimeout(() => cookieBanner.classList.add('visible'), 500); }
      acceptBtn.addEventListener('click', () => { localStorage.setItem(COOKIE_CONSENT_KEY, 'true'); cookieBanner.classList.remove('visible'); });

      // Theme toggle button
      const themeToggle = document.createElement('button');
      themeToggle.innerHTML = '🌓';
      themeToggle.style.cssText = 'position:fixed;bottom:16px;left:16px;z-index:999;background:var(--bg-secondary);border:1px solid var(--border-primary);border-radius:50%;width:40px;height:40px;cursor:pointer;';
      themeToggle.onclick = () => Theme.toggle();
      document.body.appendChild(themeToggle);
    });
  </script>
  <script src="assets/js/rdv-public.js" defer></script>
</body>
</html>