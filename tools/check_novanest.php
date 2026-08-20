<?php
require_once dirname(__DIR__) . '/includes/connection.php';
require_once dirname(__DIR__) . '/includes/subscription_check.php';
$conn = $conn ?? $connect;
$q = $conn->query("SELECT u.id uid, s.id sid, s.status, s.store_name FROM users u JOIN stores s ON s.user_id=u.id WHERE s.store_slug='novanest'");
$row = $q->fetch_assoc();
print_r($row);
$has = hasActiveSubscription($conn, (int) $row['uid']);
echo 'hasActiveSubscription=' . ($has ? 'yes' : 'no') . "\n";
$subs = $conn->query('SELECT id, plan, status, end_date FROM subscriptions WHERE user_id=' . (int) $row['uid'] . ' ORDER BY id DESC LIMIT 5');
while ($s = $subs->fetch_assoc()) print_r($s);
