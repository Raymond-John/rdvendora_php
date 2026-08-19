<?php
require_once __DIR__ . '/includes/public_site.php';
header('Location: ' . rdv_blog_url('accepting-payments'), true, 301);
exit;
