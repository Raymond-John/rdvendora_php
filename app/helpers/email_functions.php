<?php
/**
 * RD Vendora Email Functions – Premium Styled Emails
 * All emails use Royal Blue (#0A3D91) & Gold (#D4AF37) theme.
 */

// ----- Load PHPMailer (if available) -----
$phpmailer_available = function_exists('rdv_load_phpmailer') ? rdv_load_phpmailer() : false;
if (!function_exists('rdv_smtp_settings')) {
    require_once APP_PATH . '/helpers/smtp_config.php';
}
require_once APP_PATH . '/helpers/email_template.php';
require_once APP_PATH . '/helpers/order_actions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------- SMTP Mailer ----------
function getMailer() {
    $mail = new PHPMailer(true);
    $smtp = function_exists('rdv_smtp_settings') ? rdv_smtp_settings() : [
        'host' => rdv_env('SMTP_HOST', 'smtp.gmail.com'),
        'port' => (int) rdv_env('SMTP_PORT', 587),
        'username' => rdv_env('SMTP_USER', ''),
        'password' => rdv_env('SMTP_PASS', ''),
        'encryption' => rdv_env('SMTP_ENCRYPTION', 'tls'),
        'from' => rdv_env('SMTP_FROM', rdv_env('SMTP_USER', 'notifications@rdvendora.com')),
        'from_name' => rdv_env('SMTP_FROM_NAME', 'RD Vendora'),
    ];
    $mail->isSMTP();
    $mail->Host       = $smtp['host'];
    $mail->SMTPAuth   = ($smtp['username'] !== '' && $smtp['password'] !== '');
    $mail->Username   = $smtp['username'];
    $mail->Password   = $smtp['password'];
    $encryption       = strtolower((string) $smtp['encryption']);
    $mail->SMTPSecure = ($encryption === 'ssl')
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) $smtp['port'];
    $mail->setFrom($smtp['from'], $smtp['from_name']);
    $mail->addReplyTo($smtp['from'], 'Support');
    $mail->XMailer = ' ';
    return $mail;
}

// ---------- Fallback mail() sender ----------
function sendEmailFallback($to, $subject, $htmlBody, $plainBody) {
    $boundary = md5(uniqid(time()));
    $headers = "From: notifications@rdvendora.com\r\n";
    $headers .= "Reply-To: support@rdvendora.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $plainBody . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--$boundary--";
    
    return mail($to, $subject, $message, $headers);
}

// ---------- Universal sender (PHPMailer or fallback) ----------
function sendEmail($to, $subject, $htmlBody, $plainBody) {
    global $phpmailer_available;
    if ($phpmailer_available) {
        try {
            $mail = getMailer();
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            $info = isset($mail) && is_object($mail) ? ($mail->ErrorInfo ?? $e->getMessage()) : $e->getMessage();
            error_log("PHPMailer failed: " . $info . " - falling back to mail()");
            return sendEmailFallback($to, $subject, $htmlBody, $plainBody);
        }
    } else {
        return sendEmailFallback($to, $subject, $htmlBody, $plainBody);
    }
}

// ============================================================
//  WELCOME EMAIL – Premium Styled
// ============================================================
function sendWelcomeEmail($email, $fullname) {
    $create_store_link = 'http://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/create-store.php';
    $year = date('Y');

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to RD Vendora</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">
                        <!-- HEADER -->
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
                                <td style="padding:32px 30px 24px 30px; text-align:center;">
                                    <span style="font-size:48px;">🚀</span>
                                    <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 4px 0;">Welcome, ' . $fullname . '! 👋</h1>
                                    <p style="font-size:16px; color:#64748B; margin:0 0 20px 0; line-height:1.6;">
                                        Thank you for joining <strong style="color:#1A56DB;">RD Vendora</strong>.<br>
                                        We\'re excited to help you build your online store and grow your business.
                                    </p>
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin:10px 0 24px 0;">
                                        <tr>
                                            <td style="background-color:#D4AF37; border-radius:50px; padding:14px 40px; box-shadow:0 4px 12px rgba(212,175,55,0.25);">
                                                <a href="' . $create_store_link . '" style="color:#0A3D91; text-decoration:none; font-weight:700; font-size:16px; display:inline-block;">🚀 Create Your Store</a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="font-size:15px; color:#64748B; margin:0 0 10px 0;">
                                        If you have any questions, feel free to reply to this email.<br>
                                        Our support team is always here to help you.
                                    </p>
                                    <p style="font-size:15px; color:#1A56DB; font-weight:500; margin:0;">– The RD Vendora Team</p>
                                    <!-- FOOTER -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">rdvendora.com</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated message. Please do not reply.</span>
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

    $plainText = "Hi $fullname,\n\nThank you for joining RD Vendora. We're excited to help you build your online store.\n\nCreate your store at: $create_store_link\n\nIf you have any questions, feel free to reply to this email.\n\n– The RD Vendora Team";

    return sendEmail($email, "Welcome to RD Vendora, $fullname!", $htmlBody, $plainText);
}

// ============================================================
//  SIGNUP EMAIL VERIFICATION CODE
// ============================================================
function sendSignupVerificationCode($email, $code) {
    $year = date('Y');
    $safeEmail = htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
    $minutes = 15;

    $htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#F5F7FB;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">
<table align="center" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FB;padding:40px 20px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:18px;border:1px solid #E5E7EB;">
<tr><td style="background:#071530;border-bottom:6px solid #D4AF37;border-radius:18px 18px 0 0;padding:20px;text-align:center;color:#fff;font-size:22px;font-weight:700;">RD Vendora</td></tr>
<tr><td style="padding:32px 28px;text-align:center;">
<p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#b45309;">Verify your email</p>
<h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Confirm your signup</h1>
<p style="margin:0 0 20px;color:#64748b;line-height:1.6;">Enter this code on the signup page for <strong>' . $safeEmail . '</strong>. It expires in ' . $minutes . ' minutes.</p>
<p style="margin:0 0 8px;font-size:36px;font-weight:800;letter-spacing:0.35em;color:#071530;">' . $safeCode . '</p>
<p style="margin:20px 0 0;font-size:13px;color:#94a3b8;line-height:1.5;">If you did not request this, you can ignore this email.</p>
<p style="margin:16px 0 0;font-size:13px;color:#94a3b8;">&copy; ' . $year . ' RD Vendora</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';

    $plainText = "Your RD Vendora verification code is: $code\n\nEnter it on the signup page for $email. It expires in $minutes minutes.\n\nIf you did not request this, ignore this email.";

    return sendEmail($email, 'Your RD Vendora verification code: ' . $code, $htmlBody, $plainText);
}

// ============================================================
//  LOGIN NOTIFICATION – Premium Styled
// ============================================================
function sendLoginNotification($email, $fullname, $context = 'account') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
    if (strpos((string) $ip, ',') !== false) {
        $ip = trim(explode(',', (string) $ip)[0]);
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown browser';
    $time = date('Y-m-d H:i:s');
    $isAdmin = ($context === 'admin');
    $place = $isAdmin ? 'RD Vendora admin dashboard' : 'RD Vendora account';
    if (function_exists('rdv_url')) {
        $reset_link = rdv_url('reset-password');
    } else {
        $reset_link = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rdvendora.com') . '/reset-password';
    }
    $year = date('Y');
    $safeName = htmlspecialchars((string) $fullname, ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars((string) $ip, ENT_QUOTES, 'UTF-8');
    $safeAgent = htmlspecialchars((string) $userAgent, ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');
    $safeReset = htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8');

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Notification</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">
                        <!-- HEADER -->
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
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="text-align:center; padding-bottom:10px;">
                                                <span style="font-size:48px;">🔐</span>
                                                <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 4px 0;">Hello, ' . $safeName . '</h1>
                                                <p style="font-size:16px; color:#64748B; margin:0; line-height:1.6;">
                                                    We noticed a new login to your <strong style="color:#1A56DB;">' . htmlspecialchars($place, ENT_QUOTES, 'UTF-8') . '</strong>.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC; border-radius:14px; border-left:6px solid #D4AF37; padding:16px 20px; margin:16px 0 20px 0;">
                                        <tr>
                                            <td>
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="padding:4px 0; font-size:15px; color:#1E293B;">
                                                            <strong>🕒 Time:</strong> ' . $safeTime . '
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:4px 0; font-size:15px; color:#1E293B;">
                                                            <strong>🌐 IP Address:</strong> ' . $safeIp . '
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:4px 0; font-size:15px; color:#1E293B;">
                                                            <strong>💻 Browser:</strong> ' . $safeAgent . '
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="font-size:15px; color:#64748B; margin:0 0 12px 0; line-height:1.6;">
                                        If this was you, you can safely ignore this email.<br>
                                        If you did not log in, please <a href="' . $safeReset . '" style="color:#1A56DB; font-weight:600; text-decoration:none;">reset your password</a> immediately and contact support.
                                    </p>
                                    <p style="font-size:15px; color:#1A56DB; font-weight:500; margin:20px 0 0 0;">– RD Vendora Security Team</p>
                                    <!-- FOOTER -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">rdvendora.com</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated security alert. Please do not reply.</span>
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

    $plainText = "Hello $fullname,\n\nWe noticed a new login to your $place.\n\nTime: $time\nIP Address: $ip\nBrowser: $userAgent\n\nIf this was you, ignore this email. If not, reset your password immediately at: $reset_link\n\n– RD Vendora Security Team";

    $subject = $isAdmin
        ? 'Security Alert: New admin login to RD Vendora'
        : 'Security Alert: New login to your RD Vendora account';

    return sendEmail($email, $subject, $htmlBody, $plainText);
}

// ============================================================
//  ORDER CONFIRMATION – Premium Styled
// ============================================================
function sendOrderConfirmation($customerEmail, $customerName, $orderData) {
    $orderId    = $orderData['order_id'];
    $orderDate  = $orderData['created_at'] ?? date('Y-m-d H:i:s');
    $total      = $orderData['total_amount'];
    $items      = $orderData['items'];
    $year = date('Y');

    // Build product table
    $itemsHtml = '';
    $itemsText = '';
    $rowCount  = 0;
    foreach ($items as $item) {
        $rowCount++;
        $bgColor = ($rowCount % 2 == 0) ? '#F8FAFC' : '#FFFFFF';
        $name    = htmlspecialchars($item['name']);
        $qty     = (int)$item['qty'];
        $price   = $item['price'];
        $totalItem = $qty * $price;
        $itemsHtml .= "
            <tr style='background-color:{$bgColor};'>
                <td style='padding:12px 10px; border-bottom:1px solid #E5E7EB; font-size:14px; color:#1E293B;'>{$name}</td>
                <td style='padding:12px 10px; border-bottom:1px solid #E5E7EB; text-align:center; font-size:14px; color:#1E293B;'>{$qty}</td>
                <td style='padding:12px 10px; border-bottom:1px solid #E5E7EB; text-align:right; font-size:14px; color:#1E293B;'>₦" . number_format($price, 2) . "</td>
                <td style='padding:12px 10px; border-bottom:1px solid #E5E7EB; text-align:right; font-size:14px; font-weight:600; color:#1A56DB;'>₦" . number_format($totalItem, 2) . "</td>
            </tr>
        ";
        $itemsText .= "{$name} x {$qty} = ₦" . number_format($totalItem, 2) . "\n";
    }

    $delivery = 0;
    $discount = 0;
    $subtotal = $total - $delivery + $discount;
    $grandTotal = $total;

    $formattedSubtotal = '₦' . number_format($subtotal, 2);
    $formattedDelivery = ($delivery == 0) ? 'Free' : '₦' . number_format($delivery, 2);
    $formattedDiscount = '₦' . number_format($discount, 2);
    $formattedGrandTotal = '₦' . number_format($grandTotal, 2);

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">
                        <!-- HEADER -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
                            <tr>
                                <td style="padding:22px 30px;">
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="vertical-align:middle;">
                                                <span style="font-size:22px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px;">RD Vendora</span>
                                            </td>
                                            <td style="vertical-align:middle; text-align:right;">
                                                <span style="font-size:14px; color:#D4AF37; font-weight:500;">Order #' . $orderId . '</span>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        <!-- BODY -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:32px 30px 24px 30px;">
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="text-align:center; padding-bottom:20px;">
                                                <span style="font-size:42px;">✅</span>
                                                <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 4px 0;">Hello, ' . $customerName . ' 👋</h1>
                                                <p style="font-size:16px; color:#64748B; margin:0; line-height:1.6;">
                                                    Thank you for shopping with <strong style="color:#1A56DB;">RD Vendora</strong>.<br>
                                                    We have received your order successfully and it is now being processed.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#F0F7FF; border-radius:14px; border:1px solid #DBEAFE; padding:16px 20px; margin-bottom:24px;">
                                        <tr>
                                            <td style="text-align:center;">
                                                <div style="font-size:16px; font-weight:700; color:#16A34A; text-transform:uppercase; letter-spacing:0.5px;">✅ Order Confirmed</div>
                                                <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin-top:10px;">
                                                    <tr>
                                                        <td style="padding:0 15px; text-align:center; border-right:1px solid #DBEAFE;">
                                                            <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Order Number</div>
                                                            <div style="font-size:16px; font-weight:600; color:#1E293B;">#' . $orderId . '</div>
                                                        </td>
                                                        <td style="padding:0 15px; text-align:center; border-right:1px solid #DBEAFE;">
                                                            <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Status</div>
                                                            <div style="font-size:16px; font-weight:600; color:#16A34A;">Confirmed</div>
                                                        </td>
                                                        <td style="padding:0 15px; text-align:center;">
                                                            <div style="font-size:12px; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Processing</div>
                                                            <div style="font-size:16px; font-weight:600; color:#1E293B;">24-48 hrs</div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#F8FAFC; padding:14px 20px; border-bottom:1px solid #E5E7EB;">
                                                <span style="font-size:16px; font-weight:600; color:#1E293B;">📦 Order Summary</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:10px 20px;">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                    <thead>
                                                        <tr style="border-bottom:1px solid #E5E7EB;">
                                                            <th style="padding:10px 10px 10px 0; text-align:left; font-size:12px; font-weight:600; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Product</th>
                                                            <th style="padding:10px 10px; text-align:center; font-size:12px; font-weight:600; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Qty</th>
                                                            <th style="padding:10px 10px; text-align:right; font-size:12px; font-weight:600; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Price</th>
                                                            <th style="padding:10px 0 10px 10px; text-align:right; font-size:12px; font-weight:600; color:#64748B; text-transform:uppercase; letter-spacing:0.5px;">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        ' . $itemsHtml . '
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px 20px;">
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border-top:1px solid #E5E7EB; margin-top:8px;">
                                                    <tr>
                                                        <td style="padding:8px 0 4px 0; text-align:right; font-size:15px; color:#1E293B;">Subtotal</td>
                                                        <td style="padding:8px 0 4px 20px; text-align:right; font-size:15px; color:#1E293B;">' . $formattedSubtotal . '</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:4px 0; text-align:right; font-size:15px; color:#1E293B;">Delivery</td>
                                                        <td style="padding:4px 0 4px 20px; text-align:right; font-size:15px; color:#1E293B;">' . $formattedDelivery . '</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:4px 0; text-align:right; font-size:15px; color:#1E293B;">Discount</td>
                                                        <td style="padding:4px 0 4px 20px; text-align:right; font-size:15px; color:#1E293B;">' . $formattedDiscount . '</td>
                                                    </tr>
                                                    <tr style="background-color:#0A3D91; border-radius:8px;">
                                                        <td style="padding:12px 0 12px 0; text-align:right; font-size:18px; font-weight:700; color:#FFFFFF; border-radius:8px 0 0 8px;">Grand Total</td>
                                                        <td style="padding:12px 0 12px 20px; text-align:right; font-size:20px; font-weight:700; color:#D4AF37; border-radius:0 8px 8px 0;">' . $formattedGrandTotal . '</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    ' . rdv_email_buttons_row([
                                        [
                                            'label' => 'Continue',
                                            'url' => rdv_url('marketplace'),
                                            'style' => 'gold',
                                        ],
                                        [
                                            'label' => 'I Have Received My Order',
                                            'url' => rdv_order_received_url($orderId),
                                            'style' => 'success',
                                        ],
                                    ]) . '
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="text-align:center; padding:0 10px 8px 10px;">
                                                <p style="font-size:14px; color:#64748B; margin:0; line-height:1.6;">
                                                    When your package arrives, tap <strong style="color:#16A34A;">I Have Received My Order</strong> so we can notify the seller.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC; border-radius:14px; border:1px solid #E5E7EB; padding:12px 16px; margin-bottom:24px;">
                                        <tr>
                                            <td>
                                                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td style="text-align:center; padding:6px 0;">
                                                            <span style="font-size:22px;">📦</span>
                                                            <div style="font-size:14px; font-weight:500; color:#1E293B;">Preparing</div>
                                                        </td>
                                                        <td style="text-align:center; padding:6px 0;">
                                                            <span style="font-size:22px;">🚚</span>
                                                            <div style="font-size:14px; font-weight:500; color:#1E293B;">Shipping soon</div>
                                                        </td>
                                                        <td style="text-align:center; padding:6px 0;">
                                                            <span style="font-size:22px;">📧</span>
                                                            <div style="font-size:14px; font-weight:500; color:#1E293B;">Email alerts</div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="3" style="text-align:center; padding-top:8px; font-size:14px; color:#64748B;">
                                                            You\'ll receive another email when your order ships.
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="text-align:center; padding-top:8px;">
                                                <h2 style="font-size:22px; font-weight:600; color:#1E293B; margin:0 0 4px 0;">Thank You For Shopping With Us</h2>
                                                <p style="font-size:15px; color:#64748B; margin:0;">
                                                    We appreciate your trust in <strong style="color:#1A56DB;">RD Vendora</strong>.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    <!-- FOOTER -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">rdvendora.com</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated email. Please do not reply.</span>
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

    $receivedUrl = rdv_order_received_url($orderId);
    $plainText = "Thank you for your order, $customerName!\n\nOrder #$orderId\nDate: $orderDate\n\nItems:\n$itemsText\nTotal: ₦" . number_format($total, 2) . "\n\nContinue shopping: " . rdv_url('marketplace') . "\nConfirm delivery: $receivedUrl\n\nWhen your package arrives, use the confirmation link above so we can notify the seller.\n– RD Vendora Team";

    return sendEmail($customerEmail, "Order Confirmation #$orderId – RD Vendora", $htmlBody, $plainText);
}

// ============================================================
//  ORDER DELIVERED – Notify vendor & admin
// ============================================================
function sendOrderDeliveredNotification(array $order, $conn) {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0 || !$conn) {
        return false;
    }

    $customerName = trim((string) ($order['user_name'] ?? $order['customer_name'] ?? 'Customer'));
    $customerEmail = trim((string) ($order['user_email'] ?? $order['customer_email'] ?? ''));
    $orderRef = trim((string) ($order['order_ref'] ?? ('#' . $orderId)));
    $total = (float) ($order['total_amount'] ?? $order['total'] ?? 0);
    $confirmedAt = date('F j, Y \a\t g:i A');

    $itemsHtml = '';
    $itemsText = '';
    $stmt = $conn->prepare('SELECT oi.product_name, oi.quantity, oi.price, s.store_name, u.email AS vendor_email, u.full_name AS vendor_name
        FROM order_items oi
        LEFT JOIN stores s ON oi.store_id = s.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE oi.order_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $vendorEmails = [];
        while ($row = $res->fetch_assoc()) {
            $name = htmlspecialchars((string) ($row['product_name'] ?? 'Product'), ENT_QUOTES, 'UTF-8');
            $qty = (int) ($row['quantity'] ?? 0);
            $price = (float) ($row['price'] ?? 0);
            $storeName = htmlspecialchars((string) ($row['store_name'] ?? 'Store'), ENT_QUOTES, 'UTF-8');
            $lineTotal = $qty * $price;
            $itemsHtml .= '<tr><td style="padding:10px 0; border-bottom:1px solid #E5E7EB; font-size:14px; color:#1E293B;">' . $name . ' <span style="color:#64748B;">(' . $storeName . ')</span></td><td style="padding:10px 0; border-bottom:1px solid #E5E7EB; text-align:center; font-size:14px;">' . $qty . '</td><td style="padding:10px 0; border-bottom:1px solid #E5E7EB; text-align:right; font-size:14px; font-weight:600;">₦' . number_format($lineTotal, 2) . '</td></tr>';
            $itemsText .= "- {$row['product_name']} x {$qty} ({$row['store_name']}) = ₦" . number_format($lineTotal, 2) . "\n";
            $vendorEmail = trim((string) ($row['vendor_email'] ?? ''));
            if ($vendorEmail !== '' && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
                $vendorEmails[$vendorEmail] = (string) ($row['vendor_name'] ?? 'Seller');
            }
        }
        $stmt->close();
    }

    $safeCustomer = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8');
    $safeRef = htmlspecialchars($orderRef, ENT_QUOTES, 'UTF-8');
    $safeTime = htmlspecialchars($confirmedAt, ENT_QUOTES, 'UTF-8');

    $inner = '
        <div style="text-align:center; margin-bottom:20px;">
            <span style="font-size:42px;">📦</span>
            <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:8px 0 6px 0;">Customer Confirmed Delivery</h1>
            <p style="font-size:16px; color:#64748B; margin:0;">The customer has confirmed that this order was delivered successfully.</p>
        </div>
        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:14px; padding:16px 20px; margin-bottom:22px;">
            <tr>
                <td style="font-size:15px; color:#166534; line-height:1.7;">
                    <strong>Order:</strong> ' . $safeRef . '<br>
                    <strong>Customer:</strong> ' . $safeCustomer . ($customerEmail !== '' ? ' (' . $safeEmail . ')' : '') . '<br>
                    <strong>Confirmed at:</strong> ' . $safeTime . '<br>
                    <strong>Order total:</strong> ₦' . number_format($total, 2) . '
                </td>
            </tr>
        </table>
        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; margin-bottom:8px;">
            <tr><td style="background:#F8FAFC; padding:12px 16px; font-weight:600; color:#1E293B;">Items delivered</td></tr>
            <tr><td style="padding:0 16px 12px;">
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <th style="text-align:left; font-size:12px; color:#64748B; padding:10px 0;">Product</th>
                        <th style="text-align:center; font-size:12px; color:#64748B; padding:10px 0;">Qty</th>
                        <th style="text-align:right; font-size:12px; color:#64748B; padding:10px 0;">Total</th>
                    </tr>
                    ' . $itemsHtml . '
                </table>
            </td></tr>
        </table>';

    $htmlBody = rdv_email_wrap($inner, [
        'title' => 'Order Delivered',
        'badge' => $safeRef,
        'preheader' => $customerName . ' confirmed delivery for ' . $orderRef,
        'footer_note' => 'Delivery confirmation from RD Vendora.',
        'buttons' => [
            ['label' => 'View Orders', 'url' => rdv_url('orders'), 'style' => 'primary'],
        ],
    ]);

    $plainText = "Customer confirmed delivery\n\nOrder: $orderRef\nCustomer: $customerName"
        . ($customerEmail !== '' ? " ($customerEmail)" : '')
        . "\nConfirmed at: $confirmedAt\nTotal: ₦" . number_format($total, 2)
        . "\n\nItems:\n$itemsText\nView orders: " . rdv_url('orders');

    $subject = 'Delivery Confirmed: ' . $orderRef . ' – RD Vendora';
    $sent = false;

    foreach ($vendorEmails as $email => $vendorName) {
        if (sendEmail($email, $subject, $htmlBody, $plainText)) {
            $sent = true;
        }
    }

    $adminEmail = rdv_get_admin_alert_email($conn);
    if ($adminEmail !== '') {
        if (sendEmail($adminEmail, $subject, $htmlBody, $plainText)) {
            $sent = true;
        }
    }

    return $sent;
}

// ============================================================
//  SUBSCRIPTION EMAIL – Premium Styled
// ============================================================
function sendSubscriptionEmail($email, $fullname, $planName, $billingCycle, $amount, $startDate, $endDate) {
    $amountFormatted = $amount > 0 ? '₦' . number_format($amount, 2) : 'Free';
    $cycleText = ($billingCycle == 'annual') ? 'Annual (20% discount applied)' : 'Monthly';
    $year = date('Y');

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Confirmation</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">
                        <!-- HEADER -->
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
                                <td style="padding:32px 30px 24px 30px; text-align:center;">
                                    <span style="font-size:48px;">🎉</span>
                                    <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 4px 0;">Hi ' . $fullname . ',</h1>
                                    <p style="font-size:16px; color:#64748B; margin:0 0 20px 0; line-height:1.6;">
                                        Your <strong style="color:#1A56DB;">' . $planName . '</strong> plan (' . $cycleText . ') has been successfully activated.
                                    </p>
                                    <div style="background:#F8FAFC; border-left:6px solid #D4AF37; padding:16px 20px; border-radius:8px; margin:16px auto; max-width:300px;">
                                        <p style="margin:4px 0; font-size:15px; color:#1E293B;"><strong>Amount:</strong> ' . $amountFormatted . '</p>
                                        <p style="margin:4px 0; font-size:15px; color:#1E293B;"><strong>Start:</strong> ' . $startDate . '</p>
                                        <p style="margin:4px 0; font-size:15px; color:#1E293B;"><strong>End:</strong> ' . $endDate . '</p>
                                    </div>
                                    <p style="font-size:15px; color:#64748B; margin:20px 0 10px 0;">
                                        You can manage your subscription from your vendor dashboard.
                                    </p>
                                    <p style="font-size:15px; color:#1A56DB; font-weight:500; margin:0;">– RD Vendora Team</p>
                                    <!-- FOOTER -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">rdvendora.com</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated message. Please do not reply.</span>
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

    $plainText = "Hi $fullname,\n\nYour $planName plan ($cycleText) has been successfully activated.\nAmount: $amountFormatted\nStart: $startDate\nEnd: $endDate\n\nManage your subscription from your vendor dashboard.\n\n– RD Vendora Team";

    return sendEmail($email, "Subscription Confirmation – RD Vendora", $htmlBody, $plainText);
}

/**
 * Styled HTML email for store team invitations.
 *
 * @param string $email       Invitee email
 * @param string $storeName   Store display name
 * @param string $role        admin|editor|viewer
 * @param string $inviteLink  Absolute accept-invite URL
 * @param string $inviterName Name of the person sending the invite
 */
function sendTeamInviteEmail($email, $storeName, $role, $inviteLink, $inviterName = '') {
    $year = date('Y');
    $storeRaw = trim((string) $storeName);
    $inviterRaw = trim((string) $inviterName);
    $inviteRaw = (string) $inviteLink;
    $storeEsc = htmlspecialchars($storeRaw, ENT_QUOTES, 'UTF-8');
    $inviterEsc = htmlspecialchars($inviterRaw, ENT_QUOTES, 'UTF-8');
    $inviteEsc = htmlspecialchars($inviteRaw, ENT_QUOTES, 'UTF-8');
    $roleKey = strtolower(trim((string) $role));
    $roleLabels = [
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ];
    $roleLabel = $roleLabels[$roleKey] ?? ucfirst($roleKey);
    $roleDescriptions = [
        'admin' => 'Full access to manage the store, products, orders, and team.',
        'editor' => 'Can manage products and orders for this store.',
        'viewer' => 'Read-only access to store information.',
    ];
    $roleDesc = $roleDescriptions[$roleKey] ?? 'Access to this store on RD Vendora.';
    $fromLine = $inviterEsc !== ''
        ? '<strong style="color:#1E293B;">' . $inviterEsc . '</strong> invited you'
        : 'You have been invited';

    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team invitation</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
                            <tr>
                                <td style="padding:22px 30px; text-align:center;">
                                    <span style="font-size:24px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px;">RD Vendora</span>
                                </td>
                            </tr>
                        </table>
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:32px 30px 24px 30px; text-align:center;">
                                    <span style="font-size:48px;">✉️</span>
                                    <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 8px 0;">You\'re invited to join a store</h1>
                                    <p style="font-size:16px; color:#64748B; margin:0 0 22px 0; line-height:1.6;">
                                        ' . $fromLine . ' to collaborate on<br>
                                        <strong style="color:#1A56DB; font-size:18px;">' . $storeEsc . '</strong>
                                    </p>

                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:420px; margin:0 auto 24px auto; background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:14px;">
                                        <tr>
                                            <td style="padding:18px 20px; text-align:left;">
                                                <p style="margin:0 0 6px 0; font-size:12px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#94A3B8;">Your role</p>
                                                <p style="margin:0 0 6px 0; font-size:18px; font-weight:700; color:#0A3D91;">' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</p>
                                                <p style="margin:0; font-size:14px; color:#64748B; line-height:1.5;">' . htmlspecialchars($roleDesc, ENT_QUOTES, 'UTF-8') . '</p>
                                            </td>
                                        </tr>
                                    </table>

                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin:8px 0 20px 0;">
                                        <tr>
                                            <td style="background-color:#D4AF37; border-radius:50px; padding:14px 40px; box-shadow:0 4px 12px rgba(212,175,55,0.25);">
                                                <a href="' . $inviteEsc . '" style="color:#0A3D91; text-decoration:none; font-weight:700; font-size:16px; display:inline-block;">Accept invitation</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="font-size:13px; color:#94A3B8; margin:0 0 8px 0; line-height:1.5;">
                                        This invitation expires in <strong style="color:#64748B;">7 days</strong>.<br>
                                        If the button does not work, copy and paste this link:
                                    </p>
                                    <p style="font-size:12px; color:#1A56DB; word-break:break-all; margin:0 0 18px 0; line-height:1.5;">
                                        <a href="' . $inviteEsc . '" style="color:#1A56DB; text-decoration:underline;">' . $inviteEsc . '</a>
                                    </p>
                                    <p style="font-size:14px; color:#64748B; margin:0 0 6px 0; line-height:1.5;">
                                        If you did not expect this email, you can ignore it.
                                    </p>
                                    <p style="font-size:15px; color:#1A56DB; font-weight:500; margin:12px 0 0 0;">– The RD Vendora Team</p>

                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="https://rdvendora.com" style="color:#1A56DB; text-decoration:none;">rdvendora.com</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated message. Please do not reply.</span>
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

    $plainIntro = $inviterRaw !== ''
        ? "$inviterRaw invited you to join \"$storeRaw\" on RD Vendora as $roleLabel."
        : "You have been invited to join \"$storeRaw\" on RD Vendora as $roleLabel.";
    $plainText = "Hello,\n\n$plainIntro\n\n$roleDesc\n\nAccept your invitation:\n$inviteRaw\n\nThis invite expires in 7 days.\n\n– The RD Vendora Team";

    return sendEmail($email, "You're invited to join $storeRaw on RD Vendora", $htmlBody, $plainText);
}
?>