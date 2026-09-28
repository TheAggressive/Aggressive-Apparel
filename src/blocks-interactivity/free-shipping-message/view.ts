/**
 * Free Shipping Message — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext } from '@wordpress/interactivity';
import {
  buildFreeShippingSegments,
  subscribeFreeShippingCartRefresh,
  type FreeShippingCartContext,
  type FreeShippingMessageSegment,
} from './cart-refresh';

store('aggressive-apparel/free-shipping-message', {
  state: {
    // Server twin: Free_Shipping::message_segments(), registered in render.php.
    get segments(): FreeShippingMessageSegment[] {
      const ctx = getContext<FreeShippingCartContext>();
      return buildFreeShippingSegments(ctx);
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
