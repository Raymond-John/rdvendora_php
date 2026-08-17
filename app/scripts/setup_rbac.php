<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if (!$conn) die('Database connection failed.');

// Create tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    page_name VARCHAR(100) NOT NULL,
    can_access TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role_id, page_name),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
)");

// Add role_id if missing
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'role_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL,
                  ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL");
}

// Insert super_admin role
$conn->query("INSERT IGNORE INTO roles (name, description) VALUES ('super_admin', 'Full system access')");

// Get role ID
$roleId = $conn->query("SELECT id FROM roles WHERE name = 'super_admin'")->fetch_assoc()['id'];

// Grant all permissions
$pages = ['dashboard','users','stores','pricing','testimonials','contacts','about','chat','orders','transport','customers','send_email','marketplace_design','settings'];
foreach ($pages as $page) {
    $conn->query("INSERT IGNORE INTO role_permissions (role_id, page_name, can_access) VALUES ($roleId, '$page', 1)");
}

// Create super admin user
$email = 'admin@rdvendora.com';
$fullname = 'Super Admin';
$password = 'Admin123#';
$hashed = password_hash($password, PASSWORD_DEFAULT);

$check = $conn->query("SELECT id FROM users WHERE email = '$email'");
if ($check->num_rows > 0) {
    $conn->query("UPDATE users SET fullname = '$fullname', password = '$hashed', is_admin = 1, role_id = $roleId WHERE email = '$email'");
    echo "✅ User updated to super admin.\n";
} else {
    $conn->query("INSERT INTO users (fullname, email, password, is_admin, role_id, created_at) VALUES ('$fullname', '$email', '$hashed', 1, $roleId, NOW())");
    echo "✅ Super admin created.\n";
}
echo "Email: $email\nPassword: $password";
?>