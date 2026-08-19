<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/includes/connection.php';
require_once __DIR__ . '/includes/public_site.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!rdv_rate_limit('contact', 5, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many messages. Please wait a few minutes.']);
    exit;
}

if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully. We will get back to you if we need more detail.']);
    exit;
}

if (!rdv_csrf_verify()) {
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? 'General inquiry'));
$message = trim((string) ($_POST['message'] ?? ''));

$allowedSubjects = ['General inquiry', 'Sales', 'Support', 'Partnership', 'Other'];
if (!in_array($subject, $allowedSubjects, true)) {
    $subject = 'General inquiry';
}

$errors = [];
if ($name === '' || strlen($name) > 120) {
    $errors[] = 'Please enter your name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    $errors[] = 'Please enter a valid email address.';
}
if ($message === '' || strlen($message) < 10) {
    $errors[] = 'Please write a message of at least 10 characters.';
}
if (strlen($message) > 5000) {
    $errors[] = 'Please shorten your message.';
}
if ($errors) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$conn = $conn ?? $connect ?? null;
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'The contact form is temporarily unavailable.']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(120) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread','read') NOT NULL DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'unread')");
$stmt->bind_param('ssss', $name, $email, $subject, $message);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'We could not save your message. Please try again.']);
    $stmt->close();
    exit;
}
$stmt->close();

$adminEmail = rdv_site_contact_email($conn);
if ($adminEmail !== '' && function_exists('sendEmail') === false) {
    require_once APP_PATH . '/helpers/email_functions.php';
}
if ($adminEmail !== '' && function_exists('sendEmail')) {
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $body = '<p>New contact form message on RD Vendora.</p><p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        . '<br><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8')
        . '<br><strong>Subject:</strong> ' . $safeSubject . '</p><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';
    $plain = "Name: $name\nEmail: $email\nSubject: $subject\n\n$message";
    @sendEmail($adminEmail, 'Contact form: ' . $subject, $body, $plain);
}

echo json_encode(['success' => true, 'message' => 'Message sent. We will reply to the email you provided when we can.']);
