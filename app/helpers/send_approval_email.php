<?php
// includes/send_approval_email.php

/**
 * Send a store approval email to the user with company logo.
 * Uses PHPMailer – adjust SMTP credentials as needed.
 *
 * @param int    $user_id    ID of the store owner
 * @param string $store_name Optional store name (will be fetched if not provided)
 * @return bool  True if email was sent successfully
 */
function sendStoreApprovalEmail($user_id, $store_name = '') {
    // ---------- Load PHPMailer ----------
    $phpmailer_available = function_exists('rdv_load_phpmailer') ? rdv_load_phpmailer() : false;
    
    if (!$phpmailer_available) {
        error_log('PHPMailer not available for approval email.');
        return false;
    }

    // ---------- Database connection ----------
    global $conn;
    if (!$conn) {
        error_log('Database connection failed in sendStoreApprovalEmail');
        return false;
    }

    // Get user details
    $stmt = $conn->prepare("SELECT email, fullname FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("User ID $user_id not found for approval email.");
        return false;
    }

    $email = $user['email'];
    $name  = $user['fullname'] ?: 'Store Owner';

    // If store_name not provided, fetch it
    if (empty($store_name)) {
        $stmt = $conn->prepare("SELECT store_name FROM stores WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $store = $stmt->get_result()->fetch_assoc();
        $store_name = $store['store_name'] ?? 'your store';
        $stmt->close();
    }

    // ---------- Build URLs ----------
    $base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
    $store_url = $base_url . "/storefront.php";
    $login_url = $base_url . "/login.php";
    $year = date('Y');

    // ---------- HTML email body with Royal Blue & Gold theme ----------
    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Approved</title>
</head>
<body style="margin:0; padding:0; background-color:#F5F7FB; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#F5F7FB; padding:40px 20px;">
    <tr>
        <td align="center" style="padding:0;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; box-shadow:0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding:0;">

                        <!-- ===== HEADER (Royal Blue) ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color:#0A3D91; border-bottom:6px solid #D4AF37; border-radius:18px 18px 0 0;">
                            <tr>
                                <td style="padding:22px 30px; text-align:center;">
                                    <span style="font-size:24px; font-weight:700; color:#FFFFFF; letter-spacing:-0.3px;">RD Vendora</span>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== BODY ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:32px 30px 24px 30px; text-align:center;">

                                    <!-- Success Icon & Greeting -->
                                    <span style="font-size:48px;">🎉</span>
                                    <h1 style="font-size:24px; font-weight:600; color:#1E293B; margin:6px 0 4px 0;">Congratulations, ' . $name . '!</h1>
                                    <p style="font-size:16px; color:#64748B; margin:0 0 16px 0; line-height:1.6;">
                                        We are pleased to inform you that your store <strong style="color:#1A56DB;">"' . $store_name . '"</strong> has been <strong style="color:#16A34A;">approved</strong> by the administrator.
                                    </p>

                                    <!-- Call-to-Action Buttons -->
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin:16px 0;">
                                        <tr>
                                            <td style="background-color:#1A56DB; border-radius:50px; padding:12px 32px; box-shadow:0 4px 12px rgba(26,86,219,0.25); display:inline-block; margin:0 6px 10px 6px;">
                                                <a href="' . $login_url . '" style="color:#FFFFFF; text-decoration:none; font-weight:600; font-size:16px; display:inline-block;">🔑 Login to Dashboard</a>
                                            </td>
                                        </tr>
                                    </table>
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin:0 0 20px 0;">
                                        <tr>
                                            <td style="background-color:#D4AF37; border-radius:50px; padding:12px 32px; box-shadow:0 4px 12px rgba(212,175,55,0.2); display:inline-block; margin:0 6px 10px 6px;">
                                                <a href="' . $store_url . '" style="color:#0A3D91; text-decoration:none; font-weight:600; font-size:16px; display:inline-block;">👀 View Your Store</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Thank You Message -->
                                    <p style="font-size:15px; color:#64748B; margin:20px 0 10px 0;">
                                        Thank you for choosing RD Vendora. We wish you great success!
                                    </p>
                                    <p style="font-size:15px; color:#1A56DB; font-weight:500; margin:0;">– The RD Vendora Team</p>

                                    <!-- ===== FOOTER ===== -->
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top:32px; border-top:1px solid #E5E7EB; padding-top:20px;">
                                        <tr>
                                            <td style="text-align:center; font-size:13px; color:#94A3B8; line-height:1.6;">
                                                <span style="color:#1E293B; font-weight:600;">RD Vendora</span><br>
                                                <a href="mailto:support@rdvendora.com" style="color:#1A56DB; text-decoration:none;">support@rdvendora.com</a> &nbsp;|&nbsp; <a href="' . $base_url . '" style="color:#1A56DB; text-decoration:none;">' . $base_url . '</a><br>
                                                &copy; ' . $year . ' RD Vendora — All Rights Reserved.<br>
                                                <span style="font-size:12px; color:#94A3B8;">This is an automated notification. Please do not reply.</span>
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

    // ---------- Plain text version ----------
    $plainBody = "Dear $name,\n\n"
               . "Congratulations! Your store \"$store_name\" has been approved by the administrator.\n"
               . "You can now log in to your dashboard and start selling.\n\n"
               . "Login: $login_url\n"
               . "Visit your store: $store_url\n\n"
               . "Thank you for choosing RD Vendora!\n"
               . "Best regards,\n"
               . "RD Vendora Team";

    // ---------- Send email ----------
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // === SMTP Configuration ===
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mrrayjohnson2@gmail.com';
        $mail->Password   = 'tpkt rcnc lgmw wzzp';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('noreply@rdvendora.com', 'RD Vendora');
        $mail->addAddress($email, $name);
        $mail->addReplyTo('support@rdvendora.com', 'Support');

        // --- (Optional) Embed company logo – we can skip because we are not using an image in this template ---
        // The header now uses plain text, so no logo embedding needed.
        // If you want a logo, you can embed it and update the HTML to include it.

        // Email content
        $mail->isHTML(true);
        $mail->Subject = "🎉 Your store has been approved!";
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Approval email failed: " . $e->getMessage());
        return false;
    }
}
?>