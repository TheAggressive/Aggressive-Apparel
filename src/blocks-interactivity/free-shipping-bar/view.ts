/**
 * Free Shipping Bar — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { store, getContext } from '@wordpress/interactivity';
import { interpolateI18n } from '../free-shipping-message/cart-data';
import {
  subscribeFreeShippingBarCartRefresh,
  type FreeShippingBarContext,
} from '../free-shipping-message/cart-refresh';

store('aggressive-apparel/free-shipping-bar', {
  state: {
    get progressWidth(): string {
      const ctx = getContext<FreeShippingBarContext>();
      return `${Math.min(100, ctx.percent).toFixed(1)}%`;
    },

    get isComplete(): boolean {
      const ctx = getContext<FreeShippingBarContext>();
      return ctx.complete;
    },

    get message(): string {
      const ctx = getContext<FreeShippingBarContext>();

      if (ctx.complete) {
        return ctx.i18n.complete;
      }

      const amount = `${ctx.currencyPrefix}${ctx.remaining.toFixed(ctx.currencyMinorUnit)}${ctx.currencySuffix}`;

      return interpolateI18n(ctx.i18n.progress, amount);
    },
  },

  callbacks: {
    init(): void {
      const ctx = getContext<FreeShippingBarContext>();
      subscribeFreeShippingBarCartRefresh(ctx);
    },
  },
});
