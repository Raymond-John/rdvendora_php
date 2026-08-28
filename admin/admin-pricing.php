<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';   // <-- added permission helper

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

// ---------- PERMISSION CHECK FOR PRICING PAGE ----------
if (!adminHasPermission('pricing', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage pricing plans.</p><a href="admin">Go to Dashboard</a></div>');
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $duration = $_POST['duration'];
            $features = isset($_POST['features']) ? trim($_POST['features']) : '';
            $status = $_POST['status'];
            
            $featuresArray = array_filter(array_map('trim', explode("\n", $features)));
            $featuresJson = json_encode($featuresArray);
            
            $stmt = $conn->prepare("INSERT INTO subscription_plans (name, price, duration, features, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsss", $name, $price, $duration, $featuresJson, $status);
            if ($stmt->execute()) {
                $message = "Plan added successfully.";
            } else {
                $error = "Error adding plan: " . $conn->error;
            }
            $stmt->close();
        } 
        elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $price = floatval($_POST['price']);
            $features = isset($_POST['features']) ? trim($_POST['features']) : '';
            $status = $_POST['status'];
            
            // Convert features text to JSON array
            $featuresArray = array_filter(array_map('trim', explode("\n", $features)));
            $featuresJson = json_encode($featuresArray);
            
            // Only update price, features, status – name and duration remain unchanged
            $stmt = $conn->prepare("UPDATE subscription_plans SET price=?, features=?, status=? WHERE id=?");
            $stmt->bind_param("dssi", $price, $featuresJson, $status, $id);
            if ($stmt->execute()) {
                $message = "Plan updated successfully.";
            } else {
                $error = "Error updating plan: " . $conn->error;
            }
            $stmt->close();
        } 
        elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Plan deleted successfully.";
            } else {
                $error = "Error deleting plan: " . $conn->error;
            }
            $stmt->close();
        } 
        elseif ($action === 'toggle_status') {
            $id = (int)$_POST['id'];
            $newStatus = $_POST['status'];
            $stmt = $conn->prepare("UPDATE subscription_plans SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            if ($stmt->execute()) {
                $message = "Plan status updated.";
            } else {
                $error = "Error updating status.";
            }
            $stmt->close();
        }
    }
}

// Fetch all plans
$plans = $conn->query("SELECT * FROM subscription_plans ORDER BY price ASC");
$planList = $plans->fetch_all(MYSQLI_ASSOC);


$adminPageTitle = 'Pricing Manager - Admin | RD Vendora';
$adminPageHeading = 'Pricing Plans';
$adminPageSubtitle = 'Manage subscription plans';
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

        <h3 style="margin-bottom: 1.5rem;">➕ Add New Plan</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group">
                    <label>Plan Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Pro, Business">
                </div>
                <div class="form-group">
                    <label>Price (₦) *</label>
                    <input type="number" step="0.01" name="price" required placeholder="49.99">
                </div>
                <div class="form-group">
                    <label>Duration *</label>
                    <select name="duration" required>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Features (one per line)</label>
                <textarea name="features" rows="3" placeholder="Up to 100 products&#10;Email support&#10;Analytics dashboard"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Create Plan</button>
        </form>
    </div>

    <div class="content-card">
        <h3 style="margin-bottom: 1rem;">📋 Existing Plans</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Features</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planList as $plan): 
                        $featuresList = json_decode($plan['features'], true);
                        $featuresText = $featuresList ? implode(', ', array_slice($featuresList, 0, 2)) . (count($featuresList) > 2 ? '...' : '') : '—';
                    ?>
                    <tr>
                        <td><?= $plan['id'] ?></td>
                        <td><strong><?= htmlspecialchars($plan['name']) ?></strong></td>
                        <td>₦<?= number_format($plan['price'], 2) ?></td>
                        <td><?= ucfirst($plan['duration']) ?></td>
                        <td title="<?= htmlspecialchars(implode("\n", $featuresList ?: [])) ?>"><?= htmlspecialchars($featuresText) ?></td>
                        <td>
                            <span class="badge <?= $plan['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $plan['status'] ?>
                            </span>
                        </td>
                        <td class="action-buttons">
                            <button type="button" class="icon-btn rdv-admin-json" data-fn="editPlan" data-payload="<?= admin_json_attr($plan) ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this plan?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                <button type="submit" class="icon-btn" style="color:var(--error);">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                <input type="hidden" name="status" value="<?= $plan['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="icon-btn" title="Toggle Active">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                                </button>
                            </form>
                          </td>
                      </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal – Only price, features, status can be changed (name and duration are read‑only) -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Edit Plan</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
            <!-- Plan Name – read‑only (display only) -->
            <div class="form-group">
                <label>Plan Name (cannot be changed)</label>
                <input type="text" id="edit_name_display" disabled style="background: var(--bg-tertiary); opacity:0.7;">
            </div>
            
            <!-- Duration – read‑only (display only) -->
            <div class="form-group">
                <label>Duration (cannot be changed)</label>
                <input type="text" id="edit_duration_display" disabled style="background: var(--bg-tertiary); opacity:0.7;">
            </div>
            
            <!-- Price – editable -->
            <div class="form-group">
                <label>Price (₦) *</label>
                <input type="number" step="0.01" name="price" id="edit_price" required>
            </div>
            
            <!-- Status – editable -->
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <!-- Features – editable -->
            <div class="form-group">
                <label>Features (one per line)</label>
                <textarea name="features" id="edit_features" rows="4"></textarea>
            </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php
$adminFooterScripts = <<<'JS'
<script>
function editPlan(plan) {
    document.getElementById('edit_id').value = plan.id;
    document.getElementById('edit_name_display').value = plan.name || '';
    document.getElementById('edit_duration_display').value = plan.duration ? plan.duration.charAt(0).toUpperCase() + plan.duration.slice(1) : '';
    document.getElementById('edit_price').value = plan.price;
    document.getElementById('edit_status').value = plan.status || 'active';
    var features = plan.features;
    if (typeof features === 'string') {
        try { features = JSON.parse(features); } catch (e) {}
    }
    document.getElementById('edit_features').value = Array.isArray(features) ? features.join('\n') : (features || '');
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
</script>
JS;
require __DIR__ . '/../includes/admin_layout_end.php';
