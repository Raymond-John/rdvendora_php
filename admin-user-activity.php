<?php
$query = empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING'];
header('Location: admin/' . basename(__FILE__) . $query, true, 301);
exit;
