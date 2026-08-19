<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
rdv_load_phpmailer();
if (!function_exists('rdv_smtp_settings')) {
    require_once APP_PATH . '/helpers/smtp_config.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$smtp = rdv_smtp_settings();
$mail = new PHPMailer(true);
$mail->SMTPDebug = 2;
$mail->Debugoutput = 'html';
$mail->isSMTP();
$mail->Host       = $smtp['host'];
$mail->SMTPAuth   = ($smtp['username'] !== '' && $smtp['password'] !== '');
$mail->Username   = $smtp['username'];
$mail->Password   = $smtp['password'];
$mail->SMTPSecure = (strtolower((string) $smtp['encryption']) === 'ssl')
    ? PHPMailer::ENCRYPTION_SMTPS
    : PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = (int) $smtp['port'];
$mail->setFrom($smtp['from'], $smtp['from_name']);
$mail->addAddress($smtp['from']);
$mail->Subject = 'Test SMTP from RD Vendora';
$mail->Body    = 'If you see this, SMTP from .env or Admin Settings is working.';
$mail->send();
echo 'Email sent successfully.';
