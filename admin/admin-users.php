<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
    }
}

// Permission check for users page
if (!adminHasPermission('users', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage users.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    if ($userId !== ($_SESSION['user_id'] ?? 0)) {
        $checkAdmin = $conn->query("SELECT is_admin FROM users WHERE id = $userId");
        $isTargetAdmin = $checkAdmin->fetch_assoc()['is_admin'] ?? 0;
        if (!$isTargetAdmin) {
            $conn->query("DELETE FROM users WHERE id = $userId");
            $message = "User deleted successfully.";
        } else {
            $message = "Cannot delete another admin user.";
        }
    } else {
        $message = "You cannot delete your own account.";
    }
}

// Fetch all users – resolve name from whichever column the users table actually has
$users = [];
$nameExpr = 'email';
$colResult = $conn->query('SHOW COLUMNS FROM users');
if ($colResult) {
    $existingCols = [];
    while ($col = $colResult->fetch_assoc()) {
        $existingCols[] = $col['Field'];
    }
    $nameParts = [];
    foreach (['fullname', 'full_name', 'name', 'username'] as $candidate) {
        if (in_array($candidate, $existingCols, true)) {
            $nameParts[] = $candidate;
        }
    }
    if ($nameParts) {
        $nullIfParts = array_map(static function ($col) {
            return "NULLIF($col, '')";
        }, $nameParts);
        $nameExpr = 'COALESCE(' . implode(', ', $nullIfParts) . ', email, \'\')';
    }
}
$query = "SELECT id, $nameExpr AS fullname, email, is_admin, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$adminPageTitle = 'Users - RD Vendora Admin';
$adminPageHeading = 'Users';
$adminPageSubtitle = 'Manage all registered users on the platform';
$adminSearchPlaceholder = 'Search users...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <?php if (isset($message)): ?>
        <div class="message <?= strpos($message, 'success') !== false ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody id="usersTableBody">
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="empty-state">👥 No users found</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <?php
                        $userName = (string) ($user['fullname'] ?? '');
                        $userEmail = (string) ($user['email'] ?? '');
                        $joinedAt = !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—';
                    ?>
                    <tr data-user-name="<?= strtolower(htmlspecialchars($userName)) ?>" data-user-email="<?= strtolower(htmlspecialchars($userEmail)) ?>">
                        <td><strong><?= htmlspecialchars($userName !== '' ? $userName : '—') ?></strong></td>
                        <td><?= htmlspecialchars($userEmail) ?></td>
                        <td><span class="badge <?= $user['is_admin'] ? 'badge-admin' : 'badge-user' ?>"><?= $user['is_admin'] ? 'Admin' : 'User' ?></span></td>
                        <td><?= htmlspecialchars($joinedAt) ?></td>
                        <td>
                            <?php if (!$user['is_admin'] || ($user['is_admin'] && $_SESSION['email'] !== $user['email'])): ?>
                            <form method="POST" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" name="delete_user" class="btn-sm">Delete</button>
                            </form>
                            <?php else: ?>
                            <span style="color: var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
