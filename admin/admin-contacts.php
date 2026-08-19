<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';   // permission helper

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

// ---------- PERMISSION CHECK FOR CONTACTS PAGE ----------
if (!adminHasPermission('contacts', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view contact messages.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'mark_read' && $id) {
        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Message marked as read.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
    elseif ($action === 'mark_unread' && $id) {
        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'unread' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Message marked as unread.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
    elseif ($action === 'delete' && $id) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Message deleted.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$countResult = $conn->query("SELECT COUNT(*) as total FROM contact_messages");
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

$messages = [];
$result = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
if ($result) $messages = $result->fetch_all(MYSQLI_ASSOC);

$adminPageTitle = 'Contact Messages - Admin | RD Vendora';
$adminPageHeading = 'Contact Messages';
$adminPageSubtitle = 'Messages from the public contact form';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="content-card">
        <?php if ($message): ?>
            <div class="message message-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message message-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name / Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Received</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr><td colspan="7" style="text-align: center;">No messages found.</td></td>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= $msg['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($msg['name']) ?></strong><br>
                                <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" style="color: var(--primary);"><?= htmlspecialchars($msg['email']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($msg['subject']) ?></td>
                            <td style="max-width: 250px;"><?= htmlspecialchars(substr($msg['message'], 0, 80)) . (strlen($msg['message']) > 80 ? '...' : '') ?></td>
                            <td><span class="badge <?= $msg['status'] === 'unread' ? 'badge-unread' : 'badge-read' ?>"><?= ucfirst($msg['status']) ?></span></td>
                            <td><?= date('M d, Y H:i', strtotime($msg['created_at'])) ?></td>
                            <td class="action-buttons">
                                <button class="btn-sm btn-outline" onclick="viewMessage(<?= htmlspecialchars(json_encode($msg)) ?>)">View</button>
                                <?php if ($msg['status'] === 'unread'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button type="submit" class="btn-sm btn-primary">Mark Read</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="mark_unread">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button type="submit" class="btn-sm btn-outline">Mark Unread</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for viewing full message -->
<div id="messageModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Message Details</h3>
            <div class="modal-close" onclick="closeModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Dynamic content will be injected here -->
        </div>
    </div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
