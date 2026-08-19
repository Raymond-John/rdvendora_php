<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';

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

$message = '';
$messageType = '';

// Handle sending a new message (broadcast to all vendors)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject']);
    $content = trim($_POST['message']);
    
    if (empty($subject) || empty($content)) {
        $message = "Please fill in both subject and message.";
        $messageType = "error";
    } else {
        // Get all vendor IDs from stores that are active
        $vendorQuery = $conn->query("SELECT user_id FROM stores WHERE status = 'active'");
        if ($vendorQuery && $vendorQuery->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO messages (vendor_id, subject, message, sender_type) VALUES (?, ?, ?, 'admin')");
            while ($vendor = $vendorQuery->fetch_assoc()) {
                $stmt->bind_param("iss", $vendor['user_id'], $subject, $content);
                $stmt->execute();
            }
            $stmt->close();
            $message = "Message sent to all active vendors.";
            $messageType = "success";
        } else {
            $message = "No active vendors found.";
            $messageType = "error";
        }
    }
}

// Fetch all messages (threaded view)
$threads = [];
$query = $conn->query("
    SELECT m.*, u.full_name as vendor_name, s.store_name
    FROM messages m
    LEFT JOIN users u ON m.vendor_id = u.id
    LEFT JOIN stores s ON m.vendor_id = s.user_id
    ORDER BY m.created_at DESC
");
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $threads[] = $row;
    }
}

$adminPageTitle = 'Admin - Messages';
$adminPageHeading = 'Messages';
$adminPageSubtitle = 'Broadcast messages to vendors';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content">
        <div class="page-header">
            <h1 class="page-title">Admin Messages</h1>
            <p>Send broadcast messages to all vendors, and view their replies.</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- New message form -->
        <div style="background:var(--bg-secondary); border-radius:1rem; padding:1.5rem; margin-bottom:2rem;">
            <h3>Send to all vendors</h3>
            <form method="POST">
                <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
                <div class="form-group"><label>Message</label><textarea name="message" rows="4" required></textarea></div>
                <button type="submit" name="send_message" class="btn btn-primary">Send Broadcast</button>
            </form>
        </div>
        
        <h3>All Messages (Admin & Vendor Replies)</h3>
        <?php if (empty($threads)): ?>
            <p>No messages yet.</p>
        <?php else: ?>
            <?php foreach ($threads as $msg): ?>
                <div class="message-thread">
                    <div class="message-header">
                        <strong><?= htmlspecialchars($msg['vendor_name'] ?? 'Vendor') ?> (<?= htmlspecialchars($msg['store_name'] ?? 'Store') ?>)</strong>
                        <small><?= date('Y-m-d H:i', strtotime($msg['created_at'])) ?></small>
                    </div>
                    <div><strong><?= htmlspecialchars($msg['subject']) ?></strong></div>
                    <div style="margin:0.5rem 0;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <div><span class="badge"><?= $msg['sender_type'] === 'admin' ? '📢 Admin' : '💬 Vendor Reply' ?></span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
