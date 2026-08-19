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

$authPageTitle = 'Forgot Password - RD Vendora';
$authVisualTitle = 'Reset access to your store';
$authVisualText = 'We will email a reset link if that address has an RD Vendora account.';
$authVisualFeatures = [
    'Link expires after one hour',
    'Your password stays unchanged until you finish',
    'Back to login whenever you need',
];
require __DIR__ . '/includes/auth_layout_start.php';
?>
        <div class="auth-form-header">
          <h1 class="auth-form-title">Forgot password?</h1>
          <p class="auth-form-subtitle">Enter your email and we will send a reset link if the account exists.</p>
        </div>
        <?php if ($message): ?>
          <div class="auth-alert <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-input" id="email" name="email" required placeholder="you@example.com" autocomplete="email">
          </div>
          <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">Send reset link</button>
        </form>
        <div class="auth-footer">
          <a href="login.php">Back to login</a>
        </div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>