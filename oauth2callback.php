<?php
session_start();
require_once 'includes/connection.php';
require_once 'includes/email_functions.php';
require_once APP_PATH . '/lib/GoogleOAuth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$google = new GoogleOAuth(GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI);

// Step 1: If no code, redirect to Google consent screen
if (!isset($_GET['code'])) {
    $authUrl = $google->getAuthUrl();
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Exchange code for access token
try {
    $tokenData = $google->getAccessToken($_GET['code']);
    $accessToken = $tokenData['access_token'];
} catch (Exception $e) {
    die('Error getting access token: ' . $e->getMessage());
}

// Step 3: Fetch user info from Google
try {
    $userInfo = $google->getUserInfo($accessToken);
} catch (Exception $e) {
    die('Error fetching user info: ' . $e->getMessage());
}

$google_id = $userInfo['id'];
$email     = $userInfo['email'];
$fullname  = $userInfo['name'];

// Step 4: Check if user already exists (by google_id or email)
$stmt = $conn->prepare("SELECT id, full_name, google_id FROM users WHERE google_id = ? OR email = ?");
$stmt->bind_param("ss", $google_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$isNewUser = false;

if ($user) {
    // Existing user – log them in
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['fullname']  = $user['full_name'];
    
    // If the user existed by email but google_id is missing, update it
    if (empty($user['google_id'])) {
        $stmt = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
        $stmt->bind_param("si", $google_id, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Optional: update full_name if it changed in Google
    if ($user['full_name'] !== $fullname) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->bind_param("si", $fullname, $user['id']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['fullname'] = $fullname;
    }
    
    // ***** SEND LOGIN NOTIFICATION (using the styled function from email_functions.php) *****
    sendLoginNotification($email, $user['full_name']);
    
} else {
    // Create new user (dummy password because they will use Google)
    $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, google_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $fullname, $email, $dummy_password, $google_id);
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $_SESSION['user_id']   = $user_id;
        $_SESSION['fullname']  = $fullname;
        $isNewUser = true;
    } else {
        die('Database error: ' . $stmt->error);
    }
    $stmt->close();
}

// Step 5: If new user, send welcome email (styled)
if ($isNewUser) {
    // Now uses the premium styled function from email_functions.php
    $emailSent = sendWelcomeEmail($email, $fullname);
    if (!$emailSent) {
        // The function already logs errors; we can set a session warning
        $_SESSION['registration_warning'] = "Account created via Google, but welcome email could not be sent. Please check your email settings.";
    } else {
        $_SESSION['registration_success'] = "Welcome email sent! Check your inbox.";
    }
}

// Step 6: Redirect to store creation page (or dashboard if store already exists)
header('Location: create-store.php');
exit;
?>