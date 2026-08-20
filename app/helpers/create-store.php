<?php
session_start();
require_once "connection.php";

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    
    $fullname = trim($_POST['fullname'] ?? '');      // fixed: was undefined
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Check empty fields
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm)) {
        header('Location: ../register?error=All fields are required');
        exit();
    }
   
    // Check password length
    if (strlen($password) < 6) {
        header('Location: ../register?error=Password too short');
        exit();
    }

    // Check password match
    if ($password !== $confirm) {
        header('Location: ../register?error=Password Mismatch');
        exit();
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../register?error=Email is invalid');
        exit();
    }

    // Check if email already exists
    $checkEmail = $connect->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    if ($result->num_rows > 0) {
        $checkEmail->close();
        header('Location: ../register?error=Email Already Exists');
        exit();
    }
    $checkEmail->close();

    // Hash password (column name in DB is 'password', not 'password_hash')
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user – using column names: fullname, email, password
    $stmt = $connect->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $fullname, $email, $hashedPassword);
    if (!$stmt->execute()) {
        $stmt->close();
        header('Location: ../register?error=Registration failed');
        exit();
    }
    $userId = $stmt->insert_id;
    $stmt->close();

    // ---------- Automatically create a seller profile for the user ----------
    $stmt2 = $connect->prepare("INSERT INTO seller_profiles (user_id) VALUES (?)");
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $stmt2->close();

    // Log the user in
    $_SESSION['user_id']   = $userId;
    $_SESSION['fullname']  = $fullname;
    $_SESSION['email']     = $email;

    // Redirect to store creation page
    header('Location: ../create-store?success=User registered successfully');
    exit();
}
?>