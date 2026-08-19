<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
    }
}

if (!adminHasPermission('send_email', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to send emails.</p><a href="admin.php">Go to Dashboard</a></div>');
}

$emails = $conn->query("SELECT DISTINCT user_email, user_name FROM orders WHERE user_email IS NOT NULL AND user_email != '' ORDER BY user_name ASC")->fetch_all(MYSQLI_ASSOC);
$sendStatus = '';
$sendError = '';

$smtp_host   = 'smtp.gmail.com';
$smtp_port   = 587;
$smtp_user   = 'mrrayjohnson2@gmail.com';
$smtp_pass   = 'tpkt rcnc lgmw wzzp';
$smtp_from   = 'mrrayjohnson2@gmail.com';
$smtp_from_name = 'RD Vendora Marketplace';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $email_list = $_POST['email_list'] ?? [];
    $custom_email = trim($_POST['custom_email'] ?? '');

    if (!empty($custom_email)) {
        $recipients = [$custom_email];
    } elseif (!empty($email_list)) {
        $recipients = $email_list;
    } else {
        $sendError = 'Please select at least one recipient.';
    }

    if (empty($sendError) && empty($subject)) $sendError = 'Subject is required.';
    if (empty($sendError) && empty($message)) $sendError = 'Message is required.';

    if (empty($sendError)) {
        $companyName    = 'RD Vendora Marketplace';
        $companyWebsite = 'https://rdvendora.com';
        $year = date('Y');

        // Prepare message (convert newlines to <br> and escape HTML)
        $messageHtml = nl2br(htmlspecialchars($message));

        // ==== SIMPLIFIED HTML EMAIL – NO LOGO ====
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
                                    <span style="font-size:24px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px;">RD Vendora</span>
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
                                                <span style="color:#1E293B; font-weight:600;">' . $companyName . '</span><br>
                                                <a href="' . $companyWebsite . '" style="color:#1A56DB; text-decoration:none;">' . $companyWebsite . '</a><br>
                                                &copy; ' . $year . ' ' . $companyName . ' — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is a promotional email. You are receiving this because you are a valued customer.</span>
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
        foreach ($recipients as $to) {
            if (sendSmtpMail($to, $subject, $htmlBody, $smtp_from, $smtp_from_name, $smtp_host, $smtp_port, $smtp_user, $smtp_pass)) {
                $successCount++;
            }
        }
        if ($successCount > 0) {
            $sendStatus = "Email sent to $successCount recipient(s).";
        } else {
            $sendError = "Failed to send emails. Check SMTP credentials and server settings.";
        }
    }
}

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

$adminPageTitle = 'Send Email to Customers - Admin';
$adminPageHeading = 'Send Email';
$adminPageSubtitle = 'Message customers from the admin panel';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="email-form-container">
        <div class="form-card">
            <?php if ($sendStatus): ?>
                <div class="alert alert-success"><?= htmlspecialchars($sendStatus) ?></div>
            <?php endif; ?>
            <?php if ($sendError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($sendError) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Select Customer(s) (multiple allowed)</label>
                    <div class="checkbox-group" id="emailCheckboxGroup">
                        <?php foreach ($emails as $c): ?>
                            <label>
                                <input type="checkbox" name="email_list[]" value="<?= htmlspecialchars($c['user_email']) ?>">
                                <?= htmlspecialchars($c['user_name'] ?: $c['user_email']) ?> (<?= htmlspecialchars($c['user_email']) ?>)
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($emails)): ?>
                            <p>No customer emails found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Or enter custom email address</label>
                    <input type="email" name="custom_email" class="form-input" placeholder="customer@example.com">
                </div>

                <hr>

                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-input" required placeholder="Special Offer from RD Vendora">
                </div>

                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-textarea" required placeholder="Hello valued customer, ..."></textarea>
                </div>

                <button type="submit" name="send_email" class="btn-send">Send Email</button>
            </form>
        </div>
    </div>
<script>
// Search filter for email checkboxes
    const searchInput = document.getElementById('searchEmails');
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

    
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
