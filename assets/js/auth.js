/**
 * RD Vendora - Authentication JavaScript
 * Login, Register, Forgot Password form handling
 */

document.addEventListener('DOMContentLoaded', () => {
  // Initialize form validation
  FormValidator.init('#loginForm');
  FormValidator.init('#registerForm');
  FormValidator.init('#forgotForm');

  // Password strength meter
  const passwordInput = document.getElementById('password');
  if (passwordInput) {
    passwordInput.addEventListener('input', updatePasswordStrength);
  }

  // Login form
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', handleLogin);
  }

  // Register form
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', handleRegister);
  }

  // Forgot password form
  const forgotForm = document.getElementById('forgotForm');
  if (forgotForm) {
    forgotForm.addEventListener('submit', handleForgotPassword);
  }
});

/**
 * Handle login form submission
 */
function handleLogin(e) {
  e.preventDefault();

  const form = e.target;
  if (!FormValidator.validate(form)) return;

  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const btn = document.getElementById('loginBtn');

  // Show loading
  setButtonLoading(btn, true);

  // Simulate API call
  setTimeout(() => {
    setButtonLoading(btn, false);

    // Check credentials against demo user
    const user = DataStore.get('user');
    if (user && user.email === email) {
      // Store session
      DataStore.set('session', {
        isLoggedIn: true,
        email: email,
        loginTime: new Date().toISOString()
      });

      showAuthMessage('success', 'Login successful! Redirecting...');
      Toast.success('Welcome back!', 'Login Successful');

      setTimeout(() => {
        window.location.href='dashboard';
      }, 1000);
    } else {
      showAuthMessage('error', 'Invalid email or password. Try alex@example.com / any password');
      Toast.error('Invalid credentials');
    }
  }, 1500);
}

/**
 * Handle register form submission
 */
function handleRegister(e) {
  e.preventDefault();

  const form = e.target;
  if (!FormValidator.validate(form)) return;

  // Check terms agreement
  const agreeCheckbox = document.getElementById('agree');
  if (agreeCheckbox && !agreeCheckbox.checked) {
    showAuthMessage('error', 'Please agree to the Terms of Service and Privacy Policy');
    return;
  }

  const fullName = document.getElementById('fullName').value;
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  const btn = document.getElementById('registerBtn');

  setButtonLoading(btn, true);

  setTimeout(() => {
    setButtonLoading(btn, false);

    // Update demo user
    const user = DataStore.get('user') || {};
    user.name = fullName;
    user.email = email;
    DataStore.set('user', user);

    // Store session
    DataStore.set('session', {
      isLoggedIn: true,
      email: email,
      loginTime: new Date().toISOString()
    });

    showAuthMessage('success', 'Account created successfully! Redirecting...');
    Toast.success('Welcome to RD Vendora!', 'Account Created');

    setTimeout(() => {
      window.location.href='create-store';
    }, 1000);
  }, 1500);
}

/**
 * Handle forgot password form submission
 */
function handleForgotPassword(e) {
  e.preventDefault();

  const form = e.target;
  if (!FormValidator.validate(form)) return;

  const email = document.getElementById('email').value;
  const btn = document.getElementById('resetBtn');

  setButtonLoading(btn, true);

  setTimeout(() => {
    setButtonLoading(btn, false);

    showAuthMessage('success', `Password reset link sent to ${email}. Please check your inbox.`);
    Toast.success('Reset link sent!', 'Check your email');

    // Clear form
    form.reset();
  }, 1500);
}

/**
 * Social login handler
 */
function handleSocialLogin(provider) {
  Toast.info(`Redirecting to ${provider}...`);

  setTimeout(() => {
    // Store session
    DataStore.set('session', {
      isLoggedIn: true,
      provider: provider,
      loginTime: new Date().toISOString()
    });

    Toast.success(`Logged in with ${provider}!`);
    setTimeout(() => {
      window.location.href='dashboard';
    }, 1000);
  }, 1000);
}

/**
 * Update password strength indicator
 */
function updatePasswordStrength(e) {
  const password = e.target.value;
  const strengthContainer = document.getElementById('passwordStrength');
  const strengthFill = document.getElementById('strengthFill');
  const strengthText = document.getElementById('strengthText');

  if (!strengthContainer || !strengthFill || !strengthText) return;

  if (password.length === 0) {
    strengthContainer.style.display = 'none';
    return;
  }

  strengthContainer.style.display = 'block';

  let strength = 0;
  if (password.length >= 8) strength++;
  if (password.length >= 12) strength++;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
  if (/\d/.test(password)) strength++;
  if (/[^a-zA-Z0-9]/.test(password)) strength++;

  strengthFill.className = 'strength-fill';

  if (strength <= 2) {
    strengthFill.classList.add('weak');
    strengthText.textContent = 'Weak password';
    strengthText.style.color = 'var(--danger)';
  } else if (strength <= 4) {
    strengthFill.classList.add('medium');
    strengthText.textContent = 'Medium strength';
    strengthText.style.color = 'var(--warning)';
  } else {
    strengthFill.classList.add('strong');
    strengthText.textContent = 'Strong password';
    strengthText.style.color = 'var(--success)';
  }
}

/**
 * Show auth message
 */
function showAuthMessage(type, message) {
  const msgEl = document.getElementById('authMessage');
  if (!msgEl) return;

  msgEl.className = `auth-message ${type} show`;
  msgEl.textContent = message;
}

/**
 * Set button loading state
 */
function setButtonLoading(btn, loading) {
  if (!btn) return;

  if (loading) {
    btn.disabled = true;
    btn.dataset.originalText = btn.innerHTML;
    btn.innerHTML = `
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite">
        <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/>
      </svg>
      Please wait...
    `;
  } else {
    btn.disabled = false;
    if (btn.dataset.originalText) {
      btn.innerHTML = btn.dataset.originalText;
    }
  }
}

/**
 * Check auth state - redirect if not logged in
 */
function requireAuth() {
  const session = DataStore.get('session');
  if (!session || !session.isLoggedIn) {
    window.location.href='login';
    return false;
  }
  return true;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
  const session = DataStore.get('session');
  return session && session.isLoggedIn;
}

/**
 * Logout user
 */
function logout() {
  DataStore.remove('session');
  Toast.success('You have been logged out');
  setTimeout(() => {
    window.location.href='./';
  }, 500);
}
