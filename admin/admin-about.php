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

// ---------- PERMISSION CHECK FOR ABOUT PAGE ----------
if (!adminHasPermission('about', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to manage the About page.</p><a href="admin">Go to Dashboard</a></div>');
}

// Create uploads directory if not exists
$uploadDir = dirname(__DIR__) . '/uploads/team/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update general content
    if ($action === 'update_content') {
        $updates = [
            'hero_title' => $_POST['hero_title'],
            'hero_subtitle' => $_POST['hero_subtitle'],
            'story_title' => $_POST['story_title'],
            'story_text' => $_POST['story_text'],
            'stat1_number' => $_POST['stat1_number'],
            'stat1_label' => $_POST['stat1_label'],
            'stat2_number' => $_POST['stat2_number'],
            'stat2_label' => $_POST['stat2_label'],
            'stat3_number' => $_POST['stat3_number'],
            'stat3_label' => $_POST['stat3_label'],
            'stat4_number' => $_POST['stat4_number'],
            'stat4_label' => $_POST['stat4_label']
        ];
        
        $success = true;
        foreach ($updates as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO about_content (section_key, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)");
            $stmt->bind_param("ss", $key, $value);
            if (!$stmt->execute()) $success = false;
            $stmt->close();
        }
        if ($success) $message = "About page content updated successfully.";
        else $error = "Error updating content.";
    }
    
    // Add team member
    elseif ($action === 'add_team') {
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $bio = trim($_POST['bio']);
        $initials = strtoupper(trim($_POST['initials']));
        $avatar_color = $_POST['avatar_color'];
        $display_order = (int)$_POST['display_order'];
        
        // Handle file upload
        $avatar_path = '';
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $avatar_path = 'uploads/team/' . $filename;
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO team_members (name, role, bio, initials, avatar, avatar_color, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssssssi", $name, $role, $bio, $initials, $avatar_path, $avatar_color, $display_order);
            if ($stmt->execute()) $message = "Team member added.";
            else $error = "Error: " . $conn->error;
            $stmt->close();
        }
    }
    
    // Edit team member
    elseif ($action === 'edit_team') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $role = trim($_POST['role']);
        $bio = trim($_POST['bio']);
        $initials = strtoupper(trim($_POST['initials']));
        $avatar_color = $_POST['avatar_color'];
        $display_order = (int)$_POST['display_order'];
        
        // Fetch current avatar to delete old file if replacing
        $current = $conn->query("SELECT avatar FROM team_members WHERE id = $id")->fetch_assoc();
        $old_avatar = $current['avatar'] ?? '';
        
        // Handle file upload
        $avatar_path = $old_avatar; // keep existing by default
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    $avatar_path = 'uploads/team/' . $filename;
                    // Delete old file if exists
                    if ($old_avatar && file_exists(rdv_fs_path($old_avatar))) {
                        unlink(rdv_fs_path($old_avatar));
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE team_members SET name=?, role=?, bio=?, initials=?, avatar=?, avatar_color=?, display_order=? WHERE id=?");
            $stmt->bind_param("ssssssii", $name, $role, $bio, $initials, $avatar_path, $avatar_color, $display_order, $id);
            if ($stmt->execute()) $message = "Team member updated.";
            else $error = "Error: " . $conn->error;
            $stmt->close();
        }
    }
    
    // Delete team member
    elseif ($action === 'delete_team') {
        $id = (int)$_POST['id'];
        // Get avatar path to delete file
        $avatar = $conn->query("SELECT avatar FROM team_members WHERE id = $id")->fetch_assoc()['avatar'] ?? '';
        $stmt = $conn->prepare("DELETE FROM team_members WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($avatar && file_exists(rdv_fs_path($avatar))) {
                unlink(rdv_fs_path($avatar));
            }
            $message = "Team member deleted.";
        } else $error = "Error: " . $conn->error;
        $stmt->close();
    }
    
    // Toggle status
    elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['status'];
        $stmt = $conn->prepare("UPDATE team_members SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $id);
        if ($stmt->execute()) $message = "Status updated.";
        else $error = "Error: " . $conn->error;
        $stmt->close();
    }
}

// Fetch current about content
$content = [];
$result = $conn->query("SELECT section_key, content FROM about_content");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $content[$row['section_key']] = $row['content'];
    }
}

// Fetch all team members
$team_members = [];
$teamResult = $conn->query("SELECT * FROM team_members ORDER BY display_order ASC");
if ($teamResult) {
    $team_members = $teamResult->fetch_all(MYSQLI_ASSOC);
}

$adminPageTitle = 'Manage About Page - Admin | RD Vendora';
$adminPageHeading = 'About Page';
$adminPageSubtitle = 'Edit the public About page';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
    <?php if ($message): ?>
        <div class="message message-success" style="margin: 0 2rem 1rem 2rem;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message message-error" style="margin: 0 2rem 1rem 2rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- General Content Form (unchanged) -->
    <div class="content-card">
        <h3 style="margin-bottom: 1.5rem;">📝 General Content (Hero, Story, Stats)</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_content">
            <div class="form-grid">
                <div class="form-group"><label>Hero Title</label><input type="text" name="hero_title" value="<?= htmlspecialchars($content['hero_title'] ?? '') ?>" required></div>
                <div class="form-group"><label>Hero Subtitle</label><textarea name="hero_subtitle" rows="2"><?= htmlspecialchars($content['hero_subtitle'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Story Title</label><input type="text" name="story_title" value="<?= htmlspecialchars($content['story_title'] ?? '') ?>" required></div>
            </div>
            <div class="form-group"><label>Story Text (use double line breaks for paragraphs)</label><textarea name="story_text" rows="6"><?= htmlspecialchars($content['story_text'] ?? '') ?></textarea></div>
            <h4 style="margin: 1.5rem 0 0.5rem;">Stats (4 cards)</h4>
            <div class="form-grid">
                <div class="form-group"><label>Stat 1 Number</label><input type="text" name="stat1_number" value="<?= htmlspecialchars($content['stat1_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 1 Label</label><input type="text" name="stat1_label" value="<?= htmlspecialchars($content['stat1_label'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 2 Number</label><input type="text" name="stat2_number" value="<?= htmlspecialchars($content['stat2_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 2 Label</label><input type="text" name="stat2_label" value="<?= htmlspecialchars($content['stat2_label'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 3 Number</label><input type="text" name="stat3_number" value="<?= htmlspecialchars($content['stat3_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 3 Label</label><input type="text" name="stat3_label" value="<?= htmlspecialchars($content['stat3_label'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 4 Number</label><input type="text" name="stat4_number" value="<?= htmlspecialchars($content['stat4_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Stat 4 Label</label><input type="text" name="stat4_label" value="<?= htmlspecialchars($content['stat4_label'] ?? '') ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary">Save General Content</button>
        </form>
    </div>

    <!-- Team Members Section (with file upload) -->
    <div class="content-card">
        <h3 style="margin-bottom: 1.5rem;">👥 Team Members</h3>
        
        <!-- Add Team Member Form (with file input) -->
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem; padding: 1rem; background: var(--bg-tertiary); border-radius: var(--radius);">
            <input type="hidden" name="action" value="add_team">
            <div class="form-grid">
                <div class="form-group"><label>Name *</label><input type="text" name="name" required></div>
                <div class="form-group"><label>Role *</label><input type="text" name="role" required></div>
                <div class="form-group"><label>Bio</label><textarea name="bio" rows="2"></textarea></div>
                <div class="form-group"><label>Initials (e.g., AM)</label><input type="text" name="initials" placeholder="Auto from name if empty"></div>
                <div class="form-group"><label>Avatar Image (file)</label><input type="file" name="avatar" accept="image/*"></div>
                <div class="form-group"><label>Avatar Color (fallback)</label>
                    <select name="avatar_color">
                        <option value="primary">Blue (Primary)</option>
                        <option value="success">Green</option>
                        <option value="warning">Orange</option>
                        <option value="error">Red</option>
                    </select>
                </div>
                <div class="form-group"><label>Display Order</label><input type="number" name="display_order" value="0"></div>
            </div>
            <button type="submit" class="btn btn-primary">Add Team Member</button>
        </form>
        
        <!-- Existing Team Members Table -->
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Avatar</th><th>Name</th><th>Role</th><th>Bio</th><th>Order</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                    <tr>
                        <td><?= $member['id'] ?></span></td>
                        <td><?php if($member['avatar'] && file_exists(rdv_fs_path($member['avatar']))): ?><img src="<?= htmlspecialchars(rdv_admin_src($member['avatar'])) ?>" class="avatar-preview"><?php else: ?>—<?php endif; ?></td>
                        <td><strong><?= htmlspecialchars($member['name']) ?></strong></td>
                        <td><?= htmlspecialchars($member['role']) ?></span></td>
                        <td><?= htmlspecialchars(substr($member['bio'], 0, 60)) . (strlen($member['bio']) > 60 ? '...' : '') ?></td>
                        <td><?= $member['display_order'] ?></td>
                        <td><span class="badge <?= $member['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ucfirst($member['status']) ?></span></td>
                        <td class="action-buttons">
                            <?php
                            $memberEdit = $member;
                            $memberEdit['avatar_url'] = (!empty($member['avatar']) && file_exists(rdv_fs_path($member['avatar'])))
                                ? rdv_admin_src($member['avatar'])
                                : '';
                            ?>
                            <button type="button" class="icon-btn rdv-admin-json" data-fn="editTeamMember" data-payload="<?= admin_json_attr($memberEdit) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this team member?')">
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                <button type="submit" class="icon-btn" style="color:var(--error);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                <input type="hidden" name="status" value="<?= $member['status'] === 'active' ? 'inactive' : 'active' ?>">
                                <button type="submit" class="icon-btn" title="Toggle Status"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Team Member Modal (with file upload and preview) -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Edit Team Member</h3>
            <div class="modal-close" onclick="closeEditModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="action" value="edit_team">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" required></div>
                <div class="form-group"><label>Role</label><input type="text" name="role" id="edit_role" required></div>
                <div class="form-group"><label>Bio</label><textarea name="bio" id="edit_bio" rows="3"></textarea></div>
                <div class="form-group"><label>Initials</label><input type="text" name="initials" id="edit_initials"></div>
                <div class="form-group">
                    <label>Avatar Image</label>
                    <div id="currentAvatarPreview" style="margin-bottom: 8px;"></div>
                    <input type="file" name="avatar" id="edit_avatar" accept="image/*">
                    <small style="color: var(--text-muted);">Leave empty to keep current image.</small>
                </div>
                <div class="form-group"><label>Avatar Color (fallback)</label>
                    <select name="avatar_color" id="edit_avatar_color">
                        <option value="primary">Blue</option><option value="success">Green</option>
                        <option value="warning">Orange</option><option value="error">Red</option>
                    </select>
                </div>
                <div class="form-group"><label>Display Order</label><input type="number" name="display_order" id="edit_display_order"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php
$adminFooterScripts = <<<'JS'
<script>
function editTeamMember(member) {
    document.getElementById('edit_id').value = member.id;
    document.getElementById('edit_name').value = member.name || '';
    document.getElementById('edit_role').value = member.role || '';
    document.getElementById('edit_bio').value = member.bio || '';
    document.getElementById('edit_initials').value = member.initials || '';
    document.getElementById('edit_avatar_color').value = member.avatar_color || 'primary';
    document.getElementById('edit_display_order').value = member.display_order || 0;
    var preview = document.getElementById('currentAvatarPreview');
    if (member.avatar_url) {
        preview.innerHTML = '<img src="' + member.avatar_url.replace(/"/g, '&quot;') + '" class="avatar-preview" alt="">';
    } else {
        preview.innerHTML = '<span style="color:var(--text-muted)">No image</span>';
    }
    document.getElementById('edit_avatar').value = '';
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
</script>
JS;
require __DIR__ . '/../includes/admin_layout_end.php';
