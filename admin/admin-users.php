<?php
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once dirname(__DIR__) . '/app/helpers/admin_login_security.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

rdv_ensure_users_is_active_column($conn);

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

// Permission check for users page
if (!adminHasPermission('users', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage users.</p><a href="admin">Go to Dashboard</a></div>');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_user'])) {
    $userId = intval($_POST['user_id']);
    if ($userId > 0 && rdv_reactivate_admin_user($conn, $userId)) {
        $message = "User reactivated successfully.";
    } else {
        $message = "Could not reactivate that user.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_user'])) {
    $userId = intval($_POST['user_id']);
    if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
        $message = "You cannot deactivate your own account.";
    } elseif ($userId > 0 && rdv_deactivate_admin_user($conn, $userId)) {
        $message = "Admin access deactivated.";
    } else {
        $message = "Could not deactivate that user.";
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
$query = "SELECT id, $nameExpr AS fullname, email, is_admin, is_active, created_at FROM users ORDER BY created_at DESC";
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
        <div class="message <?= preg_match('/success|reactivated|deactivated/i', $message) ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody id="usersTableBody">
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" class="empty-state">👥 No users found</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <?php
                        $userName = (string) ($user['fullname'] ?? '');
                        $userEmail = (string) ($user['email'] ?? '');
                        $joinedAt = !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—';
                        $isActive = !isset($user['is_active']) || (int) $user['is_active'] === 1;
                        $isSelf = strcasecmp((string) ($_SESSION['email'] ?? ''), $userEmail) === 0;
                    ?>
                    <tr data-user-name="<?= strtolower(htmlspecialchars($userName)) ?>" data-user-email="<?= strtolower(htmlspecialchars($userEmail)) ?>">
                        <td><strong><?= htmlspecialchars($userName !== '' ? $userName : '—') ?></strong></td>
                        <td><?= htmlspecialchars($userEmail) ?></td>
                        <td><span class="badge <?= $user['is_admin'] ? 'badge-admin' : 'badge-user' ?>"><?= $user['is_admin'] ? 'Admin' : 'User' ?></span></td>
                        <td><span class="badge <?= $isActive ? 'badge-user' : 'badge-admin' ?>"><?= $isActive ? 'Active' : 'Deactivated' ?></span></td>
                        <td><?= htmlspecialchars($joinedAt) ?></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if (!empty($user['is_admin']) && !$isSelf && $isActive): ?>
                            <form method="POST" onsubmit="return confirm('Deactivate this admin? They will be signed out of the dashboard.')">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button type="submit" name="deactivate_user" class="btn-sm">Deactivate</button>
                            </form>
                            <?php elseif (!empty($user['is_admin']) && !$isSelf && !$isActive): ?>
                            <form method="POST" onsubmit="return confirm('Reactivate this admin?')">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button type="submit" name="reactivate_user" class="btn-sm">Reactivate</button>
                            </form>
                            <?php endif; ?>
                            <?php if (!$user['is_admin'] || ($user['is_admin'] && !$isSelf)): ?>
                            <form method="POST" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button type="submit" name="delete_user" class="btn-sm">Delete</button>
                            </form>
                            <?php elseif ($isSelf): ?>
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
