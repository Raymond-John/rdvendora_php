<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php'; // adjust path if needed

if (!$conn) die('Database connection failed.');

// --- YOUR SPECIFIED CREDENTIALS ---
$email    = 'admin@rdvendora.com';
$fullname = 'Super Admin';
$password = 'Admin123#';
// ----------------------------------

// Hash the password securely
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Check if this email already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    // Update the existing user to be a super admin
    $check->close();
    $update = $conn->prepare("UPDATE users SET fullname = ?, password = ?, is_admin = 1, role = 'super_admin' WHERE email = ?");
    $update->bind_param("sss", $fullname, $hashed, $email);
    if ($update->execute()) {
        echo "✅ User <b>$email</b> updated to Super Admin successfully!<br>";
        echo "You can now log in with password: <b>$password</b>";
    } else {
        echo "❌ Update failed: " . $update->error;
    }
    $update->close();
} else {
    // Insert a brand new super admin
    $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, is_admin, role) VALUES (?, ?, ?, 1, 'super_admin')");
    $stmt->bind_param("sss", $fullname, $email, $hashed);
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        echo "✅ Super Admin created successfully!<br>";
        echo "Email: $email<br>";
        echo "Password: $password<br>";
        
        // Give full permissions in admin_permissions
        $pages = ['dashboard', 'users', 'stores', 'pricing', 'testimonials', 'contacts', 'about', 'chat', 'orders', 'transport', 'customers', 'send_email', 'marketplace_design', 'settings'];
        $perm_stmt = $conn->prepare("INSERT INTO admin_permissions (admin_id, page_name, can_access) VALUES (?, ?, 1)");
        foreach ($pages as $page) {
            $perm_stmt->bind_param("is", $user_id, $page);
            $perm_stmt->execute();
        }
        $perm_stmt->close();
        echo "✅ All permissions granted.";
    } else {
        echo "❌ Insert failed: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
?>