<?php
require_once "connection.php";
require_once "email_functions.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use correct connection variable
if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) {
    header('location: ../login.php?error=Database connection failed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $plain_password = $_POST['password'] ?? '';

    if (empty($email) || empty($plain_password)) {
        header('location: ../login.php?error=All fields are required');
        exit();
    }

    // Prepare and execute query – use 'password' column (matches reset script)
    $stmt = $conn->prepare("SELECT id, email, full_name, password FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        $stmt->close();
        header('location: ../login.php?error=Email not found');
        exit();
    }

    // Verify password using the 'password' column
    if (!password_verify($plain_password, $user['password'])) {
        $stmt->close();
        header('location: ../login.php?error=Incorrect password');
        exit();
    }

    // Set session variables
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'] ?? $user['email'];

    $stmt->close();

    require_once __DIR__ . '/log_activity.php';
    if (function_exists('logUserActivity')) {
        logUserActivity((int) $user['id'], 'login', 'login.php', 'Vendor signed in');
    }

    // Optional: send login notification
    if (function_exists('sendLoginNotification')) {
        sendLoginNotification($email, $user['full_name']);
    }

    header('location: ../dashboard.php?success=Login successful');
    exit();
}
?>