/**
 * RD Vendora Marketplace — shared cart helpers
 * Cart key kept as greenshop_cart for existing carts.
 */
(function (window, document) {
  'use strict';

  var CART_KEY = 'greenshop_cart';

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
  }

  function getCart() {
    try {
      var raw = localStorage.getItem(CART_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }

  function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartCount();
    document.dispatchEvent(new CustomEvent('mp:cart-updated', { detail: { cart: cart } }));
  }

  function cartCount(cart) {
    cart = cart || getCart();
    return cart.reduce(function (s, i) { return s + (parseInt(i.quantity, 10) || 0); }, 0);
  }

  function updateCartCount() {
    var total = cartCount();
    document.querySelectorAll('[data-mp-cart-count], #cartCount').forEach(function (el) {
      el.textContent = total > 99 ? '99+' : String(total);
      el.classList.toggle('is-empty', total < 1);
    });
  }

  function showToast(msg, type) {
    type = type || 'success';
    var host = document.getElementById('mpToastHost') || document.body;
    var toast = document.createElement('div');
    toast.className = 'mp-toast mp-toast--' + type;
    toast.setAttribute('role', 'status');
    toast.innerHTML = '<span>' + escapeHtml(msg) + '</span>';
    host.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('is-visible'); });
    setTimeout(function () {
      toast.classList.remove('is-visible');
      setTimeout(function () { toast.remove(); }, 280);
    }, 2600);
  }

  function addToCart(productId, storeId, storeName, name, price, image, evt) {
    if (evt) {
      evt.preventDefault();
      evt.stopPropagation();
    }
    var cart = getCart();
    var pid = parseInt(productId, 10);
    var sid = parseInt(storeId, 10);
    var idx = cart.findIndex(function (i) {
      return parseInt(i.product_id, 10) === pid && parseInt(i.store_id, 10) === sid;
    });
    if (idx >= 0) {
      cart[idx].quantity = (parseInt(cart[idx].quantity, 10) || 0) + 1;
    } else {
      cart.push({
        product_id: pid,
        store_id: sid,
        store_name: storeName || 'Store',
        name: name || 'Product',
        price: parseFloat(price) || 0,
        image: image || '',
        quantity: 1
      });
    }
    saveCart(cart);
    showToast('Added to cart');
  }

  function updateQuantity(storeId, productId, delta) {
    var cart = getCart();
    var pid = parseInt(productId, 10);
    var sid = parseInt(storeId, 10);
    var idx = cart.findIndex(function (i) {
      return parseInt(i.product_id, 10) === pid && parseInt(i.store_id, 10) === sid;
    });
    if (idx < 0) return;
    var next = (parseInt(cart[idx].quantity, 10) || 0) + delta;
    if (next <= 0) cart.splice(idx, 1);
    else cart[idx].quantity = next;
    saveCart(cart);
  }

  function removeItem(storeId, productId) {
    var cart = getCart().filter(function (i) {
      return !(parseInt(i.product_id, 10) === parseInt(productId, 10) && parseInt(i.store_id, 10) === parseInt(storeId, 10));
    });
    saveCart(cart);
    showToast('Item removed');
  }

  function clearCart() {
    localStorage.removeItem(CART_KEY);
    updateCartCount();
    document.dispatchEvent(new CustomEvent('mp:cart-updated', { detail: { cart: [] } }));
  }

  function productUrl(id) {
    var base = (window.MP_URLS && window.MP_URLS.home) ? window.MP_URLS.home.replace(/\/?$/, '') : 'marketplace';
    return base + '/product/' + id;
  }

  /* Header chrome */
  function initChrome() {
    var menuToggle = document.getElementById('mpMenuToggle');
    var menuClose = document.getElementById('mpMenuClose');
    var menu = document.getElementById('mpMobileMenu');
    var overlay = document.getElementById('mpDrawerOverlay');
    var searchToggle = document.getElementById('mpSearchToggle');
    var mobileSearch = document.getElementById('mpMobileSearch');

    function openMenu() {
      if (!menu) return;
      menu.hidden = false;
      if (overlay) overlay.hidden = false;
      document.body.classList.add('mp-drawer-open');
      if (menuToggle) menuToggle.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
      if (!menu) return;
      menu.hidden = true;
      if (overlay) overlay.hidden = true;
      document.body.classList.remove('mp-drawer-open');
      if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
    }

    if (menuToggle) menuToggle.addEventListener('click', openMenu);
    if (menuClose) menuClose.addEventListener('click', closeMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);

    if (searchToggle && mobileSearch) {
      searchToggle.addEventListener('click', function () {
        var open = mobileSearch.hasAttribute('hidden');
        if (open) {
          mobileSearch.removeAttribute('hidden');
          searchToggle.setAttribute('aria-expanded', 'true');
          var input = document.getElementById('mpSearchMobile');
          if (input) input.focus();
        } else {
          mobileSearch.setAttribute('hidden', '');
          searchToggle.setAttribute('aria-expanded', 'false');
        }
      });
    }

    document.querySelectorAll('.mp-drawer-nav a, .mp-drawer-cats a').forEach(function (a) {
      a.addEventListener('click', closeMenu);
    });
  }

  window.MPCart = {
    CART_KEY: CART_KEY,
    getCart: getCart,
    saveCart: saveCart,
    cartCount: cartCount,
    updateCartCount: updateCartCount,
    showToast: showToast,
    addToCart: addToCart,
    updateQuantity: updateQuantity,
    removeItem: removeItem,
    clearCart: clearCart,
    escapeHtml: escapeHtml,
    productUrl: productUrl
  };

  // Legacy globals used by existing inline handlers
  window.getCart = getCart;
  window.saveCart = saveCart;
  window.updateCartCountDisplay = updateCartCount;
  window.updateCartCount = updateCartCount;
  window.addToCart = addToCart;
  window.showToast = showToast;
  window.CART_KEY = CART_KEY;

  document.addEventListener('DOMContentLoaded', function () {
    updateCartCount();
    initChrome();
  });
})(window, document);
