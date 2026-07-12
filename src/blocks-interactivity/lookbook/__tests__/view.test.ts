/**
 * Tests for lookbook frontend product loading.
 *
 * @jest-environment jsdom
 */

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: () => ({}),
    getContext: () => ({}),
    getElement: () => ({ ref: null }),
  }),
  { virtual: true }
);

import {
  __testing,
  fetchProduct,
  formatPrice,
  renderMessage,
  renderProduct,
  safeUrl,
  supportsHoverInteraction,
} from '../view';
import type { LookbookI18n } from '../view';

const mockFetchProduct = (product: unknown, ok = true) =>
  Promise.resolve({
    ok,
    status: ok ? 200 : 500,
    json: () => Promise.resolve(product),
  } as Response);

describe('fetchProduct', () => {
  beforeEach(() => {
    window.fetch = jest.fn();
  });

  it('deduplicates in-flight requests for the same product endpoint', async () => {
    (window.fetch as jest.Mock).mockReturnValue(
      mockFetchProduct({ name: 'Cached hoodie' })
    );

    const first = fetchProduct(101, '/wp-json/wc/store/v1/products/');
    const second = fetchProduct(101, '/wp-json/wc/store/v1/products/');

    await expect(Promise.all([first, second])).resolves.toEqual([
      { name: 'Cached hoodie' },
      { name: 'Cached hoodie' },
    ]);
    expect(window.fetch).toHaveBeenCalledTimes(1);
  });

  it('uses the resolved cache after a product has loaded', async () => {
    (window.fetch as jest.Mock).mockReturnValue(
      mockFetchProduct({ name: 'Loaded tee' })
    );

    await expect(
      fetchProduct(102, '/wp-json/wc/store/v1/products/')
    ).resolves.toEqual({ name: 'Loaded tee' });
    await expect(
      fetchProduct(102, '/wp-json/wc/store/v1/products/')
    ).resolves.toEqual({ name: 'Loaded tee' });

    expect(window.fetch).toHaveBeenCalledTimes(1);
  });

  it('does not cache failed responses', async () => {
    (window.fetch as jest.Mock)
      .mockReturnValueOnce(mockFetchProduct({}, false))
      .mockReturnValueOnce(mockFetchProduct({ name: 'Retry tee' }));

    await expect(
      fetchProduct(103, '/wp-json/wc/store/v1/products/')
    ).rejects.toThrow('HTTP 500');
    await expect(
      fetchProduct(103, '/wp-json/wc/store/v1/products/')
    ).resolves.toEqual({ name: 'Retry tee' });

    expect(window.fetch).toHaveBeenCalledTimes(2);
  });

  it('rejects invalid product IDs before fetching', async () => {
    await expect(
      fetchProduct(0, '/wp-json/wc/store/v1/products/')
    ).rejects.toThrow('Invalid product id');

    expect(window.fetch).not.toHaveBeenCalled();
  });
});

describe('formatPrice', () => {
  it('formats using the store currency separators', () => {
    expect(
      formatPrice({
        price: '123456',
        currency_minor_unit: 2,
        currency_prefix: '',
        currency_suffix: ' €',
        currency_decimal_separator: ',',
        currency_thousand_separator: '.',
      })
    ).toBe('1.234,56 €');
  });

  it('defaults to US-style separators without a hardcoded symbol', () => {
    expect(formatPrice({ price: '123456', currency_prefix: '$' })).toBe(
      '$1,234.56'
    );
    expect(formatPrice({ price: '123456' })).toBe('1,234.56');
  });

  it('handles currencies without minor units', () => {
    expect(
      formatPrice({
        price: '1234567',
        currency_minor_unit: 0,
        currency_prefix: '¥',
      })
    ).toBe('¥1,234,567');
  });

  it('returns an empty string for missing or invalid prices', () => {
    expect(formatPrice(undefined)).toBe('');
    expect(formatPrice({ price: 'not-a-number' })).toBe('');
  });
});

describe('safeUrl', () => {
  it('allows relative, hash, and http(s) URLs', () => {
    expect(safeUrl('/shop/hoodie')).toBe('/shop/hoodie');
    expect(safeUrl('#details')).toBe('#details');
    expect(safeUrl('https://example.com/p/1')).toBe('https://example.com/p/1');
  });

  it('rejects protocol-relative and unknown-scheme URLs', () => {
    expect(safeUrl('//evil.example/p/1')).toBe('#');
    expect(safeUrl('javascript:alert(1)')).toBe('#');
    expect(safeUrl(undefined)).toBe('#');
  });
});

describe('supportsHoverInteraction', () => {
  it('requires a fine pointer that supports hover', () => {
    window.matchMedia = jest.fn().mockReturnValue({ matches: true });

    expect(supportsHoverInteraction()).toBe(true);
    expect(window.matchMedia).toHaveBeenCalledWith(
      '(hover: hover) and (pointer: fine)'
    );
  });

  it('returns false for coarse/touch environments', () => {
    window.matchMedia = jest.fn().mockReturnValue({ matches: false });

    expect(supportsHoverInteraction()).toBe(false);
  });
});

describe('keyboard focus lands in the rendered card', () => {
  const I18N: LookbookI18n = {
    loading: 'Loading product.',
    noProduct: 'No product selected.',
    loadError: 'Product could not be loaded.',
    viewProduct: 'View product',
    viewProductName: 'View product: %s',
  };

  const buildRoot = (): HTMLElement => {
    const root = document.createElement('div');
    root.className = 'aggressive-apparel-lookbook';
    root.innerHTML =
      '<div class="aggressive-apparel-lookbook__popover-content"></div>';
    document.body.appendChild(root);
    return root;
  };

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('focuses the product link on keyboard-initiated renders', () => {
    const root = buildRoot();
    renderProduct(
      root,
      { id: 1, name: 'Hoodie', permalink: '/shop/hoodie' },
      I18N,
      true
    );

    const link = root.querySelector(
      'a.aggressive-apparel-lookbook__product-card'
    );
    expect(link).not.toBeNull();
    expect(document.activeElement).toBe(link);
  });

  it('does not steal focus on pointer/hover renders', () => {
    const root = buildRoot();
    renderProduct(
      root,
      { id: 1, name: 'Hoodie', permalink: '/shop/hoodie' },
      I18N,
      false
    );

    expect(document.activeElement).toBe(document.body);
  });

  it('focuses the message card so errors are not a dead end', () => {
    const root = buildRoot();
    renderMessage(root, I18N.loadError, true);

    const card = root.querySelector<HTMLElement>(
      '.aggressive-apparel-lookbook__product-card--message'
    );
    expect(card?.tabIndex).toBe(-1);
    expect(document.activeElement).toBe(card);
  });

  it('moves focus into an already-rendered product card', () => {
    const root = buildRoot();
    renderProduct(
      root,
      { id: 1, name: 'Hoodie', permalink: '/shop/hoodie' },
      I18N,
      false
    );

    const link = root.querySelector(
      'a.aggressive-apparel-lookbook__product-card'
    );
    expect(document.activeElement).toBe(document.body);

    __testing.focusPopoverCard(root);

    expect(document.activeElement).toBe(link);
  });

  it('moves from the popover card to the next hotspot in DOM order', () => {
    const root = buildRoot();
    root.insertAdjacentHTML(
      'afterbegin',
      [
        '<button class="aggressive-apparel-lookbook__hotspot" data-aa-hotspot-index="0">One</button>',
        '<button class="aggressive-apparel-lookbook__hotspot" data-aa-hotspot-index="1">Two</button>',
        '<button class="aggressive-apparel-lookbook__hotspot" data-aa-hotspot-index="2">Three</button>',
      ].join('')
    );

    expect(__testing.focusAdjacentHotspotFromPopover(root, 1)).toBe(true);
    expect(document.activeElement).toBe(
      root.querySelector('[data-aa-hotspot-index="2"]')
    );
  });

  it('moves backward from the popover card to its owning hotspot', () => {
    const root = buildRoot();
    root.insertAdjacentHTML(
      'afterbegin',
      [
        '<button class="aggressive-apparel-lookbook__hotspot" data-aa-hotspot-index="0">One</button>',
        '<button class="aggressive-apparel-lookbook__hotspot" data-aa-hotspot-index="1">Two</button>',
      ].join('')
    );

    expect(__testing.focusAdjacentHotspotFromPopover(root, 1, true)).toBe(true);
    expect(document.activeElement).toBe(
      root.querySelector('[data-aa-hotspot-index="1"]')
    );
  });
});
