<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/send_approval_email.php'; // for sending approval email

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

// Permission check
if (!adminHasPermission('stores', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage documents.</p><a href="admin">Go to Dashboard</a></div>');
}

// Handle approve/reject actions
$action_message = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $new_status = null;

    if ($_POST['action'] === 'approve_document') {
        $new_status = 'approved';
        $action_message = 'Document approved.';
    } elseif ($_POST['action'] === 'reject_document') {
        $new_status = 'rejected';
        $action_message = 'Document rejected.';
    } elseif ($_POST['action'] === 'approve_all') {
        // Approve all pending documents for this user and activate store
        $user_id = (int)$_POST['user_id'];
        $conn->begin_transaction();
        try {
            // Update all pending documents for this user to approved
            $stmt = $conn->prepare("UPDATE company_documents SET status = 'approved' WHERE user_id = ? AND status = 'pending'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // Get store_id and store_name for this user
            $stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $store = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($store) {
                // Update store status to active
                $stmt = $conn->prepare("UPDATE stores SET status = 'active' WHERE id = ?");
                $stmt->bind_param("i", $store['id']);
                $stmt->execute();
                $stmt->close();

                // Send approval email
                sendStoreApprovalEmail($user_id, $store['store_name']);
                $action_message = "All documents approved and store '{$store['store_name']}' activated. Email sent to owner.";
                $action_type = 'success';
            } else {
                throw new Exception("No store found for user ID $user_id");
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $action_message = "Error: " . $e->getMessage();
            $action_type = 'error';
        }
        // Redirect to refresh
        header("Location: admin-documents?message=" . urlencode($action_message) . "&type=" . $action_type);
        exit();
    }

    if ($new_status !== null && $doc_id > 0) {
        $stmt = $conn->prepare("UPDATE company_documents SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $doc_id);
        if ($stmt->execute()) {
            $action_message = "Document status updated to $new_status.";
            $action_type = 'success';
            // Check if all documents for this user are now approved
            $check_stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM company_documents WHERE user_id = (SELECT user_id FROM company_documents WHERE id = ?) AND status = 'pending'");
            $check_stmt->bind_param("i", $doc_id);
            $check_stmt->execute();
            $pending = $check_stmt->get_result()->fetch_assoc()['pending_count'] ?? 0;
            $check_stmt->close();
            if ($pending == 0) {
                // All documents for this user are now approved – auto‑activate store
                $user_stmt = $conn->prepare("SELECT user_id FROM company_documents WHERE id = ?");
                $user_stmt->bind_param("i", $doc_id);
                $user_stmt->execute();
                $uid = $user_stmt->get_result()->fetch_assoc()['user_id'] ?? 0;
                $user_stmt->close();
                if ($uid) {
                    $store_stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ?");
                    $store_stmt->bind_param("i", $uid);
                    $store_stmt->execute();
                    $store = $store_stmt->get_result()->fetch_assoc();
                    $store_stmt->close();
                    if ($store) {
                        $conn->query("UPDATE stores SET status = 'active' WHERE id = {$store['id']}");
                        sendStoreApprovalEmail($uid, $store['store_name']);
                        $action_message .= " All documents are now approved – store '{$store['store_name']}' has been activated and the owner notified.";
                    }
                }
            }
        } else {
            $action_message = "Database error: " . $conn->error;
            $action_type = 'error';
        }
        $stmt->close();
        header("Location: admin-documents?message=" . urlencode($action_message) . "&type=" . $action_type);
        exit();
    }
}

// Retrieve messages from redirect
$message = isset($_GET['message']) ? $_GET['message'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Fetch all users who have at least one document (with pending or rejected status)
$users = [];
$query = "
    SELECT 
        u.id as user_id, 
        u.full_name AS name, 
        u.email,
        s.id as store_id,
        s.store_name,
        s.status as store_status
    FROM users u
    JOIN stores s ON u.id = s.user_id
    WHERE EXISTS (
        SELECT 1 FROM company_documents cd 
        WHERE cd.user_id = u.id 
        AND cd.status IN ('pending', 'rejected')
    )
    ORDER BY u.full_name
";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Fetch documents for this user
        $doc_stmt = $conn->prepare("SELECT * FROM company_documents WHERE user_id = ? ORDER BY document_type");
        $doc_stmt->bind_param("i", $row['user_id']);
        $doc_stmt->execute();
        $docs = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $doc_stmt->close();
        $row['documents'] = $docs;
        $users[] = $row;
    }
}

$adminPageTitle = 'Document Review - Admin';
$adminPageHeading = 'Document Review';
$adminPageSubtitle = 'Review vendor documents';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($type) === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="doc-container">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <h3 style="margin-bottom: 0.5rem;">No documents pending</h3>
                <p>All submitted company documents have been reviewed.</p>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php 
                    $pending_docs = array_filter($user['documents'], fn($d) => $d['status'] === 'pending');
                    $has_pending = count($pending_docs) > 0;
                ?>
                <div class="user-card" data-user-id="<?= $user['user_id'] ?>">
                    <div class="user-header">
                        <div class="user-info">
                            <h3><?= htmlspecialchars($user['fullname']) ?></h3>
                            <p><?= htmlspecialchars($user['email']) ?> • Store: <?= htmlspecialchars($user['store_name']) ?></p>
                        </div>
                        <div>
                            <span class="store-status <?= htmlspecialchars($user['store_status']) ?>">
                                Store: <?= ucfirst($user['store_status']) ?>
                            </span>
                            <?php if ($has_pending && $user['store_status'] !== 'active'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve all documents and activate this store?')">
                                    <input type="hidden" name="action" value="approve_all">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" class="btn-primary">✅ Approve All & Activate Store</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="doc-list">
                        <?php foreach ($user['documents'] as $doc): 
                            $doc_types = [
                                'business_registration' => 'Business Registration',
                                'tax_id' => 'Tax ID (TIN)',
                                'proof_of_address' => 'Proof of Address',
                                'certificate_of_incorporation' => 'Certificate of Incorporation (CAC)'
                            ];
                            $type_label = $doc_types[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                        ?>
                        <div class="doc-item">
                            <div class="doc-info">
                                <span class="doc-type"><?= $type_label ?></span>
                                <span class="doc-status <?= $doc['status'] ?>"><?= ucfirst($doc['status']) ?></span>
                                <a href="<?= htmlspecialchars(rdv_admin_src($doc['file_path'])) ?>" target="_blank" class="btn-download">📎 Download</a>
                            </div>
                            <div class="doc-actions">
                                <?php if ($doc['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="action" value="approve_document">
                                        <button type="submit" class="btn-sm btn-approve">✅ Approve</button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="action" value="reject_document">
                                        <button type="submit" class="btn-sm btn-reject">❌ Reject</button>
                                    </form>
                                <?php elseif ($doc['status'] === 'rejected'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="action" value="approve_document">
                                        <button type="submit" class="btn-sm btn-approve">✅ Approve</button>
                                    </form>
                                    <span style="font-size:0.75rem;color:var(--text-muted);">Rejected</span>
                                <?php else: ?>
                                    <span style="font-size:0.75rem;color:var(--success);">Approved ✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<script>
    // Theme
    // Search filter
    document.getElementById('searchInput')?.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.user-card').forEach(card => {
            const name = card.querySelector('.user-info h3')?.textContent?.toLowerCase() || '';
            const email = card.querySelector('.user-info p')?.textContent?.toLowerCase() || '';
            card.style.display = (name.includes(term) || email.includes(term)) ? '' : 'none';
        });
    });

    
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
