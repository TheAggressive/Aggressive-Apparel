/**
 * Free Shipping Message — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext } from '@wordpress/interactivity';
import {
  formatFreeShippingMessage,
  subscribeFreeShippingCartRefresh,
  type FreeShippingCartContext,
} from './cart-refresh';

store('aggressive-apparel/free-shipping-message', {
  state: {
    get message(): string {
      const ctx = getContext<FreeShippingCartContext>();
      return formatFreeShippingMessage(ctx);
    },
  },

  callbacks: {
    init(): (() => void) | void {
      const ctx = getContext<FreeShippingCartContext>();
      // Returning the unsubscribe lets the runtime clean up when the
      // element is removed (e.g. client-side navigation).
      return subscribeFreeShippingCartRefresh(ctx);
    },
  },
});
