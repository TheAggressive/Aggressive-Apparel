/**
 * Back in Stock — Interactivity API Store
 *
 * Handles the email subscription form for out-of-stock products.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import type { InteractivityActions } from '../../types/interactivity-shared';

import { store, getContext } from '@wordpress/interactivity';

interface BackInStockContext {
  productId: string;
}

interface AjaxResponse {
  success: boolean;
  data?: {
    message?: string;
  };
}

interface BackInStockI18n {
  invalidEmail?: string;
  consentRequired?: string;
  successFallback?: string;
  errorFallback?: string;
}

interface BackInStockState {
  // Server-injected (from wp_interactivity_state())
  nonce: string;
  ajaxUrl: string;
  productId: string;
  i18n?: BackInStockI18n;
  // Imperative state set in actions
  isSubmitting: boolean;
  isSuccess: boolean;
  hasError: boolean;
  errorMessage: string;
  _successMessage: string;
  // Getters
  readonly isNotSuccess: boolean;
  readonly isNotError: boolean;
  readonly successMessage: string;
}

interface BackInStockStore {
  state: BackInStockState;
  actions: InteractivityActions;
}

const { state } = store<BackInStockStore>('aggressive-apparel/back-in-stock', {
  state: {
    get isNotSuccess(): boolean {
      return !state.isSuccess;
    },
    get isNotError(): boolean {
      return !state.hasError;
    },
    get successMessage(): string {
      return state._successMessage || '';
    },
  },

  actions: {
    async submit(event: Event): Promise<void> {
      event.preventDefault();

      if (state.isSubmitting) return;

      const form = event.target as HTMLFormElement;
      const email = (
        form.querySelector('.aa-bis__input') as HTMLInputElement | null
      )?.value?.trim();
      const consent = (
        form.querySelector(
          '.aa-bis__consent input[type="checkbox"]'
        ) as HTMLInputElement | null
      )?.checked;

      const i18n = state.i18n ?? {};

      if (!email) {
        state.hasError = true;
        state.errorMessage =
          i18n.invalidEmail ?? 'Please enter a valid email address.';
        return;
      }

      if (!consent) {
        state.hasError = true;
        state.errorMessage =
          i18n.consentRequired ?? 'You must agree to receive the notification.';
        return;
      }

      const ctx = getContext<BackInStockContext>();

      state.isSubmitting = true;
      state.isSuccess = false;
      state.hasError = false;
      state.errorMessage = '';

      const body = new FormData();
      body.append('action', 'aa_stock_subscribe');
      body.append('nonce', state.nonce);
      body.append('email', email);
      body.append('product_id', ctx.productId);
      body.append('consent', '1');

      try {
        const res = await fetch(state.ajaxUrl, {
          method: 'POST',
          body,
        });

        const json: AjaxResponse = await res.json();

        if (json.success) {
          state.isSuccess = true;
          state._successMessage =
            json.data?.message ||
            i18n.successFallback ||
            "We'll email you when this product is back in stock!";
        } else {
          state.hasError = true;
          state.errorMessage =
            json.data?.message ||
            i18n.errorFallback ||
            'Something went wrong. Please try again.';
        }
      } catch {
        state.hasError = true;
        state.errorMessage =
          i18n.errorFallback || 'Something went wrong. Please try again.';
      } finally {
        state.isSubmitting = false;
      }
    },

    clearMessages(): void {
      state.isSuccess = false;
      state.hasError = false;
      state.errorMessage = '';
    },
  },
});
