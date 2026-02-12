/**
 * Back in Stock — Interactivity API Store
 *
 * Handles the email subscription form for out-of-stock products.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store, getContext } from '@wordpress/interactivity';

const { state } = store('aggressive-apparel/back-in-stock', {
  state: {
    get isNotSuccess() {
      return !state.isSuccess;
    },
    get isNotError() {
      return !state.hasError;
    },
    get successMessage() {
      return state._successMessage || '';
    },
  },

  actions: {
    async submit(event) {
      event.preventDefault();

      if (state.isSubmitting) return;

      const form = event.target;
      const email = form.querySelector('.aa-bis__input')?.value?.trim();
      const consent = form.querySelector(
        '.aa-bis__consent input[type="checkbox"]'
      )?.checked;

      if (!email) {
        state.hasError = true;
        state.errorMessage = 'Please enter a valid email address.';
        return;
      }

      if (!consent) {
        state.hasError = true;
        state.errorMessage = 'You must agree to receive the notification.';
        return;
      }

      const ctx = getContext();

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

        const json = await res.json();

        if (json.success) {
          state.isSuccess = true;
          state._successMessage =
            json.data?.message ||
            "We'll email you when this product is back in stock!";
        } else {
          state.hasError = true;
          state.errorMessage =
            json.data?.message || 'Something went wrong. Please try again.';
        }
      } catch {
        state.hasError = true;
        state.errorMessage = 'Something went wrong. Please try again.';
      } finally {
        state.isSubmitting = false;
      }
    },

    clearMessages() {
      state.isSuccess = false;
      state.hasError = false;
      state.errorMessage = '';
    },
  },
});
