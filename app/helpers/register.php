<?php
session_start();
require_once "connection.php";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm)) {
        header('Location: ../register?error=All fields are required');
        exit();
    }
   
    if (strlen($password) < 6) {
        header('Location: ../register?error=Password too short');
        exit();
    }

    if ($password !== $confirm) {
        header('Location: ../register?error=Password mismatch');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../register?error=Invalid email');
        exit();
    }

    // Check if email already exists
    $checkEmail = $connect->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    if ($result->num_rows > 0) {
        $checkEmail->close();
        header('Location: ../register?error=Email already exists');
        exit();
    }
    $checkEmail->close();

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user – adjust column names to match your `users` table
    // Your table likely has `fullname`, `email`, `password`
    $stmt = $connect->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $fullname, $email, $hashedPassword);
    if (!$stmt->execute()) {
        $stmt->close();
        header('Location: ../register?error=Registration failed');
        exit();
    }
    $userId = $stmt->insert_id;
    $stmt->close();

    // Create a seller profile (required for `create-store.php` to find the seller_id)
    $stmt2 = $connect->prepare("INSERT INTO seller_profiles (user_id) VALUES (?)");
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $stmt2->close();

    // Set session variables (same keys that `create-store.php` expects)
    $_SESSION['user_id']   = $userId;
    $_SESSION['fullname']  = $fullname;
    $_SESSION['email']     = $email;

    // Redirect to the store creation wizard
    header('Location: ../create-store?success=Account created! Now set up your store.');
    exit();
}
?>