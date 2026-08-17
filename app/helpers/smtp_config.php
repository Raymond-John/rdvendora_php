<?php
$smtp_host      = rdv_env('SMTP_HOST', 'smtp.gmail.com');
$smtp_auth      = true;
$smtp_username  = rdv_env('SMTP_USER', '');
$smtp_password  = rdv_env('SMTP_PASS', '');
$smtp_secure    = rdv_env('SMTP_ENCRYPTION', 'tls');
$smtp_port      = (int) rdv_env('SMTP_PORT', 587);
$smtp_from      = rdv_env('SMTP_FROM', $smtp_username);
$smtp_from_name = rdv_env('SMTP_FROM_NAME', 'RD Vendora Marketplace');
