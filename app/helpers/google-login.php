<?php
require_once dirname(__DIR__, 2) . '/includes/connection.php';
header('Location: https://rdvendora.com/oauth2callback.php', true, 302);
exit;
