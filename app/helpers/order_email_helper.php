<?php
// includes/order_email_helper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (function_exists('rdv_load_phpmailer')) {
    rdv_load_phpmailer();
} elseif (file_exists(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
}

function sendCustomerOrderEmailSMTP($customerEmail, $customerName, $orderId, $cartItems, $totalAmount) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration – replace with your own credentials
        $mail->SMTPDebug = 0;                         // 0 = off, 2 = verbose
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';         // your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';   // SMTP username
        $mail->Password   = 'your-app-password';      // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Sender & Recipient
        $mail->setFrom('no-reply@yourdomain.com', 'Your Store Name');
        $mail->addAddress($customerEmail, $customerName);
        
        // Email content
        $mail->isHTML(false);
        $mail->Subject = "Your Order #{$orderId} Confirmation";
        
        // Build product list from cart
        $productLines = [];
        foreach ($cartItems as $item) {
            $productLines[] = "- {$item['name']} (x{$item['quantity']}) – ₦" . number_format($item['price'], 2);
        }
        $productList = implode("\n", $productLines);
        
        $body = "Hello {$customerName},\n\n";
        $body .= "Thank you for your purchase! Your order has been received.\n\n";
        $body .= "Order ID: {$orderId}\n";
        $body .= "Total Paid: ₦" . number_format($totalAmount, 2) . "\n\n";
        $body .= "Products Ordered:\n{$productList}\n\n";
        $body .= "We'll notify you once your order is shipped.\n\n";
        $body .= "Regards,\nYour Store Team";
        
        $mail->Body = $body;
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Order email failed to {$customerEmail}: " . $mail->ErrorInfo);
        return false;
    }
}
?>