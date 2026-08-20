/**
 * RD Vendora - Products JavaScript
 * Full CRUD operations, search, filter, and image upload
 */

document.addEventListener('DOMContentLoaded', () => {
  if (!isAuthenticated()) {
    window.location.href='login';
    return;
  }

  loadUserData();
  renderProducts();
  updateStats();

  // Search
  document.getElementById('productSearch')?.addEventListener('input', debounce(() => {
    renderProducts();
  }, 300));

  // Category filter
  document.getElementById('categoryFilter')?.addEventListener('change', () => {
    renderProducts();
  });

  // Stock filter
  document.getElementById('stockFilter')?.addEventListener('change', () => {
    renderProducts();
  });

  // View toggle
  document.getElementById('gridViewBtn')?.addEventListener('click', () => {
    setView('grid');
  });
  document.getElementById('listViewBtn')?.addEventListener('click', () => {
    setView('list');
  });
});

let currentView = 'grid';
let editingProductId = null;
let uploadedImage = null;
let productToDelete = null;

/**
 * Load user data
 */
function loadUserData() {
  const user = DataStore.get('user');
  if (user) {
    const nameEl = document.getElementById('userName');
    const avatarEl = document.getElementById('userAvatar');
    if (nameEl) nameEl.textContent = user.name;
    if (avatarEl) avatarEl.src = user.avatar;
  }
}

/**
 * Get filtered products
 */
function getFilteredProducts() {
  let products = DataStore.get('products') || [];

  const search = document.getElementById('productSearch')?.value.toLowerCase() || '';
  const category = document.getElementById('categoryFilter')?.value || '';
  const stock = document.getElementById('stockFilter')?.value || '';

  if (search) {
    products = products.filter(p =>
      p.name.toLowerCase().includes(search) ||
      p.category.toLowerCase().includes(search) ||
      p.description?.toLowerCase().includes(search)
    );
  }

  if (category) {
    products = products.filter(p => p.category === category);
  }

  if (stock === 'in') {
    products = products.filter(p => p.stock > 20);
  } else if (stock === 'low') {
    products = products.filter(p => p.stock <= 20);
  }

  return products;
}

/**
 * Render products grid
 */
function renderProducts() {
  const products = getFilteredProducts();
  const grid = document.getElementById('productsGrid');
  const tableView = document.getElementById('productsTableView');
  const emptyState = document.getElementById('emptyState');

  if (products.length === 0) {
    grid.classList.add('hidden');
    tableView.classList.add('hidden');
    emptyState.classList.remove('hidden');
    return;
  }

  emptyState.classList.add('hidden');

  if (currentView === 'grid') {
    grid.classList.remove('hidden');
    tableView.classList.add('hidden');
    renderGridView(products);
  } else {
    grid.classList.add('hidden');
    tableView.classList.remove('hidden');
    renderTableView(products);
  }
}

function renderGridView(products) {
  const grid = document.getElementById('productsGrid');

  grid.innerHTML = products.map(product => `
    <div class="product-card">
      <div class="product-image">
        <img src="${product.image}" alt="${product.name}" loading="lazy">
        <div class="product-actions-overlay">
          <button onclick="editProduct(${product.id})" title="Edit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button onclick="confirmDelete(${product.id})" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </div>
      </div>
      <div class="product-content">
        <div class="product-category">${product.category}</div>
        <h3 class="product-name">${product.name}</h3>
        <div class="product-meta">
          <span class="product-price">$${product.price.toFixed(2)}</span>
          <span class="product-stock ${getStockClass(product.stock)}">${product.stock} in stock</span>
        </div>
        <div class="product-footer">
          <button class="btn btn-secondary btn-sm" onclick="editProduct(${product.id})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button class="btn btn-ghost btn-sm" onclick="confirmDelete(${product.id})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Delete
          </button>
        </div>
      </div>
    </div>
  `).join('');
}

function renderTableView(products) {
  const tbody = document.querySelector('#productsTable tbody');
  if (!tbody) return;

  tbody.innerHTML = products.map(product => `
    <tr>
      <td>
        <div class="product-cell">
          <img src="${product.image}" alt="${product.name}">
          <div class="product-cell-info">
            <div class="name">${product.name}</div>
            <div class="cat">${product.category}</div>
          </div>
        </div>
      </td>
      <td><span class="badge badge-primary">${product.category}</span></td>
      <td><strong>$${product.price.toFixed(2)}</strong></td>
      <td>${product.stock}</td>
      <td><span class="badge badge-${product.status === 'active' ? 'success' : 'warning'}">${product.status}</span></td>
      <td>
        <div class="table-actions">
          <button class="btn btn-icon btn-sm btn-ghost" onclick="editProduct(${product.id})" title="Edit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="btn btn-icon btn-sm btn-ghost" onclick="confirmDelete(${product.id})" title="Delete">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function getStockClass(stock) {
  if (stock === 0) return 'out';
  if (stock <= 20) return 'low';
  return 'in';
}

/**
 * Switch view mode
 */
function setView(view) {
  currentView = view;
  document.getElementById('gridViewBtn').classList.toggle('active', view === 'grid');
  document.getElementById('listViewBtn').classList.toggle('active', view === 'list');
  renderProducts();
}

/**
 * Update product stats
 */
function updateStats() {
  const products = DataStore.get('products') || [];
  const total = products.length;
  const active = products.filter(p => p.status === 'active').length;
  const lowStock = products.filter(p => p.stock <= 20).length;

  document.getElementById('totalProducts').textContent = total;
  document.getElementById('activeProducts').textContent = active;
  document.getElementById('lowStockProducts').textContent = lowStock;
}

/**
 * Handle image upload
 */
function handleImageUpload(input) {
  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    uploadedImage = e.target.result;
    document.getElementById('imageUpload').classList.add('hidden');
    document.getElementById('imagePreview').classList.remove('hidden');
    document.getElementById('previewImg').src = uploadedImage;
  };
  reader.readAsDataURL(file);
}

function removeImage() {
  uploadedImage = null;
  document.getElementById('productImage').value = '';
  document.getElementById('imageUpload').classList.remove('hidden');
  document.getElementById('imagePreview').classList.add('hidden');
  document.getElementById('previewImg').src = '';
}

/**
 * Save product (create or update)
 */
function saveProduct() {
  const name = document.getElementById('productName').value.trim();
  const price = parseFloat(document.getElementById('productPrice').value);
  const stock = parseInt(document.getElementById('productStock').value);
  const category = document.getElementById('productCategory').value;
  const status = document.getElementById('productStatus').value;
  const description = document.getElementById('productDescription').value.trim();

  if (!name || !price || isNaN(stock) || !category) {
    Toast.error('Please fill in all required fields');
    return;
  }

  let products = DataStore.get('products') || [];

  if (editingProductId) {
    // Update existing
    const index = products.findIndex(p => p.id === editingProductId);
    if (index !== -1) {
      products[index] = {
        ...products[index],
        name,
        price,
        stock,
        category,
        status,
        description,
        ...(uploadedImage && { image: uploadedImage })
      };
      Toast.success('Product updated successfully');
    }
  } else {
    // Create new
    const newProduct = {
      id: Date.now(),
      name,
      price,
      stock,
      category,
      status,
      description,
      image: uploadedImage || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=400'
    };
    products.push(newProduct);
    Toast.success('Product added successfully');
  }

  DataStore.set('products', products);
  Modal.close('addProductModal');
  resetForm();
  renderProducts();
  updateStats();
}

/**
 * Edit product
 */
function editProduct(id) {
  const products = DataStore.get('products') || [];
  const product = products.find(p => p.id === id);
  if (!product) return;

  editingProductId = id;
  document.getElementById('modalTitle').textContent = 'Edit Product';
  document.getElementById('productId').value = id;
  document.getElementById('productName').value = product.name;
  document.getElementById('productPrice').value = product.price;
  document.getElementById('productStock').value = product.stock;
  document.getElementById('productCategory').value = product.category;
  document.getElementById('productStatus').value = product.status || 'active';
  document.getElementById('productDescription').value = product.description || '';

  if (product.image) {
    uploadedImage = product.image;
    document.getElementById('imageUpload').classList.add('hidden');
    document.getElementById('imagePreview').classList.remove('hidden');
    document.getElementById('previewImg').src = product.image;
  }

  Modal.open('addProductModal');
}

/**
 * Confirm delete
 */
function confirmDelete(id) {
  const products = DataStore.get('products') || [];
  const product = products.find(p => p.id === id);
  if (!product) return;

  productToDelete = id;
  document.getElementById('deleteProductName').textContent = product.name;
  Modal.open('deleteModal');

  // Setup confirm button
  document.getElementById('confirmDeleteBtn').onclick = () => {
    deleteProduct(productToDelete);
    Modal.close('deleteModal');
  };
}

/**
 * Delete product
 */
function deleteProduct(id) {
  let products = DataStore.get('products') || [];
  products = products.filter(p => p.id !== id);
  DataStore.set('products', products);

  Toast.success('Product deleted successfully');
  renderProducts();
  updateStats();
}

/**
 * Reset form
 */
function resetForm() {
  editingProductId = null;
  uploadedImage = null;
  document.getElementById('modalTitle').textContent = 'Add Product';
  document.getElementById('productForm').reset();
  document.getElementById('productId').value = '';
  removeImage();
}

// Reset form when modal closes
document.getElementById('addProductModal')?.addEventListener('click', (e) => {
  if (e.target === e.currentTarget) {
    resetForm();
  }
});

document.querySelector('#addProductModal .modal-close')?.addEventListener('click', resetForm);

/**
 * Debounce utility
 */
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
