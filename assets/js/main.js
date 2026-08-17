/**
 * RD Vendora - Main JavaScript
 * Shared utilities, theme management, localStorage, and UI components
 */

// ============================================
// Theme Management
// ============================================
const ThemeManager = {
  init() {
    const savedTheme = localStorage.getItem('RD Vendora-theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    this.updateToggleIcon(savedTheme);
  },

  toggle() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('RD Vendora-theme', next);
    this.updateToggleIcon(next);
  },

  updateToggleIcon(theme) {
    const toggles = document.querySelectorAll('.theme-toggle');
    toggles.forEach(toggle => {
      toggle.innerHTML = theme === 'dark'
        ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>'
        : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    });
  }
};

// ============================================
// Toast Notifications
// ============================================
const Toast = {
  container: null,

  init() {
    this.container = document.querySelector('.toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(message, type = 'info', title = '') {
    if (!this.container) this.init();

    const icons = {
      success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>',
      error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
      warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
      info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <div class="toast-icon">${icons[type]}</div>
      <div class="toast-content">
        ${title ? `<div class="toast-title">${title}</div>` : ''}
        <div class="toast-message">${message}</div>
      </div>
    `;

    this.container.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('removing');
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  },

  success(message, title) { this.show(message, 'success', title); },
  error(message, title) { this.show(message, 'error', title); },
  warning(message, title) { this.show(message, 'warning', title); },
  info(message, title) { this.show(message, 'info', title); }
};

// ============================================
// Modal System
// ============================================
const Modal = {
  open(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  },

  close(id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  },

  init() {
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
          overlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    });

    document.querySelectorAll('.modal-close').forEach(btn => {
      btn.addEventListener('click', () => {
        const overlay = btn.closest('.modal-overlay');
        if (overlay) {
          overlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
          m.classList.remove('active');
        });
        document.body.style.overflow = '';
      }
    });
  }
};

// ============================================
// Dropdown System
// ============================================
const Dropdown = {
  init() {
    document.querySelectorAll('.dropdown').forEach(dropdown => {
      const trigger = dropdown.querySelector('.dropdown-trigger');
      if (trigger) {
        trigger.addEventListener('click', (e) => {
          e.stopPropagation();
          document.querySelectorAll('.dropdown.active').forEach(d => {
            if (d !== dropdown) d.classList.remove('active');
          });
          dropdown.classList.toggle('active');
        });
      }
    });

    document.addEventListener('click', () => {
      document.querySelectorAll('.dropdown.active').forEach(d => d.classList.remove('active'));
    });
  }
};

// ============================================
// localStorage Data Manager
// ============================================
const DataStore = {
  prefix: 'RD Vendora_',

  get(key) {
    try {
      const data = localStorage.getItem(this.prefix + key);
      return data ? JSON.parse(data) : null;
    } catch {
      return null;
    }
  },

  set(key, value) {
    localStorage.setItem(this.prefix + key, JSON.stringify(value));
  },

  remove(key) {
    localStorage.removeItem(this.prefix + key);
  },

  // Initialize demo data
  initDemoData() {
    if (!this.get('initialized')) {
      // Demo products
      this.set('products', [
        { id: 1, name: 'Wireless Headphones Pro', price: 129.99, category: 'electronics', stock: 45, image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400', status: 'active', description: 'Premium noise-cancelling wireless headphones with 30-hour battery life.' },
        { id: 2, name: 'Minimalist Watch', price: 199.99, category: 'fashion', stock: 23, image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400', status: 'active', description: 'Elegant minimalist design with genuine leather strap.' },
        { id: 3, name: 'Smart Home Hub', price: 89.99, category: 'electronics', stock: 67, image: 'https://images.unsplash.com/photo-1558089687-f282ffcbc126?w=400', status: 'active', description: 'Control all your smart devices from one central hub.' },
        { id: 4, name: 'Organic Face Serum', price: 54.99, category: 'beauty', stock: 89, image: 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=400', status: 'active', description: 'Natural anti-aging serum with vitamin C and hyaluronic acid.' },
        { id: 5, name: 'Running Shoes Elite', price: 159.99, category: 'fashion', stock: 34, image: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400', status: 'active', description: 'Lightweight performance running shoes with responsive cushioning.' },
        { id: 6, name: 'Yoga Mat Premium', price: 49.99, category: 'sports', stock: 56, image: 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=400', status: 'active', description: 'Extra thick non-slip yoga mat with alignment lines.' },
        { id: 7, name: 'Bluetooth Speaker', price: 79.99, category: 'electronics', stock: 78, image: 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=400', status: 'active', description: 'Portable waterproof speaker with 360-degree sound.' },
        { id: 8, name: 'Leather Backpack', price: 119.99, category: 'fashion', stock: 19, image: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400', status: 'low', description: 'Handcrafted genuine leather backpack with laptop compartment.' },
        { id: 9, name: 'Ceramic Coffee Set', price: 64.99, category: 'home', stock: 42, image: 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=400', status: 'active', description: 'Artisan ceramic coffee set including 4 cups and saucers.' },
        { id: 10, name: 'Fitness Tracker', price: 99.99, category: 'electronics', stock: 91, image: 'https://images.unsplash.com/photo-1575311373937-040b8e1fd5b6?w=400', status: 'active', description: 'Advanced fitness tracker with heart rate and sleep monitoring.' },
        { id: 11, name: 'Scented Candle Collection', price: 34.99, category: 'home', stock: 63, image: 'https://images.unsplash.com/photo-1602607434359-a1de6a3db820?w=400', status: 'active', description: 'Set of 3 hand-poured soy candles in signature scents.' },
        { id: 12, name: 'Desk Lamp Modern', price: 74.99, category: 'home', stock: 28, image: 'https://images.unsplash.com/photo-1507473885765-e6ed057ab6ae?w=400', status: 'active', description: 'Adjustable LED desk lamp with wireless charging base.' }
      ]);

      // Demo cart
      this.set('cart', []);

      // Demo user
      this.set('user', {
        id: 1,
        name: 'Alex Johnson',
        email: 'alex@example.com',
        avatar: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100',
        storeName: 'Dream Boutique',
        subdomain: 'dreamboutique',
        plan: 'Growth',
        joined: '2024-01-15'
      });

      // Demo orders
      this.set('orders', [
        { id: 'ORD-2024-001', customer: 'Sarah Miller', email: 'sarah@email.com', total: 259.98, status: 'completed', date: '2024-12-28', items: 2 },
        { id: 'ORD-2024-002', customer: 'James Wilson', email: 'james@email.com', total: 89.99, status: 'processing', date: '2024-12-29', items: 1 },
        { id: 'ORD-2024-003', customer: 'Emily Chen', email: 'emily@email.com', total: 414.97, status: 'completed', date: '2024-12-30', items: 3 },
        { id: 'ORD-2024-004', customer: 'Michael Brown', email: 'michael@email.com', total: 54.99, status: 'pending', date: '2025-01-02', items: 1 },
        { id: 'ORD-2024-005', customer: 'Lisa Davis', email: 'lisa@email.com', total: 199.99, status: 'shipped', date: '2025-01-03', items: 1 },
        { id: 'ORD-2024-006', customer: 'David Lee', email: 'david@email.com', total: 324.96, status: 'completed', date: '2025-01-05', items: 2 },
        { id: 'ORD-2024-007', customer: 'Anna Garcia', email: 'anna@email.com', total: 129.99, status: 'processing', date: '2025-01-06', items: 1 },
        { id: 'ORD-2024-008', customer: 'Robert Taylor', email: 'robert@email.com', total: 614.95, status: 'completed', date: '2025-01-07', items: 4 }
      ]);

      // Demo customers
      this.set('customers', [
        { id: 1, name: 'Sarah Miller', email: 'sarah@email.com', orders: 5, total: 649.95, joined: '2024-06-15', status: 'active' },
        { id: 2, name: 'James Wilson', email: 'james@email.com', orders: 3, total: 289.97, joined: '2024-08-22', status: 'active' },
        { id: 3, name: 'Emily Chen', email: 'emily@email.com', orders: 8, total: 1249.92, joined: '2024-03-10', status: 'active' },
        { id: 4, name: 'Michael Brown', email: 'michael@email.com', orders: 2, total: 144.98, joined: '2024-11-05', status: 'active' },
        { id: 5, name: 'Lisa Davis', email: 'lisa@email.com', orders: 6, total: 899.94, joined: '2024-05-18', status: 'active' }
      ]);

      // Demo subscribers for admin
      this.set('subscribers', [
        { id: 1, name: 'John Smith', email: 'john@company.com', plan: 'Scale', stores: 3, revenue: 12450, status: 'active', joined: '2024-01-10' },
        { id: 2, name: 'Maria Garcia', email: 'maria@shop.com', plan: 'Growth', stores: 1, revenue: 5890, status: 'active', joined: '2024-02-15' },
        { id: 3, name: 'Chris Lee', email: 'chris@brand.com', plan: 'Empire', stores: 5, revenue: 45200, status: 'active', joined: '2023-11-20' },
        { id: 4, name: 'Priya Patel', email: 'priya@store.com', plan: 'Launch', stores: 1, revenue: 1230, status: 'active', joined: '2024-06-01' },
        { id: 5, name: 'Tom Wilson', email: 'tom@biz.com', plan: 'Growth', stores: 2, revenue: 8900, status: 'trial', joined: '2025-01-05' }
      ]);

      // Demo notifications
      this.set('notifications', [
        { id: 1, title: 'New order received', message: 'Order #ORD-2024-008 for $614.95', type: 'order', read: false, time: '2 min ago' },
        { id: 2, title: 'Product low stock', message: 'Leather Backpack only 19 left', type: 'alert', read: false, time: '1 hour ago' },
        { id: 3, title: 'New customer signup', message: 'Robert Taylor joined your store', type: 'customer', read: true, time: '3 hours ago' },
        { id: 4, title: 'Payment processed', message: 'Payout of $1,240.50 initiated', type: 'payment', read: true, time: '1 day ago' },
        { id: 5, title: 'Review received', message: 'New 5-star review on Wireless Headphones', type: 'review', read: true, time: '2 days ago' }
      ]);

      this.set('initialized', true);
    }
  }
};

// ============================================
// Cart Manager
// ============================================
const CartManager = {
  get() {
    return DataStore.get('cart') || [];
  },

  add(product) {
    const cart = this.get();
    const existing = cart.find(item => item.id === product.id);

    if (existing) {
      existing.quantity += 1;
    } else {
      cart.push({ ...product, quantity: 1 });
    }

    DataStore.set('cart', cart);
    this.updateBadge();
    Toast.success(`${product.name} added to cart`);
  },

  remove(productId) {
    let cart = this.get();
    cart = cart.filter(item => item.id !== productId);
    DataStore.set('cart', cart);
    this.updateBadge();
  },

  updateQuantity(productId, quantity) {
    const cart = this.get();
    const item = cart.find(i => i.id === productId);
    if (item) {
      if (quantity <= 0) {
        this.remove(productId);
        return;
      }
      item.quantity = quantity;
      DataStore.set('cart', cart);
      this.updateBadge();
    }
  },

  getTotal() {
    return this.get().reduce((sum, item) => sum + item.price * item.quantity, 0);
  },

  getCount() {
    return this.get().reduce((sum, item) => sum + item.quantity, 0);
  },

  clear() {
    DataStore.set('cart', []);
    this.updateBadge();
  },

  updateBadge() {
    const badges = document.querySelectorAll('.cart-badge');
    const count = this.getCount();
    badges.forEach(badge => {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    });
  }
};

// ============================================
// Form Validation
// ============================================
const FormValidator = {
  rules: {
    required: (value) => value.trim() !== '' || 'This field is required',
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || 'Please enter a valid email',
    minLength: (value, length) => value.length >= length || `Minimum ${length} characters required`,
    maxLength: (value, length) => value.length <= length || `Maximum ${length} characters allowed`,
    password: (value) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(value) || 'Password must be 8+ chars with uppercase, lowercase, and number',
    match: (value, matchValue) => value === matchValue || 'Passwords do not match',
    url: (value) => /^https?:\/\/.+/.test(value) || 'Please enter a valid URL',
    phone: (value) => /^[\d\s\-+()]{7,}$/.test(value) || 'Please enter a valid phone number'
  },

  validate(form) {
    let isValid = true;
    const fields = form.querySelectorAll('[data-validate]');

    fields.forEach(field => {
      const validations = field.dataset.validate.split('|');
      const value = field.value;
      let fieldValid = true;
      let errorMessage = '';

      for (const validation of validations) {
        const [rule, param] = validation.split(':');
        let result;

        switch (rule) {
          case 'required': result = this.rules.required(value); break;
          case 'email': result = value ? this.rules.email(value) : true; break;
          case 'min': result = value ? this.rules.minLength(value, parseInt(param)) : true; break;
          case 'max': result = value ? this.rules.maxLength(value, parseInt(param)) : true; break;
          case 'password': result = value ? this.rules.password(value) : true; break;
          case 'match': result = value ? this.rules.match(value, document.getElementById(param)?.value) : true; break;
          case 'url': result = value ? this.rules.url(value) : true; break;
          case 'phone': result = value ? this.rules.phone(value) : true; break;
          default: result = true;
        }

        if (result !== true) {
          fieldValid = false;
          errorMessage = result;
          break;
        }
      }

      // Update UI
      const formGroup = field.closest('.form-group');
      const existingError = formGroup?.querySelector('.form-error');

      if (!fieldValid) {
        isValid = false;
        field.classList.add('error');
        if (existingError) {
          existingError.textContent = errorMessage;
        } else if (formGroup) {
          const errorEl = document.createElement('div');
          errorEl.className = 'form-error';
          errorEl.textContent = errorMessage;
          formGroup.appendChild(errorEl);
        }
      } else {
        field.classList.remove('error');
        if (existingError) existingError.remove();
      }
    });

    return isValid;
  },

  init(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    form.addEventListener('submit', (e) => {
      if (!this.validate(form)) {
        e.preventDefault();
      }
    });

    // Clear errors on input
    form.querySelectorAll('[data-validate]').forEach(field => {
      field.addEventListener('input', () => {
        field.classList.remove('error');
        const formGroup = field.closest('.form-group');
        const errorEl = formGroup?.querySelector('.form-error');
        if (errorEl) errorEl.remove();
      });
    });
  }
};

// ============================================
// Password Toggle
// ============================================
const PasswordToggle = {
  init() {
    document.querySelectorAll('.password-toggle').forEach(toggle => {
      toggle.addEventListener('click', () => {
        const input = toggle.previousElementSibling;
        if (input) {
          const type = input.type === 'password' ? 'text' : 'password';
          input.type = type;
          toggle.innerHTML = type === 'password'
            ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
        }
      });
    });
  }
};

// ============================================
// Navbar Scroll Effect
// ============================================
const NavbarScroll = {
  init() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    window.addEventListener('scroll', () => {
      if (window.scrollY > 10) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });
  }
};

// ============================================
// Mobile Menu Toggle
// ============================================
const MobileMenu = {
  init() {
    const toggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (toggle && navLinks) {
      toggle.addEventListener('click', () => {
        toggle.classList.toggle('active');
        navLinks.classList.toggle('active');
      });

      // Close menu on link click
      navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
          toggle.classList.remove('active');
          navLinks.classList.remove('active');
        });
      });
    }
  }
};

// ============================================
// Sidebar Toggle (Dashboard)
// ============================================
const SidebarToggle = {
  init() {
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    if (toggle && sidebar) {
      toggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        if (mainContent) {
          mainContent.classList.toggle('expanded');
        }
      });
    }

    // Mobile sidebar
    const mobileToggle = document.querySelector('.mobile-sidebar-toggle');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    if (mobileToggle && sidebar) {
      mobileToggle.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-open');
        if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
      });
    }

    if (sidebarOverlay) {
      sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
      });
    }
  }
};

// ============================================
// Animated Counter
// ============================================
const AnimatedCounter = {
  init() {
    const counters = document.querySelectorAll('[data-counter]');

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const target = parseInt(entry.target.dataset.counter);
          const duration = parseInt(entry.target.dataset.duration) || 2000;
          const prefix = entry.target.dataset.prefix || '';
          const suffix = entry.target.dataset.suffix || '';
          this.animate(entry.target, target, duration, prefix, suffix);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(counter => observer.observe(counter));
  },

  animate(element, target, duration, prefix, suffix) {
    const start = performance.now();
    const animate = (currentTime) => {
      const elapsed = currentTime - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(eased * target);
      element.textContent = prefix + current.toLocaleString() + suffix;

      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        element.textContent = prefix + target.toLocaleString() + suffix;
      }
    };
    requestAnimationFrame(animate);
  }
};

// ============================================
// Scroll Animations
// ============================================
const ScrollAnimations = {
  init() {
    const animatedElements = document.querySelectorAll('[data-animate]');

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const animation = entry.target.dataset.animate;
          const delay = entry.target.dataset.delay || 0;
          setTimeout(() => {
            entry.target.classList.add(`animate-${animation}`);
          }, delay);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    animatedElements.forEach(el => observer.observe(el));
  }
};

// ============================================
// Search/Filter
// ============================================
const SearchFilter = {
  init(inputSelector, itemsSelector, attribute = 'textContent') {
    const input = document.querySelector(inputSelector);
    const items = document.querySelectorAll(itemsSelector);

    if (!input) return;

    input.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase();

      items.forEach(item => {
        const text = attribute === 'textContent'
          ? item.textContent.toLowerCase()
          : (item.getAttribute(attribute) || '').toLowerCase();

        if (text.includes(query)) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });
    });
  }
};

// ============================================
// Tab System
// ============================================
const Tabs = {
  init(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    const triggers = container.querySelectorAll('[data-tab]');
    const panels = container.querySelectorAll('[data-tab-panel]');

    triggers.forEach(trigger => {
      trigger.addEventListener('click', () => {
        const tab = trigger.dataset.tab;

        triggers.forEach(t => t.classList.remove('active'));
        trigger.classList.add('active');

        panels.forEach(panel => {
          panel.style.display = panel.dataset.tabPanel === tab ? 'block' : 'none';
        });
      });
    });
  }
};

// ============================================
// Accordion
// ============================================
const Accordion = {
  init(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    const items = container.querySelectorAll('.accordion-item');

    items.forEach(item => {
      const trigger = item.querySelector('.accordion-trigger');
      const content = item.querySelector('.accordion-content');

      if (trigger && content) {
        trigger.addEventListener('click', () => {
          const isOpen = item.classList.contains('open');

          // Close all
          items.forEach(i => {
            i.classList.remove('open');
            i.querySelector('.accordion-content').style.maxHeight = null;
          });

          // Open clicked if was closed
          if (!isOpen) {
            item.classList.add('open');
            content.style.maxHeight = content.scrollHeight + 'px';
          }
        });
      }
    });
  }
};

// ============================================
// Copy to Clipboard
// ============================================
const Clipboard = {
  copy(text) {
    navigator.clipboard.writeText(text).then(() => {
      Toast.success('Copied to clipboard');
    }).catch(() => {
      // Fallback
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      Toast.success('Copied to clipboard');
    });
  }
};

// ============================================
// Initialize Everything on DOM Ready
// ============================================
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  DataStore.initDemoData();
  Modal.init();
  Dropdown.init();
  PasswordToggle.init();
  NavbarScroll.init();
  MobileMenu.init();
  SidebarToggle.init();
  AnimatedCounter.init();
  ScrollAnimations.init();
  CartManager.updateBadge();

  // Theme toggle buttons
  document.querySelectorAll('.theme-toggle').forEach(btn => {
    btn.addEventListener('click', () => ThemeManager.toggle());
  });
});

// Export for use in other scripts
window.RD Vendora = {
  ThemeManager,
  Toast,
  Modal,
  Dropdown,
  DataStore,
  CartManager,
  FormValidator,
  PasswordToggle,
  NavbarScroll,
  MobileMenu,
  SidebarToggle,
  AnimatedCounter,
  ScrollAnimations,
  SearchFilter,
  Tabs,
  Accordion,
  Clipboard
};
