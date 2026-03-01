/**
 * Sticky Add to Cart — Interactivity API Store
 *
 * Shows a fixed bar with product info and add-to-cart when the main
 * add-to-cart form scrolls out of the viewport.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';
import { matchVariation, setupFocusTrap } from '@aggressive-apparel/helpers';

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

// Focus trap cleanup reference.
let focusTrapCleanup = null;

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
    const value = el.value;
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

/**
 * Defer unlockScroll until the drawer slide-out transition finishes.
 *
 * @param {Element} drawer The drawer container element.
 */
function deferUnlock(drawer) {
  const panel = drawer.querySelector('.aa-sticky-cart__drawer-panel');
  let done = false;
  const finish = () => {
    if (done || state.isDrawerOpen) return;
    done = true;
    drawer.hidden = true;
    unlockScroll();
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
}

/* ---------------------------------------------------------------
 * Interactivity store
 * ------------------------------------------------------------- */

const { state, actions } = store('aggressive-apparel/sticky-add-to-cart', {
  state: {
    get ariaHidden() {
      return state.isVisible ? 'false' : 'true';
    },
    get isVariable() {
      return state.productType === 'variable';
    },
    get isAddDisabled() {
      if (state.isAdding || state.isBuyingNow) return true;
      // Variable products without matched variation can still be clicked
      // to open the drawer — not disabled.
      return false;
    },
    get buttonText() {
      if (state.productType === 'variable') return 'Select options';
      if (state.isAdding) return '…';
      if (state.isSuccess) return '✓';
      if (state.hasError) return 'Error';
      return 'Add to Cart';
    },
    get isDrawerAddDisabled() {
      if (state.isAdding || state.isBuyingNow) return true;
      if (state.productType === 'variable' && !state.matchedVariationId)
        return true;
      return false;
    },
    get isOnSale() {
      return !!state.regularPrice && state.regularPrice !== state.displayPrice;
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
    get buyNowLabel() {
      if (state.isBuyingNow) return 'Redirecting…';
      return 'Buy Now';
    },
    get hideDrawerSelection() {
      return state.drawerView !== 'selection';
    },
    get hideDrawerSuccess() {
      return state.drawerView !== 'success';
    },
  },

  actions: {
    addToCart() {
      if (state.isAdding || state.isBuyingNow) return;

      // Variable products: open the drawer first so the user can pick options.
      // Once the drawer is open the button inside it calls this same action,
      // so we only bail when the drawer still needs to be opened.
      if (state.productType === 'variable' && !state.isDrawerOpen) {
        actions.openDrawer();
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

      // Build the request body.
      const body = { id: itemId, quantity: state.quantity };

      // For variable products, include variation attributes so the
      // Store API can validate the selection.
      if (state.productType === 'variable' && state.matchedVariationId) {
        const matchedVar = state.variations.find(
          v => v.id === state.matchedVariationId
        );
        if (matchedVar && matchedVar.attributes) {
          body.variation = Object.entries(matchedVar.attributes)
            .filter(([, val]) => val)
            .map(([key, val]) => ({
              attribute: key.replace(/^attribute_/, ''),
              value: val,
            }));
        }
      }

      fetch(state.cartApiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Nonce: state.nonce,
        },
        body: JSON.stringify(body),
      })
        .then(res => {
          // Capture refreshed nonce for subsequent requests.
          const newNonce = res.headers.get('Nonce');
          if (newNonce) {
            state.nonce = newNonce;
          }

          if (!res.ok) {
            return res.json().then(err => {
              throw new Error(err.message || `HTTP ${res.status}`);
            });
          }
          return res.json();
        })
        .then(() => {
          state.isAdding = false;
          state.isSuccess = true;
          state.announcement = 'Added to cart';
          state.quantity = 1;

          // Dispatch on document.body with bubbles so WooCommerce
          // mini-cart block picks it up and refreshes.
          document.body.dispatchEvent(
            new CustomEvent('wc-blocks_added_to_cart', {
              bubbles: true,
            })
          );

          // Switch drawer to success view.
          if (wasDrawerOpen) {
            setTimeout(() => {
              state.isSuccess = false;
              state.drawerView = 'success';
            }, 600);
          } else {
            setTimeout(() => {
              state.isSuccess = false;
            }, 2000);
          }
        })
        .catch(() => {
          state.isAdding = false;
          state.hasError = true;
          state.announcement = 'Error adding to cart';

          setTimeout(() => {
            state.hasError = false;
            state.announcement = '';
          }, 3000);
        });
    },

    /**
     * Add item to cart and redirect to checkout.
     */
    buyNow() {
      if (state.isBuyingNow || state.isAdding) return;

      // Variable products: open drawer first if not already open.
      if (state.productType === 'variable' && !state.isDrawerOpen) {
        // Set flag so that when the drawer's Buy Now is clicked,
        // we know to redirect after adding.
        actions.openDrawer();
        return;
      }

      const itemId =
        state.productType === 'variable'
          ? state.matchedVariationId
          : state.productId;

      if (!itemId) return;

      state.isBuyingNow = true;
      state.hasError = false;

      const body = { id: itemId, quantity: state.quantity };

      if (state.productType === 'variable' && state.matchedVariationId) {
        const matchedVar = state.variations.find(
          v => v.id === state.matchedVariationId
        );
        if (matchedVar && matchedVar.attributes) {
          body.variation = Object.entries(matchedVar.attributes)
            .filter(([, val]) => val)
            .map(([key, val]) => ({
              attribute: key.replace(/^attribute_/, ''),
              value: val,
            }));
        }
      }

      fetch(state.cartApiUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Nonce: state.nonce,
        },
        body: JSON.stringify(body),
      })
        .then(res => {
          const newNonce = res.headers.get('Nonce');
          if (newNonce) {
            state.nonce = newNonce;
          }
          if (!res.ok) {
            return res.json().then(err => {
              throw new Error(err.message || `HTTP ${res.status}`);
            });
          }
          return res.json();
        })
        .then(() => {
          window.location.href = state.checkoutUrl;
        })
        .catch(() => {
          state.isBuyingNow = false;
          state.hasError = true;
          state.announcement = 'Error adding to cart';

          setTimeout(() => {
            state.hasError = false;
            state.announcement = '';
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
      const activeKeys = Object.keys(selected).filter(k => selected[k]);

      if (activeKeys.length === 0) {
        state.matchedVariationId = 0;
        state.drawerImage = state.originalDrawerImage;
        state.drawerImageAlt = state.originalDrawerImageAlt;
        return;
      }

      const match = matchVariation(state.variations, selected);

      if (match) {
        state.matchedVariationId = match.id;
        state.displayPrice = match.price;
        state.regularPrice = match.regularPrice || '';

        // Swap drawer image if the variation has its own image.
        if (match.image) {
          state.drawerImage = match.image;
          state.drawerImageAlt = match.imageAlt || state.originalDrawerImageAlt;
        }
      } else {
        state.matchedVariationId = 0;
        state.displayPrice = state.originalPrice;
        state.regularPrice = state.originalRegularPrice || '';

        // Restore original product image.
        state.drawerImage = state.originalDrawerImage;
        state.drawerImageAlt = state.originalDrawerImageAlt;
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

      lockScroll();

      // Sync pill button visuals with current selections.
      syncDrawerOptions();

      // Setup focus trap after drawer renders.
      requestAnimationFrame(() => {
        const panel = drawer.querySelector('.aa-sticky-cart__drawer-panel');
        if (panel) {
          focusTrapCleanup = setupFocusTrap(panel);
          const closeBtn = panel.querySelector('.aa-sticky-cart__drawer-close');
          closeBtn?.focus();
        }
      });
    },

    continueShopping() {
      state.drawerView = 'selection';
      actions.closeDrawer();
    },

    closeDrawer() {
      const drawer = document.querySelector('.aa-sticky-cart__drawer');
      if (!drawer) return;

      // Clean up focus trap.
      if (focusTrapCleanup) {
        focusTrapCleanup();
        focusTrapCleanup = null;
      }

      drawer.classList.remove('is-open');
      state.isDrawerOpen = false;
      state.drawerView = 'selection';

      // Sync all drawer selections to the main form now that the
      // drawer is closing (deferred to avoid triggering WooCommerce
      // navigation while the drawer is open).
      const selected = state.selectedAttrs;
      for (const attrName of Object.keys(selected)) {
        actions.syncToMainForm(attrName, selected[attrName]);
      }

      // Defer unlockScroll until the slide-out transition finishes.
      deferUnlock(drawer);
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
