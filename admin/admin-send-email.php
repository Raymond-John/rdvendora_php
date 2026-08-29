<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

if (!adminHasPermission('send_email', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to send emails.</p><a href="admin">Go to Dashboard</a></div>');
}

$emails = $conn->query("SELECT DISTINCT user_email, user_name FROM orders WHERE user_email IS NOT NULL AND user_email != '' ORDER BY user_name ASC");
$emails = ($emails) ? $emails->fetch_all(MYSQLI_ASSOC) : [];
$sendStatus = '';
$sendError = '';

require_once __DIR__ . '/../includes/email_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $email_list = $_POST['email_list'] ?? [];
    $custom_email = trim($_POST['custom_email'] ?? '');
    $button1Label = trim($_POST['button_1_label'] ?? '');
    $button1Url = trim($_POST['button_1_url'] ?? '');
    $button2Label = trim($_POST['button_2_label'] ?? '');
    $button2Url = trim($_POST['button_2_url'] ?? '');

    if (!empty($custom_email)) {
        $recipients = [$custom_email];
    } elseif (!empty($email_list)) {
        $recipients = $email_list;
    } else {
        $sendError = 'Please select at least one recipient.';
    }

    if (empty($sendError) && empty($subject)) $sendError = 'Subject is required.';
    if (empty($sendError) && empty($message)) $sendError = 'Message is required.';
    if (empty($sendError) && $button1Label !== '' && $button1Url === '') {
        $sendError = 'Button 1 needs a URL when a label is provided.';
    }
    if (empty($sendError) && $button2Label !== '' && $button2Url === '') {
        $sendError = 'Button 2 needs a URL when a label is provided.';
    }

    if (empty($sendError)) {
        $messageHtml = nl2br(htmlspecialchars($message));
        $buttons = [];
        if ($button1Label !== '' && $button1Url !== '') {
            $buttons[] = ['label' => $button1Label, 'url' => $button1Url, 'style' => 'primary'];
        }
        if ($button2Label !== '' && $button2Url !== '') {
            $buttons[] = ['label' => $button2Label, 'url' => $button2Url, 'style' => 'gold'];
        }

        $htmlBody = rdv_email_wrap(
            '<div style="font-size:16px; color:#1E293B; line-height:1.7;">' . $messageHtml . '</div>',
            [
                'title' => $subject,
                'preheader' => mb_substr(strip_tags($message), 0, 140),
                'footer_note' => 'This is a promotional email. You are receiving this because you are a valued customer.',
                'buttons' => $buttons,
                'header_centered' => true,
                'header_show_name' => false,
            ]
        );

        $plainButtons = '';
        foreach ($buttons as $btn) {
            $plainButtons .= "\n" . $btn['label'] . ': ' . $btn['url'];
        }
        $plainText = strip_tags($message) . $plainButtons;

        $successCount = 0;
        foreach ($recipients as $to) {
            if (sendEmail($to, $subject, $htmlBody, $plainText)) {
                $successCount++;
            }
        }
        if ($successCount > 0) {
            $sendStatus = "Email sent to $successCount recipient(s).";
        } else {
            $sendError = "Failed to send emails. Check SMTP credentials and server settings.";
        }
    }
}

$adminPageTitle = 'Send Email to Customers - Admin';
$adminPageHeading = 'Send Email';
$adminPageSubtitle = 'Message customers from the admin panel';
$adminSearchPlaceholder = 'Search customers...';
$adminShowHeader = true;
$adminPageStyles = <<<'CSS'
.email-form-container { padding: 1.5rem 2rem 2rem; }
@media (max-width: 768px) {
    .email-form-container { padding: 1rem; }
    .email-form-container .form-card { padding: 1.15rem; }
}
.email-form-container .form-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-xl);
    padding: 2rem;
    max-width: 800px;
    margin: 0 auto;
    box-shadow: var(--shadow-sm);
}
.email-form-container .form-group { margin-bottom: 1.5rem; }
.email-form-container .form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}
.admin-app .email-form-container .form-input,
.admin-app .email-form-container .form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    background: var(--bg-tertiary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-lg);
    font-size: 0.875rem;
    color: var(--text-primary);
    font-family: inherit;
}
.email-form-container .form-textarea { min-height: 200px; resize: vertical; }
.checkbox-group {
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-lg);
    padding: 0.75rem;
    background: var(--bg-tertiary);
}
.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: var(--radius-sm);
}
.checkbox-group label:hover { background: var(--primary-light); }
.admin-app .checkbox-group input[type="checkbox"] {
    width: 16px;
    height: 16px;
    padding: 0;
    margin: 0;
    flex-shrink: 0;
    accent-color: var(--primary);
    background: none;
    border: none;
}
.admin-app .email-form-container .btn-send,
.admin-app .email-form-container button[type="submit"].btn-send {
    background: var(--gradient-primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius-lg);
    font-weight: 600;
    width: 100%;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    justify-content: center;
}
.email-form-container .alert {
    padding: 1rem;
    border-radius: var(--radius-lg);
    margin: 0 0 1.5rem;
}
.email-form-container .alert-success {
    background: var(--success-light);
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.email-form-container .alert-error {
    background: var(--error-light);
    color: #991b1b;
    border: 1px solid #fecaca;
}
.email-form-container hr {
    margin: 1.5rem 0;
    border: none;
    border-top: 1px solid var(--border-primary);
}
.email-form-container .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media (max-width: 768px) {
    .email-form-container .form-row { grid-template-columns: 1fr; }
}
.email-form-container .form-hint {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: var(--text-tertiary);
}
@media (max-width: 768px) {
    .email-form-container { padding: 1rem; }
    .email-form-container .form-card { padding: 1rem; }
}
CSS;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="email-form-container">
        <div class="form-card">
            <?php if ($sendStatus): ?>
                <div class="alert alert-success"><?= htmlspecialchars($sendStatus) ?></div>
            <?php endif; ?>
            <?php if ($sendError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($sendError) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Select Customer(s) (multiple allowed)</label>
                    <div class="checkbox-group" id="emailCheckboxGroup">
                        <?php foreach ($emails as $c): ?>
                            <label>
                                <input type="checkbox" name="email_list[]" value="<?= htmlspecialchars($c['user_email']) ?>">
                                <?= htmlspecialchars($c['user_name'] ?: $c['user_email']) ?> (<?= htmlspecialchars($c['user_email']) ?>)
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($emails)): ?>
                            <p>No customer emails found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Or enter custom email address</label>
                    <input type="email" name="custom_email" class="form-input" placeholder="customer@example.com">
                </div>

                <hr>

                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-input" required placeholder="Special Offer from RD Vendora">
                </div>

                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-textarea" required placeholder="Hello valued customer, ..."></textarea>
                </div>

                <hr>

                <div class="form-group">
                    <label class="form-label">Email buttons (optional)</label>
                    <span class="form-hint">Add up to two call-to-action buttons that appear below your message.</span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Button 1 label</label>
                        <input type="text" name="button_1_label" class="form-input" placeholder="Shop Now">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Button 1 URL</label>
                        <input type="url" name="button_1_url" class="form-input" placeholder="https://rdvendora.com/marketplace">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Button 2 label</label>
                        <input type="text" name="button_2_label" class="form-input" placeholder="Visit Store">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Button 2 URL</label>
                        <input type="url" name="button_2_url" class="form-input" placeholder="https://rdvendora.com">
                    </div>
                </div>

                <button type="submit" name="send_email" class="btn-send">Send Email</button>
            </form>
        </div>
    </div>
<script>
// Search filter for email checkboxes
    const searchInput = document.getElementById('adminSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const labels = document.querySelectorAll('#emailCheckboxGroup label');
            labels.forEach(label => {
                const text = label.innerText.toLowerCase();
                label.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
