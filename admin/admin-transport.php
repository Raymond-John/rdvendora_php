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
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

// Create notifications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS `transport_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `manifest_filename` VARCHAR(255) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `manifest_filename` (`manifest_filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle AJAX requests (mark as read, mark all read)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    if ($action === 'mark_read') {
        $filename = $_POST['filename'] ?? '';
        if ($filename) {
            $stmt = $conn->prepare("INSERT INTO transport_notifications (manifest_filename, is_read) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_read = 1");
            $stmt->bind_param("s", $filename);
            $stmt->execute();
            $stmt->close();
            $response = ['success' => true];
        }
    } elseif ($action === 'mark_all_read') {
        $conn->query("UPDATE transport_notifications SET is_read = 1");
        $response = ['success' => true];
    }
    echo json_encode($response);
    exit;
}

// Handle file deletion
$message = '';
$messageType = '';
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = '../transport_manifests/' . $filename;
    if (file_exists($filepath) && unlink($filepath)) {
        $stmt = $conn->prepare("DELETE FROM transport_notifications WHERE manifest_filename = ?");
        $stmt->bind_param("s", $filename);
        $stmt->execute();
        $stmt->close();
        $message = "Manifest deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Failed to delete manifest.";
        $messageType = "error";
    }
}

// Scan manifests directory
$manifestDir = '../transport_manifests/';
$manifests = [];
$debugInfo = '';

if (!is_dir($manifestDir)) {
    $debugInfo = "⚠️ The folder '../transport_manifests/' does not exist. Create it with write permissions.";
} else {
    $files = scandir($manifestDir);
    $fileCount = 0;
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $filepath = $manifestDir . $file;
            // Ensure notification record exists (for old manifests)
            $stmt = $conn->prepare("INSERT IGNORE INTO transport_notifications (manifest_filename) VALUES (?)");
            $stmt->bind_param("s", $file);
            $stmt->execute();
            $stmt->close();
            // Get read status
            $stmt = $conn->prepare("SELECT is_read FROM transport_notifications WHERE manifest_filename = ?");
            $stmt->bind_param("s", $file);
            $stmt->execute();
            $readRes = $stmt->get_result();
            $isRead = $readRes->fetch_assoc()['is_read'] ?? 0;
            $stmt->close();

            $manifests[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'modified' => filemtime($filepath),
                'path' => $filepath,
                'is_read' => $isRead
            ];
            $fileCount++;
        }
    }
    usort($manifests, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    if ($fileCount == 0) {
        $debugInfo = "📭 No manifest files found in '../transport_manifests/'.";
    }
}

$unreadCount = 0;
$stmt = $conn->query("SELECT COUNT(*) as cnt FROM transport_notifications WHERE is_read = 0");
if ($stmt) {
    $unreadCount = $stmt->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
}

$adminPageTitle = 'Transport Orders - RD Vendora Admin';
$adminPageHeading = 'Transport Orders';
$adminPageSubtitle = 'Logistics and transport requests';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($debugInfo): ?>
        <div class="debug-info"><?= htmlspecialchars($debugInfo) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <button class="btn-sm" id="markAllReadBtn" <?= $unreadCount == 0 ? 'disabled' : '' ?>>✓ Mark all as read</button>
        <button class="btn-sm" id="refreshBtn">⟳ Refresh</button>
    </div>

    <div class="table-container">
        <table class="data-table" id="manifestsTable">
            <thead><tr><th>File Name</th><th>Date Created</th><th>Size</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if (empty($manifests)): ?>
                    <tr><td colspan="4" class="empty-state">📭 No transport manifests found yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($manifests as $m): ?>
                        <tr data-filename="<?= strtolower($m['name']) ?>" data-is-read="<?= $m['is_read'] ?>">
                            <td><?= htmlspecialchars($m['name']) ?><?php if (!$m['is_read']): ?><span class="new-badge">New</span><?php endif; ?></td>
                            <td><?= date('Y-m-d H:i:s', $m['modified']) ?></td>
                            <td><?= number_format($m['size'] / 1024, 2) ?> KB</td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn-sm view-manifest" data-filename="<?= htmlspecialchars($m['name']) ?>">View</button>
                                    <a href="<?= htmlspecialchars($m['path']) ?>" class="btn-sm" download>Download</a>
                                    <?php if (!$m['is_read']): ?>
                                        <button class="btn-sm mark-read-btn" data-filename="<?= htmlspecialchars($m['name']) ?>">✓ Mark as read</button>
                                    <?php endif; ?>
                                    <button class="btn-sm btn-danger" onclick="deleteManifest('<?= htmlspecialchars($m['name']) ?>')">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for viewing manifest content -->
<div id="manifestModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Manifest Content</h3>
            <button class="modal-close" onclick="closeManifestModal()">&times;</button>
        </div>
        <div class="modal-body" id="manifestContent"><div style="text-align:center;">Loading...</div></div>
        <div class="modal-footer">
            <button class="btn-sm" onclick="closeManifestModal()">Close</button>
        </div>
    </div>
<script>
    // Theme & sidebar (unchanged)
// Search filter
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#manifestsTable tbody tr');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            tableRows.forEach(row => {
                const filename = row.getAttribute('data-filename') || '';
                if (filename.includes(term)) row.style.display = '';
                else row.style.display = 'none';
            });
        });
    }

    // ---- MODAL HANDLING (FIXED: no auto-reload, keep modal open) ----
    const modal = document.getElementById('manifestModal');
    const manifestContentDiv = document.getElementById('manifestContent');

    // Attach event listeners to all "View" buttons (use event delegation)
    document.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.view-manifest');
        if (viewBtn) {
            const filename = viewBtn.getAttribute('data-filename');
            viewManifest(filename);
        }
    });

    window.viewManifest = async function(filename) {
        manifestContentDiv.innerHTML = '<div style="text-align:center; padding:1rem;">📄 Loading manifest...</div>';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        try {
            const response = await fetch('../transport_manifests/' + encodeURIComponent(filename));
            if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            const content = await response.text();
            manifestContentDiv.innerHTML = `<pre style="margin:0; white-space:pre-wrap; word-wrap:break-word;">${escapeHtml(content)}</pre>`;
            // Mark as read after successful load
            markAsRead(filename);
        } catch (err) {
            console.error(err);
            manifestContentDiv.innerHTML = `<div style="color:red; text-align:center; padding:1rem;">❌ Failed to load manifest.<br>File: ${escapeHtml(filename)}<br>Error: ${escapeHtml(err.message)}</div>`;
        }
    };

    function closeManifestModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    modal.addEventListener('click', (e) => { if (e.target === modal) closeManifestModal(); });
    window.closeManifestModal = closeManifestModal;

    // Mark as read – NO PAGE RELOAD (only update UI)
    function markAsRead(filename) {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=mark_read&filename=${encodeURIComponent(filename)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-filename="${filename.toLowerCase()}"]`);
                if (row) {
                    const newBadge = row.querySelector('.new-badge');
                    if (newBadge) newBadge.remove();
                    const markBtn = row.querySelector('.mark-read-btn');
                    if (markBtn) markBtn.remove();
                    row.setAttribute('data-is-read', '1');
                }
                // Update unread count in sidebar badge and page subtitle without reload
                const badge = document.getElementById('sidebarUnreadBadge');
                const unreadInfo = document.getElementById('unreadInfo');
                if (badge) {
                    let cnt = parseInt(badge.textContent) || 0;
                    cnt = Math.max(0, cnt - 1);
                    if (cnt === 0) badge.remove(); else badge.textContent = cnt;
                    if (unreadInfo) unreadInfo.textContent = cnt > 0 ? `(${cnt} new)` : '';
                }
            }
        })
        .catch(err => console.error('Mark read error:', err));
    }

    // Mark all as read – also no reload
    document.getElementById('markAllReadBtn')?.addEventListener('click', function() {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Remove all new badges and mark-read buttons
                document.querySelectorAll('.new-badge').forEach(b => b.remove());
                document.querySelectorAll('.mark-read-btn').forEach(b => b.remove());
                // Hide sidebar badge and subtitle info
                const badge = document.getElementById('sidebarUnreadBadge');
                if (badge) badge.remove();
                const unreadInfo = document.getElementById('unreadInfo');
                if (unreadInfo) unreadInfo.textContent = '';
                // Also update row attributes
                document.querySelectorAll('#manifestsTable tbody tr').forEach(row => row.setAttribute('data-is-read', '1'));
            }
        });
    });

    document.getElementById('refreshBtn')?.addEventListener('click', () => location.reload());

    function deleteManifest(filename) {
        if (confirm(`Delete manifest "${filename}"? This action cannot be undone.`)) {
            window.location.href='admin-transport?delete=' + encodeURIComponent(filename);
        }
    }

    

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]);
    }

    // Auto-refresh every 30 seconds (optional)
    setInterval(() => { if (!document.hidden) location.reload(); }, 30000);
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
