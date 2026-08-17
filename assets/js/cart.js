/**
 * RD Vendora - Cart JavaScript
 * Shopping cart with quantity controls, remove, summary
 */

document.addEventListener('DOMContentLoaded', () => {
  DataStore.initDemoData();
  renderCart();
  CartManager.updateBadge();
});

/**
 * Render cart page
 */
function renderCart() {
  const cart = CartManager.get();
  const container = document.getElementById('cartContent');

  if (cart.length === 0) {
    container.innerHTML = `
      <div style="grid-column:1/-1;text-align:center;padding:4rem 2rem">
        <div style="width:80px;height:80px;border-radius:1.5rem;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;color:var(--text-muted)">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <h3 style="font-size:1.25rem;font-weight:600;margin-bottom:0.5rem">Your cart is empty</h3>
        <p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1.5rem">Looks like you haven't added any products yet.</p>
        <a href="storefront.php" class="btn btn-primary">Start Shopping</a>
      </div>
    `;
    return;
  }

  const subtotal = CartManager.getTotal();
  const shipping = subtotal > 50 ? 0 : 5.99;
  const total = subtotal + shipping;

  container.innerHTML = `
    <div class="cart-items">
      ${cart.map(item => `
        <div class="cart-item">
          <img src="${item.image}" alt="${item.name}">
          <div class="cart-item-info">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-cat">${item.category}</div>
            <div class="cart-item-price">$${(item.price * item.quantity).toFixed(2)}</div>
          </div>
          <div class="qty-control">
            <button onclick="updateQty(${item.id}, ${item.quantity - 1})">-</button>
            <span>${item.quantity}</span>
            <button onclick="updateQty(${item.id}, ${item.quantity + 1})">+</button>
          </div>
          <button class="remove-btn" onclick="removeItem(${item.id})" title="Remove">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </div>
      `).join('')}
    </div>
    <div class="cart-summary">
      <h3>Order Summary</h3>
      <div class="summary-row">
        <span>Subtotal (${cart.reduce((s,i)=>s+i.quantity,0)} items)</span>
        <span>$${subtotal.toFixed(2)}</span>
      </div>
      <div class="summary-row">
        <span>Shipping</span>
        <span style="color:${shipping===0?'var(--success)':'inherit'}">${shipping===0?'FREE': '$'+shipping.toFixed(2)}</span>
      </div>
      <div class="summary-row">
        <span>Tax</span>
        <span>Calculated at checkout</span>
      </div>
      <div class="summary-row total">
        <span>Total</span>
        <span>$${total.toFixed(2)}</span>
      </div>
      <a href="checkout.php" class="btn btn-primary btn-full btn-lg" style="margin-top:1rem;display:flex">
        Proceed to Checkout
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
      <p style="font-size:0.75rem;color:var(--text-muted);text-align:center;margin-top:0.75rem">
        ${shipping > 0 ? `Add $${(50 - subtotal).toFixed(2)} more for free shipping!` : 'You qualified for free shipping!'}
      </p>
    </div>
  `;
}

function updateQty(productId, qty) {
  CartManager.updateQuantity(productId, qty);
  renderCart();
}

function removeItem(productId) {
  CartManager.remove(productId);
  renderCart();
  Toast.success('Item removed from cart');
}
