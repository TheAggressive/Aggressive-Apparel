/**
 * Sticky Add to Cart — Interactivity API Store
 *
 * Shows a fixed bar with product info and add-to-cart when the main
 * add-to-cart form scrolls out of the viewport.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store, getContext } from '@wordpress/interactivity';

/* ---------------------------------------------------------------
 * Main form selectors
 * ------------------------------------------------------------- */

const FORM_SELECTORS = [
  '.wc-block-add-to-cart-form',
  '.wp-block-woocommerce-add-to-cart-with-options',
  '.variations_form',
].join(', ');

const ATTR_SELECTORS = [
  'select[data-attribute_name]',
  'select[name^="attribute_"]',
  'input[name^="attribute_"]:checked',
].join(', ');

/**
 * Read all current attribute selections from a form element.
 *
 * @param {Element} form The form container.
 * @return {Object} Attribute name-value pairs.
 */
function readFormAttributes(form) {
  const attrs = {};
  form.querySelectorAll(ATTR_SELECTORS).forEach(el => {
    const name = el.getAttribute('data-attribute_name') || el.name || '';
    const value =
      el.type === 'radio' || el.type === 'checkbox' ? el.value : el.value;
    if (name && value) {
      attrs[name] = value;
    }
  });
  return attrs;
}

/**
 * Sync drawer pill button visual states with current selectedAttrs.
 */
function syncDrawerOptions() {
  document.querySelectorAll('.aa-sticky-cart__drawer-option').forEach(btn => {
    const attrName = btn.dataset.attribute;
    const attrValue = btn.dataset.value;
    const isSelected = state.selectedAttrs[attrName] === attrValue;
    btn.classList.toggle('is-selected', isSelected);

    // Set --swatch-color for the color swatch selection ring.
    if (
      btn.classList.contains('is-color-swatch') &&
      btn.style.backgroundColor
    ) {
      btn.style.setProperty('--swatch-color', btn.style.backgroundColor);
    }
  });
}

/* ---------------------------------------------------------------
 * Interactivity store
 * ------------------------------------------------------------- */

const { state, actions } = store('aggressive-apparel/sticky-add-to-cart', {
  state: {
    get ariaHidden() {
      return state.isVisible ? 'false' : 'true';
    },
    get isAddDisabled() {
      if (state.isAdding) return true;
      // Variable products without matched variation can still be clicked
      // to open the drawer — not disabled.
      return false;
    },
    get buttonText() {
      if (state.isAdding) return '…';
      if (state.isSuccess) return '✓';
      if (state.hasError) return 'Error';
      if (state.productType === 'variable' && !state.matchedVariationId) {
        return 'Select options';
      }
      return 'Add to Cart';
    },
    get isDrawerAddDisabled() {
      if (state.isAdding) return true;
      if (state.productType === 'variable' && !state.matchedVariationId)
        return true;
      return false;
    },
    get drawerButtonText() {
      if (state.isAdding) return '…';
      if (state.isSuccess) return '✓ Added';
      if (state.hasError) return 'Error';
      if (state.productType === 'variable' && !state.matchedVariationId) {
        return 'Select options';
      }
      return 'Add to Cart';
    },
  },

  actions: {
    addToCart() {
      if (state.isAdding) return;

      // Variable product without matched variation → open drawer.
      if (state.productType === 'variable' && !state.matchedVariationId) {
        if (!state.isDrawerOpen) {
          actions.openDrawer();
        }
        return;
      }

      state.isAdding = true;
      state.isSuccess = false;
      state.hasError = false;

      const itemId =
        state.productType === 'variable'
          ? state.matchedVariationId
          : state.productId;

      if (!itemId) {
        state.isAdding = false;
        return;
      }

      const wasDrawerOpen = state.isDrawerOpen;

      fetch(state.cartApiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Nonce: state.nonce,
        },
        body: JSON.stringify({ id: itemId, quantity: state.quantity }),
      })
        .then(res => {
          if (!res.ok) throw new Error('Add to cart failed');
          return res.json();
        })
        .then(() => {
          state.isAdding = false;
          state.isSuccess = true;
          state.quantity = 1;

          // Dispatch event for other components (mini-cart, bottom nav).
          document.dispatchEvent(
            new CustomEvent('wc-blocks_added_to_cart', {
              detail: { productId: itemId },
            })
          );

          // Close drawer after brief success feedback, or just reset.
          const delay = wasDrawerOpen ? 1200 : 2000;
          setTimeout(() => {
            state.isSuccess = false;
            if (wasDrawerOpen && state.isDrawerOpen) {
              actions.closeDrawer();
            }
          }, delay);
        })
        .catch(() => {
          state.isAdding = false;
          state.hasError = true;

          setTimeout(() => {
            state.hasError = false;
          }, 3000);
        });
    },

    /* -----------------------------------------------------------
     * Quantity controls
     * --------------------------------------------------------- */

    incrementQty(event) {
      if (event) event.preventDefault();
      state.quantity = state.quantity + 1;
    },

    decrementQty(event) {
      if (event) event.preventDefault();
      if (state.quantity > 1) {
        state.quantity = state.quantity - 1;
      }
    },

    setQuantity(event) {
      const value = parseInt(event.target.value, 10);
      if (!isNaN(value) && value >= 1) {
        state.quantity = value;
      } else {
        event.target.value = state.quantity;
      }
    },

    /* -----------------------------------------------------------
     * Variant selection + sync
     * --------------------------------------------------------- */

    matchVariation() {
      const selected = state.selectedAttrs;
      const selectedKeys = Object.keys(selected);

      if (selectedKeys.length === 0) {
        state.matchedVariationId = 0;
        return;
      }

      const match = state.variations.find(v => {
        return selectedKeys.every(key => {
          const normalizedKey = key.startsWith('attribute_')
            ? key
            : `attribute_${key}`;
          const vAttrValue = v.attributes[normalizedKey] || '';
          // Empty value in variation means "any".
          return vAttrValue === '' || vAttrValue === selected[key];
        });
      });

      if (match) {
        state.matchedVariationId = match.id;
        state.displayPrice = match.price;
      } else {
        state.matchedVariationId = 0;
      }
    },

    /**
     * Push a single attribute change from sticky bar → main form.
     *
     * @param {string} attrName  Attribute name (e.g. "attribute_pa_color").
     * @param {string} attrValue Selected value.
     */
    syncToMainForm(attrName, attrValue) {
      if (state._syncing) return;
      state._syncing = true;

      const form = document.querySelector(FORM_SELECTORS);
      if (form) {
        // Try select dropdown first (classic WC + block dropdown).
        const sel = form.querySelector(
          `select[data-attribute_name="${attrName}"], select[name="${attrName}"]`
        );
        if (sel) {
          sel.value = attrValue;
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          // Try radio pill input (block-based form).
          const radio = form.querySelector(
            `input[name="${attrName}"][value="${attrValue}"]`
          );
          if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      }

      state._syncing = false;
    },

    /* -----------------------------------------------------------
     * Drawer (variant bottom sheet)
     * --------------------------------------------------------- */

    openDrawer() {
      if (state.isDrawerOpen) return;

      const drawer = document.querySelector('.aa-sticky-cart__drawer');
      if (!drawer) return;

      drawer.hidden = false;
      void drawer.offsetHeight; // force reflow for transition
      drawer.classList.add('is-open');
      state.isDrawerOpen = true;

      // Lock page scroll.
      document.documentElement.style.overflow = 'hidden';

      // Sync pill button visuals with current selections.
      syncDrawerOptions();
    },

    closeDrawer() {
      const drawer = document.querySelector('.aa-sticky-cart__drawer');
      if (!drawer) return;

      drawer.classList.remove('is-open');
      state.isDrawerOpen = false;

      // Sync all drawer selections to the main form now that the
      // drawer is closing (deferred to avoid triggering WooCommerce
      // navigation while the drawer is open).
      const selected = state.selectedAttrs;
      for (const attrName of Object.keys(selected)) {
        actions.syncToMainForm(attrName, selected[attrName]);
      }

      // Wait for slide-out transition, then hide + unlock scroll.
      const panel = drawer.querySelector('.aa-sticky-cart__drawer-panel');
      let done = false;
      const finish = () => {
        if (done) return;
        done = true;
        drawer.hidden = true;
        document.documentElement.style.overflow = '';
      };

      if (panel) {
        panel.addEventListener(
          'transitionend',
          e => {
            if (e.propertyName === 'transform') finish();
          },
          { once: true }
        );
      }

      // Safety fallback for reduced-motion or missed events.
      setTimeout(finish, 350);
    },

    selectDrawerOption(event) {
      event.stopPropagation();
      const button = event.target.closest('.aa-sticky-cart__drawer-option');
      if (!button) return;

      const attrName = button.dataset.attribute;
      const clickedValue = button.dataset.value;

      // Toggle: clicking the same option deselects it.
      const newAttrs = { ...state.selectedAttrs };
      if (newAttrs[attrName] === clickedValue) {
        delete newAttrs[attrName];
      } else {
        newAttrs[attrName] = clickedValue;
      }
      state.selectedAttrs = newAttrs;

      // Update pill button visuals.
      syncDrawerOptions();

      // Match variation.
      actions.matchVariation();

      // Do NOT sync to main form here — dispatching change events
      // on the WooCommerce form triggers its own variation handler
      // which can cause page navigation. Sync happens on close.
    },
  },

  callbacks: {
    init() {
      // Observe the main add-to-cart form.
      const form = document.querySelector(FORM_SELECTORS);

      if (!form) return;

      const observer = new IntersectionObserver(
        ([entry]) => {
          state.isVisible = !entry.isIntersecting;
        },
        { threshold: 0 }
      );

      observer.observe(form);

      // Match default variation if set.
      if (
        state.productType === 'variable' &&
        Object.keys(state.selectedAttrs).length > 0
      ) {
        actions.matchVariation();
      }

      /* ---------------------------------------------------------
       * Variant sync: main form → sticky bar
       * ------------------------------------------------------- */

      if (state.productType === 'variable') {
        form.addEventListener('change', e => {
          if (state._syncing) return;

          const el = e.target;
          const name = el.name || el.getAttribute('data-attribute_name') || '';
          if (!name.startsWith('attribute_')) return;

          state._syncing = true;
          state.selectedAttrs = readFormAttributes(form);
          actions.matchVariation();

          // Sync drawer pills if open.
          if (state.isDrawerOpen) {
            syncDrawerOptions();
          }

          state._syncing = false;
        });
      }

      /* ---------------------------------------------------------
       * Escape key closes drawer
       * ------------------------------------------------------- */

      document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && state.isDrawerOpen) {
          actions.closeDrawer();
        }
      });
    },
  },
});
