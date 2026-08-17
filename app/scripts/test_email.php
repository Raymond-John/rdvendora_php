<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
rdv_load_phpmailer();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
$mail->SMTPDebug = 2;               // Detailed output
$mail->Debugoutput = 'html';

$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'mrrayjohnson2@gmail.com ';
$mail->Password   = 'oryapokrexcjibjo';   // spaces removed
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;

$mail->setFrom('mrrayjohnson2@gmail.com ', 'RD Vendora Test');
$mail->addAddress('your-own-email@gmail.com');  // Replace with your real email
$mail->Subject = 'Test SMTP from XAMPP';
$mail->Body    = 'If you see this, everything works!';

$mail->send();
echo "✅ Email sent successfully!";