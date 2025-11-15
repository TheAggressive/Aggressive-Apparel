/**
 * WooCommerce Cart Scripts
 *
 * @package
 */

/* global aggressiveApparelData */

/**
 * Initialize cart functionality
 */
document.addEventListener('DOMContentLoaded', () => {
  if (
    typeof aggressiveApparelData === 'undefined' ||
    !aggressiveApparelData.isWooCommerce
  ) {
    return;
  }

  initMiniCart();
  updateCartFragments();
});

/**
 * Initialize mini cart
 */
function initMiniCart() {
  const cartToggle = document.querySelector('.mini-cart-toggle');
  const miniCart = document.querySelector('.mini-cart');

  if (!cartToggle || !miniCart) {
    return;
  }

  cartToggle.addEventListener('click', e => {
    e.preventDefault();
    miniCart.classList.toggle('is-open');
    document.body.classList.toggle('mini-cart-open');
  });

  // Close on outside click
  document.addEventListener('click', e => {
    if (!miniCart.contains(e.target) && !cartToggle.contains(e.target)) {
      miniCart.classList.remove('is-open');
      document.body.classList.remove('mini-cart-open');
    }
  });

  // Close on escape key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && miniCart.classList.contains('is-open')) {
      miniCart.classList.remove('is-open');
      document.body.classList.remove('mini-cart-open');
    }
  });
}

/**
 * Update cart fragments on add to cart
 */
function updateCartFragments() {
  // Listen for WooCommerce's custom 'added_to_cart' event
  document.body.addEventListener('added_to_cart', () => {
    // WooCommerce stores fragments in a global variable after adding to cart
    if (typeof wc_cart_fragments_params !== 'undefined') {
      // Trigger WooCommerce's fragment refresh
      const refreshFragments = new Event('wc_fragment_refresh');
      document.body.dispatchEvent(refreshFragments);
    }

    // Show mini cart
    const miniCart = document.querySelector('.mini-cart');
    if (miniCart) {
      miniCart.classList.add('is-open');
      document.body.classList.add('mini-cart-open');
    }
  });
}
