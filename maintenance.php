<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ------------------ REDIRECT IF MAINTENANCE IS OFF ------------------
$modeStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
$modeStmt->execute();
$modeResult = $modeStmt->get_result();
$maintenanceMode = '0';
if ($row = $modeResult->fetch_assoc()) {
    $maintenanceMode = $row['setting_value'];
}
if ($maintenanceMode !== '1') {
    header('Location: index.php');
    exit;
}
// --------------------------------------------------------------------

// Fetch maintenance end time (only used if maintenance is on)
$maintenanceEnd = '';
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_end_time'");
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $maintenanceEnd = $row['setting_value'];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance - RD Vendora</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ============================================================
       RD Vendora Design System (copied from index.php)
       ============================================================ */
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
      --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
      --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      --gradient-error: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      --gradient-mesh: radial-gradient(ellipse at 20% 50%, rgba(37,99,235,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, rgba(245,158,11,0.06) 0%, transparent 50%),
                        radial-gradient(ellipse at 40% 80%, rgba(16,185,129,0.04) 0%, transparent 50%);
      --gradient-hero: linear-gradient(135deg, #1e3a8a 0%, #f59e0b 100%);
      --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
      --text-xs: 0.75rem; --text-sm: 0.8125rem; --text-base: 0.9375rem;
      --text-lg: 1.125rem; --text-xl: 1.25rem; --text-2xl: 1.5rem;
      --text-3xl: 2rem; --text-4xl: 2.5rem; --text-5xl: 3.5rem;
      --font-normal: 400; --font-medium: 500; --font-semibold: 600; --font-bold: 700;
      --leading-none: 1; --leading-tight: 1.25; --leading-snug: 1.375;
      --leading-normal: 1.5; --leading-relaxed: 1.625;
      --space-0: 0; --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem;
      --space-4: 1rem; --space-5: 1.25rem; --space-6: 1.5rem; --space-8: 2rem;
      --space-10: 2.5rem; --space-12: 3rem; --space-16: 4rem; --space-20: 5rem; --space-24: 6rem;
      --radius-sm: 6px; --radius-md: 10px; --radius-lg: 16px; --radius-xl: 20px; --radius-2xl: 28px; --radius-full: 9999px;
      --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04);
      --shadow-lg: 0 8px 24px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
      --shadow-xl: 0 16px 48px rgba(0,0,0,0.10), 0 8px 16px rgba(0,0,0,0.04);
      --shadow-glow: 0 0 40px rgba(37,99,235,0.15);
      --shadow-glow-sm: 0 0 20px rgba(37,99,235,0.10);
      --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
      --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
      --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
      --z-base: 0; --z-dropdown: 100; --z-sticky: 200; --z-fixed: 300;
      --z-modal-backdrop: 400; --z-modal: 500; --z-popover: 600; --z-tooltip: 700;
      --z-toast: 800; --z-overlay: 900;
      --sidebar-width: 260px; --sidebar-collapsed: 72px; --topbar-height: 64px;
      --container-max: 1280px; --container-wide: 1440px;
    }
    [data-theme="dark"] {
      --bg-primary: #0c0e14; --bg-secondary: #14161f; --bg-tertiary: #1a1d28;
      --bg-elevated: #1e2130; --bg-hover: #242838; --bg-active: #2a2e40;
      --surface-primary: #14161f; --surface-secondary: #1a1d28; --surface-tertiary: #1e2130;
      --text-primary: #e8eaf0; --text-secondary: #9ca3b0; --text-muted: #6b7280;
      --text-inverse: #1a1d23; --border-primary: #2d3139; --border-secondary: #3a3f4a;
      --border-focus: #2563eb; --primary: #3b82f6; --primary-hover: #60a5fa;
      --primary-light: rgba(59,130,246,0.15); --primary-dark: #1e3a8a;
      --warning: #f59e0b; --warning-light: rgba(245,158,11,0.15); --warning-dark: #b45309;
      --shadow-xs: 0 1px 2px rgba(0,0,0,0.20);
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.25), 0 1px 2px rgba(0,0,0,0.20);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.30), 0 2px 4px rgba(0,0,0,0.20);
      --shadow-lg: 0 8px 24px rgba(0,0,0,0.35), 0 4px 8px rgba(0,0,0,0.25);
      --shadow-xl: 0 16px 48px rgba(0,0,0,0.40), 0 8px 16px rgba(0,0,0,0.30);
      --shadow-glow: 0 0 40px rgba(59,130,246,0.20);
      --shadow-glow-sm: 0 0 20px rgba(59,130,246,0.15);
    }
    /* ========== Global Reset & Base ========== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; }
    body {
      font-family: var(--font-sans);
      font-size: var(--text-base);
      line-height: var(--leading-normal);
      color: var(--text-primary);
      background: var(--bg-primary);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    a { color: inherit; text-decoration: none; }
    .container {
      width: 100%;
      max-width: var(--container-max);
      margin: 0 auto;
      padding: 0 var(--space-6);
    }
    /* ========== Glass Navbar (reduced) ========== */
    .navbar {
      padding: var(--space-4) 0;
      background: rgba(255,255,255,0.7);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(0,0,0,0.06);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    [data-theme="dark"] .navbar {
      background: rgba(20,22,31,0.7);
      border-bottom-color: rgba(255,255,255,0.06);
    }
    .navbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      font-weight: var(--font-bold);
      font-size: var(--text-lg);
      color: var(--text-primary);
    }
    .navbar-brand-icon svg {
      width: 24px;
      height: 24px;
      stroke: var(--primary);
    }
    /* ========== Maintenance Card ========== */
    .maintenance-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-8) var(--space-4);
    }
    .maintenance-card {
      max-width: 560px;
      width: 100%;
      background: var(--bg-secondary);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--border-primary);
      border-radius: var(--radius-xl);
      padding: var(--space-12) var(--space-8);
      text-align: center;
      box-shadow: var(--shadow-xl);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .maintenance-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-glow);
    }
    .maintenance-icon {
      width: 80px;
      height: 80px;
      margin: 0 auto var(--space-6);
      border-radius: 50%;
      background: var(--gradient-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: 0 8px 24px rgba(37,99,235,0.25);
    }
    .maintenance-icon svg {
      width: 40px;
      height: 40px;
      stroke: white;
      stroke-width: 1.8;
    }
    .maintenance-title {
      font-size: var(--text-3xl);
      font-weight: var(--font-bold);
      margin-bottom: var(--space-3);
      color: var(--text-primary);
    }
    .maintenance-text {
      font-size: var(--text-base);
      color: var(--text-secondary);
      margin-bottom: var(--space-6);
      line-height: 1.6;
    }
    .maintenance-text strong {
      color: var(--primary);
    }
    .countdown-container {
      display: flex;
      justify-content: center;
      gap: var(--space-4);
      margin: var(--space-6) 0;
      flex-wrap: wrap;
    }
    .countdown-item {
      background: var(--bg-tertiary);
      border-radius: var(--radius-lg);
      padding: var(--space-3) var(--space-4);
      min-width: 70px;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--border-primary);
    }
    .countdown-item span {
      display: block;
      font-size: var(--text-2xl);
      font-weight: var(--font-bold);
      color: var(--primary);
    }
    .countdown-item small {
      font-size: var(--text-xs);
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    /* ========== Footer ========== */
    .footer-glass {
      background: rgba(255,255,255,0.65);
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border-top: 1px solid rgba(0,0,0,0.08);
      color: var(--text-primary);
      padding: var(--space-8) 0 var(--space-4);
      margin-top: auto;
    }
    [data-theme="dark"] .footer-glass {
      background: rgba(20,22,31,0.7);
      border-top-color: rgba(255,255,255,0.06);
    }
    .footer-glass .footer-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: var(--text-xs);
      color: var(--text-muted);
      flex-wrap: wrap;
      gap: var(--space-4);
    }
    .footer-glass .footer-bottom a {
      color: var(--text-muted);
      transition: color var(--transition-fast);
    }
    .footer-glass .footer-bottom a:hover {
      color: var(--primary);
    }
    .footer-glass .footer-social {
      display: flex;
      gap: var(--space-3);
    }
    .footer-glass .footer-social a {
      padding: 6px 12px;
      border-radius: var(--radius-full);
      background: var(--bg-tertiary);
      font-size: var(--text-xs);
      font-weight: var(--font-medium);
      transition: background 0.2s, color 0.2s;
    }
    .footer-glass .footer-social a:hover {
      background: var(--primary);
      color: white;
    }
    /* ========== Responsive ========== */
    @media (max-width: 767px) {
      .maintenance-card { padding: var(--space-8) var(--space-4); }
      .maintenance-title { font-size: var(--text-2xl); }
      .countdown-container { gap: var(--space-2); }
      .countdown-item { min-width: 60px; padding: var(--space-2) var(--space-3); }
      .countdown-item span { font-size: var(--text-xl); }
      .footer-glass .footer-bottom { flex-direction: column; text-align: center; }
    }
    /* ========== Animations ========== */
    .anim-fade-in-up {
      opacity: 0;
      transform: translateY(20px);
      animation: fadeInUp 0.8s ease forwards;
    }
    @keyframes fadeInUp {
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <!-- ========== SIMPLIFIED NAVBAR ========== -->
  <header class="navbar">
    <div class="container navbar-inner">
      <a href="index.php" class="navbar-brand">
        <span class="navbar-brand-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
            <path d="M2 17l10 5 10-5"/>
            <path d="M2 12l10 5 10-5"/>
          </svg>
        </span>
        RD Vendora
      </a>
      <!-- No action buttons -->
    </div>
  </header>

  <!-- ========== MAINTENANCE CONTENT ========== -->
  <main class="maintenance-wrapper">
    <div class="maintenance-card anim-fade-in-up">
      <div class="maintenance-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
      </div>
      <h1 class="maintenance-title">Under Maintenance</h1>
      <p class="maintenance-text">
        We are performing scheduled upgrades to serve you better.<br>
        <strong>We will be back shortly.</strong>
      </p>

      <!-- Countdown -->
      <?php if (!empty($maintenanceEnd)): ?>
      <div class="countdown-container" id="countdown">
        <div class="countdown-item"><span id="days">00</span><small>Days</small></div>
        <div class="countdown-item"><span id="hours">00</span><small>Hours</small></div>
        <div class="countdown-item"><span id="minutes">00</span><small>Minutes</small></div>
        <div class="countdown-item"><span id="seconds">00</span><small>Seconds</small></div>
      </div>
      <p style="font-size:var(--text-xs); color:var(--text-muted); margin-bottom:var(--space-6);">
        Estimated time remaining
      </p>
      <?php endif; ?>
    </div>
  </main>

  <!-- ========== FOOTER (GLASS) ========== -->
  <footer class="footer-glass">
    <div class="container">
      <div class="footer-bottom">
        <span>© 2026 RD Vendora. All rights reserved.</span>
        <div class="footer-social">
          <a href="#">Twitter</a>
          <a href="#">GitHub</a>
          <a href="#">LinkedIn</a>
        </div>
        <span>
          <a href="#">Privacy</a> · <a href="#">Terms</a>
        </span>
      </div>
    </div>
  </footer>

  <!-- ========== COUNTDOWN SCRIPT ========== -->
  <?php if (!empty($maintenanceEnd)): ?>
  <script>
    (function() {
      const endTime = new Date('<?= $maintenanceEnd ?>').getTime();

      function updateCountdown() {
        const now = new Date().getTime();
        let diff = endTime - now;
        if (diff <= 0) {
          document.getElementById('days').textContent = '00';
          document.getElementById('hours').textContent = '00';
          document.getElementById('minutes').textContent = '00';
          document.getElementById('seconds').textContent = '00';
          return;
        }
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        document.getElementById('days').textContent = String(days).padStart(2, '0');
        document.getElementById('hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
        document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
      }

      updateCountdown();
      setInterval(updateCountdown, 1000);
    })();
  </script>
  <?php endif; ?>

  <!-- Theme toggle (mini) – same as main site -->
  <script>
    (function() {
      const saved = localStorage.getItem('RD Vendora_theme') || 
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', saved);
      const toggleBtn = document.createElement('button');
      toggleBtn.textContent = saved === 'dark' ? '☀️' : '🌙';
      toggleBtn.style.position = 'fixed';
      toggleBtn.style.bottom = '16px';
      toggleBtn.style.left = '16px';
      toggleBtn.style.zIndex = '999';
      toggleBtn.style.background = 'var(--bg-secondary)';
      toggleBtn.style.border = '1px solid var(--border-primary)';
      toggleBtn.style.borderRadius = '50%';
      toggleBtn.style.width = '40px';
      toggleBtn.style.height = '40px';
      toggleBtn.style.cursor = 'pointer';
      toggleBtn.style.fontSize = '1.2rem';
      toggleBtn.onclick = function() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('RD Vendora_theme', next);
        this.textContent = next === 'dark' ? '☀️' : '🌙';
      };
      document.body.appendChild(toggleBtn);
    })();
  </script>
</body>
</html>