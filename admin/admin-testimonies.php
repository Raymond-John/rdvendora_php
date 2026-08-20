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
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR TESTIMONIALS PAGE ----------
if (!adminHasPermission('testimonials', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage testimonials.</p><a href="admin">Go to Dashboard</a></div>');
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $rating = (int)$_POST['rating'];
        $review = trim($_POST['review']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("INSERT INTO testimonials (name, email, rating, review, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiss", $name, $email, $rating, $review, $status);
        if ($stmt->execute()) {
            $message = "Testimonial added successfully.";
        } else {
            $error = "Error: " . $conn->error;
        }
        $stmt->close();
    }
    elseif ($action === 'approve') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE testimonials SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Testimonial approved.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
    elseif ($action === 'reject') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE testimonials SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Testimonial rejected.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $message = "Testimonial deleted.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
}

// Fetch testimonials with user info (optional join) – using 'fullname' column; adjust if needed
$testimonials = [];
$result = $conn->query("SELECT t.*, u.fullname as user_fullname 
                        FROM testimonials t 
                        LEFT JOIN users u ON t.user_id = u.id 
                        ORDER BY 
                            FIELD(t.status, 'pending', 'approved', 'rejected'),
                            t.created_at DESC");
if ($result) $testimonials = $result->fetch_all(MYSQLI_ASSOC);


$adminPageTitle = 'Testimonials Manager - Admin | RD Vendora';
$adminPageHeading = 'Testimonials';
$adminPageSubtitle = 'Review customer testimonials';
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

        <h3 style="margin-bottom: 1.5rem;">➕ Add New Testimonial (Manual)</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" required placeholder="Customer name">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required placeholder="customer@example.com">
                </div>
                <div class="form-group">
                    <label>Rating (1-5)</label>
                    <select name="rating">
                        <option value="5">★★★★★ (5)</option>
                        <option value="4">★★★★☆ (4)</option>
                        <option value="3">★★★☆☆ (3)</option>
                        <option value="2">★★☆☆☆ (2)</option>
                        <option value="1">★☆☆☆☆ (1)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Review Text *</label>
                <textarea name="review" rows="3" required placeholder="What did the customer say?"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Testimonial</button>
        </form>
    </div>

    <div class="content-card">
        <h3 style="margin-bottom: 1rem;">📋 All Testimonials</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $testimonial): ?>
                    <tr>
                        <td><?= $testimonial['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($testimonial['name']) ?></strong><br>
                            <small><?= htmlspecialchars($testimonial['email']) ?></small>
                            <?php if ($testimonial['user_fullname']): ?>
                                <br><small>(User: <?= htmlspecialchars($testimonial['user_fullname']) ?>)</small>
                            <?php endif; ?>
                         </td>
                        <td class="stars">
                            <?php 
                            $rating = (int)$testimonial['rating'];
                            echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                            ?>
                        </td>
                        <td style="max-width: 300px;"><?= nl2br(htmlspecialchars($testimonial['review'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $testimonial['status'] ?>">
                                <?= ucfirst($testimonial['status']) ?>
                            </span>
                         </td>
                        <td><?= date('M d, Y', strtotime($testimonial['created_at'])) ?></td>
                        <td class="action-buttons">
                            <?php if ($testimonial['status'] !== 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $testimonial['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm" style="padding: 0.3rem 0.8rem;">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($testimonial['status'] !== 'rejected'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?= $testimonial['id'] ?>">
                                    <button type="submit" class="btn btn-warning btn-sm" style="padding: 0.3rem 0.8rem;">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this testimonial?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $testimonial['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.3rem 0.8rem;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
