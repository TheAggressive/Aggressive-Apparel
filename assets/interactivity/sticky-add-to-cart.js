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
        body: JSON.stringify({ id: itemId, quantity: 1 }),
      })
        .then(res => {
          if (!res.ok) throw new Error('Add to cart failed');
          return res.json();
        })
        .then(() => {
          state.isAdding = false;
          state.isSuccess = true;

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
  },

  callbacks: {
    init() {
      // Observe the main add-to-cart form.
      const target = document.querySelector(
        '.wc-block-add-to-cart-form, .wp-block-woocommerce-add-to-cart-with-options'
      );

      if (!target) return;

      const observer = new IntersectionObserver(
        ([entry]) => {
          state.isVisible = !entry.isIntersecting;
        },
        { threshold: 0 }
      );

      observer.observe(target);

      // Match default variation if set.
      if (
        state.productType === 'variable' &&
        Object.keys(state.selectedAttrs).length > 0
      ) {
        actions.matchVariation();
      }
    },
  },
});
