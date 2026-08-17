/**
 * RD Vendora - Store Creation Wizard JavaScript
 * Multi-step form for creating a new store
 */

let currentStep = 1;
const totalSteps = 3;

/**
 * Update subdomain preview
 */
function updatePreview() {
  const name = document.getElementById('storeNameInput').value.trim() || 'yourstore';
  const subdomain = name.toLowerCase().replace(/[^a-z0-9]/g, '');
  document.getElementById('subdomainName').textContent = subdomain || 'yourstore';
}

/**
 * Select category
 */
function selectCategory(el) {
  document.querySelectorAll('.category-option').forEach(opt => opt.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('selectedCategory').value = el.dataset.category;
}

/**
 * Handle logo upload
 */
function handleLogoUpload(input) {
  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    document.getElementById('logoUpload').classList.add('hidden');
    document.getElementById('logoPreview').classList.remove('hidden');
    document.getElementById('logoPreviewImg').src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function removeLogo() {
  document.getElementById('logoInput').value = '';
  document.getElementById('logoUpload').classList.remove('hidden');
  document.getElementById('logoPreview').classList.add('hidden');
  document.getElementById('logoPreviewImg').src = '';
}

/**
 * Navigate to next step
 */
function nextStep() {
  if (currentStep === 1) {
    const storeName = document.getElementById('storeNameInput').value.trim();
    if (!storeName) {
      Toast.error('Please enter a store name');
      return;
    }
  }

  if (currentStep === 2) {
    const category = document.getElementById('selectedCategory').value;
    if (!category) {
      Toast.error('Please select a category');
      return;
    }
  }

  if (currentStep === 3) {
    finishWizard();
    return;
  }

  if (currentStep < totalSteps) {
    goToStep(currentStep + 1);
  }
}

/**
 * Navigate to previous step
 */
function prevStep() {
  if (currentStep > 1) {
    goToStep(currentStep - 1);
  }
}

/**
 * Go to specific step
 */
function goToStep(step) {
  // Hide all steps
  document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));

  // Show target step
  const targetStep = document.getElementById(step <= totalSteps ? `step${step}` : 'stepSuccess');
  if (targetStep) targetStep.classList.add('active');

  // Update dots
  for (let i = 1; i <= totalSteps; i++) {
    const dot = document.getElementById(`dot${i}`);
    const line = document.getElementById(`line${i}`);

    if (dot) {
      dot.classList.remove('active', 'completed');
      if (i < step) dot.classList.add('completed');
      else if (i === step) dot.classList.add('active');
    }

    if (line) {
      line.classList.toggle('completed', i < step);
    }
  }

  // Update buttons
  document.getElementById('backBtn').style.visibility = step === 1 ? 'hidden' : 'visible';

  const nextBtn = document.getElementById('nextBtn');
  if (step === 3) {
    nextBtn.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      Create Store
    `;
    updateSummary();
  } else {
    nextBtn.innerHTML = `
      Continue
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    `;
  }

  // Update header
  const titles = ['Create Your Store', 'Choose Category', 'Finalize Setup'];
  const subtitles = [
    "Let's get your online store up and running",
    'What type of products will you sell?',
    'Review and create your store'
  ];

  document.getElementById('wizardTitle').textContent = titles[step - 1] || 'All Done!';
  document.getElementById('wizardSubtitle').textContent = subtitles[step - 1] || 'Your store is ready';

  // Hide actions on success
  document.getElementById('wizardActions').style.display = step > totalSteps ? 'none' : 'flex';

  currentStep = step;
}

/**
 * Update summary
 */
function updateSummary() {
  const storeName = document.getElementById('storeNameInput').value.trim();
  const category = document.getElementById('selectedCategory').value;
  const subdomain = storeName.toLowerCase().replace(/[^a-z0-9]/g, '');

  document.getElementById('summaryName').textContent = storeName;
  document.getElementById('summaryUrl').textContent = subdomain || 'yourstore';
  document.getElementById('summaryCategory').textContent = category.charAt(0).toUpperCase() + category.slice(1);
}

/**
 * Finish wizard
 */
function finishWizard() {
  const storeName = document.getElementById('storeNameInput').value.trim();
  const category = document.getElementById('selectedCategory').value;
  const subdomain = storeName.toLowerCase().replace(/[^a-z0-9]/g, '');

  // Save store data
  const user = DataStore.get('user') || {};
  user.storeName = storeName;
  user.subdomain = subdomain;
  user.category = category;
  DataStore.set('user', user);

  Toast.success('Store created successfully!');
  goToStep(4);
}
