<?php
session_start();
// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Capture messages from loginuser.php (error or success)
$errorMsg = $_SESSION['login_error'] ?? '';
$successMsg = $_SESSION['login_success'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_success']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php require __DIR__ . '/includes/adsense_head.php'; ?>
  <title>Log In - RD Vendora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/auth.css">
  <link rel="stylesheet" href="assets/css/animations.css">
  <link rel="stylesheet" href="assets/css/public-extras.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <style>
    /* ========== YOUR COMPLETE ORIGINAL CSS (keep exactly as you had) ========== */
    :root {
      --bg-primary: #ffffff;
      --bg-secondary: #f9fafb;
      --bg-tertiary: #f3f4f6;
      --bg-elevated: #ffffff;
      --bg-hover: #e5e7eb;
      --bg-active: #d1d5db;
      --surface-primary: #ffffff;
      --surface-secondary: #f9fafb;
      --surface-tertiary: #f3f4f6;
      --text-primary: #111827;
      --text-secondary: #4b5563;
      --text-muted: #6b7280;
      --text-inverse: #ffffff;
      --border-primary: #e5e7eb;
      --border-secondary: #d1d5db;
      --border-focus: #2563eb;
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --primary-light: #dbeafe;
      --primary-dark: #1e3a8a;
      --success: #10b981;
      --success-light: #d1fae5;
      --success-dark: #047857;
      --warning: #f59e0b;
      --warning-light: #fef3c7;
      --warning-dark: #b45309;
      --error: #ef4444;
      --error-light: #fee2e2;
      --error-dark: #b91c1c;
      --info: #3b82f6;
      --info-light: #dbeafe;
      --info-dark: #1d4ed8;
      --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      --gradient-hero: linear-gradient(135deg, #1e3a8a 0%, #f59e0b 100%);
      --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --text-xs: 0.75rem;
      --text-sm: 0.8125rem;
      --text-base: 0.9375rem;
      --text-lg: 1.125rem;
      --text-xl: 1.25rem;
      --text-2xl: 1.5rem;
      --text-3xl: 2rem;
      --text-4xl: 2.5rem;
      --text-5xl: 3.5rem;
      --font-normal: 400;
      --font-medium: 500;
      --font-semibold: 600;
      --font-bold: 700;
      --space-0: 0;
      --space-1: 0.25rem;
      --space-2: 0.5rem;
      --space-3: 0.75rem;
      --space-4: 1rem;
      --space-5: 1.25rem;
      --space-6: 1.5rem;
      --space-8: 2rem;
      --space-10: 2.5rem;
      --space-12: 3rem;
      --space-16: 4rem;
      --space-20: 5rem;
      --space-24: 6rem;
      --radius-sm: 6px;
      --radius-md: 10px;
      --radius-lg: 16px;
      --radius-xl: 20px;
      --radius-2xl: 28px;
      --radius-full: 9999px;
      --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);
      --shadow-lg: 0 8px 24px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
      --shadow-xl: 0 16px 48px rgba(0,0,0,0.10), 0 8px 16px rgba(0,0,0,0.04);
      --shadow-glow: 0 0 40px rgba(37,99,235,0.15);
      --shadow-glow-sm: 0 0 20px rgba(37,99,235,0.10);
      --transition-fast: 150ms cubic-bezier(0.4,0,0.2,1);
      --transition-base: 250ms cubic-bezier(0.4,0,0.2,1);
      --transition-slow: 350ms cubic-bezier(0.4,0,0.2,1);
      --topbar-height: 64px;
      --container-max: 1280px;
      --container-wide: 1440px;
    }
    [data-theme="dark"] {
      --bg-primary: #0c0e14;
      --bg-secondary: #14161f;
      --bg-tertiary: #1a1d28;
      --bg-elevated: #1e2130;
      --bg-hover: #242838;
      --bg-active: #2a2e40;
      --surface-primary: #14161f;
      --surface-secondary: #1a1d28;
      --surface-tertiary: #1e2130;
      --text-primary: #e8eaf0;
      --text-secondary: #9ca3b0;
      --text-muted: #6b7280;
      --text-inverse: #1a1d23;
      --border-primary: #2d3139;
      --border-secondary: #3a3f4a;
      --primary: #3b82f6;
      --primary-hover: #60a5fa;
      --primary-light: rgba(59,130,246,0.15);
      --primary-dark: #1e3a8a;
      --warning: #f59e0b;
      --warning-light: rgba(245,158,11,0.15);
      --warning-dark: #b45309;
    }

    /* ---------- GLOBAL BUTTONS ---------- */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-2);
      padding: var(--space-3) var(--space-5);
      font-size: var(--text-sm);
      font-weight: var(--font-medium);
      border-radius: var(--radius-md);
      transition: all var(--transition-fast);
      cursor: pointer;
      border: 1px solid transparent;
    }
    .btn-primary {
      background: var(--gradient-primary);
      color: var(--text-inverse);
      box-shadow: 0 2px 8px rgba(37,99,235,0.25);
    }
    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(37,99,235,0.35);
    }
    .btn-ghost {
      background: transparent;
      color: var(--text-primary);
      border-color: transparent;
    }
    .btn-ghost:hover {
      background: var(--bg-tertiary);
      border-color: var(--border-primary);
    }
    .btn-sm {
      padding: var(--space-2) var(--space-4);
      font-size: var(--text-xs);
    }
    .btn-icon {
      width: 36px;
      height: 36px;
      padding: 0;
      border-radius: var(--radius-md);
      background: transparent;
      border: 1px solid var(--border-primary);
    }
    .btn-icon:hover {
      background: var(--bg-tertiary);
    }

    /* Google button */
    .btn-google {
      background: #fff;
      border: 1px solid var(--border-primary);
      color: #1f2937;
      gap: 0.75rem;
      width: 100%;
      justify-content: center;
      margin-top: var(--space-4);
    }
    .btn-google:hover {
      background: #f3f4f6;
      transform: translateY(-1px);
    }
    .auth-divider {
      text-align: center;
      margin: var(--space-4) 0;
      position: relative;
    }
    .auth-divider span {
      background: var(--bg-primary);
      padding: 0 var(--space-3);
      color: var(--text-muted);
      font-size: var(--text-xs);
      position: relative;
      z-index: 1;
    }
    .auth-divider::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      width: 100%;
      height: 1px;
      background: var(--border-primary);
      z-index: 0;
    }

    /* ---------- NAVBAR ---------- */
    header.navbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 300;
      transition: background 0.3s, box-shadow 0.3s;
    }
    header.navbar.glass {
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      background: rgba(255,255,255,0.8);
      border-bottom: 1px solid var(--border-primary);
    }
    [data-theme="dark"] header.navbar.glass {
      background: rgba(14,16,22,0.8);
    }
    header.navbar.scrolled {
      background: var(--bg-secondary);
      box-shadow: var(--shadow-sm);
    }
    header.navbar .navbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: var(--topbar-height);
      padding: 0 var(--space-6);
      max-width: var(--container-max);
      margin: 0 auto;
      width: 100%;
    }
    header.navbar .navbar-brand {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      font-weight: var(--font-bold);
      font-size: var(--text-lg);
      color: var(--text-primary);
    }
    header.navbar .navbar-brand-icon {
      width: 20px;
      height: 20px;
      color: var(--primary);
    }
    header.navbar .navbar-nav {
      display: flex;
      align-items: center;
      gap: var(--space-2);
    }
    header.navbar .nav-link {
      position: relative;
      padding: var(--space-2) var(--space-3);
      font-weight: var(--font-medium);
      color: var(--text-primary);
      border-radius: var(--radius-md);
      transition: all var(--transition-fast);
    }
    header.navbar .nav-link::after {
      display: none;
    }
    header.navbar .nav-link:hover {
      background: var(--bg-tertiary);
      color: var(--primary);
    }
    header.navbar .nav-link.active {
      color: var(--primary);
      background: var(--primary-light);
    }
    header.navbar .navbar-actions {
      display: flex;
      align-items: center;
      gap: var(--space-3);
    }
    header.navbar .mobile-menu-toggle {
      display: none;
      flex-direction: column;
      gap: 5px;
      width: 36px;
      height: 36px;
      align-items: center;
      justify-content: center;
      border-radius: var(--radius-md);
    }
    header.navbar .mobile-menu-toggle span {
      display: block;
      width: 20px;
      height: 2px;
      background: var(--text-primary);
      transition: transform 0.3s ease, opacity 0.2s ease;
    }

    @media (max-width: 767px) {
      header.navbar .navbar-nav {
        display: none !important;
      }
      header.navbar .mobile-menu-toggle {
        display: flex !important;
      }
    }
    @media (min-width: 768px) {
      header.navbar .mobile-menu-toggle {
        display: none !important;
      }
      header.navbar .navbar-nav {
        display: flex !important;
      }
    }

    /* ========== WORLD-CLASS MOBILE MENU OVERLAY ========== */
    .mobile-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255,255,255,0.95);
      backdrop-filter: blur(24px) saturate(180%);
      -webkit-backdrop-filter: blur(24px) saturate(180%);
      z-index: 9999;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.4s ease, visibility 0.4s ease;
    }
    [data-theme="dark"] .mobile-overlay {
      background: rgba(14,16,22,0.95);
    }
    .mobile-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    .mobile-overlay .menu-close {
      position: absolute;
      top: 24px;
      right: 24px;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--bg-tertiary);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform 0.3s ease, background 0.2s;
      z-index: 2;
      cursor: pointer;
    }
    .mobile-overlay .menu-close:hover {
      transform: rotate(90deg);
      background: var(--bg-hover);
    }
    .mobile-overlay .menu-close svg {
      width: 20px;
      height: 20px;
      stroke: var(--text-primary);
      stroke-width: 2.5;
    }
    .mobile-menu-brand {
      position: absolute;
      top: 32px;
      left: 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: var(--font-bold);
      font-size: var(--text-lg);
      color: var(--text-primary);
      z-index: 2;
    }
    .mobile-menu-brand .brand-icon {
      width: 24px;
      height: 24px;
    }
    .mobile-nav-links {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-8);
      margin-top: -40px;
    }
    .mobile-nav-link {
      font-size: 2.5rem;
      font-weight: var(--font-bold);
      color: var(--text-primary);
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.4s ease, transform 0.4s ease, color 0.2s;
    }
    .mobile-overlay.active .mobile-nav-link {
      opacity: 1;
      transform: translateY(0);
    }
    .mobile-nav-link:hover {
      color: var(--primary);
    }
    .mobile-nav-link:nth-child(1) { transition-delay: 0.1s; }
    .mobile-nav-link:nth-child(2) { transition-delay: 0.2s; }
    .mobile-nav-link:nth-child(3) { transition-delay: 0.3s; }
    .mobile-nav-link:nth-child(4) { transition-delay: 0.4s; }
    .mobile-nav-link:nth-child(5) { transition-delay: 0.5s; }
    .mobile-nav-link:nth-child(6) { transition-delay: 0.6s; }
    .mobile-menu-footer {
      position: absolute;
      bottom: 40px;
      display: flex;
      gap: var(--space-6);
      opacity: 0;
      transition: opacity 0.5s ease 0.6s;
    }
    .mobile-overlay.active .mobile-menu-footer {
      opacity: 1;
    }
    .mobile-menu-footer a {
      font-size: var(--text-sm);
      color: var(--text-muted);
      transition: color 0.2s;
    }
    .mobile-menu-footer a:hover {
      color: var(--primary);
    }

    /* Hamburger animation */
    .mobile-menu-toggle.active span:nth-child(1) { transform: translateY(6px) rotate(45deg); }
    .mobile-menu-toggle.active span:nth-child(2) { opacity: 0; }
    .mobile-menu-toggle.active span:nth-child(3) { transform: translateY(-6px) rotate(-45deg); }

    /* ========== GLASSMORPHIC FOOTER ========== */
    .footer-glass {
      background: rgba(255,255,255,0.65) !important;
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border-top: 1px solid rgba(0,0,0,0.08);
      color: var(--text-primary) !important;
      padding: var(--space-16) 0 var(--space-8);
      position: relative;
      z-index: 1;
    }
    [data-theme="dark"] .footer-glass {
      background: rgba(20,22,31,0.7) !important;
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border-top: 1px solid rgba(255,255,255,0.06);
    }
    .footer-glass .footer-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: var(--space-8);
      margin-bottom: var(--space-8);
    }
    .footer-glass .footer-brand-desc {
      color: var(--text-secondary);
      max-width: 320px;
      margin-top: var(--space-4);
    }
    .footer-glass .footer-column h4 {
      font-size: var(--text-sm);
      font-weight: var(--font-semibold);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: var(--space-4);
      color: var(--text-primary);
    }
    .footer-glass .footer-links a {
      display: block;
      padding: 4px 0;
      font-size: var(--text-sm);
      color: var(--text-secondary);
      transition: color var(--transition-fast);
    }
    .footer-glass .footer-links a:hover {
      color: var(--primary);
    }
    .footer-glass .footer-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid var(--border-primary);
      padding-top: var(--space-6);
      font-size: var(--text-xs);
      color: var(--text-muted);
    }
    .footer-glass .footer-social a {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: var(--radius-full);
      background: var(--bg-tertiary);
      color: var(--text-secondary);
      font-weight: var(--font-medium);
      transition: background 0.3s ease, color 0.3s ease, transform 0.2s ease;
      margin-left: var(--space-4);
    }
    .footer-glass .footer-social a:hover {
      background: var(--primary);
      color: #fff;
      transform: translateY(-2px);
    }
    .footer-glass .footer-social a svg {
      width: 16px;
      height: 16px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
    }
    @media (max-width: 767px) {
      .footer-glass .footer-grid {
        grid-template-columns: 1fr;
        gap: var(--space-6);
        text-align: center;
      }
      .footer-glass .footer-brand-desc {
        max-width: 100%;
      }
      .footer-glass .footer-bottom {
        flex-direction: column;
        gap: var(--space-4);
        text-align: center;
      }
      .footer-glass .footer-social {
        margin-top: var(--space-4);
      }
    }

    /* ========== LOGIN PAGE SPECIFIC ========== */
    body {
      padding-top: var(--topbar-height);
    }
    .auth-layout {
      min-height: calc(100vh - var(--topbar-height));
      display: flex;
    }
    .w-full {
      width: 100%;
    }
    .auth-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-6);
    }
    .auth-forgot-link {
      font-size: var(--text-sm);
      color: var(--primary);
    }
    .auth-footer {
      text-align: center;
      margin-top: var(--space-6);
      font-size: var(--text-sm);
    }
    @media (max-width: 767px) {
      .auth-visual {
        display: flex !important;
        position: relative;
        width: 100%;
        height: auto;
        padding: 32px var(--space-4);
        background: var(--gradient-primary);
        color: #fff;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
      }
      .auth-visual-bg {
        display: none;
      }
      .auth-visual-content {
        position: static;
        margin-bottom: 24px;
      }
      .auth-visual-body {
        position: static;
        max-width: 400px;
        margin: 0 auto;
      }
      .auth-visual-title {
        font-size: 1.5rem;
      }
      .auth-visual-text {
        font-size: 0.9rem;
        opacity: 0.9;
      }
      .auth-visual-features {
        justify-content: center;
      }
      .auth-visual-feature {
        font-size: 0.9rem;
      }
      .auth-visual-footer {
        position: static;
        margin-top: 24px;
        font-size: 0.75rem;
        opacity: 0.7;
        flex-direction: column;
        gap: 8px;
      }
      .auth-layout {
        flex-direction: column;
      }
      .auth-form-side {
        width: 100%;
        padding: var(--space-6) var(--space-4);
      }
    }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <header class="navbar glass" id="navbar"></header>

  <!-- AUTH LAYOUT -->
  <div class="auth-layout">
    <div class="auth-visual">
      <div class="auth-visual-bg"></div>
      <div class="auth-visual-content">
        <a href="index.php" class="auth-visual-brand">
          <div class="auth-visual-brand-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
          RD Vendora
        </a>
      </div>
      <div class="auth-visual-body">
        <h2 class="auth-visual-title">Welcome back to your store</h2>
        <p class="auth-visual-text">Log in to manage your products, track orders, and grow your business.</p>
        <div class="auth-visual-features">
          <div class="auth-visual-feature"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Free 14-day trial, no credit card</div>
          <div class="auth-visual-feature"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Setup in under 10 minutes</div>
          <div class="auth-visual-feature"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Cancel anytime</div>
        </div>
      </div>
      <div class="auth-visual-footer">
        <span>© 2024 RD Vendora</span>
        <div class="auth-visual-social"><a href="#"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231z"/></svg></a><a href="#"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a></div>
      </div>
    </div>
    <div class="auth-form-side">
      <div class="auth-form-container anim-fade-in-up">
        <div class="auth-form-header">
          <h1 class="auth-form-title">Welcome back</h1>
          <p class="auth-form-subtitle">Enter your credentials to access your account.</p>
        </div>

        <!-- Display messages from loginuser.php -->
        <?php if ($errorMsg): ?>
          <div class="error-msg" style="background: var(--error-light); border: 1px solid var(--error); color: var(--error); padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
            <?= htmlspecialchars($errorMsg) ?>
          </div>
        <?php elseif ($successMsg): ?>
          <div class="success-msg" style="background: var(--success-light); border: 1px solid var(--success); color: var(--success-dark); padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
            <?= htmlspecialchars($successMsg) ?>
          </div>
        <?php endif; ?>

        <!-- Google Sign-In Button -->
        <a href="oauth2callback.php" class="btn btn-google">
          <svg width="18" height="18" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Continue with Google
        </a>

        <div class="auth-divider"><span>or</span></div>

        <!-- Regular login form -->
        <form id="login-form" method="POST" action="./includes/loginuser.php">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-input" id="login-email" name="email" placeholder="you@example.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="password-input-wrapper">
              <input type="password" class="form-input" id="login-password" name="password" placeholder="Enter your password" required>
              <span class="password-toggle" onclick="togglePassword('login-password', this)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
            </div>
          </div>
          <div class="auth-options">
            <label class="form-check" style="margin: 0;"><input type="checkbox" id="login-remember" name="remember"><span style="font-size: 14px; color: var(--text-secondary);">Remember me</span></label>
            <a href="forgot-password.php" class="auth-forgot-link">Forgot password?</a>
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content: center;" id="login-btn">Log In</button>
        </form>
        <div class="auth-footer">
          Don't have an account? <a href="register.php">Sign up</a>
        </div>
      </div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer footer-glass" id="footer"></footer>

  <!-- THEME TOGGLE BUTTON (bottom left) -->
  <button id="theme-toggle" style="position:fixed; bottom:16px; left:16px; z-index:999; background:var(--bg-secondary); border:1px solid var(--border-primary); border-radius:50%; width:40px; height:40px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:20px;">🌓</button>

  <script>
    // ========== THEME TOGGLE ==========
    const Theme = {
      init() {
        const saved = localStorage.getItem('RD Vendora_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        this.set(saved);
      },
      set(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('RD Vendora_theme', theme);
      },
      toggle() {
        const cur = document.documentElement.getAttribute('data-theme');
        const next = cur === 'dark' ? 'light' : 'dark';
        this.set(next);
        return next;
      }
    };
    document.getElementById('theme-toggle').addEventListener('click', () => Theme.toggle());
    Theme.init();

    // ========== PASSWORD TOGGLE ==========
    function togglePassword(fieldId, btn) {
      const field = document.getElementById(fieldId);
      if (!field) return;
      if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
      } else {
        field.type = 'password';
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
      }
    }

    // ========== NAVBAR INJECTION (with working hamburger) ==========
    (function() {
      const nav = document.getElementById('navbar');
      if (!nav) return;
      nav.innerHTML = `
        <div class="navbar-inner">
          <a href="index.php" class="navbar-brand">
            <div class="navbar-brand-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
            RD Vendora
          </a>
          <nav class="navbar-nav" id="navbar-nav">
            <a href="index.php" class="nav-link">Home</a>
            <a href="features.php" class="nav-link">Features</a>
            <a href="pricing.php" class="nav-link">Pricing</a>
            <a href="about.php" class="nav-link">About</a>
            <a href="contact.php" class="nav-link">Contact</a>
          </nav>
          <div class="navbar-actions">
            <a href="login.php" class="btn btn-ghost btn-sm">Log in</a>
            <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
            <button class="btn-icon mobile-menu-toggle" id="mobile-menu-toggle">
              <span></span><span></span><span></span>
            </button>
          </div>
        </div>`;

      const toggle = document.getElementById('mobile-menu-toggle');
      if (toggle) {
        let overlay = document.querySelector('.mobile-overlay');
        if (!overlay) {
          overlay = document.createElement('div');
          overlay.className = 'mobile-overlay';
          overlay.innerHTML = `
            <div class="mobile-menu-brand">
              <svg class="brand-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
              RD Vendora
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
            </div>`;
          document.body.appendChild(overlay);
        }

        const closeBtn = document.getElementById('menu-close');
        const openMenu = () => {
          overlay.classList.add('active');
          toggle.classList.add('active');
          document.body.style.overflow = 'hidden';
        };
        const closeMenu = () => {
          overlay.classList.remove('active');
          toggle.classList.remove('active');
          document.body.style.overflow = '';
        };

        // Clone to remove old listeners (simple but effective)
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
        const newClose = closeBtn ? closeBtn.cloneNode(true) : null;
        if (closeBtn && newClose) closeBtn.parentNode.replaceChild(newClose, closeBtn);

        newToggle.addEventListener('click', openMenu);
        if (newClose) newClose.addEventListener('click', closeMenu);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeMenu(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && overlay.classList.contains('active')) closeMenu(); });
        overlay.querySelectorAll('.mobile-nav-link').forEach(link => link.addEventListener('click', closeMenu));
      }

      window.addEventListener('scroll', () => {
        if (window.scrollY > 50) nav.classList.add('scrolled', 'glass');
        else nav.classList.remove('scrolled', 'glass');
      });
    })();

    // ========== FOOTER INJECTION ==========
    (function() {
      const footer = document.getElementById('footer');
      if (!footer) return;
      footer.innerHTML = `
        <div class="container">
          <div class="footer-grid">
            <div class="footer-brand">
              <a href="index.php" class="navbar-brand" style="margin-bottom:16px;">
                <div class="navbar-brand-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
                RD Vendora
              </a>
              <p class="footer-brand-desc">The complete multi-vendor eCommerce platform. Build, manage, and scale your online business with powerful tools.</p>
            </div>
            <div class="footer-column">
              <h4>Product</h4>
              <div class="footer-links">
                <a href="features.php">Features</a>
                <a href="pricing.php">Pricing</a>
                <a href="storefront.php">Store Demo</a>
                <a href="#">Changelog</a>
              </div>
            </div>
            <div class="footer-column">
              <h4>Company</h4>
              <div class="footer-links">
                <a href="about.php">About</a>
                <a href="contact.php">Contact</a>
                <a href="faq.php">FAQ</a>
                <a href="#">Careers</a>
              </div>
            </div>
            <div class="footer-column">
              <h4>Legal</h4>
              <div class="footer-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#">Security</a>
                <a href="#">Cookies</a>
              </div>
            </div>
          </div>
          <div class="footer-bottom">
            <p class="footer-copyright">© 2024 RD Vendora. All rights reserved.</p>
            <b>Designed By RD NEXA TECH</b>
          </div>
        </div>`;
    })();
  </script>
  <script src="assets/js/rdv-public.js" defer></script>
</body>
</html>