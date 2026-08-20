<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login?error=Not logged in');
    exit();
}

require_once 'includes/connection.php';
require_once 'includes/subscription_check.php';
require_once 'includes/smtp_config.php';

$smtp = rdv_smtp_settings();
$smtp_host   = $smtp['host'];
$smtp_port   = $smtp['port'];
$smtp_user   = $smtp['username'];
$smtp_pass   = $smtp['password'];
$smtp_from   = $smtp['from'];
$smtp_from_name = $smtp['from_name'];

if (empty($smtp_user) || empty($smtp_pass)) {
    error_log("SMTP credentials missing. Emails cannot be sent until SMTP is set in .env or Admin Settings.");
}

// ---------- Ensure store exists and get store_id ----------
$stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$storeResult = $stmt->get_result();
if ($storeResult->num_rows === 0) {
    header("Location: create-store");
    exit();
}
$storeData = $storeResult->fetch_assoc();
$storeId = $storeData['id'];
$storeName = $storeData['store_name'];
$stmt->close();

// ---------- Check if store is active ----------
if (!isStoreActive($conn, $_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Store Disabled</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⛔ Store Disabled</h1>
        <p>Your store has been disabled by the administrator. Please contact support.</p>
        <a href="logout">Logout</a>
    </body>
    </html>
    <?php
    exit();
}

// ---------- Get active plan (only Empire allowed) ----------
$activePlan = null;
$isEmpire = false;
$stmt = $conn->prepare("SELECT plan FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$planRow = $stmt->get_result()->fetch_assoc();
$activePlan = $planRow['plan'] ?? null;
$stmt->close();

$isEmpire = ($activePlan === 'Empire');

// ---------- Fetch customers from orders (distinct email only) ----------
$customers = [];
if ($isEmpire) {
    $sql = "SELECT DISTINCT user_email, user_name 
            FROM orders 
            WHERE store_id = ? 
            AND user_email IS NOT NULL 
            ORDER BY user_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $storeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();
}
$conn->close();

// ---------- Handle email submission (only Empire) ----------
$emailMessage = '';
$emailMessageType = '';

// Enhanced SMTP function with detailed error reporting
function sendSmtpMail($to, $subject, $htmlBody, $fromEmail, $fromName, $smtpHost, $smtpPort, $smtpUser, $smtpPass, &$errorMsg = '') {
    $crlf = "\r\n";
    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: $fromName <$fromEmail>",
        "Reply-To: $fromEmail"
    ];
    $header = implode($crlf, $headers);
    
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
    if (!$socket) {
        $errorMsg = "Cannot connect to SMTP server ($smtpHost:$smtpPort) - $errstr ($errno)";
        return false;
    }
    
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        $errorMsg = "SMTP greeting failed: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "HELO " . gethostname() . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "HELO failed: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "STARTTLS" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        $errorMsg = "STARTTLS failed: $response";
        fclose($socket);
        return false;
    }
    
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        $errorMsg = "TLS handshake failed";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "HELO " . gethostname() . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "HELO after TLS failed: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "AUTH LOGIN" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        $errorMsg = "AUTH LOGIN not accepted: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, base64_encode($smtpUser) . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        $errorMsg = "Username rejected: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, base64_encode($smtpPass) . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') {
        $errorMsg = "Password rejected: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "MAIL FROM: <$fromEmail>" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "MAIL FROM rejected: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "RCPT TO: <$to>" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "RCPT TO rejected: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "DATA" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '354') {
        $errorMsg = "DATA command failed: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "Subject: $subject$crlf$header$crlf$crlf$htmlBody$crlf.$crlf");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "Message body rejected: $response";
        fclose($socket);
        return false;
    }
    
    fputs($socket, "QUIT" . $crlf);
    fclose($socket);
    return true;
}

if ($isEmpire && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $recipients = $_POST['email_list'] ?? [];
    $custom_email = trim($_POST['custom_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $messageText = trim($_POST['email_message'] ?? '');
    
    if (!empty($custom_email)) $recipients = [$custom_email];
    if (empty($recipients)) {
        $emailMessage = 'Please select at least one recipient.';
        $emailMessageType = 'error';
    } elseif (empty($subject)) {
        $emailMessage = 'Subject is required.';
        $emailMessageType = 'error';
    } elseif (empty($messageText)) {
        $emailMessage = 'Message is required.';
        $emailMessageType = 'error';
    } else {
        // ===== BUILD STUNNING EMAIL TEMPLATE (Royal Blue & Gold) =====
        $senderName = "RD Vendora" . " - " .( htmlspecialchars($storeName));
        $storeLink = function_exists('rdv_store_url') && !empty($_SESSION['store_slug'])
            ? rdv_store_url(['id' => (int) ($_SESSION['store_id'] ?? 0), 'store_slug' => (string) $_SESSION['store_slug']])
            : (rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/') . '/storefront.php');
        $year = date('Y');
        $messageHtml = nl2br(htmlspecialchars($messageText));

        $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $subject . '</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">

                        <!-- HEADER – Royal Blue with Gold border -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
                            <tr>
                                <td style="padding:22px 30px; text-align:center;">
                                    <span style="font-size:24px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px;">' . $senderName . '</span>
                                </td>
                            </tr>
                        </table>

                        <!-- BODY -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:32px 30px 24px 30px;">
                                    <div style="font-size:16px; color:#1E293B; line-height:1.7;">
                                        ' . $messageHtml . '
                                    </div>

                                    <!-- FOOTER -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">' . $senderName . '</span><br>
                                                <a href="' . $storeLink . '" style="color:#1A56DB; text-decoration:none;">' . $storeLink . '</a><br>
                                                &copy; ' . $year . ' ' . $senderName . ' — All Rights Reserved.<br>
                                                <!-- ADDED: RD Vendora attribution -->
                                                <div style="font-size:12px; color:#94a3b8; margin-top:6px;">
                                                    Sent via <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">RD Vendora</a> – Multi‑vendor Marketplace
                                                </div>
                                                <span style="font-size:12px; color:#94a3b8;">This is a promotional email from ' . $senderName . '. You are receiving this because you are a valued customer.</span>
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>';

        $successCount = 0;
        $errors = [];
        foreach ($recipients as $email) {
            $errorDetail = '';
            if (sendSmtpMail($email, $subject, $htmlBody, $smtp_from, $senderName, $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $errorDetail)) {
                $successCount++;
            } else {
                $errors[] = "$email: $errorDetail";
            }
        }
        if ($successCount > 0) {
            $emailMessage = "Email sent to $successCount recipient(s).";
            if (!empty($errors)) $emailMessage .= " Errors: " . implode('; ', $errors);
            $emailMessageType = 'success';
        } else {
            $emailMessage = "Failed to send emails. Details: " . implode('; ', $errors);
            $emailMessageType = 'error';
        }
    }
}

$fullname = $_SESSION['fullname'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication - <?= htmlspecialchars($storeName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ========== FULL DASHBOARD STYLES (same as your dashboard.php) ========== */
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
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06), 0 2px 4px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08), 0 4px 8px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.10), 0 8px 16px rgba(0, 0, 0, 0.04);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.15);
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: 500ms cubic-bezier(0.34, 1.56, 0.64, 1);
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
            --primary-light: rgba(99, 102, 241, 0.15);
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.20);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.25), 0 1px 2px rgba(0, 0, 0, 0.20);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.30), 0 2px 4px rgba(0, 0, 0, 0.20);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.35), 0 4px 8px rgba(0, 0, 0, 0.25);
            --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.40), 0 8px 16px rgba(0, 0, 0, 0.30);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.20);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            scroll-behavior: smooth;
            -webkit-font-smoothing: antialiased;
        }
        body {
            font-family: var(--font-sans);
            font-size: var(--text-base);
            line-height: var(--leading-normal);
            color: var(--text-primary);
            background: var(--bg-primary);
            min-height: 100vh;
            transition: background var(--transition-base), color var(--transition-base);
        }
        a { color: inherit; text-decoration: none; }
        button { cursor: pointer; border: none; background: none; }
        input, select { font-family: inherit; font-size: inherit; color: inherit; }
        ul, ol { list-style: none; }
        img { max-width: 100%; display: block; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-secondary); border-radius: var(--radius-full); }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-primary);
            display: flex;
            flex-direction: column;
            z-index: 300;
            transition: width var(--transition-slow), transform var(--transition-slow);
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--space-4) var(--space-5);
            height: var(--topbar-height);
            border-bottom: 1px solid var(--border-primary);
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            font-weight: 700;
            font-size: var(--text-lg);
            white-space: nowrap;
        }
                .rdv-brand-logo { height: 36px; width: auto; max-width: 140px; object-fit: contain; background: #fff; border-radius: 8px; padding: 2px 6px; display: block; }
        .rdv-brand-name { font-weight: 800; font-size: 1.05rem; letter-spacing: -0.03em; white-space: nowrap; }
        .sidebar.collapsed .rdv-brand-logo { max-width: 40px; height: 32px; padding: 1px; }
        .sidebar-brand-icon {
            width: 34px;
            height: 34px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .sidebar-brand-text { transition: opacity var(--transition-fast); }
        .sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; overflow: hidden; }
        .sidebar-toggle {
            width: 30px;
            height: 30px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: all var(--transition-fast);
        }
        .sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-3);
        }
        .sidebar-section-title {
            padding: var(--space-2) var(--space-4);
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-top: var(--space-2);
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            font-size: var(--text-sm);
            font-weight: 500;
            transition: all var(--transition-fast);
            margin-bottom: 1px;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: var(--primary-light);
            color: var(--primary);
        }
        .sidebar-footer {
            padding: var(--space-3);
            border-top: 1px solid var(--border-primary);
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md);
            cursor: pointer;
        }
        .sidebar-user:hover { background: var(--bg-hover); }
        .sidebar-user-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
        .sidebar-user-info { flex: 1; min-width: 0; }
        .sidebar-user-name { font-size: var(--text-sm); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-role { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 299;
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition-base);
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: all; }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-slow);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            height: var(--topbar-height);
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--space-6);
            z-index: 200;
            gap: var(--space-4);
            backdrop-filter: blur(12px);
        }
        [data-theme="light"] .topbar { background: rgba(255,255,255,0.85); }
        [data-theme="dark"] .topbar { background: rgba(20,22,31,0.85); }
        .topbar-left { display: flex; align-items: center; gap: var(--space-3); }
        .mobile-sidebar-toggle {
            display: none;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
        }
        .mobile-sidebar-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .topbar-actions { display: flex; align-items: center; gap: var(--space-2); }
        .theme-toggle {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
        }
        .theme-toggle:hover { background: var(--bg-hover); color: var(--text-primary); }
        .dropdown { position: relative; }
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 240px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            opacity: 0;
            pointer-events: none;
            transform: translateY(-8px);
            transition: all var(--transition-fast);
        }
        .dropdown.open .dropdown-menu { opacity: 1; pointer-events: all; transform: translateY(0); }
        .dropdown-item {
            display: block;
            padding: 8px 16px;
            color: var(--text-secondary);
        }
        .dropdown-item:hover { background: var(--bg-tertiary); }

        /* Page content */
        .page-content { flex: 1; padding: var(--space-6); }
        .page-header { margin-bottom: var(--space-6); }
        .page-title {
            font-size: var(--text-2xl);
            font-weight: 700;
            background: var(--gradient-primary);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }
        .page-subtitle { font-size: var(--text-sm); color: var(--text-secondary); margin-top: 4px; }
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
        }
        .form-group { margin-bottom: var(--space-4); }
        .form-label { display: block; font-weight: 500; margin-bottom: 6px; }
        .form-input, .form-textarea {
            width: 100%;
            padding: var(--space-3);
            background: var(--bg-tertiary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
        }
        .form-input:disabled, .form-textarea:disabled, .checkbox-group.disabled label {
            opacity: 0.6;
            cursor: not-allowed;
            filter: grayscale(0.1);
        }
        .checkbox-group.disabled input {
            pointer-events: none;
        }
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius-md);
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: var(--space-3);
            background: var(--bg-tertiary);
        }
        .checkbox-group label { display: block; margin-bottom: 8px; }
        .alert { padding: var(--space-4); border-radius: var(--radius-lg); margin-bottom: var(--space-4); }
        .alert-success { background: var(--success-light); color: var(--success-dark); }
        .alert-error { background: var(--error-light); color: var(--error-dark); }
        .alert-warning {
            background: var(--warning-light);
            color: var(--warning-dark);
            border-left: 4px solid var(--warning);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .alert-warning a {
            background: var(--warning);
            color: white;
            padding: 6px 16px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-sidebar-toggle { display: flex; }
            .page-content { padding: var(--space-4); }
            .card { padding: var(--space-4); }
            .topbar { padding: 0 var(--space-3); }
        }
        .toast-container {
            position: fixed;
            top: calc(var(--topbar-height) + var(--space-4));
            right: var(--space-4);
            z-index: 800;
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
        }
        .toast {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-5);
            box-shadow: var(--shadow-xl);
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="./" class="sidebar-brand">
                <img class="rdv-brand-logo" src="assets/brand-logo.png" alt=""><span class="rdv-brand-name">RD Vendora</span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6" /></svg>
            </button>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-title">Main</div>
            <a href="dashboard" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="products" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="sidebar-link-text">Products</span>
            </a>
            <a href="orders" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="sidebar-link-text">Orders</span>
            </a>
            <a href="customers" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="sidebar-link-text">Customers</span>
            </a>
            <div class="sidebar-section-title">Store</div>
            <a href="storefront" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="sidebar-link-text">Storefront</span>
            </a>
            <a href="settings" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="sidebar-link-text">Store Settings</span>
            </a>
            <a href="subscription" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span class="sidebar-link-text">Subscription</span>
            </a>
            <a href="vendor-chat" class="sidebar-link"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span class="sidebar-link-text">Chat</span></a>
            <a href="vendor-communication" class="sidebar-link active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span class="sidebar-link-text">Communication</span>
            </a>
            <div class="sidebar-section-title">Account</div>
            <a href="profile" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span class="sidebar-link-text">Profile</span>
            </a>
            <a href="#" class="sidebar-link" onclick="handleLogout()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="sidebar-link-text">Logout</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" alt="User" class="sidebar-user-avatar">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($fullname) ?></div>
                    <div class="sidebar-user-role">🏪 <?= htmlspecialchars($storeName) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">☰</button>
                <div class="topbar-search" style="position:relative; flex:1; max-width:320px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="position:absolute; left:12px; top:11px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="globalSearch" placeholder="Search customers..." style="width:100%; padding:8px 12px 8px 36px; background:var(--bg-tertiary); border:1px solid var(--border-primary); border-radius:var(--radius-md);">
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" id="themeToggle">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <div class="dropdown" id="userDropdown">
                    <div class="topbar-user dropdown-trigger" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                        <span><?= htmlspecialchars($fullname) ?></span>
                    </div>
                    <div class="dropdown-menu">
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Profile')">Profile</a>
                        <a href="#" class="dropdown-item" onclick="showToast('info','Coming Soon','Settings')">Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item" onclick="handleLogout()">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="page-content">
            <div class="page-header">
                <h1 class="page-title">Customer Communication</h1>
                <p class="page-subtitle">Send emails to your customers – Empire Plan Feature</p>
            </div>

            <?php if ($emailMessage): ?>
                <div class="alert alert-<?= $emailMessageType ?>"><?= htmlspecialchars($emailMessage) ?></div>
            <?php endif; ?>

            <?php if (!$isEmpire): ?>
                <div class="alert alert-warning">
                    <span>⚠️ <strong>Empire Plan Required</strong> – Upgrade to Empire to send emails to your customers.</span>
                    <a href="subscription">Upgrade to Empire →</a>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3>📧 Send Email</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Select Customer(s) (email)</label>
                        <div class="checkbox-group <?= !$isEmpire ? 'disabled' : '' ?>" id="emailCheckboxGroup">
                            <?php if ($isEmpire): ?>
                                <?php foreach ($customers as $c): ?>
                                    <label>
                                        <input type="checkbox" name="email_list[]" value="<?= htmlspecialchars($c['user_email']) ?>">
                                        <?= htmlspecialchars($c['user_name'] ?: $c['user_email']) ?> (<?= htmlspecialchars($c['user_email']) ?>)
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: var(--text-muted); text-align: center; padding: 10px;">Empire plan required to see customer list</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Or custom email</label>
                        <input type="email" name="custom_email" class="form-input" placeholder="customer@example.com" <?= !$isEmpire ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-input" required <?= !$isEmpire ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="email_message" class="form-textarea" rows="6" required placeholder="Write your email..." <?= !$isEmpire ? 'disabled' : '' ?>></textarea>
                    </div>
                    <button type="submit" name="send_email" class="btn-primary" <?= !$isEmpire ? 'disabled' : '' ?>>Send Email</button>
                </form>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Sidebar toggle (same as dashboard)
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        function toggleSidebar() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
            } else {
                sidebar.classList.toggle('collapsed');
            }
        }
        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
        if (mobileToggle) mobileToggle.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Theme toggle
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.innerHTML = savedTheme === 'light' ? '🌙' : '☀️';
            themeToggle.addEventListener('click', () => {
                const newTheme = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('RD Vendora-theme', newTheme);
                themeToggle.innerHTML = newTheme === 'light' ? '🌙' : '☀️';
            });
        }

        // Search filter for customer checkboxes (only if Empire)
        <?php if ($isEmpire): ?>
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const labels = document.querySelectorAll('#emailCheckboxGroup label');
                    labels.forEach(label => {
                        const text = label.innerText.toLowerCase();
                        label.style.display = text.includes(filter) ? '' : 'none';
                    });
                });
            }
        <?php endif; ?>

        // Dropdown for user menu
        const userDD = document.getElementById('userDropdown');
        if (userDD) {
            const trigger = userDD.querySelector('.dropdown-trigger');
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                userDD.classList.toggle('open');
            });
            document.addEventListener('click', () => userDD.classList.remove('open'));
        }

        // Simple toast
        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `<div><strong>${title}</strong><br><small>${message}</small></div>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        function handleLogout() { if (confirm('Logout?')) window.location.href='logout'; }
    </script>
</body>
</html>