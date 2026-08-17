<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact - RD Vendora</title>
  <meta name="description" content="Get in touch with RD Vendora. We're here to help with any questions.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="stylesheet" href="./assets/css/responsive.css">
  <link rel="stylesheet" href="./assets/css/animations.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ============================================================
       RD Vendora - Base Styles (same as index)
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
      .contact-grid { grid-template-columns: 1fr !important; gap: 32px !important; }
      .contact-info-card { margin-bottom: 24px; }
      .contact-form-card { padding: 24px !important; }
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
      .contact-info-item h3 { font-size: 16px !important; }
      .contact-info-item p, .contact-info-item a { font-size: 13px !important; }
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

    /* ========== PRELOADER ========== */
    #preloader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-primary); display: flex; align-items: center; justify-content: center; z-index: 9999; transition: opacity 0.6s ease, visibility 0.6s ease; }
    #preloader.fade-out { opacity: 0; visibility: hidden; }
    .preloader-content { display: flex; flex-direction: column; align-items: center; gap: 24px; }
    .preloader-ring { width: 64px; height: 64px; border-radius: 50%; background: conic-gradient(from 0deg, var(--primary) 0%, var(--warning) 80%, transparent 80%); animation: preloader-spin 1.2s linear infinite; display: flex; align-items: center; justify-content: center; }
    .preloader-ring::after { content: ''; width: 48px; height: 48px; border-radius: 50%; background: var(--bg-primary); }
    @keyframes preloader-spin { to { transform: rotate(360deg); } }
    .preloader-brand { font-family: var(--font-sans); font-weight: var(--font-bold); font-size: var(--text-xl); background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -0.3px; }

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
    .typing-indicator {
      display: flex;
      gap: 4px;
      padding: 8px 12px;
      background: var(--bg-tertiary);
      border-radius: 20px;
      width: fit-content;
    }
    .typing-indicator span {
      width: 8px;
      height: 8px;
      background: var(--text-muted);
      border-radius: 50%;
      animation: bounce 1.4s infinite ease-in-out;
    }
    .typing-indicator span:nth-child(1) { animation-delay: 0s; }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-6px); }
    }
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

    /* ========== CONTACT FORM & TOAST STYLES ========== */
    .form-group { margin-bottom: var(--space-4); }
    .form-label { display: block; font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); margin-bottom: var(--space-2); }
    .form-input { width: 100%; padding: var(--space-3) var(--space-4); font-size: var(--text-sm); border: 1px solid var(--border-primary); border-radius: var(--radius-md); background: var(--bg-primary); color: var(--text-primary); transition: border-color 0.2s ease, box-shadow 0.2s ease; outline: none; }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
    .form-input::placeholder { color: var(--text-muted); }
    textarea.form-input { resize: vertical; min-height: 100px; }
    .map-container { border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border-primary); }
    .map-container iframe { display: block; width: 100%; height: 350px; border: none; }
    @media (max-width: 767px) { .map-container iframe { height: 280px; } }

    .toast-container {
      position: fixed;
      top: 80px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .toast {
      background: var(--bg-secondary);
      border: 1px solid var(--border-primary);
      border-radius: var(--radius-md);
      padding: 12px 20px;
      box-shadow: var(--shadow-lg);
      font-size: 14px;
      animation: slideInRight 0.3s ease;
    }
    .toast.success { border-left: 4px solid var(--success); color: var(--success-dark); background: var(--success-light); }
    .toast.error { border-left: 4px solid var(--error); color: var(--error-dark); background: var(--error-light); }
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    .toast.fade-out {
      animation: fadeOut 0.3s ease forwards;
    }
    @keyframes fadeOut {
      to { opacity: 0; transform: translateX(100%); }
    }
  </style>
</head>
<body>
  <!-- PRELOADER -->
  <div id="preloader">
    <div class="preloader-content">
      <div class="preloader-ring"></div>
      <span class="preloader-brand">RD Vendora</span>
    </div>
  </div>

  <!-- NAVBAR (same as index) -->
  <header class="navbar glass" id="navbar"></header>

  <!-- CONTENT -->
  <section style="padding: 140px 0 80px;">
    <div class="container">
      <div class="section-header reveal">
        <div class="section-label">Contact</div>
        <h1 class="section-title" style="font-size: var(--text-4xl);">Let's <span class="gradient-text">talk</span></h1>
        <p class="section-description">Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top: 0;">
    <div class="container" style="max-width: 1000px;">
      <div class="contact-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 48px;">
        <!-- Left: contact info -->
        <div class="reveal contact-info-card">
          <div class="contact-info-item" style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Email</h3>
            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">For general inquiries</p>
            <a href="mailto:hello@RD Vendora.com" style="color: var(--primary); font-weight: 500;">hello@RD Vendora.com</a>
          </div>
          <div class="contact-info-item" style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Support</h3>
            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">For technical help</p>
            <a href="mailto:support@RD Vendora.com" style="color: var(--primary); font-weight: 500;">support@RD Vendora.com</a>
          </div>
          <div class="contact-info-item" style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Sales</h3>
            <p style="font-size: 14px; color: var(--text-secondary); margin-bottom: 4px;">For enterprise inquiries</p>
            <a href="mailto:sales@RD Vendora.com" style="color: var(--primary); font-weight: 500;">sales@RD Vendora.com</a>
          </div>
          <div class="contact-info-item">
            <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Office</h3>
            <p style="font-size: 14px; color: var(--text-secondary);">123 Commerce Street<br>San Francisco, CA 94105</p>
          </div>
        </div>
        <!-- Right: contact form -->
        <div class="card contact-form-card reveal-right" style="padding: 32px;">
          <form id="contact-form">
            <div class="form-group">
              <label class="form-label">Name</label>
              <input type="text" id="contact_name" class="form-input" placeholder="Your name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" id="contact_email" class="form-input" placeholder="you@example.com" required>
            </div>
            <div class="form-group">
              <label class="form-label">Subject</label>
              <select id="contact_subject" class="form-input">
                <option>General inquiry</option>
                <option>Sales</option>
                <option>Support</option>
                <option>Partnership</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Message</label>
              <textarea id="contact_message" class="form-input" rows="4" placeholder="How can we help?" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Send Message</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- MAP SECTION (unchanged) -->
  <section class="section" style="padding-top: 0;">
    <div class="container" style="max-width: 1000px;">
      <div class="section-header reveal" style="margin-bottom: 32px;">
        <div class="section-label">Find Us</div>
        <h2 class="section-title" style="font-size: var(--text-2xl);">Visit our <span class="gradient-text">office</span></h2>
      </div>
      <div class="map-container reveal">
        <iframe
          src="https://www.openstreetmap.org/export/embed.php?bbox=-122.429%2C37.769%2C-122.409%2C37.779&layer=mapnik&marker=37.7749295%2C-122.4194155"
          title="RD Vendora Office Location"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer footer-glass" id="footer"></footer>

  <!-- SCROLL TO TOP BUTTON -->
  <div id="scroll-to-top">
    <div class="progress-ring-container">
      <svg class="progress-ring" width="50" height="50" viewBox="0 0 50 50">
        <defs><linearGradient id="scrollGradient" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="var(--primary)" /><stop offset="100%" stop-color="var(--warning)" /></linearGradient></defs>
        <circle class="progress-ring-bg" cx="25" cy="25" r="21" />
        <circle class="progress-ring-fill" cx="25" cy="25" r="21" stroke-dasharray="131.95" stroke-dashoffset="131.95" />
      </svg>
      <span class="progress-percentage">0%</span>
    </div>
  </div>

  <!-- CHATBOT WIDGET (AI-powered) -->
  <div class="chatbot-toggle" id="chatbot-toggle" title="Chat with us!">
    <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
  </div>
  <div class="chatbot-panel" id="chatbot-panel">
    <div class="chatbot-header"><span>💬 RD Vendora Assistant (AI)</span><button id="chatbot-close"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
    <div class="chatbot-messages" id="chatbot-messages"><div class="chat-message bot">Hello! 👋 I'm the RD Vendora AI assistant. Ask me anything about our platform, features, pricing, or how to start selling online.</div></div>
    <div class="chatbot-input-area"><input type="text" id="chatbot-input" placeholder="Type a message..."><button id="chatbot-send">Send</button></div>
  </div>

  <!-- COOKIE CONSENT BANNER -->
  <div class="cookie-banner" id="cookie-banner">
    <p>We use cookies to enhance your browsing experience. By clicking "Accept All", you consent to our use of cookies. <a href="privacy.php">Learn more</a></p>
    <button class="btn-accept" id="accept-cookies">Accept All</button>
  </div>

  <!-- TOAST CONTAINER -->
  <div id="toast-container" class="toast-container"></div>

  <script>
    // ============================================================
    // RD Vendora - Core Application JavaScript (with AI Chatbot + Cookie Consent)
    // ============================================================
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
        nav.innerHTML = `<div class="navbar-inner"><a href="index.php" class="navbar-brand"><div class="navbar-brand-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>RD Vendora</a><nav class="navbar-nav" id="navbar-nav"><a href="index.php" class="nav-link">Home</a><a href="features.php" class="nav-link">Features</a><a href="pricing.php" class="nav-link">Pricing</a><a href="about.php" class="nav-link">About</a><a href="contact.php" class="nav-link active">Contact</a></nav><div class="navbar-actions">${session ? `<a href="${session.role === 'admin' ? 'admin/admin.php' : 'dashboard.php'}" class="btn btn-primary btn-sm">Dashboard</a>` : `<a href="login.php" class="btn btn-ghost btn-sm">Log in</a><a href="register.php" class="btn btn-primary btn-sm">Get Started</a>`}<button class="btn-icon mobile-menu-toggle" id="mobile-menu-toggle"><span></span><span></span><span></span></button></div></div>`;
      },
      injectFooter() {
        const footer = document.getElementById('footer');
        if (!footer) return;
        footer.innerHTML = `<div class="container"><div class="footer-grid"><div class="footer-brand"><a href="index.php" class="navbar-brand" style="margin-bottom:16px;"><div class="navbar-brand-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>RD Vendora</a><p class="footer-brand-desc">The complete multi-vendor eCommerce platform. Build, manage, and scale your online business with powerful tools.</p></div><div class="footer-column"><h4>Product</h4><div class="footer-links"><a href="features.php">Features</a><a href="pricing.php">Pricing</a><a href="storefront.php">Store Demo</a><a href="#">Changelog</a></div></div><div class="footer-column"><h4>Company</h4><div class="footer-links"><a href="about.php">About</a><a href="contact.php">Contact</a><a href="faq.php">FAQ</a><a href="#">Careers</a></div></div><div class="footer-column"><h4>Legal</h4><div class="footer-links"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Security</a><a href="#">Cookies</a></div></div></div><div class="footer-bottom"><p class="footer-copyright">© 2024 RD Vendora. All rights reserved. <div><b>Designed By RD NEXA TECH</b></div></div></div>`;
      },
      initScrollReveal() {
        const reveals = document.querySelectorAll('[class*="reveal"]');
        const obs = new IntersectionObserver((entries) => {
          entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('revealed'); });
        }, { threshold: 0.1 });
        reveals.forEach(r => obs.observe(r));
      }
    };

    // Helper: show toast
    function showToast(message, type = 'success') {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.innerText = message;
      container.appendChild(toast);
      setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
      }, 4000);
    }

    // Preloader
    window.addEventListener('load', () => {
      const preloader = document.getElementById('preloader');
      if (preloader) setTimeout(() => preloader.classList.add('fade-out'), 400);
    });

    document.addEventListener('DOMContentLoaded', () => {
      DB.init(); Theme.init(); UI.injectNavbar(); UI.injectFooter(); UI.initScrollReveal();

      // Glass navbar effect
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
        overlay.innerHTML = `<div class="mobile-menu-brand"><svg class="brand-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>RD Vendora</div><button class="menu-close" id="menu-close"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button><nav class="mobile-nav-links"><a href="index.php" class="mobile-nav-link">Home</a><a href="features.php" class="mobile-nav-link">Features</a><a href="pricing.php" class="mobile-nav-link">Pricing</a><a href="about.php" class="mobile-nav-link">About</a><a href="contact.php" class="mobile-nav-link">Contact</a><a href="register.php" class="mobile-nav-link">Get Started</a></nav><div class="mobile-menu-footer"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Security</a></div>`;
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
        scrollBtn.classList.toggle('visible', scrollTop > 300);
      }
      window.addEventListener('scroll', updateScrollProgress, { passive: true });
      window.addEventListener('resize', updateScrollProgress);
      scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

      // ========== AI CHATBOT (Groq API) ==========
      const chatbotMessages = document.getElementById('chatbot-messages');
      const chatbotInput = document.getElementById('chatbot-input');
      const chatbotSend = document.getElementById('chatbot-send');
      const chatbotToggle = document.getElementById('chatbot-toggle');
      const chatbotPanel = document.getElementById('chatbot-panel');
      const chatbotClose = document.getElementById('chatbot-close');

      let isTyping = false;

      function appendMessage(text, sender) {
        const msg = document.createElement('div');
        msg.className = `chat-message ${sender}`;
        msg.textContent = text;
        chatbotMessages.appendChild(msg);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
      }

      function showTypingIndicator() {
        if (isTyping) return;
        isTyping = true;
        const indicator = document.createElement('div');
        indicator.className = 'chat-message bot typing-indicator';
        indicator.id = 'typing-indicator';
        indicator.innerHTML = '<span></span><span></span><span></span>';
        chatbotMessages.appendChild(indicator);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
      }

      function hideTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) indicator.remove();
        isTyping = false;
      }

      async function sendMessageToAI(userMessage) {
        try {
          const response = await fetch('chatbot-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: userMessage })
          });
          const data = await response.json();
          hideTypingIndicator();
          if (data.error) {
            appendMessage('⚠️ ' + data.error, 'bot');
          } else {
            appendMessage(data.reply, 'bot');
          }
        } catch (err) {
          hideTypingIndicator();
          appendMessage('⚠️ Network error. Please try again.', 'bot');
          console.error('Chatbot error:', err);
        }
      }

      function handleSend() {
        const text = chatbotInput.value.trim();
        if (!text) return;
        appendMessage(text, 'user');
        chatbotInput.value = '';
        showTypingIndicator();
        sendMessageToAI(text);
      }

      chatbotSend.addEventListener('click', handleSend);
      chatbotInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSend(); });
      chatbotToggle.addEventListener('click', () => chatbotPanel.classList.add('open'));
      chatbotClose.addEventListener('click', () => chatbotPanel.classList.remove('open'));

      // Cookie consent
      const cookieBanner = document.getElementById('cookie-banner');
      const acceptBtn = document.getElementById('accept-cookies');
      if (!localStorage.getItem('RD Vendora_cookies_accepted')) setTimeout(() => cookieBanner.classList.add('visible'), 500);
      acceptBtn.addEventListener('click', () => { localStorage.setItem('RD Vendora_cookies_accepted', 'true'); cookieBanner.classList.remove('visible'); });

      // Theme toggle button
      const themeToggleBtn = document.createElement('button');
      themeToggleBtn.innerHTML = '🌓';
      themeToggleBtn.style.cssText = 'position:fixed;bottom:16px;left:16px;z-index:999;background:var(--bg-secondary);border:1px solid var(--border-primary);border-radius:50%;width:40px;height:40px;cursor:pointer;';
      themeToggleBtn.onclick = () => Theme.toggle();
      document.body.appendChild(themeToggleBtn);

      // ========== CONTACT FORM SUBMISSION (AJAX) ==========
      const contactForm = document.getElementById('contact-form');
      contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('contact_name').value.trim();
        const email = document.getElementById('contact_email').value.trim();
        const subject = document.getElementById('contact_subject').value;
        const message = document.getElementById('contact_message').value.trim();
        if (!name || !email || !message) {
          showToast('Please fill in all required fields.', 'error');
          return;
        }
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerText;
        submitBtn.innerText = 'Sending...';
        submitBtn.disabled = true;
        try {
          const response = await fetch('submit-contact.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ name, email, subject, message })
          });
          const result = await response.json();
          if (result.success) {
            showToast(result.message, 'success');
            contactForm.reset();
          } else {
            showToast(result.message || 'Failed to send message. Please try again.', 'error');
          }
        } catch (error) {
          showToast('Network error. Please try again later.', 'error');
        } finally {
          submitBtn.innerText = originalText;
          submitBtn.disabled = false;
        }
      });
    });
  </script>
</body>
</html>