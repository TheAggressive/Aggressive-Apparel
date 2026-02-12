/**
 * Frequently Bought Together — Interactivity API Store
 *
 * Checkbox-based product bundling with combined add-to-cart.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store } from '@wordpress/interactivity';

const { state, actions } = store(
  'aggressive-apparel/frequently-bought-together',
  {
    state: {
      get formattedTotal() {
        const prefix = state.currencyPrefix || '$';
        return `${prefix}${state.totalPrice.toFixed(2)}`;
      },
      get buttonText() {
        if (state.isAdding) return 'Adding…';
        if (state.isSuccess) return 'Added!';
        if (state.hasError) return 'Error — try again';
        return `Add all ${state.selectedCount} items to cart`;
      },
      get announcement() {
        if (state.isSuccess) return 'All items have been added to your cart.';
        if (state.hasError) return 'There was an error adding items to cart.';
        return '';
      },
    },

    actions: {
      toggleItem(event) {
        const checkbox = event.target;
        const productId = parseInt(checkbox.dataset.productId, 10);

        const item = state.items.find(i => i.id === productId);
        if (!item || item.isCurrent) return;

        item.isSelected = checkbox.checked;

        // Recalculate total and count.
        let total = 0;
        let count = 0;
        state.items.forEach(i => {
          if (i.isSelected) {
            total += i.price;
            count += 1;
          }
        });
        state.totalPrice = total;
        state.selectedCount = count;
      },

      async addAllToCart() {
        if (state.isAdding || state.selectedCount === 0) return;

        state.isAdding = true;
        state.isSuccess = false;
        state.hasError = false;

        const selectedItems = state.items.filter(i => i.isSelected);

        try {
          for (const item of selectedItems) {
            const res = await fetch(state.cartApiUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                Nonce: state.nonce,
              },
              body: JSON.stringify({ id: item.id, quantity: 1 }),
            });

            if (!res.ok) {
              throw new Error(`Failed to add ${item.name}`);
            }
          }

          state.isAdding = false;
          state.isSuccess = true;

          // Notify other components.
          document.dispatchEvent(
            new CustomEvent('wc-blocks_added_to_cart', {
              detail: { source: 'fbt' },
            })
          );

          setTimeout(() => {
            state.isSuccess = false;
          }, 3000);
        } catch {
          state.isAdding = false;
          state.hasError = true;

          setTimeout(() => {
            state.hasError = false;
          }, 4000);
        }
      },
    },
  }
);
