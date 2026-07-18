/**
 * Frequently Bought Together — Interactivity API Store
 *
 * Checkbox-based product bundling with combined add-to-cart.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import type { InteractivityActions } from '../../types/interactivity-shared';

import { store } from '@wordpress/interactivity';

interface FbtItem {
  id: number;
  name: string;
  price: number;
  isSelected: boolean;
  isCurrent: boolean;
}

interface FbtI18n {
  adding?: string;
  added?: string;
  error?: string;
  addAll?: string;
  successAnnounce?: string;
  errorAnnounce?: string;
}

interface FbtState {
  // Server-injected (from wp_interactivity_state())
  currencyPrefix: string;
  currencySuffix: string;
  currencyMinorUnit: number;
  cartApiUrl: string;
  nonce: string;
  i18n?: FbtI18n;
  // Imperative state set in actions
  items: FbtItem[];
  totalPrice: number;
  selectedCount: number;
  isAdding: boolean;
  isSuccess: boolean;
  hasError: boolean;
  errorMessage: string;
  // Getters
  readonly formattedTotal: string;
  readonly buttonText: string;
  readonly announcement: string;
}

interface FbtStore {
  state: FbtState;
  actions: InteractivityActions;
}

const { state } = store<FbtStore>(
  'aggressive-apparel/frequently-bought-together',
  {
    state: {
      get formattedTotal(): string {
        const prefix: string = state.currencyPrefix || '$';
        return `${prefix}${state.totalPrice.toFixed(2)}`;
      },
      get buttonText(): string {
        const i18n = state.i18n ?? {};
        if (state.isAdding) return i18n.adding ?? 'Adding\u2026';
        if (state.isSuccess) return i18n.added ?? 'Added!';
        if (state.hasError) return i18n.error ?? 'Error \u2014 try again';
        const template = i18n.addAll ?? 'Add all %d items to cart';
        return template.replace('%d', String(state.selectedCount));
      },
      get announcement(): string {
        const i18n = state.i18n ?? {};
        if (state.isSuccess) {
          return (
            i18n.successAnnounce ?? 'All items have been added to your cart.'
          );
        }
        if (state.hasError) {
          return (
            i18n.errorAnnounce ?? 'There was an error adding items to cart.'
          );
        }
        return '';
      },
    },

    actions: {
      toggleItem(event: Event): void {
        const checkbox = event.target as HTMLInputElement;
        const productId = parseInt(checkbox.dataset.productId!, 10);

        const item: FbtItem | undefined = state.items.find(
          (i: FbtItem) => i.id === productId
        );
        if (!item || item.isCurrent) return;

        item.isSelected = checkbox.checked;

        // Recalculate total and count.
        let total = 0;
        let count = 0;
        state.items.forEach((i: FbtItem) => {
          if (i.isSelected) {
            total += i.price;
            count += 1;
          }
        });
        state.totalPrice = total;
        state.selectedCount = count;
      },

      async addAllToCart(): Promise<void> {
        if (state.isAdding || state.selectedCount === 0) return;

        state.isAdding = true;
        state.isSuccess = false;
        state.hasError = false;

        const selectedItems: FbtItem[] = state.items.filter(
          (i: FbtItem) => i.isSelected
        );

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
