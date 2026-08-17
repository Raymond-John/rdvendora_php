/**
 * RD Vendora - Storefront JavaScript
 * Public store with product browsing, search, categories, and cart
 */

document.addEventListener('DOMContentLoaded', () => {
  DataStore.initDemoData();
  renderStoreProducts();
  CartManager.updateBadge();

  // Category filter
  document.querySelectorAll('.category-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      renderStoreProducts();
    });
  });

  // Search
  document.getElementById('storeSearch')?.addEventListener('input', debounce(() => {
    renderStoreProducts();
  }, 300));
});

/**
 * Get filtered store products
 */
function getStoreProducts() {
  let products = DataStore.get('products') || [];
  const search = document.getElementById('storeSearch')?.value.toLowerCase() || '';
  const activeCategory = document.querySelector('.category-chip.active')?.dataset.category || '';

  if (activeCategory) {
    products = products.filter(p => p.category === activeCategory);
  }

  if (search) {
    products = products.filter(p =>
      p.name.toLowerCase().includes(search) ||
      p.category.toLowerCase().includes(search) ||
      p.description?.toLowerCase().includes(search)
    );
  }

  return products;
}

/**
 * Render store products
 */
function renderStoreProducts() {
  const products = getStoreProducts();
  const grid = document.getElementById('storeProducts');
  const emptyState = document.getElementById('storeEmptyState');
  const countEl = document.getElementById('productCount');

  if (countEl) countEl.textContent = `${products.length} product${products.length !== 1 ? 's' : ''}`;

  if (products.length === 0) {
    grid.classList.add('hidden');
    emptyState.classList.remove('hidden');
    return;
  }

  grid.classList.remove('hidden');
  emptyState.classList.add('hidden');

  grid.innerHTML = products.map(product => `
    <div class="store-product-card" data-animate="fade-in-up">
      <div class="store-product-image" onclick="openQuickView(${product.id})">
        <img src="${product.image}" alt="${product.name}" loading="lazy">
        <div class="store-product-overlay">
          <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();addToCart(${product.id})" style="gap:0.25rem">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            Quick Add
          </button>
        </div>
      </div>
      <div class="store-product-info">
        <div class="store-product-category">${product.category}</div>
        <h3 class="store-product-name">${product.name}</h3>
        <div class="store-product-price">$${product.price.toFixed(2)}</div>
      </div>
    </div>
  `).join('');

  // Re-trigger animations
  ScrollAnimations.init();
}

/**
 * Add to cart
 */
function addToCart(productId) {
  const products = DataStore.get('products') || [];
  const product = products.find(p => p.id === productId);
  if (product) {
    CartManager.add(product);
  }
}

/**
 * Open quick view modal
 */
function openQuickView(productId) {
  const products = DataStore.get('products') || [];
  const product = products.find(p => p.id === productId);
  if (!product) return;

  const content = document.getElementById('quickViewContent');
  content.innerHTML = `
    <div class="quick-view-image">
      <img src="${product.image}" alt="${product.name}">
    </div>
    <div class="quick-view-info">
      <div class="quick-view-category">${product.category}</div>
      <h2 class="quick-view-name">${product.name}</h2>
      <div class="quick-view-price">$${product.price.toFixed(2)}</div>
      <p class="quick-view-desc">${product.description || 'No description available.'}</p>
      <div class="quick-view-meta">
        <div class="quick-view-meta-item">
          <span class="quick-view-meta-label">Availability</span>
          <span class="quick-view-meta-value" style="color:${product.stock > 20 ? 'var(--success)' : 'var(--warning)'}">${product.stock > 0 ? product.stock + ' in stock' : 'Out of stock'}</span>
        </div>
        <div class="quick-view-meta-item">
          <span class="quick-view-meta-label">SKU</span>
          <span class="quick-view-meta-value">SKU-${product.id.toString().padStart(4, '0')}</span>
        </div>
      </div>
      <div class="quick-view-actions">
        <button class="btn btn-primary btn-lg" onclick="addToCart(${product.id}); Modal.close('quickViewModal');">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          Add to Cart
        </button>
        <a href="cart.php" class="btn btn-secondary btn-lg" style="display:flex;justify-content:center">View Cart</a>
      </div>
    </div>
  `;

  Modal.open('quickViewModal');
}

function debounce(func, wait) {
  let timeout;
  return function(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}
