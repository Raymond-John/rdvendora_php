<?php
session_start();

require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) {
    $conn = $connect;
}
if (!isset($conn) || $conn === null) {
    die('Database connection failed.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$user_id = $_SESSION['user_id'];

// ----- Check if user already has a store -----
$checkStmt = $conn->prepare("SELECT id, store_name, store_slug FROM stores WHERE user_id = ? LIMIT 1");
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$existingStore = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existingStore) {
    // User already has a store – set session variables
    $_SESSION['store_id']   = $existingStore['id'];
    $_SESSION['store_name'] = $existingStore['store_name'];
    $_SESSION['store_slug'] = $existingStore['store_slug'];
    require_once __DIR__ . '/app/helpers/store_account_details.php';
    rdv_ensure_store_account_details_table($conn);
    if (!rdv_store_account_details_exists($conn, (int) $existingStore['id'])) {
        header('Location: store-account-details');
        exit;
    }
    header('Location: dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = trim($_POST['store_name'] ?? '');
    $category = trim($_POST['category'] ?? ''); // not stored here, but kept for possible later use

    if (empty($store_name)) {
        $error = 'Store name is required.';
    } elseif (strlen($store_name) < 2 || strlen($store_name) > 100) {
        $error = 'Store name must be between 2 and 100 characters.';
    } else {
        $base_slug = rdv_slugify_store_name($store_name);
        if ($base_slug === '' || rdv_is_reserved_store_slug($base_slug)) {
            // unique helper will prefix away from reserved names
        }
        $store_slug = rdv_unique_store_slug($conn, $base_slug);
        if (!rdv_is_valid_store_slug($store_slug)) {
            $error = 'Could not generate a valid store URL. Please try a different store name.';
        }

        $logo_path = null;
        if ($error === '' && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/logos/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = 'Logo must be less than 2MB.';
            } else {
                $filename = uniqid('store_') . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
                    $logo_path = $destination;
                } else {
                    $error = 'Failed to upload logo.';
                }
            }
        }

        if (empty($error)) {
            $status = 'pending'; // new stores need admin approval
            $description = '';   // optional, can be added later
            $stmt = $conn->prepare("INSERT INTO stores (user_id, store_name, store_slug, description, logo_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssss", $user_id, $store_name, $store_slug, $description, $logo_path, $status);
            if ($stmt->execute()) {
                $store_id = $stmt->insert_id;
                $_SESSION['store_id'] = $store_id;
                $_SESSION['store_name'] = $store_name;
                $_SESSION['store_slug'] = $store_slug;
                header('Location: store-account-details');
                exit;
            } else {
                $error = 'Could not create your store. Please try again.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Store - RD Vendora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <style>
        /* ============================================================
           (Your original CSS – exactly as you had it)
           ============================================================ */
        .wizard-container { max-width: 600px; margin: 0 auto; padding: 2rem 1.5rem; }
        .wizard-header { text-align: center; margin-bottom: 2rem; }
        .wizard-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        .wizard-header p { color: var(--text-secondary); font-size: 0.9375rem; }
        .steps-indicator { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 2.5rem; }
        .step-dot { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; color: var(--text-muted); transition: all var(--transition-base); z-index: 1; }
        .step-dot.active { background: var(--primary-gradient); border-color: var(--primary); color: white; box-shadow: var(--shadow-primary); }
        .step-dot.completed { background: var(--success); border-color: var(--success); color: white; }
        .step-line { width: 60px; height: 2px; background: var(--border-color); margin: 0 -2px; }
        .step-line.completed { background: var(--success); }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; animation: fadeIn 0.4s ease; }
        .store-preview { background: var(--bg-secondary); border-radius: var(--radius-xl); padding: 1.5rem; margin-top: 1.5rem; border: 1px solid var(--border-color); }
        .store-preview h4 { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); margin-bottom: 1rem; }
        .subdomain-display { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-primary); border-radius: var(--radius-lg); font-family: var(--font-mono); font-size: 0.9375rem; }
        .subdomain-display .name { font-weight: 600; color: var(--primary); }
        .subdomain-display .domain { color: var(--text-muted); }
        .logo-preview { width: 80px; height: 80px; border-radius: var(--radius-xl); object-fit: cover; border: 2px solid var(--border-color); margin-top: 0.75rem; }
        .category-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
        .category-option { padding: 1rem; border: 1.5px solid var(--border-color); border-radius: var(--radius-lg); text-align: center; cursor: pointer; transition: all var(--transition-fast); }
        .category-option:hover { border-color: var(--primary-light); background: rgba(99,102,241,0.03); }
        .category-option.selected { border-color: var(--primary); background: rgba(99,102,241,0.08); }
        .category-option svg { color: var(--text-muted); margin-bottom: 0.5rem; }
        .category-option.selected svg { color: var(--primary); }
        .category-option-label { font-size: 0.8125rem; font-weight: 500; color: var(--text-primary); }
        .wizard-actions { display: flex; justify-content: space-between; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
        .error-message { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; padding: 0.75rem 1rem; border-radius: var(--radius-lg); margin-bottom: 1rem; font-size: 0.875rem; }
        @media (max-width: 767px) { .category-grid { grid-template-columns: repeat(2, 1fr); } }
        .image-upload { border: 2px dashed var(--border-color); border-radius: var(--radius-xl); padding: 1.5rem; text-align: center; cursor: pointer; transition: all var(--transition-fast); }
        .image-upload:hover { border-color: var(--primary); background: rgba(99,102,241,0.03); }
        .image-preview { position: relative; display: inline-block; margin-top: 1rem; }
        .remove-image { position: absolute; top: -8px; right: -8px; background: var(--error); border: none; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: white; }
        .hidden { display: none; }
        .w-full { width: 100%; }
    </style>
</head>
<body class="auth-page">
    <div class="auth-bg"></div>

    <div class="wizard-container">
        <div class="auth-logo" style="margin-bottom:1.5rem;display:flex;align-items:center;gap:0.6rem;">
            <img class="rdv-brand-logo" src="assets/brand-logo.png" alt="" style="height:40px;width:auto;max-width:120px;object-fit:contain;background:#fff;border-radius:8px;padding:4px 8px;">
            <span class="rdv-brand-name">RD Vendora</span>
        </div>

        <div class="auth-card">
            <div class="wizard-header">
                <h1>Create Your Store</h1>
                <p>Let's get your online store up and running</p>
            </div>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="steps-indicator">
                    <div class="step-dot active" id="dot1">1</div>
                    <div class="step-line" id="line1"></div>
                    <div class="step-dot" id="dot2">2</div>
                    <div class="step-line" id="line2"></div>
                    <div class="step-dot" id="dot3">3</div>
                </div>

                <!-- Step 1 -->
                <div class="wizard-step active" id="step1">
                    <div class="form-group">
                        <label class="form-label">Store Name *</label>
                        <input type="text" name="store_name" class="form-input" id="storeNameInput" placeholder="e.g., Dream Boutique" required oninput="updatePreview()">
                    </div>
                    <div class="store-preview">
                        <h4>Your Store URL Preview</h4>
                        <div class="subdomain-display">
                            <span class="domain"><?= htmlspecialchars(rtrim(rdv_app_base_url(), '/') . '/', ENT_QUOTES, 'UTF-8') ?></span><span class="name" id="subdomainName">yourstore</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="wizard-step" id="step2">
                    <div class="form-group">
                        <label class="form-label">What do you sell? *</label>
                        <div class="category-grid" id="categoryGrid">
                            <div class="category-option" data-category="fashion" onclick="selectCategory(this)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.38 3.46L16 2 12 6l-4-4-4.38 1.46a2 2 0 0 0-1.34 2.23l1.44 8.64A2 2 0 0 0 5.67 18H18.33a2 2 0 0 0 1.95-1.67l1.44-8.64a2 2 0 0 0-1.34-2.23z"/></svg>
                                <div class="category-option-label">Fashion</div>
                            </div>
                            <div class="category-option" data-category="electronics" onclick="selectCategory(this)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                                <div class="category-option-label">Electronics</div>
                            </div>
                            <div class="category-option" data-category="home" onclick="selectCategory(this)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                <div class="category-option-label">Home & Living</div>
                            </div>
                            <div class="category-option" data-category="beauty" onclick="selectCategory(this)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
                                <div class="category-option-label">Beauty</div>
                            </div>
                            <div class="category-option" data-category="sports" onclick="selectCategory(this)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <div class="category-option-label">Sports</div>
                            </div>
                            <div class="category-option" data-category="other" onclick="selectCategory(this)">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                <div class="category-option-label">Other</div>
                            </div>
                        </div>
                        <input type="hidden" name="category" id="selectedCategory" required>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="wizard-step" id="step3">
                    <div class="form-group">
                        <label class="form-label">Store Logo (Optional)</label>
                        <div class="image-upload" id="logoUpload" onclick="document.getElementById('logoInput').click()">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            <div class="image-upload-text">Click to upload logo</div>
                            <div class="image-upload-hint">PNG, JPG up to 2MB</div>
                        </div>
                        <div class="image-preview hidden" id="logoPreview">
                            <img id="logoPreviewImg" class="logo-preview" src="" alt="Logo preview">
                            <button type="button" class="remove-image" onclick="removeLogo()">✕</button>
                        </div>
                        <input type="file" name="logo" id="logoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="handleLogoUpload(this)">
                    </div>
                    <div class="store-preview">
                        <h4>Store Summary</h4>
                        <div style="display:flex;flex-direction:column;gap:0.75rem">
                            <div><span style="color:var(--text-muted)">Store Name</span> <strong id="summaryName">-</strong></div>
                            <div><span style="color:var(--text-muted)">URL</span> <strong id="summaryUrl"><?= htmlspecialchars(rtrim(rdv_app_base_url(), '/') . '/yourstore', ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div><span style="color:var(--text-muted)">Category</span> <strong id="summaryCategory">-</strong></div>
                        </div>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="btn btn-ghost" id="backBtn" style="visibility:hidden">← Back</button>
                    <button type="button" class="btn btn-primary" id="nextBtn">Continue →</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // (Your JavaScript unchanged)
        let currentStep = 1;
        let selectedCategoryValue = null;

        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');
        const dot1 = document.getElementById('dot1');
        const dot2 = document.getElementById('dot2');
        const dot3 = document.getElementById('dot3');
        const line1 = document.getElementById('line1');
        const line2 = document.getElementById('line2');
        const backBtn = document.getElementById('backBtn');
        const nextBtn = document.getElementById('nextBtn');
        const form = document.querySelector('form');

        function updatePreview() {
            const name = document.getElementById('storeNameInput').value;
            let sub = name.toLowerCase().replace(/[''`]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
            if (!sub) sub = 'yourstore';
            document.getElementById('subdomainName').innerText = sub;
            document.getElementById('summaryName').innerText = name || '-';
            document.getElementById('summaryUrl').innerText = <?= json_encode(rtrim(rdv_app_base_url(), '/') . '/', JSON_UNESCAPED_SLASHES) ?> + sub;
        }

        function selectCategory(el) {
            document.querySelectorAll('.category-option').forEach(opt => opt.classList.remove('selected'));
            el.classList.add('selected');
            selectedCategoryValue = el.getAttribute('data-category');
            document.getElementById('selectedCategory').value = selectedCategoryValue;
            document.getElementById('summaryCategory').innerText = el.querySelector('.category-option-label').innerText;
        }

        function handleLogoUpload(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (!['image/jpeg','image/png','image/gif','image/webp'].includes(file.type)) {
                    alert('Invalid file type');
                    input.value = '';
                    return;
                }
                if (file.size > 2*1024*1024) {
                    alert('Max 2MB');
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('logoPreviewImg').src = e.target.result;
                    document.getElementById('logoPreview').classList.remove('hidden');
                    document.getElementById('logoUpload').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        }

        function removeLogo() {
            document.getElementById('logoInput').value = '';
            document.getElementById('logoPreview').classList.add('hidden');
            document.getElementById('logoUpload').style.display = 'block';
        }

        function updateStepIndicators() {
            dot1.classList.remove('active','completed');
            dot2.classList.remove('active','completed');
            dot3.classList.remove('active','completed');
            if (currentStep === 1) {
                dot1.classList.add('active');
                line1.classList.remove('completed');
                line2.classList.remove('completed');
            } else if (currentStep === 2) {
                dot1.classList.add('completed');
                dot2.classList.add('active');
                line1.classList.add('completed');
                line2.classList.remove('completed');
            } else {
                dot1.classList.add('completed');
                dot2.classList.add('completed');
                dot3.classList.add('active');
                line1.classList.add('completed');
                line2.classList.add('completed');
            }
            backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
            nextBtn.innerText = currentStep === 3 ? 'Create Store ✓' : 'Continue →';
        }

        function validateStep(step) {
            if (step === 1) {
                const name = document.getElementById('storeNameInput').value.trim();
                if (!name) { alert('Store name required'); return false; }
                if (name.length < 2) { alert('Min 2 characters'); return false; }
                return true;
            }
            if (step === 2) {
                if (!selectedCategoryValue) { alert('Select a category'); return false; }
                return true;
            }
            return true;
        }

        function nextStep() {
            if (currentStep === 3) {
                form.submit();
            } else if (validateStep(currentStep)) {
                if (currentStep === 1) {
                    step1.classList.remove('active');
                    step2.classList.add('active');
                    currentStep = 2;
                } else if (currentStep === 2) {
                    step2.classList.remove('active');
                    step3.classList.add('active');
                    currentStep = 3;
                    document.getElementById('summaryName').innerText = document.getElementById('storeNameInput').value.trim() || '-';
                }
                updateStepIndicators();
            }
        }

        function prevStep() {
            if (currentStep === 2) {
                step2.classList.remove('active');
                step1.classList.add('active');
                currentStep = 1;
            } else if (currentStep === 3) {
                step3.classList.remove('active');
                step2.classList.add('active');
                currentStep = 2;
            }
            updateStepIndicators();
        }

        backBtn.addEventListener('click', prevStep);
        nextBtn.addEventListener('click', nextStep);
        document.getElementById('storeNameInput').addEventListener('input', updatePreview);
        updatePreview();
    </script>
</body>
</html>