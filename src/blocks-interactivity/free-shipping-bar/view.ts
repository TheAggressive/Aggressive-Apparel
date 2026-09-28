/**
 * Free Shipping Bar — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { store, getContext } from '@wordpress/interactivity';
import {
  buildFreeShippingSegments,
  type FreeShippingMessageSegment,
} from '../free-shipping-message/cart-data';
import {
  subscribeFreeShippingBarCartRefresh,
  type FreeShippingBarContext,
} from '../free-shipping-message/cart-refresh';

function progressPercent(ctx: FreeShippingBarContext): number {
  return Math.round(Math.min(100, ctx.percent) * 10) / 10;
}

store('aggressive-apparel/free-shipping-bar', {
  state: {
    get progressWidth(): string {
      const ctx = getContext<FreeShippingBarContext>();
      return `${progressPercent(ctx)}%`;
    },

    get progressValue(): number {
      const ctx = getContext<FreeShippingBarContext>();
      return progressPercent(ctx);
    },

    get isComplete(): boolean {
      const ctx = getContext<FreeShippingBarContext>();
      return ctx.complete;
    },

    // Server twin: Free_Shipping_Message::segments(), registered in render.php.
    get segments(): FreeShippingMessageSegment[] {
      const ctx = getContext<FreeShippingBarContext>();
      return buildFreeShippingSegments(ctx);
    },
  },

  callbacks: {
    init(): (() => void) | void {
      const ctx = getContext<FreeShippingBarContext>();
      // Returning the unsubscribe lets the runtime clean up when the
      // element is removed (e.g. client-side navigation).
      return subscribeFreeShippingBarCartRefresh(ctx);
    },
  },
});
