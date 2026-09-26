/**
 * Cart refresh helpers for free-shipping blocks.
 *
 * Singleton listener hub (no jQuery) shared by the message and bar blocks.
 *
 * @package Aggressive_Apparel
 */

import { withScope } from '@wordpress/interactivity';
import {
  emptyCartTotals,
  hasCartItemsCookie,
  parseCartTotals,
  type CartResponse,
  type CurrencyFormat,
  type FreeShippingMessageContext,
  type FreeShippingBarI18n,
  type ParsedCartTotals,
} from './cart-data';

export type {
  CartResponse,
  CurrencyFormat,
  ParsedCartTotals,
  FreeShippingMessageContext,
  FreeShippingMessageI18n,
  FreeShippingBarI18n,
} from './cart-data';

export {
  emptyCartTotals,
  hasCartItemsCookie,
  parseCartTotals,
  formatMoney,
  formatFreeShippingMessage,
  interpolateI18n,
} from './cart-data';

/** `threshold` 0 = no free shipping in this customer's zone (block hidden). */
export interface FreeShippingCartContext extends FreeShippingMessageContext {
  threshold: number;
  customThreshold: number;
  cartTotal: number;
  restBase: string;
}

export interface FreeShippingBarContext extends CurrencyFormat {
  threshold: number;
  customThreshold: number;
  cartTotal: number;
  percent: number;
  remaining: number;
  complete: boolean;
  restBase: string;
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

/** Receives the cart, or null when the cart is known to be empty. */
type CartRefreshHandler = (cart: CartResponse | null) => void;

const subscribers = new Set<CartRefreshHandler>();
let listenersBound = false;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
// Monotonic id of the latest cart fetch; older responses are discarded so a
// slow pre-mutation response can never overwrite a newer cart.
let latestFetchId = 0;
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
  if (typeof Request !== 'undefined' && input instanceof Request) {
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

  const fetchId = ++latestFetchId;

  void fetchCartTotals(hubRestBase).then(cart => {
    if (!cart || fetchId !== latestFetchId) {
      return;
    }

    subscribers.forEach(handler => {
      handler(cart);
    });
  });
}

/**
 * Sync from the cart only when it can hold something to correct.
 *
 * An empty cart (no items cookie) needs no request: the cart side is zero and
 * the server-rendered threshold is trusted. A page cache must already vary on
 * currency and geolocation for product prices, so that threshold is as
 * correct as the prices around it. This keeps anonymous page views off the
 * uncached Store API. Mutations always fetch (see the patched fetch/XHR).
 */
function syncSubscribers(): void {
  if (hasCartItemsCookie(document.cookie)) {
    scheduleCartRefresh();
    return;
  }

  subscribers.forEach(handler => {
    handler(null);
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

    // The hub's own cart read is a GET, which is never a mutation — so no
    // "internal fetch" flag is needed (a global one would swallow real
    // mutations that overlap it).
    if (isCartMutationRequest(input, init)) {
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
      syncSubscribers();
    }
  });

  patchFetchForCartMutations();
  patchXHRForCartMutations();
}

function fetchCartTotals(restBase: string): Promise<CartResponse | null> {
  return fetch(`${restBase}/cart`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Cart fetch failed');
      }
      return response.json() as Promise<CartResponse>;
    })
    .catch(() => null);
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

  // Initial sync: the server-rendered cart total can be stale (page caching,
  // history navigation). Debounced, so every instance hydrating in the same
  // tick still results in a single cart request — and none for empty carts.
  if (hasCartItemsCookie(document.cookie)) {
    scheduleCartRefresh();
  } else {
    handler(null);
  }

  return () => {
    subscribers.delete(handler);
  };
}

/**
 * Copy parsed progress onto a block context (shared by both blocks).
 */
function applyParsedTotals(
  ctx: FreeShippingCartContext | FreeShippingBarContext,
  parsed: ParsedCartTotals
): void {
  ctx.threshold = parsed.threshold;
  ctx.cartTotal = parsed.cartTotal;
  ctx.remaining = parsed.remaining;
  ctx.complete = parsed.complete;
  ctx.currencyMinorUnit = parsed.currencyMinorUnit;
  ctx.currencyPrefix = parsed.currencyPrefix;
  ctx.currencySuffix = parsed.currencySuffix;
  ctx.currencyDecimalSeparator = parsed.currencyDecimalSeparator;
  ctx.currencyThousandSeparator = parsed.currencyThousandSeparator;
}

function parseForContext(
  cart: CartResponse | null,
  ctx: FreeShippingCartContext | FreeShippingBarContext
): ParsedCartTotals | null {
  if (cart === null) {
    return emptyCartTotals(ctx.threshold, ctx);
  }

  return parseCartTotals(cart, ctx.threshold, ctx, ctx.customThreshold);
}

/**
 * Subscribe to cart changes and refresh free-shipping message context.
 *
 * Always subscribes, even when the server rendered a zero threshold: once
 * the cart holds items or the shipping address changes, the cart response
 * decides this customer's threshold and can reveal a hidden block.
 */
export function subscribeFreeShippingCartRefresh(
  ctx: FreeShippingCartContext
): () => void {
  const refresh = withScope((cart: CartResponse | null) => {
    const parsed = parseForContext(cart, ctx);
    if (parsed) {
      applyParsedTotals(ctx, parsed);
    }
  });

  return subscribeCartRefresh(ctx.restBase, refresh);
}

/**
 * Subscribe to cart changes and refresh free-shipping bar context.
 */
export function subscribeFreeShippingBarCartRefresh(
  ctx: FreeShippingBarContext
): () => void {
  const refresh = withScope((cart: CartResponse | null) => {
    const parsed = parseForContext(cart, ctx);
    if (!parsed) {
      return;
    }

    applyParsedTotals(ctx, parsed);
    ctx.percent = parsed.complete
      ? 100
      : parsed.threshold > 0
        ? Math.min(100, (parsed.cartTotal / parsed.threshold) * 100)
        : 0;
  });

  return subscribeCartRefresh(ctx.restBase, refresh);
}
