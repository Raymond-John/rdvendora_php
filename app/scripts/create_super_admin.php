<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if (!$conn) die('Database connection failed.');

$email    = 'admin@rdvendora.com';
$fullname = 'Super Admin';
$password = 'Admin123#';
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Check if user exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    // Update existing user to super admin
    $update = $conn->prepare("UPDATE users SET fullname = ?, password = ?, is_admin = 1, role_id = (SELECT id FROM roles WHERE name = 'super_admin') WHERE email = ?");
    $update->bind_param("sss", $fullname, $hashed, $email);
    $update->execute();
    echo "✅ User updated to super admin. Login with $email / $password";
} else {
    // Insert new super admin
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, is_admin, role_id, created_at) 
                            VALUES (?, ?, ?, 1, (SELECT id FROM roles WHERE name = 'super_admin'), NOW())");
    $stmt->bind_param("sss", $fullname, $email, $hashed);
    if ($stmt->execute()) {
        echo "✅ Super admin created! Email: $email, Password: $password";
    } else {
        echo "❌ Error: " . $stmt->error;
    }
}
$conn->close();
?>