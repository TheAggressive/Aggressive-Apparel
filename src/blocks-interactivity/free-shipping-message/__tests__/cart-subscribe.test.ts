/**
 * Tests for free-shipping cart subscriptions: empty-cart gating and how cart
 * responses are applied to block context.
 *
 * @jest-environment jsdom
 */

import type {
  FreeShippingBarContext,
  FreeShippingCartContext,
} from '../cart-refresh';

jest.mock(
  '@wordpress/interactivity',
  () => ({
    withScope: <T>(fn: T): T => fn,
  }),
  // The real module is a WP runtime external (not installed), so mock it virtually.
  { virtual: true }
);

const REST_BASE = 'http://example.test/wp-json/wc/store/v1';
const EXTENSION = 'aggressive-apparel/free-shipping';

const usd = {
  currencyMinorUnit: 2,
  currencyPrefix: '$',
  currencySuffix: '',
  currencyDecimalSeparator: '.',
  currencyThousandSeparator: ',',
};

function messageContext(
  overrides: Partial<FreeShippingCartContext> = {}
): FreeShippingCartContext {
  return {
    ...usd,
    threshold: 75,
    customThreshold: 0,
    cartTotal: 40,
    remaining: 35,
    complete: false,
    restBase: REST_BASE,
    emphasisText: 'FREE Shipping',
    i18n: {
      progressDefault: '%s Away from FREE Shipping!',
      progressCustom: '%1$s Away from %2$s!',
      unlockedDefault: 'FREE Shipping UNLOCKED!',
      unlockedCustom: '%s UNLOCKED!',
    },
    ...overrides,
  };
}

function barContext(): FreeShippingBarContext {
  return {
    ...usd,
    threshold: 75,
    customThreshold: 0,
    cartTotal: 0,
    percent: 0,
    remaining: 75,
    complete: false,
    restBase: REST_BASE,
    emphasisText: '',
    i18n: {
      progressDefault: '%s Away',
      progressCustom: '%1$s Away',
      unlockedDefault: 'Unlocked',
      unlockedCustom: '%s Unlocked',
    },
  };
}

function cartBody(extension: Record<string, unknown>) {
  return {
    totals: {
      total_items: '0',
      currency_minor_unit: 2,
      currency_prefix: '£',
      currency_suffix: '',
      currency_decimal_separator: '.',
      currency_thousand_separator: ',',
    },
    extensions: { [EXTENSION]: extension },
  };
}

/** Like fetch's Response: once the body is read, clone() throws. */
function jsonResponse(body: unknown): Response {
  let bodyUsed = false;
  const response = {
    ok: true,
    json: () => {
      bodyUsed = true;
      return Promise.resolve(body);
    },
    clone: () => {
      if (bodyUsed) {
        throw new TypeError('Response body is already used');
      }
      return { json: () => Promise.resolve(body) };
    },
  };
  return response as unknown as Response;
}

function cartResponse(extension: Record<string, unknown>): Response {
  return jsonResponse(cartBody(extension));
}

function clearCookies(): void {
  document.cookie.split(';').forEach(pair => {
    const name = pair.split('=')[0].trim();
    if (name) {
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT`;
    }
  });
}

type CartRefreshModule = typeof import('../cart-refresh');

function loadModule(): CartRefreshModule {
  let loaded: CartRefreshModule | undefined;
  jest.isolateModules(() => {
    loaded = jest.requireActual<CartRefreshModule>('../cart-refresh');
  });
  return loaded as CartRefreshModule;
}

async function flushMicrotasks(): Promise<void> {
  for (let i = 0; i < 20; i++) {
    await Promise.resolve();
  }
}

/** Settle pending responses, fire the debounce, then settle the cart read. */
async function flushRefresh(): Promise<void> {
  await flushMicrotasks();
  jest.advanceTimersByTime(200);
  await flushMicrotasks();
}

describe('free-shipping cart subscriptions', () => {
  let fetchMock: jest.Mock;
  const unsubscribes: Array<() => void> = [];

  beforeEach(() => {
    jest.useFakeTimers();
    clearCookies();
    fetchMock = jest.fn();
    window.fetch = fetchMock as unknown as typeof window.fetch;
  });

  afterEach(() => {
    // Module instances from isolateModules keep their document listeners;
    // dropping subscribers turns those listeners into no-ops.
    unsubscribes.splice(0).forEach(unsubscribe => unsubscribe());
    jest.useRealTimers();
    clearCookies();
  });

  it('skips the cart request and zeroes progress when the cart is empty', async () => {
    const { subscribeFreeShippingCartRefresh } = loadModule();
    const ctx = messageContext();

    unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));
    await flushRefresh();

    expect(fetchMock).not.toHaveBeenCalled();
    expect(ctx.threshold).toBe(75);
    expect(ctx.cartTotal).toBe(0);
    expect(ctx.remaining).toBe(75);
    expect(ctx.complete).toBe(false);
  });

  it('applies the customer zone threshold and currency when items are in the cart', async () => {
    document.cookie = 'woocommerce_items_in_cart=1';
    fetchMock.mockResolvedValue(
      cartResponse({ threshold: 12000, subtotal: 4500, rate: 0.79 })
    );
    const { subscribeFreeShippingCartRefresh } = loadModule();
    const ctx = messageContext();

    unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));
    await flushRefresh();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).toBe(`${REST_BASE}/cart`);
    expect(ctx.threshold).toBe(120);
    expect(ctx.cartTotal).toBe(45);
    expect(ctx.remaining).toBe(75);
    expect(ctx.currencyPrefix).toBe('£');
  });

  it('hides the block when the customer zone has no threshold', async () => {
    document.cookie = 'woocommerce_items_in_cart=1';
    fetchMock.mockResolvedValue(
      cartResponse({ threshold: 0, subtotal: 4500, rate: 1 })
    );
    const { subscribeFreeShippingCartRefresh } = loadModule();
    const ctx = messageContext();

    unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));
    await flushRefresh();

    // `data-wp-bind--hidden="!context.threshold"` keys off this.
    expect(ctx.threshold).toBe(0);
    expect(ctx.complete).toBe(false);
  });

  it('marks the bar complete when a free-shipping coupon unlocks it', async () => {
    document.cookie = 'woocommerce_items_in_cart=1';
    fetchMock.mockResolvedValue(
      cartResponse({ threshold: 7500, subtotal: 1000, rate: 1, unlocked: true })
    );
    const { subscribeFreeShippingBarCartRefresh } = loadModule();
    const ctx = barContext();

    unsubscribes.push(subscribeFreeShippingBarCartRefresh(ctx));
    await flushRefresh();

    expect(ctx.complete).toBe(true);
    expect(ctx.remaining).toBe(0);
    expect(ctx.percent).toBe(100);
  });

  it('refetches after a cart mutation even when the page loaded empty', async () => {
    fetchMock.mockResolvedValue(
      cartResponse({ threshold: 7500, subtotal: 3000, rate: 1 })
    );
    const { subscribeFreeShippingCartRefresh } = loadModule();
    const ctx = messageContext();

    unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));
    await flushRefresh();
    expect(fetchMock).not.toHaveBeenCalled();

    document.dispatchEvent(new Event('wc-blocks_added_to_cart'));
    await flushRefresh();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(ctx.cartTotal).toBe(30);
    expect(ctx.remaining).toBe(45);
  });

  it('observes a mutation that overlaps an in-flight cart read, and drops the stale response', async () => {
    document.cookie = 'woocommerce_items_in_cart=1';
    const pendingReads: Array<(response: Response) => void> = [];
    fetchMock.mockImplementation((_input: string, init?: RequestInit) => {
      if (init?.method === 'POST') {
        return Promise.resolve({ ok: true } as Response);
      }
      return new Promise<Response>(resolve => pendingReads.push(resolve));
    });
    const { subscribeFreeShippingCartRefresh } = loadModule();
    const ctx = messageContext();

    unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));
    jest.advanceTimersByTime(200);
    expect(pendingReads).toHaveLength(1);

    // Address change lands while the first read is still in flight.
    void window.fetch(`${REST_BASE}/cart/update-customer`, { method: 'POST' });
    await flushRefresh();
    expect(pendingReads).toHaveLength(2);

    // Newer read resolves first; the older (pre-mutation) one arrives late.
    pendingReads[1](cartResponse({ threshold: 20000, subtotal: 0, rate: 1 }));
    await flushRefresh();
    pendingReads[0](cartResponse({ threshold: 0, subtotal: 0, rate: 1 }));
    await flushRefresh();

    expect(ctx.threshold).toBe(200);
  });

  describe('instant updates from mutation responses', () => {
    const reads = () =>
      fetchMock.mock.calls.filter(([, init]) => init?.method !== 'POST');

    it('applies the cart a mutation returns, with no extra read', async () => {
      fetchMock.mockImplementation((_input: string, init?: RequestInit) =>
        Promise.resolve(
          init?.method === 'POST'
            ? cartResponse({ threshold: 7500, subtotal: 3000, rate: 1 })
            : cartResponse({ threshold: 0, subtotal: 0, rate: 1 })
        )
      );
      const { subscribeFreeShippingCartRefresh } = loadModule();
      const ctx = messageContext();
      unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));

      // The caller reads its body straight away, as WooCommerce does.
      await window
        .fetch(`${REST_BASE}/cart/add-item`, { method: 'POST' })
        .then(response => response.json());
      await flushMicrotasks();

      // Applied before any debounce fires.
      expect(ctx.remaining).toBe(45);

      // WooCommerce's trailing cart event is covered by that response.
      document.dispatchEvent(new Event('wc-blocks_added_to_cart'));
      await flushRefresh();
      expect(reads()).toHaveLength(0);
      expect(ctx.remaining).toBe(45);
    });

    it('takes the last successful cart from a batch response', async () => {
      fetchMock.mockResolvedValue(
        jsonResponse({
          responses: [
            {
              status: 200,
              body: cartBody({ threshold: 7500, subtotal: 1000, rate: 1 }),
            },
            {
              status: 200,
              body: cartBody({ threshold: 7500, subtotal: 5000, rate: 1 }),
            },
            {
              status: 400,
              body: { code: 'woocommerce_rest_cart_invalid_key' },
            },
          ],
        })
      );
      const { subscribeFreeShippingCartRefresh } = loadModule();
      const ctx = messageContext();
      unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));

      void window.fetch(`${REST_BASE}/batch`, { method: 'POST' });
      await flushMicrotasks();

      expect(ctx.cartTotal).toBe(50);
      expect(reads()).toHaveLength(0);
    });

    it('ignores a late response to an older mutation', async () => {
      const posts: Array<(response: Response) => void> = [];
      fetchMock.mockImplementation(
        () => new Promise<Response>(resolve => posts.push(resolve))
      );
      const { subscribeFreeShippingCartRefresh } = loadModule();
      const ctx = messageContext();
      unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));

      void window.fetch(`${REST_BASE}/cart/add-item`, { method: 'POST' });
      void window.fetch(`${REST_BASE}/cart/remove-item`, { method: 'POST' });

      posts[1](cartResponse({ threshold: 7500, subtotal: 1000, rate: 1 }));
      await flushMicrotasks();
      posts[0](cartResponse({ threshold: 7500, subtotal: 6000, rate: 1 }));
      await flushMicrotasks();

      expect(ctx.cartTotal).toBe(10);
    });

    it('falls back to a cart read when the response carries no cart', async () => {
      fetchMock.mockImplementation((input: string) =>
        Promise.resolve(
          input.includes('wc-ajax')
            ? jsonResponse({ fragments: {}, cart_hash: 'abc' })
            : cartResponse({ threshold: 7500, subtotal: 2000, rate: 1 })
        )
      );
      const { subscribeFreeShippingCartRefresh } = loadModule();
      const ctx = messageContext();
      unsubscribes.push(subscribeFreeShippingCartRefresh(ctx));

      void window.fetch('/?wc-ajax=add_to_cart', { method: 'POST' });
      await flushRefresh();

      expect(reads()).toHaveLength(1);
      expect(ctx.cartTotal).toBe(20);
    });
  });
});
