<?php
require_once __DIR__ . '/includes/bootstrap.php';

// google-callback.php – handles Google's redirect
require_once 'connection.php';

// 1. Verify state to prevent CSRF
if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth_state'])) {
    http_response_code(403);
    die('Invalid state parameter.');
}

// 2. Exchange authorization code for tokens
if (empty($_GET['code'])) {
    http_response_code(400);
    die('Authorization code missing.');
}

// Use cURL to get token
$token_url = 'https://oauth2.googleapis.com/token';
$post_data = [
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
$token_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    http_response_code(500);
    die('Failed to get token from Google.');
}

$token_data = json_decode($token_response, true);
$access_token = $token_data['access_token'];

// 3. Fetch user info from Google
$userinfo_url = 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=' . urlencode($access_token);
$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userinfo_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    http_response_code(500);
    die('Failed to fetch user info.');
}

$google_user = json_decode($userinfo_response, true);
$google_id   = $google_user['id'];
$email       = $google_user['email'];
$name        = $google_user['name'];

// 4. Check if user exists by google_id OR email
try {
    $pdo = getDB();

    // Find by google_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = :google_id LIMIT 1");
    $stmt->execute(['google_id' => $google_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // Find by email (user may have signed up manually before)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Link Google account to existing user
            $stmt = $pdo->prepare("UPDATE users SET google_id = :google_id, email_verified = 1 WHERE id = :id");
            $stmt->execute(['google_id' => $google_id, 'id' => $user['id']]);
        } else {
            // New seller: create user & seller profile
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, google_id, email_verified)
                 VALUES (:full_name, :email, :google_id, 1)"
            );
            $stmt->execute([
                'full_name' => $name,
                'email'     => $email,
                'google_id' => $google_id,
            ]);
            $user_id = $pdo->lastInsertId();

            // Create seller profile (pending)
            $stmt = $pdo->prepare("INSERT INTO seller_profiles (user_id, status) VALUES (:user_id, 'pending')");
            $stmt->execute(['user_id' => $user_id]);

            $user = [
                'id'        => $user_id,
                'full_name' => $name,
                'email'     => $email
            ];
        }
    } else {
        // Existing Google user – already verified
        // Possibly update name
        $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name WHERE id = :id");
        $stmt->execute(['full_name' => $name, 'id' => $user['id']]);
    }

    // 5. Log the seller in (session or JWT)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];

    // 6. Redirect to onboarding or dashboard
    // If seller profile is pending, they still need admin approval later
    header('Location: onboarding.php');
    exit;

} catch (PDOException $e) {
    error_log('Google login error: ' . $e->getMessage());
    die('Server error. Please try again.');
}