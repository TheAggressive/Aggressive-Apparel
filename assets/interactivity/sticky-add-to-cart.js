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

const ATTR_SELECT_SELECTORS = [
  'select[data-attribute_name]',
  'select[name^="attribute_"]',
].join(', ');

const ATTR_INPUT_SELECTORS = [
  'input[name^="attribute_"]:checked',
  'select[data-attribute_name]',
  'select[name^="attribute_"]',
].join(', ');

/**
 * Read all current attribute selections from a form element.
 *
 * @param {Element} form The form container.
 * @return {Object} Attribute name-value pairs.
 */
function readFormAttributes(form) {
  const attrs = {};
  // Selects (classic WC + block dropdown fallback).
  form.querySelectorAll(ATTR_SELECT_SELECTORS).forEach(sel => {
    const name = sel.getAttribute('data-attribute_name') || sel.name;
    if (name && sel.value) {
      attrs[name] = sel.value;
    }
  });
  // Radio pill inputs (block-based form).
  form.querySelectorAll('input[name^="attribute_"]:checked').forEach(input => {
    if (input.name && input.value) {
      attrs[input.name] = input.value;
    }
  });
  return attrs;
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
      if (state.productType === 'variable' && !state.matchedVariationId)
        return true;
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
  },

  actions: {
    addToCart() {
      if (state.isAdding || state.isAddDisabled) return;

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

          setTimeout(() => {
            state.isSuccess = false;
          }, 2000);
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

    selectAttribute(event) {
      const select = event.target;
      const attrName = select.dataset.attribute;
      const attrValue = select.value;

      const newAttrs = { ...state.selectedAttrs };
      if (attrValue) {
        newAttrs[attrName] = attrValue;
      } else {
        delete newAttrs[attrName];
      }
      state.selectedAttrs = newAttrs;

      // Find matching variation.
      actions.matchVariation();

      // Push change to the main product form.
      actions.syncToMainForm(attrName, attrValue);
    },

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
          state._syncing = false;
        });
      }
    },
  },
});
