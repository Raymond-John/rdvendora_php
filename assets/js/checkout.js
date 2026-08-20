/**
 * RD Vendora - Checkout JavaScript
 * Checkout form, order summary, coupon, payment
 */

document.addEventListener('DOMContentLoaded', () => {
  DataStore.initDemoData();
  CartManager.updateBadge();
  renderOrderSummary();

  // Format card number
  document.getElementById('cardNumber')?.addEventListener('input', (e) => {
    let val = e.target.value.replace(/\D/g, '');
    val = val.replace(/(\d{4})(?=\d)/g, '$1 ');
    e.target.value = val;
  });

  // Format expiry
  document.getElementById('expiry')?.addEventListener('input', (e) => {
    let val = e.target.value.replace(/\D/g, '');
    if (val.length >= 2) val = val.slice(0, 2) + '/' + val.slice(2, 4);
    e.target.value = val;
  });

  // Only allow digits in CVV
  document.getElementById('cvv')?.addEventListener('input', (e) => {
    e.target.value = e.target.value.replace(/\D/g, '');
  });
});

let discountAmount = 0;
let couponApplied = false;

/**
 * Render order summary
 */
function renderOrderSummary() {
  const cart = CartManager.get();
  const itemsContainer = document.getElementById('orderItems');

  if (cart.length === 0) {
    itemsContainer.innerHTML = '<p style="color:var(--text-muted);font-size:0.875rem">Your cart is empty. <a href="storefront" style="color:var(--primary)">Go shopping</a></p>';
    updateTotals(0, 0, 0);
    return;
  }

  itemsContainer.innerHTML = cart.map(item => `
    <div class="order-item">
      <img src="${item.image}" alt="${item.name}">
      <div class="order-item-info">
        <div class="order-item-name">${item.name}</div>
        <div class="order-item-qty">Qty: ${item.quantity}</div>
      </div>
      <div class="order-item-price">$${(item.price * item.quantity).toFixed(2)}</div>
    </div>
  `).join('');

  updateTotals(CartManager.getTotal(), 0, 0);
}

/**
 * Update totals
 */
function updateTotals(subtotal, discount, taxRate) {
  const shipping = subtotal > 50 ? 0 : 5.99;
  const tax = subtotal * taxRate;
  const total = subtotal + shipping + tax - discount;

  document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
  document.getElementById('shipping').textContent = shipping === 0 ? 'FREE' : `$${shipping.toFixed(2)}`;
  document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
  document.getElementById('total').textContent = `$${total.toFixed(2)}`;

  if (discount > 0) {
    document.getElementById('discountRow').style.display = 'flex';
    document.getElementById('discount').textContent = `-$${discount.toFixed(2)}`;
  } else {
    document.getElementById('discountRow').style.display = 'none';
  }
}

/**
 * Apply coupon
 */
function applyCoupon() {
  const code = document.getElementById('couponCode').value.trim().toUpperCase();

  if (!code) {
    Toast.error('Please enter a coupon code');
    return;
  }

  if (couponApplied) {
    Toast.warning('A coupon is already applied');
    return;
  }

  // Demo coupons
  const coupons = {
    'SAVE10': 0.10,
    'WELCOME20': 0.20,
    'VIP25': 0.25,
    'FREESHIP': 'free_shipping'
  };

  const coupon = coupons[code];
  if (!coupon) {
    Toast.error('Invalid coupon code');
    return;
  }

  const subtotal = CartManager.getTotal();

  if (coupon === 'free_shipping') {
    discountAmount = 0;
    updateTotals(subtotal, 0, 0.08);
    Toast.success('Free shipping applied!');
  } else {
    discountAmount = subtotal * coupon;
    updateTotals(subtotal, discountAmount, 0.08);
    Toast.success(`Coupon applied! ${Math.round(coupon * 100)}% off`);
  }

  couponApplied = true;
}

/**
 * Toggle payment method
 */
function togglePayment(radio) {
  document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
  radio.closest('.payment-method').classList.add('active');

  const cardFields = document.getElementById('cardFields');
  if (radio.value === 'card') {
    cardFields.style.display = 'block';
  } else {
    cardFields.style.display = 'none';
  }
}

/**
 * Place order
 */
function placeOrder() {
  const cart = CartManager.get();
  if (cart.length === 0) {
    Toast.error('Your cart is empty');
    return;
  }

  // Basic validation
  const email = document.getElementById('email')?.value;
  const fullName = document.getElementById('fullName')?.value;
  const address = document.getElementById('address')?.value;
  const city = document.getElementById('city')?.value;
  const postal = document.getElementById('postal')?.value;

  if (!email || !fullName || !address || !city || !postal) {
    Toast.error('Please fill in all required fields');
    return;
  }

  const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value;
  if (paymentMethod === 'card') {
    const cardNumber = document.getElementById('cardNumber')?.value;
    const expiry = document.getElementById('expiry')?.value;
    const cvv = document.getElementById('cvv')?.value;
    if (!cardNumber || !expiry || !cvv) {
      Toast.error('Please fill in all card details');
      return;
    }
  }

  const btn = document.querySelector('.place-order-btn');
  btn.disabled = true;
  btn.innerHTML = `
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite">
      <circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/>
    </svg>
    Processing...
  `;

  setTimeout(() => {
    CartManager.clear();

    Toast.success('Order placed successfully!');

    // Show success modal or redirect
    document.querySelector('.checkout-page').innerHTML = `
      <div style="text-align:center;padding:4rem 2rem;max-width:500px;margin:0 auto">
        <div style="width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.1);color:var(--success);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 style="font-size:1.75rem;font-weight:700;margin-bottom:0.5rem">Order Confirmed!</h2>
        <p style="color:var(--text-secondary);margin-bottom:0.5rem">Thank you for your purchase, ${fullName}.</p>
        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:2rem">Order #ORD-${Date.now().toString().slice(-6)}</p>
        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:2rem">A confirmation email has been sent to ${email}.</p>
        <a href="storefront" class="btn btn-primary btn-lg">Continue Shopping</a>
      </div>
    `;
  }, 2000);
}
