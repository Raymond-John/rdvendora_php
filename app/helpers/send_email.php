<?php
// includes/send_email.php
if (!isset($conn) && isset($connect)) $conn = $connect;

function sendOrderConfirmation($order_id, $customer_email, $customer_name = 'Customer') {
    global $conn;
    
    // ---------- FETCH ORDER ITEMS ----------
    $sql = "SELECT p.name, oi.quantity, oi.price 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // ---------- BUILD PRODUCT LISTS & CALCULATE SUBTOTAL ----------
    $product_list_plain = '';
    $product_list_html  = '';
    $subtotal = 0;
    $row_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $price    = $row['price'];
        $quantity = $row['quantity'];
        $total_item = $price * $quantity;
        $subtotal += $total_item;
        $row_count++;
        
        $product_list_plain .= "- " . htmlspecialchars($row['name']) . " (x{$quantity}) – ₦" . number_format($price, 2) . "\n";
        
        // Alternate row colors
        $bg_color = ($row_count % 2 == 0) ? '#F8FAFC' : '#FFFFFF';
        $product_list_html .= '
        <tr>
            <td style="padding: 12px 12px; font-size: 14px; color: #1E293B; border-bottom: 1px solid #E5E7EB; background-color: ' . $bg_color . ';">' . htmlspecialchars($row['name']) . '</td>
            <td style="padding: 12px 12px; text-align: center; font-size: 14px; color: #1E293B; border-bottom: 1px solid #E5E7EB; background-color: ' . $bg_color . ';">' . $quantity . '</td>
            <td style="padding: 12px 12px; text-align: right; font-size: 14px; color: #1E293B; border-bottom: 1px solid #E5E7EB; background-color: ' . $bg_color . ';">₦' . number_format($price, 2) . '</td>
            <td style="padding: 12px 12px; text-align: right; font-size: 14px; font-weight: 600; color: #1A56DB; border-bottom: 1px solid #E5E7EB; background-color: ' . $bg_color . ';">₦' . number_format($total_item, 2) . '</td>
        </tr>';
    }
    $stmt->close();
    
    // ---------- TOTALS ----------
    $delivery    = 0;          // free delivery
    $discount    = 0;          // no discount by default
    $grand_total = $subtotal + $delivery - $discount;
    
    $formatted_subtotal    = '₦' . number_format($subtotal, 2);
    $formatted_delivery    = ($delivery == 0) ? 'Free' : '₦' . number_format($delivery, 2);
    $formatted_discount    = '₦' . number_format($discount, 2);
    $formatted_grand_total = '₦' . number_format($grand_total, 2);
    
    // ---------- COMPANY DETAILS ----------
    $companyName    = 'RD Vendora Marketplace';
    $companyAddress = '123 Main Street, City, State ZIP';
    $companyPhone   = '+1 (123) 456-7890';
    $companyEmail   = 'support@rdvendora.com';
    $companyWebsite = 'https://rdvendora.com';
    
    // ---------- LOCAL LOGO (Base64) ----------
    $localLogoPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/assets/logo.png';
    if (file_exists($localLogoPath)) {
        $imageData = base64_encode(file_get_contents($localLogoPath));
        $logoUrl = 'data:image/png;base64,' . $imageData;
    } else {
        $logoUrl = '';   // fallback
    }
    
    // ---------- MODERN, MINIMAL HTML EMAIL (SaaS‑style) ----------
    $html_body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F5F7FB; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

<!-- Outer table for background and centering -->
<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F5F7FB; padding: 40px 20px;">
    <tr>
        <td align="center" style="padding: 0;">

            <!-- Main card (600px) -->
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #FFFFFF; border-radius: 18px; border: 1px solid #E5E7EB; box-shadow: 0 8px 30px rgba(0,0,0,0.04);">
                <tr>
                    <td style="padding: 40px 35px 30px 35px;">

                        <!-- ===== HEADER ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="vertical-align: middle;">
                                    ' . ($logoUrl ? '<img src="'.$logoUrl.'" alt="'.$companyName.'" style="max-height: 40px; width: auto; display: inline-block; vertical-align: middle;" />' : '') . '
                                    <span style="font-size: 20px; font-weight: 700; color: #1E293B; margin-left: 10px; vertical-align: middle;">' . $companyName . '</span>
                                </td>
                                <td style="text-align: right; vertical-align: middle;">
                                    <span style="font-size: 14px; color: #64748B;">Order #' . $order_id . '</span>
                                </td>
                            </tr>
                        </table>

                        <!-- Divider -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 20px; margin-bottom: 30px;">
                            <tr>
                                <td style="height: 1px; background-color: #E5E7EB;"></td>
                            </tr>
                        </table>

                        <!-- ===== WELCOME ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align: center; padding-bottom: 10px;">
                                    <h1 style="font-size: 24px; font-weight: 600; color: #1E293B; margin: 0 0 6px 0;">Hello, ' . $customer_name . ' 👋</h1>
                                    <p style="font-size: 16px; color: #64748B; line-height: 1.6; margin: 8px 0 0 0;">
                                        Thank you for shopping with <strong style="color: #1A56DB;">RD Vendora Marketplace</strong>.<br>
                                        We have received your order successfully.<br>
                                        Your order is now being processed.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== SUCCESS CARD (Order Confirmed) ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 30px; background-color: #F0F7FF; border-radius: 14px; border: 1px solid #DBEAFE; padding: 20px 20px;">
                            <tr>
                                <td style="text-align: center;">
                                    <div style="font-size: 42px; line-height: 1.2;">✅</div>
                                    <div style="font-size: 20px; font-weight: 700; color: #16A34A; margin-top: 6px;">ORDER CONFIRMED</div>
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin-top: 14px;">
                                        <tr>
                                            <td style="padding: 0 15px; text-align: center; border-right: 1px solid #DBEAFE;">
                                                <div style="font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Order Number</div>
                                                <div style="font-size: 16px; font-weight: 600; color: #1E293B;">#' . $order_id . '</div>
                                            </td>
                                            <td style="padding: 0 15px; text-align: center; border-right: 1px solid #DBEAFE;">
                                                <div style="font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                                                <div style="font-size: 16px; font-weight: 600; color: #16A34A;">Confirmed</div>
                                            </td>
                                            <td style="padding: 0 15px; text-align: center;">
                                                <div style="font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Processing</div>
                                                <div style="font-size: 16px; font-weight: 600; color: #1E293B;">24-48 hrs</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== ORDER SUMMARY CARD ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 30px; border: 1px solid #E5E7EB; border-radius: 14px; overflow: hidden;">
                            <!-- Title -->
                            <tr>
                                <td style="background-color: #FFFFFF; padding: 16px 20px; border-bottom: 1px solid #E5E7EB;">
                                    <span style="font-size: 16px; font-weight: 600; color: #1E293B;">📦 Order Summary</span>
                                </td>
                            </tr>
                            <!-- Product table -->
                            <tr>
                                <td style="padding: 0 20px 10px 20px;">
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 10px;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid #E5E7EB;">
                                                <th style="padding: 10px 12px 10px 0; text-align: left; font-size: 12px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Product</th>
                                                <th style="padding: 10px 12px; text-align: center; font-size: 12px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Qty</th>
                                                <th style="padding: 10px 12px; text-align: right; font-size: 12px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Price</th>
                                                <th style="padding: 10px 0 10px 12px; text-align: right; font-size: 12px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ' . $product_list_html . '
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <!-- Totals -->
                            <tr>
                                <td style="padding: 0 20px 20px 20px;">
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="border-top: 1px solid #E5E7EB; margin-top: 10px;">
                                        <tr>
                                            <td style="padding: 10px 0 4px 0; text-align: right; font-size: 15px; color: #1E293B;">Subtotal</td>
                                            <td style="padding: 10px 0 4px 20px; text-align: right; font-size: 15px; color: #1E293B;">' . $formatted_subtotal . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; text-align: right; font-size: 15px; color: #1E293B;">Delivery</td>
                                            <td style="padding: 4px 0 4px 20px; text-align: right; font-size: 15px; color: #1E293B;">' . $formatted_delivery . '</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 4px 0; text-align: right; font-size: 15px; color: #1E293B;">Discount</td>
                                            <td style="padding: 4px 0 4px 20px; text-align: right; font-size: 15px; color: #1E293B;">' . $formatted_discount . '</td>
                                        </tr>
                                        <tr style="background-color: #0A3D91; border-radius: 8px;">
                                            <td style="padding: 12px 0 12px 0; text-align: right; font-size: 18px; font-weight: 700; color: #FFFFFF; border-radius: 8px 0 0 8px;">Grand Total</td>
                                            <td style="padding: 12px 0 12px 20px; text-align: right; font-size: 20px; font-weight: 700; color: #D4AF37; border-radius: 0 8px 8px 0;">' . $formatted_grand_total . '</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== BUTTONS ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 30px;">
                            <tr>
                                <td style="text-align: center; padding: 0 10px;">
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="display: inline-block; margin: 0 8px 10px 8px;">
                                        <tr>
                                            <td style="background-color: #1A56DB; border-radius: 50px; padding: 14px 36px; box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);">
                                                <a href="' . $companyWebsite . '/account/orders" style="color: #FFFFFF; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block;">View My Order</a>
                                            </td>
                                        </tr>
                                    </table>
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="display: inline-block; margin: 0 8px 10px 8px;">
                                        <tr>
                                            <td style="background-color: #D4AF37; border-radius: 50px; padding: 14px 36px; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);">
                                                <a href="' . $companyWebsite . '" style="color: #0A3D91; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block;">Continue Shopping</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== INFORMATION CARD ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 30px; background-color: #F8FAFC; border-radius: 14px; border: 1px solid #E5E7EB; padding: 16px 20px;">
                            <tr>
                                <td>
                                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="text-align: center; padding: 8px 0;">
                                                <span style="font-size: 24px;">📦</span>
                                                <div style="font-size: 14px; font-weight: 500; color: #1E293B;">Preparing</div>
                                            </td>
                                            <td style="text-align: center; padding: 8px 0;">
                                                <span style="font-size: 24px;">🚚</span>
                                                <div style="font-size: 14px; font-weight: 500; color: #1E293B;">Shipping soon</div>
                                            </td>
                                            <td style="text-align: center; padding: 8px 0;">
                                                <span style="font-size: 24px;">📧</span>
                                                <div style="font-size: 14px; font-weight: 500; color: #1E293B;">Email alerts</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="text-align: center; padding-top: 10px; font-size: 14px; color: #64748B;">
                                                You\'ll receive another email when your order ships.
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== THANK YOU ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 30px;">
                            <tr>
                                <td style="text-align: center;">
                                    <h2 style="font-size: 22px; font-weight: 600; color: #1E293B; margin: 0 0 6px 0;">Thank You For Shopping With Us</h2>
                                    <p style="font-size: 15px; color: #64748B; margin: 0;">
                                        We appreciate your trust in <strong style="color: #1A56DB;">RD Vendora Marketplace</strong>.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- ===== FOOTER ===== -->
                        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin-top: 35px; border-top: 1px solid #E5E7EB; padding-top: 20px;">
                            <tr>
                                <td style="text-align: center; font-size: 13px; color: #94A3B8; line-height: 1.6;">
                                    <span style="color: #1E293B; font-weight: 600;">' . $companyName . '</span><br>
                                    <a href="mailto:' . $companyEmail . '" style="color: #1A56DB; text-decoration: none;">' . $companyEmail . '</a> &nbsp;|&nbsp; <a href="' . $companyWebsite . '" style="color: #1A56DB; text-decoration: none;">' . $companyWebsite . '</a> &nbsp;|&nbsp; ' . $companyPhone . '<br>
                                    © 2026 ' . $companyName . ' — All Rights Reserved.<br>
                                    <span style="font-size: 12px; color: #94A3B8;">This is an automated email. Please do not reply.</span>
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

    // ---------- PLAIN TEXT VERSION (kept intact and updated with totals) ----------
    $plain_body = "===========================================\n";
    $plain_body .= "        $companyName\n";
    $plain_body .= "        $companyAddress\n";
    $plain_body .= "Phone: $companyPhone  |  Email: $companyEmail\n";
    $plain_body .= "===========================================\n\n";
    $plain_body .= "Hello {$customer_name},\n\n";
    $plain_body .= "Thank you for your purchase! Your order has been confirmed.\n";
    $plain_body .= "Order #: {$order_id}\n\n";
    $plain_body .= "Items Purchased:\n";
    $plain_body .= $product_list_plain;
    $plain_body .= "\nSubtotal:  {$formatted_subtotal}\n";
    $plain_body .= "Delivery:  {$formatted_delivery}\n";
    $plain_body .= "Discount:  {$formatted_discount}\n";
    $plain_body .= "Grand Total: {$formatted_grand_total}\n\n";
    $plain_body .= "We'll notify you once shipped.\n\n";
    $plain_body .= "If you did not place this order, you can safely ignore this email.\n";
    $plain_body .= "Please do not reply directly to this message.\n\n";
    $plain_body .= "Regards,\nYour Store Team\n";
    $plain_body .= "-------------------------------------------\n";
    $plain_body .= "Powered by $companyName\n";

    // ---------- SEND MULTIPART/ALTERNATIVE EMAIL ----------
    $subject = "Your Order #{$order_id} Confirmation";
    $boundary = md5(uniqid(time()));
    $headers = "From: no-reply@yourdomain.com\r\n";
    $headers .= "Reply-To: support@yourdomain.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $plain_body . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $html_body . "\r\n\r\n";
    $message .= "--$boundary--";
    
    return mail($customer_email, $subject, $message, $headers);
}
?>