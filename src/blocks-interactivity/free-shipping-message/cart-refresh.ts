/**
 * Cart refresh helpers for free-shipping blocks.
 *
 * Singleton listener hub (no jQuery) shared by the message and bar blocks.
 *
 * @package Aggressive_Apparel
 */

import { withScope } from '@wordpress/interactivity';
import {
  cartFromMutationBody,
  emptyCartTotals,
  hasCartItemsCookie,
  parseCartTotals,
  type CartResponse,
  type FreeShippingMessageContext,
  type ParsedCartTotals,
} from './cart-data';

export type {
  CartResponse,
  CurrencyFormat,
  ParsedCartTotals,
  FreeShippingMessageContext,
  FreeShippingMessageI18n,
  FreeShippingMessageSegment,
} from './cart-data';

export {
  cartFromMutationBody,
  emptyCartTotals,
  hasCartItemsCookie,
  parseCartTotals,
  formatMoney,
  buildFreeShippingSegments,
  formatFreeShippingMessage,
} from './cart-data';

/** `threshold` 0 = no free shipping in this customer's zone (block hidden). */
export interface FreeShippingCartContext extends FreeShippingMessageContext {
  threshold: number;
  customThreshold: number;
  cartTotal: number;
  restBase: string;
}

/** The bar shares the message's wording, so its context is a superset. */
export interface FreeShippingBarContext extends FreeShippingCartContext {
  percent: number;
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

// WooCommerce's cart events trail the mutation response they announce; a cart
// published from that response within this window makes the refetch redundant.
const FRESH_CART_MS = 1000;

/** Receives the cart, or null when the cart is known to be empty. */
type CartRefreshHandler = (cart: CartResponse | null) => void;

const subscribers = new Set<CartRefreshHandler>();
let listenersBound = false;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
// Monotonic id of the latest cart fetch; older responses are discarded so a
// slow pre-mutation response can never overwrite a newer cart.
let latestFetchId = 0;
let hubRestBase = '';
// Mutations are numbered when sent, so a slow response to an older mutation
// can never overwrite the cart from a newer one.
let mutationSeq = 0;
let latestPublishedSeq = 0;
let lastPublishedAt = 0;

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

/**
 * Fan a cart taken straight from a mutation response out to every subscriber.
 *
 * This is the instant path: no debounce and no extra cart read. It also
 * invalidates any in-flight read, which was sent before this mutation landed.
 */
function publishMutationCart(cart: CartResponse, seq: number): void {
  if (seq <= latestPublishedSeq) {
    return;
  }

  latestPublishedSeq = seq;
  latestFetchId++;
  lastPublishedAt = Date.now();

  if (debounceTimer !== null) {
    clearTimeout(debounceTimer);
    debounceTimer = null;
  }

  subscribers.forEach(handler => {
    handler(cart);
  });
}

/**
 * Apply a successful mutation's own cart, or refetch when it carries none.
 */
function handleMutationBody(body: Promise<unknown>, seq: number): void {
  void body
    .then(cartFromMutationBody, () => null)
    .then(cart => {
      if (cart) {
        publishMutationCart(cart, seq);
      } else {
        scheduleCartRefresh();
      }
    });
}

/** A cart event only needs a read when no mutation response just covered it. */
function handleCartEvent(): void {
  if (Date.now() - lastPublishedAt < FRESH_CART_MS) {
    return;
  }

  scheduleCartRefresh();
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

/**
 * Parse a copy of a response body, leaving the original to the caller.
 *
 * Must run in the same tick the response resolves: the caller's own
 * continuation may consume the body next, after which clone() throws.
 */
function readClonedJson(response: Response): Promise<unknown> {
  try {
    return response.clone().json();
  } catch (error) {
    return Promise.reject(error);
  }
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
      const seq = ++mutationSeq;
      void responsePromise.then(
        response => {
          if (response.ok) {
            handleMutationBody(readClonedJson(response), seq);
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
    if (
      !this._aaCartUrl ||
      !this._aaCartMethod ||
      !isCartMutationRequest(this._aaCartUrl, { method: this._aaCartMethod })
    ) {
      return nativeSend.apply(this, args);
    }

    const seq = ++mutationSeq;
    this.addEventListener('load', () => {
      if (this.status >= 200 && this.status < 300) {
        handleMutationBody(
          Promise.resolve().then(() =>
            this.responseType === 'json'
              ? this.response
              : JSON.parse(this.responseText)
          ),
          seq
        );
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
    document.addEventListener(event, handleCartEvent);
    window.addEventListener(event, handleCartEvent);
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
