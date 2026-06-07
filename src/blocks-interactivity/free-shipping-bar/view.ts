/**
 * Free Shipping Bar — Interactivity API Store.
 *
 * Updates the progress bar live when the cart changes by fetching from
 * the WooCommerce Store API whenever cart events fire.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { store, getContext, withScope } from '@wordpress/interactivity';

interface FreeShippingContext {
  threshold: number;
  cartTotal: number;
  percent: number;
  remaining: number;
  complete: boolean;
  restBase: string;
}

interface CartTotals {
  total_items: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
}

interface CartResponse {
  totals?: CartTotals;
}

store('aggressive-apparel/free-shipping-bar', {
  state: {
    get progressWidth(): string {
      const ctx = getContext<FreeShippingContext>();
      return `${Math.min(100, ctx.percent).toFixed(1)}%`;
    },

    get isComplete(): boolean {
      const ctx = getContext<FreeShippingContext>();
      return ctx.complete;
    },

    get message(): string {
      const ctx = getContext<FreeShippingContext>();
      if (ctx.complete) {
        return "You've unlocked free shipping!";
      }
      const prefix = '$';
      return `You're ${prefix}${ctx.remaining.toFixed(2)} away from free shipping!`;
    },
  },

  callbacks: {
    init(): void {
      const ctx = getContext<FreeShippingContext>();
      if (ctx.threshold <= 0) return;

      const refresh = withScope(() => {
        fetch(`${ctx.restBase}/cart`)
          .then(r => {
            if (!r.ok) throw new Error('Cart fetch failed');
            return r.json();
          })
          .then(
            withScope((cart: CartResponse) => {
              const totals = cart?.totals;
              if (!totals?.total_items) return;
              const minorUnit = totals.currency_minor_unit ?? 2;
              const divisor = Math.pow(10, minorUnit);
              const total = parseInt(totals.total_items, 10) / divisor;
              ctx.cartTotal = total;
              ctx.remaining = Math.max(0, ctx.threshold - total);
              ctx.percent = Math.min(100, (total / ctx.threshold) * 100);
              ctx.complete = ctx.remaining <= 0;
            })
          )
          .catch(() => {});
      });

      document.addEventListener(
        'wc-blocks_added_to_cart',
        refresh as EventListener
      );
      document.addEventListener(
        'wc-blocks_removed_from_cart',
        refresh as EventListener
      );
      document.addEventListener('added_to_cart', refresh as EventListener);
      document.addEventListener('removed_from_cart', refresh as EventListener);
    },
  },
});
