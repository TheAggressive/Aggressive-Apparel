/**
 * Cart refresh helpers for free-shipping blocks.
 *
 * Singleton listener hub (no jQuery) shared by the message and bar blocks.
 *
 * @package Aggressive_Apparel
 */

import { withScope } from '@wordpress/interactivity';
import {
  parseCartTotals,
  type CartResponse,
  type FreeShippingMessageContext,
  type FreeShippingBarI18n,
} from './cart-data';

export type {
  CartResponse,
  ParsedCartTotals,
  FreeShippingMessageContext,
  FreeShippingMessageI18n,
  FreeShippingBarI18n,
} from './cart-data';

export {
  parseCartTotals,
  formatFreeShippingMessage,
  interpolateI18n,
} from './cart-data';

export interface FreeShippingCartContext extends FreeShippingMessageContext {
  threshold: number;
  cartTotal: number;
  restBase: string;
}

export interface FreeShippingBarContext {
  threshold: number;
  cartTotal: number;
  percent: number;
  remaining: number;
  complete: boolean;
  restBase: string;
  currencyPrefix: string;
  currencySuffix: string;
  currencyMinorUnit: number;
  i18n: FreeShippingBarI18n;
}

// Matches every request that can change cart totals. WooCommerce Blocks
// routes mini-cart edits (quantity change, remove item) through the Store
// API *batch* endpoint, so it must be matched alongside /cart.
const CART_MUTATION_URL =
  /\/wc\/store\/v1\/(?:cart|batch)(?:\/|$|\?)|wc-ajax=(?:add_to_cart|remove_from_cart|update_cart|apply_coupon|remove_coupon|get_refreshed_fragments)/;

const CART_EVENTS = [
  'wc-blocks_added_to_cart',
  'wc-blocks_removed_from_cart',
  'wc-blocks_store_sync_required',
  'added_to_cart',
  'removed_from_cart',
] as const;

const REFRESH_DEBOUNCE_MS = 150;

type CartRefreshHandler = (cart: CartResponse) => void;

const subscribers = new Set<CartRefreshHandler>();
let listenersBound = false;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let isInternalCartFetch = false;
let hubRestBase = '';

function resolveRequestUrl(input: RequestInfo | URL): string {
  if (typeof input === 'string') {
    return input;
  }
  if (input instanceof URL) {
    return input.href;
  }
  return input.url;
}

function resolveRequestMethod(
  input: RequestInfo | URL,
  init?: RequestInit
): string {
  if (init?.method) {
    return init.method.toUpperCase();
  }
  if (input instanceof Request) {
    return input.method.toUpperCase();
  }
  return 'GET';
}

function isCartMutationRequest(
  input: RequestInfo | URL,
  init?: RequestInit
): boolean {
  const url = resolveRequestUrl(input);
  if (!CART_MUTATION_URL.test(url)) {
    return false;
  }

  const method = resolveRequestMethod(input, init);
  if (url.includes('get_refreshed_fragments')) {
    return true;
  }

  return method !== 'GET';
}

/**
 * Fetch the cart once and fan the same response out to every subscriber.
 *
 * A single shared fetch (instead of one per block instance) guarantees all
 * occurrences on the page update together from identical data.
 */
function refreshSubscribers(): void {
  if ('' === hubRestBase || subscribers.size === 0) {
    return;
  }

  void fetchCartTotals(hubRestBase).then(cart => {
    if (!cart) {
      return;
    }

    subscribers.forEach(handler => {
      handler(cart);
    });
  });
}

function scheduleCartRefresh(): void {
  if (debounceTimer !== null) {
    clearTimeout(debounceTimer);
  }

  debounceTimer = setTimeout(() => {
    debounceTimer = null;
    refreshSubscribers();
  }, REFRESH_DEBOUNCE_MS);
}

function patchFetchForCartMutations(): void {
  const nativeFetch = window.fetch.bind(window);

  window.fetch = (
    input: RequestInfo | URL,
    init?: RequestInit
  ): Promise<Response> => {
    const responsePromise = nativeFetch(input, init);

    if (!isInternalCartFetch && isCartMutationRequest(input, init)) {
      void responsePromise.then(
        response => {
          if (response.ok) {
            scheduleCartRefresh();
          }
        },
        () => {}
      );
    }

    return responsePromise;
  };
}

function patchXHRForCartMutations(): void {
  const xhrProto = XMLHttpRequest.prototype;
  const nativeOpen = xhrProto.open;
  const nativeSend = xhrProto.send;

  xhrProto.open = function patchedOpen(
    this: XMLHttpRequest & {
      _aaCartMethod?: string;
      _aaCartUrl?: string;
    },
    method: string,
    url: string | URL,
    async: boolean = true,
    username?: string | null,
    password?: string | null
  ): void {
    this._aaCartMethod = method;
    this._aaCartUrl = typeof url === 'string' ? url : url.href;
    return nativeOpen.call(this, method, url, async, username, password);
  };

  xhrProto.send = function patchedSend(
    this: XMLHttpRequest & {
      _aaCartMethod?: string;
      _aaCartUrl?: string;
    },
    ...args: [Document | XMLHttpRequestBodyInit | null | undefined]
  ): void {
    this.addEventListener('load', () => {
      if (
        this._aaCartUrl &&
        this._aaCartMethod &&
        isCartMutationRequest(this._aaCartUrl, {
          method: this._aaCartMethod,
        }) &&
        this.status >= 200 &&
        this.status < 300
      ) {
        scheduleCartRefresh();
      }
    });

    return nativeSend.apply(this, args);
  };
}

function bindGlobalCartListeners(): void {
  if (listenersBound) {
    return;
  }

  listenersBound = true;

  CART_EVENTS.forEach(event => {
    document.addEventListener(event, scheduleCartRefresh);
    window.addEventListener(event, scheduleCartRefresh);
  });

  window.addEventListener('pageshow', event => {
    if (event.persisted) {
      scheduleCartRefresh();
    }
  });

  patchFetchForCartMutations();
  patchXHRForCartMutations();
}

function fetchCartTotals(restBase: string): Promise<CartResponse | null> {
  isInternalCartFetch = true;

  return fetch(`${restBase}/cart`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Cart fetch failed');
      }
      return response.json() as Promise<CartResponse>;
    })
    .catch(() => null)
    .finally(() => {
      isInternalCartFetch = false;
    });
}

function subscribeCartRefresh(
  restBase: string,
  handler: CartRefreshHandler
): () => void {
  bindGlobalCartListeners();

  if ('' === hubRestBase && restBase) {
    hubRestBase = restBase;
  }

  subscribers.add(handler);

  // Initial sync: the server-rendered amount can be stale (page caching,
  // history navigation). Debounced, so every instance hydrating in the same
  // tick still results in a single cart request.
  scheduleCartRefresh();

  return () => {
    subscribers.delete(handler);
  };
}

/**
 * Subscribe to cart changes and refresh free-shipping message context.
 */
export function subscribeFreeShippingCartRefresh(
  ctx: FreeShippingCartContext
): () => void {
  if (ctx.threshold <= 0) {
    return () => {};
  }

  const refresh = withScope((cart: CartResponse) => {
    const parsed = parseCartTotals(cart, ctx.threshold, {
      currencyMinorUnit: ctx.currencyMinorUnit,
      currencyPrefix: ctx.currencyPrefix,
      currencySuffix: ctx.currencySuffix,
    });

    if (!parsed) {
      return;
    }

    ctx.currencyMinorUnit = parsed.currencyMinorUnit;
    ctx.currencyPrefix = parsed.currencyPrefix;
    ctx.currencySuffix = parsed.currencySuffix;
    ctx.cartTotal = parsed.cartTotal;
    ctx.remaining = parsed.remaining;
    ctx.complete = parsed.complete;
  });

  return subscribeCartRefresh(ctx.restBase, refresh);
}

/**
 * Subscribe to cart changes and refresh free-shipping bar context.
 */
export function subscribeFreeShippingBarCartRefresh(
  ctx: FreeShippingBarContext
): () => void {
  if (ctx.threshold <= 0) {
    return () => {};
  }

  const refresh = withScope((cart: CartResponse) => {
    const parsed = parseCartTotals(cart, ctx.threshold, {
      currencyMinorUnit: ctx.currencyMinorUnit ?? 2,
      currencyPrefix: ctx.currencyPrefix ?? '$',
      currencySuffix: ctx.currencySuffix ?? '',
    });

    if (!parsed) {
      return;
    }

    ctx.cartTotal = parsed.cartTotal;
    ctx.remaining = parsed.remaining;
    ctx.complete = parsed.complete;
    ctx.percent = Math.min(100, (parsed.cartTotal / ctx.threshold) * 100);
  });

  return subscribeCartRefresh(ctx.restBase, refresh);
}
