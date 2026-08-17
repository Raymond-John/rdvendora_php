<?php
// google-login.php – redirects seller to Google
require_once 'connection.php';

// Generate a random state token and store in session
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// Build Google OAuth URL
$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
    'state'         => $state,
    'access_type'   => 'offline',   // optional, to get refresh token if needed
    'prompt'        => 'select_account'   // forces account selection
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;