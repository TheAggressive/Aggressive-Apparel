/**
 * WooCommerce Product Scripts
 *
 * @package
 */

/**
 * Initialize product page features
 */
document.addEventListener('DOMContentLoaded', () => {
  if (!document.body.classList.contains('single-product')) {
    return;
  }

  initProductGallery();
  initProductTabs();
  initQuantityButtons();
});

/**
 * Initialize product gallery enhancements
 */
function initProductGallery() {
  const gallery = document.querySelector('.woocommerce-product-gallery');
  if (!gallery) {
    return;
  }

  // Add custom gallery interactions here
  console.log('Product gallery initialized');
}

/**
 * Initialize product tabs
 */
function initProductTabs() {
  const tabs = document.querySelectorAll('.woocommerce-tabs .tabs li a');
  if (!tabs.length) {
    return;
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', e => {
      e.preventDefault();
      const targetId = tab.getAttribute('href');
      const target = document.querySelector(targetId);

      if (!target) {
        return;
      }

      // Remove active class from all tabs
      tabs.forEach(t => t.parentElement.classList.remove('active'));
      document.querySelectorAll('.woocommerce-tabs .panel').forEach(panel => {
        panel.style.display = 'none';
      });

      // Add active class to current tab
      tab.parentElement.classList.add('active');
      target.style.display = 'block';
    });
  });
}

/**
 * Initialize quantity buttons
 */
function initQuantityButtons() {
  const quantityInputs = document.querySelectorAll('input.qty');

  quantityInputs.forEach(input => {
    const wrapper = input.closest('.quantity');
    if (!wrapper) {
      return;
    }

    // Create plus button
    const plusButton = document.createElement('button');
    plusButton.type = 'button';
    plusButton.className = 'qty-plus';
    plusButton.textContent = '+';
    plusButton.setAttribute('aria-label', 'Increase quantity');

    // Create minus button
    const minusButton = document.createElement('button');
    minusButton.type = 'button';
    minusButton.className = 'qty-minus';
    minusButton.textContent = '-';
    minusButton.setAttribute('aria-label', 'Decrease quantity');

    // Insert buttons
    wrapper.appendChild(minusButton);
    wrapper.appendChild(plusButton);

    // Plus button click
    plusButton.addEventListener('click', () => {
      const max = parseFloat(input.getAttribute('max')) || Infinity;
      const current = parseFloat(input.value) || 0;
      const step = parseFloat(input.getAttribute('step')) || 1;

      if (current < max) {
        input.value = current + step;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Minus button click
    minusButton.addEventListener('click', () => {
      const min = parseFloat(input.getAttribute('min')) || 0;
      const current = parseFloat(input.value) || 0;
      const step = parseFloat(input.getAttribute('step')) || 1;

      if (current > min) {
        input.value = current - step;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
}
