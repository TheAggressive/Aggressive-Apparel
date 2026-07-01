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
    init(): void {
      const ctx = getContext<FreeShippingCartContext>();
      subscribeFreeShippingCartRefresh(ctx);
    },
  },
});
