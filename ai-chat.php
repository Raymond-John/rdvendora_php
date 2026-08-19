<?php
session_start();

// If this is an AJAX request, we want clean JSON – no HTML errors
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    error_reporting(0);
    header('Content-Type: application/json');
    ob_start();
}

if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['error' => 'Not logged in']);
        exit();
    }
    header('Location: login.php?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Get store details ----------
$stmt = $conn->prepare("SELECT id, store_name, status FROM stores WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$storeResult = $stmt->get_result();
if ($storeResult->num_rows === 0) {
    header("Location: create-store.php");
    exit();
}
$storeData = $storeResult->fetch_assoc();
$_SESSION['store_id'] = $storeData['id'];
$_SESSION['store_name'] = $storeData['store_name'];
$storeStatus = $storeData['status'];
$stmt->close();

$storeRestricted = ($storeStatus === 'pending' || $storeStatus === 'inactive');
$restrictionMessage = $storeStatus === 'pending' ? '⏳ Your store is pending approval.' : '⛔ Your store has been suspended.';

// ---------- Empire plan check (case-insensitive) ----------
$hasEmpire = false;
$activePlan = null;

$hasSubscription = hasActiveSubscription($conn, $_SESSION['user_id']);
if ($hasSubscription) {
    $stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $planRow = $stmt->get_result()->fetch_assoc();
    $activePlan = $planRow['plan'] ?? null;
    $hasEmpire = ($activePlan && strtolower(trim($activePlan)) === 'empire');
    $stmt->close();
}

// ---------- User display name ----------
if (!isset($_SESSION['fullname'])) {
    $stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $_SESSION['fullname'] = $stmt->get_result()->fetch_assoc()['fullname'] ?? 'User';
    $stmt->close();
}

// ==================== GROQ API CONFIGURATION ====================
define('GROQ_API_KEY', rdv_env('GROQ_API_KEY', ''));
define('GROQ_API_URL', 'https://api.groq.com/openai/v1');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');

function callGroqAPI($endpoint, $payload) {
    $ch = curl_init(GROQ_API_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $msg = $error['error']['message'] ?? 'Unknown API error';
        throw new Exception("Groq API error (HTTP $httpCode): $msg");
    }
    return json_decode($response, true);
}

// ---------- Handle AJAX chat ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ob_clean();
    
    if (!$hasEmpire) {
        echo json_encode(['error' => 'AI chat is only available for Empire plan users.']);
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    if ($action === 'chat') {
        $messages = json_decode($_POST['messages'] ?? '[]', true);
        if (empty($messages)) {
            echo json_encode(['error' => 'No messages provided.']);
            exit();
        }
        
        // ========== CUSTOM SYSTEM PROMPT WITH RD VENDORA INFO ==========
        $systemPrompt = [
            'role' => 'system',
            'content' => "You are the official AI assistant for RD Vendora, a complete multi-vendor eCommerce platform. Your job is to help store owners and merchants with accurate information about RD Vendora. Always be friendly, helpful, and concise (2-4 sentences when possible). If you don't know an answer, politely say so and suggest contacting support.\n\n" .
                         "=== ABOUT RD VENDORA ===\n" .
                         "RD Vendora is an all-in-one eCommerce platform that lets anyone build, manage, and scale an online store. It offers drag-and-drop store builder, inventory management, secure payments (Stripe, PayPal, Square), real-time analytics, email marketing tools, and multi-vendor marketplace support.\n\n" .
                         "=== PRICING PLANS ===\n" .
                         "- Launch (Free forever): up to 10 products, basic features, no transaction fees.\n" .
                         "- Growth ($49/month or $470/year): unlimited products, advanced inventory, analytics, email marketing.\n" .
                         "- Scale ($149/month or $1,430/year): everything in Growth + priority support, advanced reporting, and API access.\n" .
                         "- Empire ($399/month or $3,830/year): full platform access, dedicated account manager, custom development, and AI tools (including this chat).\n\n" .
                         "All paid plans include a 14-day free trial. Annual plans get a 20% discount.\n\n" .
                         "=== KEY FEATURES ===\n" .
                         "• Drag & drop store builder (no coding)\n" .
                         "• Multi‑vendor marketplace support\n" .
                         "• Abandoned cart recovery & email campaigns\n" .
                         "• Real-time sales analytics & customer insights\n" .
                         "• PCI‑compliant payments with 100+ gateways\n" .
                         "• Free SSL certificates, GDPR & CCPA tools\n" .
                         "• 24/7 customer support via email and chat\n\n" .
                         "=== CONTACT & SUPPORT ===\n" .
                         "• General inquiries: hello@rdvendora.com\n" .
                         "• Technical support: support@rdvendora.com\n" .
                         "• Sales (enterprise): sales@rdvendora.com\n" .
                         "• Office: 123 Commerce Street, San Francisco, CA 94105\n\n" .
                         "=== NOTES ===\n" .
                         "• You cannot generate images. If asked, politely explain that image generation is not available but you can write a detailed prompt for the user.\n" .
                         "• Always encourage users to visit the website (rdvendora.com) or contact support for complex issues.\n" .
                         "• Keep responses professional and helpful.\n" .
                         "• If the user asks about store creation, direct them to 'register.php' on the site."
        ];
        
        $apiMessages = array_merge([$systemPrompt], $messages);
        
        $payload = [
            'model' => GROQ_MODEL,
            'messages' => $apiMessages,
            'temperature' => 0.7,
            'max_tokens' => 800
        ];
        
        try {
            $data = callGroqAPI('/chat/completions', $payload);
            $assistantMessage = $data['choices'][0]['message']['content'];
            
            echo json_encode([
                'success' => true,
                'message' => $assistantMessage,
                'image_url' => null
            ]);
        } catch (Exception $e) {
            error_log("Groq Chat Error: " . $e->getMessage());
            echo json_encode(['error' => 'AI service error: ' . $e->getMessage()]);
        }
        exit();
    }
    echo json_encode(['error' => 'Invalid action']);
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat - Empire Plan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* ========== FULL DASHBOARD CSS (exactly from your original dashboard.php) ========== */
        :root {
            --bg-primary: #f8f9fb;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f1f3f6;
            --bg-elevated: #ffffff;
            --bg-hover: #eef0f4;
            --bg-active: #e4e7ed;
            --surface-primary: #ffffff;
            --surface-secondary: #f8f9fb;
            --surface-tertiary: #f1f3f6;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --text-inverse: #ffffff;
            --border-primary: #e5e7eb;
            --border-secondary: #d1d5db;
            --border-focus: #6366f1;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #4338ca;
            --success: #10b981;
            --success-light: #ecfdf5;
            --success-dark: #047857;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --warning-dark: #b45309;
            --error: #ef4444;
            --error-light: #fef2f2;
            --error-dark: #b91c1c;
            --info: #3b82f6;
            --info-light: #eff6ff;
            --info-dark: #1d4ed8;
            --gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%);
            --gradient-hero: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
            --text-xs: 0.75rem;
            --text-sm: 0.8125rem;
            --text-base: 0.9375rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --text-4xl: 2.25rem;
            --font-normal: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-bold: 700;
            --leading-tight: 1.25;
            --leading-snug: 1.375;
            --leading-normal: 1.5;
            --leading-relaxed: 1.625;
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
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 28px;
            --radius-full: 9999px;
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06),0 2px 4px rgba(0,0,0,0.04);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.08),0 4px 8px rgba(0,0,0,0.04);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.10),0 8px 16px rgba(0,0,0,0.04);
            --shadow-glow: 0 0 40px rgba(99,102,241,0.15);
            --transition-fast: 150ms cubic-bezier(0.4,0,0.2,1);
            --transition-base: 250ms cubic-bezier(0.4,0,0.2,1);
            --transition-slow: 350ms cubic-bezier(0.4,0,0.2,1);
            --transition-bounce: 500ms cubic-bezier(0.34,1.56,0.64,1);
            --z-dropdown: 100;
            --z-sticky: 200;
            --z-fixed: 300;
            --z-modal-backdrop: 400;
            --z-modal: 500;
            --z-toast: 800;
            --sidebar-width: 260px;
            --sidebar-collapsed: 72px;
            --topbar-height: 64px;
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
            --border-primary: #2d3139;
            --border-secondary: #3a3f4a;
            --primary-light: rgba(99,102,241,0.15);
            --shadow-xs: 0 1px 2px rgba(0,0,0,0.20);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.25),0 1px 2px rgba(0,0,0,0.20);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.30),0 2px 4px rgba(0,0,0,0.20);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.35),0 4px 8px rgba(0,0,0,0.25);
            --shadow-xl: 0 16px 48px rgba(0,0,0,0.40),0 8px 16px rgba(0,0,0,0.30);
            --shadow-glow: 0 0 40px rgba(99,102,241,0.20);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        body {
            font-family: var(--font-sans);
            font-size: var(--text-base);
            line-height: var(--leading-normal);
            color: var(--text-primary);
            background: var(--bg-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background var(--transition-base), color var(--transition-base);
        }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; font-size: inherit; color: inherit; }
        input, select { font-family: inherit; font-size: inherit; color: inherit; }
        ul, ol { list-style: none; }
        img { max-width: 100%; display: block; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-secondary); border-radius: var(--radius-full); }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
        ::selection { background: var(--primary-light); color: var(--primary); }

        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex; flex-direction: column;
            z-index: var(--z-fixed);
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
            flex-shrink: 0; gap: var(--space-3);
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: var(--space-3);
            font-weight: var(--font-bold); font-size: var(--text-lg);
            color: var(--text-primary); white-space: nowrap; overflow: hidden;
        }
                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
        .sidebar-brand-icon {
            width: 34px; height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            color: white; flex-shrink: 0;
        }
        .sidebar-brand-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-toggle {
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-muted);
            transition: all var(--transition-fast); flex-shrink: 0;
            background: transparent; border: none; cursor: pointer;
        }
        .sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar.collapsed .sidebar-toggle svg { transform: rotate(180deg); }
        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: var(--space-3) var(--space-3); }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px; font-weight: var(--font-semibold);
            text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--text-muted); white-space: nowrap;
            transition: all var(--transition-fast);
            margin-top: var(--space-2);
        }
        .sidebar.collapsed .sidebar-section-title { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        .sidebar-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary); font-size: var(--text-sm); font-weight: var(--font-medium);
            transition: all var(--transition-fast);
            white-space: nowrap; cursor: pointer; text-decoration: none; margin-bottom: 1px;
        }
        .sidebar-link:hover:not(.disabled) { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-link.active { background: var(--primary-light); color: var(--primary); font-weight: var(--font-semibold); }
        .sidebar-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: auto;
        }
        .sidebar-link.disabled:hover {
            background: none;
            color: var(--text-secondary);
        }
        .sidebar-link svg { flex-shrink: 0; width: 18px; height: 18px; }
        .sidebar-link-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-link-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-footer { padding: var(--space-3); border-top: 1px solid var(--border-primary); flex-shrink: 0; }
        .sidebar-user {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md); transition: background var(--transition-fast); cursor: pointer;
        }
        .sidebar-user:hover { background: var(--bg-hover); }
        .sidebar-user-avatar { width: 34px; height: 34px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .sidebar-user-info { flex: 1; min-width: 0; transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-user-info { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: calc(var(--z-fixed) - 1); opacity: 0; pointer-events: none; transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }

        /* Main content */
        .main-content { margin-left: var(--sidebar-width); transition: margin-left var(--transition-slow); min-height: 100vh; display: flex; flex-direction: column; }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        .topbar {
            position: sticky; top: 0; height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 var(--space-6); z-index: var(--z-sticky);
            gap: var(--space-4); backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar { background: rgba(255,255,255,0.85); }
        [data-theme="dark"] .topbar { background: rgba(20,22,31,0.85); }
        .topbar-left { display: flex; align-items: center; gap: var(--space-3); }
        .mobile-sidebar-toggle {
            display: none; width: 38px; height: 38px;
            align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary);
            transition: all var(--transition-fast); flex-shrink: 0;
        }
        .mobile-sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-search { flex: 1; max-width: 420px; position: relative; }
        .topbar-search svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 16px; height: 16px; pointer-events: none; }
        .topbar-search input {
            width: 100%; padding: var(--space-2) var(--space-4) var(--space-2) 40px;
            background: var(--bg-tertiary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-md); font-size: var(--text-sm); outline: none;
            transition: all var(--transition-fast); color: var(--text-primary);
        }
        .topbar-search input::placeholder { color: var(--text-muted); }
        .topbar-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); background: var(--bg-secondary); }
        .topbar-actions { display: flex; align-items: center; gap: var(--space-2); }
        .topbar-btn {
            position: relative; width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary);
            transition: all var(--transition-fast); flex-shrink: 0;
        }
        .topbar-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-btn-badge {
            position: absolute; top: 6px; right: 6px; width: 8px; height: 8px;
            background: var(--error); border-radius: var(--radius-full); border: 2px solid var(--bg-secondary);
        }
        .theme-toggle {
            width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-md); color: var(--text-secondary); transition: all var(--transition-fast); flex-shrink: 0;
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        [data-theme="light"] .theme-toggle .icon-moon { display: none; }
        [data-theme="dark"] .theme-toggle .icon-sun { display: none; }
        .topbar-user {
            display: flex; align-items: center; gap: var(--space-2);
            padding: var(--space-1) var(--space-3) var(--space-1) var(--space-1);
            border-radius: var(--radius-md); cursor: pointer; transition: background var(--transition-fast);
        }
        .topbar-user:hover { background: var(--bg-hover); }
        .topbar-user-avatar { width: 32px; height: 32px; border-radius: var(--radius-full); object-fit: cover; flex-shrink: 0; }
        .topbar-user-info { display: flex; flex-direction: column; line-height: 1.2; }
        .topbar-user-name { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); }
        .topbar-user-role { font-size: var(--text-xs); color: var(--text-muted); }

        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0; min-width: 240px;
            background: var(--bg-secondary); border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg); box-shadow: var(--shadow-xl);
            z-index: var(--z-dropdown); opacity: 0; pointer-events: none;
            transform: translateY(-8px); transition: all var(--transition-fast); overflow: hidden;
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-header { padding: var(--space-4); border-bottom: 1px solid var(--border-primary); display: flex; align-items: center; justify-content: space-between; }
        .dropdown-header h4 { font-size: var(--text-sm); font-weight: var(--font-semibold); }
        .dropdown-header a { font-size: var(--text-xs); color: var(--primary); cursor: pointer; }
        .dropdown-header a:hover { text-decoration: underline; }
        .dropdown-item {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); font-size: var(--text-sm);
            color: var(--text-secondary); transition: all var(--transition-fast); cursor: pointer; text-decoration: none;
        }
        .dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown-item svg { flex-shrink: 0; width: 16px; height: 16px; }
        .dropdown-divider { height: 1px; background: var(--border-primary); margin: var(--space-1) 0; }
        .notification-list { max-height: 320px; overflow-y: auto; }
        .notification-item {
            display: flex; align-items: flex-start; gap: var(--space-3);
            padding: var(--space-3) var(--space-4); cursor: pointer;
            transition: background var(--transition-fast); border-bottom: 1px solid var(--border-primary);
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background: var(--bg-hover); }
        .notification-item.unread { background: var(--primary-light); }
        .notification-dot { width: 8px; height: 8px; background: var(--primary); border-radius: var(--radius-full); flex-shrink: 0; margin-top: 6px; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--text-primary); }
        .notification-text { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
        .notification-time { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        .page-content { flex: 1; padding: var(--space-6); overflow-y: auto; }
        .page-header { margin-bottom: var(--space-6); }
        .page-title { font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--text-primary); letter-spacing: -0.02em; }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: var(--space-1); }

        /* Chat-specific styles */
        .chat-container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 240px);
            min-height: 500px;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-4);
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        .message {
            display: flex;
            gap: 12px;
            max-width: 85%;
        }
        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .message-bubble {
            background: var(--bg-tertiary);
            padding: 12px 16px;
            border-radius: 20px;
            font-size: var(--text-sm);
            line-height: 1.5;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .message.user .message-bubble {
            background: var(--primary);
            color: white;
        }
        .chat-input-area {
            border-top: 1px solid var(--border-primary);
            padding: var(--space-4);
            display: flex;
            gap: 12px;
            background: var(--bg-secondary);
        }
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border-primary);
            border-radius: 40px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-family: inherit;
            resize: none;
            font-size: var(--text-sm);
        }
        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .send-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 40px;
            padding: 0 24px;
            font-weight: var(--font-semibold);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .send-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
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
        .restriction-banner {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-5);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-3);
            flex-wrap: wrap;
        }
        .restriction-banner.warning { background: #fef2f2; border-left-color: #dc2626; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: var(--space-2); padding: var(--space-3) var(--space-5);
            font-size: var(--text-sm); font-weight: var(--font-semibold);
            border-radius: var(--radius-md); transition: all var(--transition-fast);
            cursor: pointer; white-space: nowrap; border: 1px solid transparent;
        }
        .copy-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: var(--font-medium);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        .copy-btn:hover {
            background: var(--bg-hover);
            border-color: var(--border-secondary);
        }
        @media (max-width: 768px) {
            .chat-container { height: calc(100vh - 180px); }
            .message { max-width: 95%; }
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); z-index: var(--z-fixed); }
            .sidebar.mobile-open { transform: translateX(0); box-shadow: var(--shadow-xl); }
            .sidebar.collapsed { width: var(--sidebar-width); transform: translateX(-100%); }
            .sidebar.collapsed.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .topbar-search { max-width: 200px; }
            .page-content { padding: var(--space-4); }
        }
        @media (max-width: 480px) {
            .topbar-search { display: none; }
            .topbar { padding: 0 var(--space-3); }
            .page-title { font-size: var(--text-xl); }
            .restriction-banner { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6" /></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard.php" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /></svg>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="products.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" /><line x1="3" y1="6" x2="21" y2="6" /><path d="M16 10a4 4 0 0 1-8 0" /></svg>
                <span class="sidebar-link-text">Products</span>
            </a>
            <a href="orders.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" /></svg>
                <span class="sidebar-link-text">Orders</span>
            </a>
            <a href="customers.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                <span class="sidebar-link-text">Customers</span>
            </a>
            <div class="sidebar-section-title">Store</div>
            <a href="storefront.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>
                <span class="sidebar-link-text">Storefront</span>
            </a>
            <a href="settings.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
                <span class="sidebar-link-text">Store Settings</span>
            </a>
            <a href="subscription.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
                <span class="sidebar-link-text">Subscription</span>
            </a>
            <div class="sidebar-section-title">AI Tools</div>
            <a href="ai-chat.php" class="sidebar-link active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2a10 10 0 1 0 10 10 10 10 0 0 0-10-10zM12 6v4M12 16h.01"/><line x1="12" y1="12" x2="12" y2="12"/></svg>
                <span class="sidebar-link-text">AI Chat</span>
            </a>
            <div class="sidebar-section-title">Account</div>
            <a href="profile.php" class="sidebar-link <?= $storeRestricted ? 'disabled' : '' ?>" <?= $storeRestricted ? 'onclick="return false;"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
                <span class="sidebar-link-text">Profile</span>
            </a>
            <a href="#" class="sidebar-link" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" /></svg>
                <span class="sidebar-link-text">Logout</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="sidebar-user-avatar">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                    <div class="sidebar-user-role">
                        <?php if ($_SESSION['store_name']): ?>
                            🏪 <?= htmlspecialchars($_SESSION['store_name']) ?>
                        <?php else: ?>
                            <a href="create-store.php" style="color: var(--primary);">Create Store</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-content" id="mainContent">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Toggle sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
                </button>
                <div class="topbar-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
                    <input type="text" id="globalSearch" placeholder="Search...">
                </div>
            </div>
            <div class="topbar-actions">
                <?php if ($activePlan): ?>
                <span style="background: var(--primary-light); padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; margin-right: 10px;">
                    🚀 <?= htmlspecialchars($activePlan) ?>
                </span>
                <?php endif; ?>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5" /><line x1="12" y1="1" x2="12" y2="3" /><line x1="12" y1="21" x2="12" y2="23" /><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" /><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" /><line x1="1" y1="12" x2="3" y2="12" /><line x1="21" y1="12" x2="23" y2="12" /><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" /><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" /></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>
                </button>

                <div class="dropdown" id="notificationDropdown">
                    <button class="topbar-btn dropdown-trigger" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                        <span class="topbar-btn-badge"></span>
                    </button>
                    <div class="dropdown-menu" style="width:340px;">
                        <div class="dropdown-header"><h4>Notifications</h4><a onclick="markAllRead()">Mark all read</a></div>
                        <div class="notification-list" id="notificationList"></div>
                    </div>
                </div>

                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="topbar-user-avatar">
                        <div class="topbar-user-info">
                            <span class="topbar-user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                            <span class="topbar-user-role">
                                <?php if ($_SESSION['store_name']): ?>
                                    Store: <?= htmlspecialchars($_SESSION['store_name']) ?>
                                <?php else: ?>
                                    <a href="create-store.php" style="color:var(--primary);">Create Store</a>
                                <?php endif; ?>
                            </span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);flex-shrink:0;"><polyline points="6 9 12 15 18 9" /></svg>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profile</a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings page coming soon')"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <?php if (!$hasEmpire): ?>
                <div class="restriction-banner warning" style="background:#fef2f2; border-left-color:#dc2626;">
                    <div class="icon">⚡</div>
                    <div class="message">
                        <strong>Empire Plan Required</strong>
                        <p>AI chat is exclusively available for Empire plan subscribers.</p>
                    </div>
                    <a href="subscription.php" class="btn" style="background:#dc2626; color:white;">Upgrade to Empire →</a>
                </div>
            <?php else: ?>
                <?php if ($storeRestricted): ?>
                    <div class="restriction-banner <?= $storeStatus === 'inactive' ? 'warning' : '' ?>">
                        <div class="icon"><?= $storeStatus === 'pending' ? '⏳' : '⚠️' ?></div>
                        <div class="message">
                            <strong><?= $storeStatus === 'pending' ? 'Store Pending Approval' : 'Store Suspended' ?></strong>
                            <p><?= htmlspecialchars($restrictionMessage) ?> However, AI chat is still available.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="page-header">
                    <h1 class="page-title">🤖 AI Chat Assistant</h1>
                    <p class="page-subtitle">Ask me anything about RD Vendora – pricing, features, store setup, and more. (Powered by Groq)</p>
                </div>

                <div class="chat-container">
                    <div class="chat-messages" id="chatMessages">
                        <div class="message assistant">
                            <div class="message-avatar">🤖</div>
                            <div class="message-bubble">Hello! 👋 I'm the RD Vendora AI assistant. I can answer any question about our platform – from pricing and features to store setup and support. What would you like to know?</div>
                        </div>
                    </div>
                    <div class="chat-input-area">
                        <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type your message..."></textarea>
                        <button class="send-btn" id="sendBtn">Send</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ========== DASHBOARD JAVASCRIPT (theme, sidebar, notifications, toast) ==========
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        document.getElementById('themeToggle')?.addEventListener('click', () => {
            const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('RD Vendora-theme', next);
        });

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            if (window.innerWidth <= 768) toggleMobile();
            else sidebar.classList.toggle('collapsed');
        });
        document.getElementById('mobileSidebarToggle')?.addEventListener('click', toggleMobile);
        overlay?.addEventListener('click', toggleMobile);
        function toggleMobile() {
            sidebar.classList.toggle('mobile-open');
            overlay?.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay?.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        document.addEventListener('click', e => {
            ['userDropdown', 'notificationDropdown'].forEach(id => {
                const dd = document.getElementById(id);
                if (dd && !dd.contains(e.target)) dd.classList.remove('open');
                else if (e.target.closest('.dropdown-trigger')) dd?.classList.toggle('open');
            });
        });

        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<div class="toast-content"><strong>${escapeHtml(title)}</strong><div>${escapeHtml(message)}</div></div>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }
        function escapeHtml(str) { return String(str).replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); }
        function handleLogout() { if(confirm('Are you sure you want to log out?')) window.location.href='logout.php'; }

        // Notifications (mock)
        let notifications = [
            {id:1,title:'New Order Received',text:'Order #1287 has been placed.',time:'2 minutes ago',unread:true},
            {id:2,title:'Payment Confirmed',text:'Payment of $245.00 confirmed.',time:'15 minutes ago',unread:true},
            {id:3,title:'Product Low Stock',text:'Wireless Headphones Pro is low stock.',time:'1 hour ago',unread:true}
        ];
        function renderNotifications() {
            const list = document.getElementById('notificationList');
            if (!list) return;
            const unread = notifications.filter(n=>n.unread).length;
            const badge = document.querySelector('.topbar-btn-badge');
            if(badge) badge.style.display = unread ? 'block' : 'none';
            list.innerHTML = notifications.map(n => `<div class="notification-item ${n.unread ? 'unread' : ''}" onclick="markNotificationRead(${n.id})">${n.unread ? '<div class="notification-dot"></div>' : '<div style="width:8px;"></div>'}<div class="notification-content"><div class="notification-title">${escapeHtml(n.title)}</div><div class="notification-text">${escapeHtml(n.text)}</div><div class="notification-time">${escapeHtml(n.time)}</div></div></div>`).join('');
        }
        function markNotificationRead(id) { const n = notifications.find(x=>x.id===id); if(n) n.unread=false; renderNotifications(); }
        function markAllRead() { notifications.forEach(n=>n.unread=false); renderNotifications(); }
        renderNotifications();

        // ========== CHAT FUNCTIONALITY (only for Empire users) ==========
        <?php if ($hasEmpire): ?>
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendBtn');
        
        let conversation = []; // stores {role, content}
        
        function addMessage(role, content, imageUrl = null) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            const avatar = role === 'user' ? '👤' : '🤖';
            let bubbleContent = `<div class="message-avatar">${avatar}</div><div class="message-bubble">${escapeHtml(content)}`;
            if (imageUrl) {
                bubbleContent += `<div><img src="${imageUrl}" class="message-image" style="max-width:200px; border-radius:12px; margin-top:8px;"><br><a href="${imageUrl}" download="ai_image.png" class="copy-btn" style="display:inline-block; margin-top:6px;">⬇️ Download</a></div>`;
            }
            bubbleContent += `</div>`;
            messageDiv.innerHTML = bubbleContent;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            // Store in conversation (text only)
            if (role !== 'typing') {
                conversation.push({ role: role === 'user' ? 'user' : 'assistant', content: content });
                if (conversation.length > 20) conversation.shift();
            }
        }
        
        function showTyping() {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message assistant';
            typingDiv.id = 'typingIndicator';
            typingDiv.innerHTML = `<div class="message-avatar">🤖</div><div class="typing-indicator"><span></span><span></span><span></span></div>`;
            chatMessages.appendChild(typingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function hideTyping() {
            const el = document.getElementById('typingIndicator');
            if (el) el.remove();
        }
        
        async function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;
            chatInput.value = '';
            addMessage('user', message);
            showTyping();
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'chat',
                        messages: JSON.stringify(conversation)
                    })
                });
                const data = await response.json();
                hideTyping();
                if (data.error) {
                    addMessage('assistant', `❌ Error: ${data.error}`);
                } else {
                    addMessage('assistant', data.message, data.image_url || null);
                }
            } catch (err) {
                hideTyping();
                addMessage('assistant', `❌ Network error: ${err.message}`);
            }
        }
        
        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>