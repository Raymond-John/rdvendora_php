<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// ---------- Ensure tables exist (same as in dashboard.php) ----------
$conn->query("CREATE TABLE IF NOT EXISTS store_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin','editor','viewer') DEFAULT 'viewer',
    invited_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_store_user (store_id, user_id),
    INDEX (store_id),
    INDEX (user_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS team_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin','editor','viewer') NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','accepted','expired') DEFAULT 'pending',
    invited_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX (token),
    INDEX (store_id),
    INDEX (status)
)");

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('Invalid invitation link.');
}

// Find the invite
$stmt = $conn->prepare("SELECT * FROM team_invites WHERE token = ? AND status = 'pending' AND (expires_at IS NULL OR expires_at > NOW())");
if (!$stmt) {
    die('Prepare failed (team_invites): ' . $conn->error);
}
$stmt->bind_param("s", $token);
$stmt->execute();
$invite = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invite) {
    die('This invitation link is invalid or has expired.');
}

// If user not logged in, store token and redirect to login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['invite_token'] = $token;
    header('Location: login?redirect=accept-invite&msg=Please login or register to accept the invitation.');
    exit();
}

$user_id = $_SESSION['user_id'];
$store_id = $invite['store_id'];
$role = $invite['role'];

// Check if already a team member
$check = $conn->prepare("SELECT id FROM store_staff WHERE store_id = ? AND user_id = ?");
if (!$check) {
    die('Prepare failed (store_staff select): ' . $conn->error);
}
$check->bind_param("ii", $store_id, $user_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    // Already a member – just mark invite accepted
    $conn->prepare("UPDATE team_invites SET status = 'accepted' WHERE id = ?")->bind_param("i", $invite['id'])->execute();
    header('Location: dashboard?msg=You are already a team member.');
    exit();
}
$check->close();

// Add to store_staff
$insert = $conn->prepare("INSERT INTO store_staff (store_id, user_id, role, invited_by) VALUES (?, ?, ?, ?)");
if (!$insert) {
    die('Prepare failed (store_staff insert): ' . $conn->error);
}
$insert->bind_param("iiii", $store_id, $user_id, $role, $invite['invited_by']);
if ($insert->execute()) {
    // Mark invite as accepted
    $update = $conn->prepare("UPDATE team_invites SET status = 'accepted' WHERE id = ?");
    if ($update) {
        $update->bind_param("i", $invite['id']);
        $update->execute();
        $update->close();
    }
    // ✅ Redirect to dashboard
    header('Location: dashboard?msg=You have successfully joined the store team!');
} else {
    die('Error adding you to the store team: ' . $insert->error);
}
$insert->close();
?>