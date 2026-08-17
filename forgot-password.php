<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ========== SMTP CONFIGURATION – EDIT THESE ==========
$smtp_host   = 'smtp.gmail.com';        // Your SMTP server
$smtp_port   = 587;                      // 587 for TLS, 465 for SSL
$smtp_user   = 'mrrayjohnson2@gmail.com';   // Your email address
$smtp_pass   = 'tpkt rcnc lgmw wzzp';      // Your app password (Gmail requires App Password)
$smtp_from   = 'mrrayjohnson2@gmail.com';
$smtp_from_name = 'RD Vendora Marketplace';

// ========== SMTP Function ==========
function sendSmtpMail($to, $subject, $htmlBody, $fromEmail, $fromName, $smtpHost, $smtpPort, $smtpUser, $smtpPass) {
    $crlf = "\r\n";
    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: $fromName <$fromEmail>",
        "Reply-To: $fromEmail"
    ];
    $header = implode($crlf, $headers);
    
    $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
    if (!$socket) return false;
    
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') return false;
    
    fputs($socket, "HELO " . gethostname() . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return false;
    
    fputs($socket, "STARTTLS" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') return false;
    
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    
    fputs($socket, "HELO " . gethostname() . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return false;
    
    fputs($socket, "AUTH LOGIN" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') return false;
    
    fputs($socket, base64_encode($smtpUser) . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') return false;
    
    fputs($socket, base64_encode($smtpPass) . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') return false;
    
    fputs($socket, "MAIL FROM: <$fromEmail>" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return false;
    
    fputs($socket, "RCPT TO: <$to>" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return false;
    
    fputs($socket, "DATA" . $crlf);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '354') return false;
    
    fputs($socket, "Subject: $subject$crlf$header$crlf$crlf$htmlBody$crlf.$crlf");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') return false;
    
    fputs($socket, "QUIT" . $crlf);
    fclose($socket);
    return true;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            // Delete old tokens (fixed: removed 'used' column condition)
            $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $del->bind_param("i", $user['id']);
            $del->execute();
            $del->close();

            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $user['id'], $token, $expires);
            $ins->execute();
            $ins->close();

            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/reset-password.php?token=" . urlencode($token);

            $subject = "Password Reset - RD Vendora";
            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Password Reset</title>
                <style>
                    @media only screen and (max-width: 600px) {
                        .container { width: 100% !important; padding: 20px !important; }
                        .button { display: block !important; width: 100% !important; text-align: center !important; }
                        .reset-link { word-break: break-all !important; }
                    }
                </style>
            </head>
            <body style="margin:0; padding:0; background:#f4f7fb; font-family: Arial, Helvetica, sans-serif;">
                <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:20px 0;">
                    <tr>
                        <td align="center">
                            <table class="container" width="500" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:20px; box-shadow:0 4px 12px rgba(0,0,0,0.05); width:500px; max-width:90%;">
                                <tr>
                                    <td style="padding:30px 30px 20px; text-align:center; border-bottom:1px solid #e5e7eb;">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                            <line x1="3" y1="6" x2="21" y2="6"/>
                                            <path d="M16 10a4 4 0 0 1-8 0"/>
                                        </svg>
                                        <h1 style="margin:12px 0 0; font-size:24px; color:#111827;">RD Vendora</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:30px;">
                                        <h2 style="margin:0 0 12px; font-size:20px; color:#111827;">Reset Your Password</h2>
                                        <p style="margin:0 0 20px; color:#4b5563; line-height:1.5;">We received a request to reset your password. Click the button below to create a new password. This link expires in 1 hour.</p>
                                        <a href="'.$resetLink.'" class="button" style="display:inline-block; background:#6366f1; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:30px; font-weight:600; margin:10px 0 20px;">Reset Password</a>
                                        <p style="margin:20px 0 0; font-size:14px; color:#6b7280;">If the button doesn’t work, copy and paste this link into your browser:</p>
                                        <p class="reset-link" style="margin:8px 0 0; font-size:12px; color:#6b7280; word-break:break-all; background:#f9fafb; padding:12px; border-radius:8px;">'.$resetLink.'</p>
                                        <hr style="margin:25px 0 0; border:0; border-top:1px solid #e5e7eb;">
                                        <p style="margin:20px 0 0; font-size:12px; color:#9ca3af;">If you didn’t request this, please ignore this email. Your password will not be changed.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:20px; text-align:center; background:#f9fafb; border-radius:0 0 20px 20px;">
                                        <p style="margin:0; font-size:12px; color:#6b7280;">© '.date('Y').' RD Vendora – All rights reserved.</p>
                                    </td>
                                </tr>
                            </table>
                        </table>
                    </tr>
                </table>
            </body>
            </html>';

            $sent = sendSmtpMail($email, $subject, $htmlBody, $smtp_from, $smtp_from_name, $smtp_host, $smtp_port, $smtp_user, $smtp_pass);

            if ($sent) {
                $message = "Password reset link has been sent to your email address.";
                $messageType = "success";
            } else {
                $message = "Email could not be sent. Please try again later or contact support.";
                $messageType = "error";
            }
        } else {
            $message = "If that email address exists in our system, you will receive a reset link.";
            $messageType = "success";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RD Vendora</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        [data-theme="dark"] .card {
            background: #1e1e2f;
            color: #e0e0e0;
        }
        .logo { text-align: center; margin-bottom: 30px; }
        h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; text-align: center; }
        .subtitle { text-align: center; color: #6b7280; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.2);
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s;
        }
        button:hover { transform: translateY(-1px); }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #6366f1; text-decoration: none; font-size: 14px; }
        .message {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .message.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .message.info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        [data-theme="dark"] .message.success { background: #064e3b; color: #a7f3d0; }
        [data-theme="dark"] .message.error { background: #7f1d1d; color: #fecaca; }
        [data-theme="dark"] .message.info { background: #1e3a8a; color: #bfdbfe; }
        [data-theme="dark"] input { background: #2d2d3f; border-color: #3a3a4f; color: #e0e0e0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
        </div>
        <h1>Forgot password?</h1>
        <p class="subtitle">Enter your email and we'll send you a reset link.</p>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email address</label>
                <input type="email" name="email" required placeholder="your@email.com">
            </div>
            <button type="submit">Send reset link</button>
        </form>
        <div class="back-link">
            <a href="login.php">← Back to login</a>
        </div>
    </div>
    <script>
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefersDark) document.documentElement.setAttribute('data-theme', 'dark');
    </script>
</body>
</html>