<?php
session_start();
header('Content-Type: application/json');

require_once 'includes/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];
if (empty($name)) $errors[] = 'Name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if (empty($subject)) $subject = 'General inquiry';
if (empty($message)) $errors[] = 'Message cannot be empty.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit();
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'unread')");
$stmt->bind_param("ssss", $name, $email, $subject, $message);
if ($stmt->execute()) {
    // Optional: send email to admin
    $adminEmail = 'admin@RD Vendora.com'; // Update with your admin email
    $emailSubject = "New Contact Message: $subject";
    $emailBody = "Name: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message";
    @mail($adminEmail, $emailSubject, $emailBody, "From: $email\r\nReply-To: $email");
    
    echo json_encode(['success' => true, 'message' => 'Message sent successfully! We will get back to you soon.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>