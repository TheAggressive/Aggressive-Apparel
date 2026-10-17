/**
 * @jest-environment jsdom
 */

import {
  findCatalogGrid,
  parseCatalogPage,
  resolveSameOriginUrl,
  scopeSelectors,
} from '../catalog-page';

const PAGE_URL = 'https://store.test/shop/page/2/?color=black';

function seed(overrides: Record<string, unknown> = {}): string {
  return JSON.stringify({
    mode: 'infinite_scroll',
    perPage: 2,
    currentPage: 2,
    totalProducts: 5,
    loadedCount: 2,
    nextUrl: 'https://store.test/shop/page/3/?color=black',
    allLoaded: false,
    ...overrides,
  }).replace(/"/g, '&quot;');
}

function page({
  context = seed(),
  styles = '',
}: { context?: string; styles?: string } = {}): string {
  return `<!doctype html><html><head><style id="core-block-supports-inline-css">${styles}</style></head><body>
    <div class="wp-block-woocommerce-product-collection">
      <ul class="wp-block-woocommerce-product-template">
        <li class="wc-block-product post-11"><h2 class="wp-elements-4"><a href="/p/11">Eleven</a></h2></li>
        <li class="wc-block-product post-12"><div class="wp-block-group is-style-card--2 wp-container-core-group-is-layout-ab12cd34"></div></li>
      </ul>
      <div class="aa-load-more" data-wp-interactive="aggressive-apparel/load-more" data-wp-context="${context}"></div>
    </div>
    <div class="wp-block-woocommerce-product-collection">
      <ul class="wp-block-woocommerce-product-template"><li class="post-99"></li></ul>
    </div>
  </body></html>`;
}

describe('parseCatalogPage', () => {
  it('returns the paginated collection cards and the page seed', () => {
    const result = parseCatalogPage(page(), PAGE_URL, 'aa1');

    expect(result?.cards.map(card => card.className)).toEqual([
      'wc-block-product post-11',
      'wc-block-product post-12',
    ]);
    expect(result?.seed).toEqual(
      expect.objectContaining({
        currentPage: 2,
        totalProducts: 5,
        nextUrl: 'https://store.test/shop/page/3/?color=black',
        allLoaded: false,
      })
    );
  });

  it('renames per-request classes and keeps only the rules the cards use', () => {
    const styles = [
      '.wp-elements-4 a:where(:not(.wp-element-button)){color:red;}',
      '.wp-elements-40 a{color:blue;}',
      '.is-style-card--2{padding:1rem;}',
      '.wp-container-core-group-is-layout-ab12cd34{display:flex;}',
    ].join('');
    const original = (globalThis as { CSSStyleSheet?: unknown }).CSSStyleSheet;
    // jsdom has no constructable stylesheets; stand in for the browser parser.
    (globalThis as { CSSStyleSheet?: unknown }).CSSStyleSheet = class {
      cssRules: Array<{ cssText: string }> = [];
      replaceSync(css: string): void {
        this.cssRules = (css.match(/[^}]+}/g) ?? []).map(cssText => ({
          cssText,
        }));
      }
    };

    try {
      const result = parseCatalogPage(page({ styles }), PAGE_URL, 'aa1');

      expect(result?.cards[0].querySelector('h2')?.className).toBe(
        'wp-elements-4-aa1'
      );
      expect(result?.cards[1].firstElementChild?.className).toBe(
        'wp-block-group is-style-card--2-aa1 wp-container-core-group-is-layout-ab12cd34'
      );
      expect(result?.css).toEqual([
        '.wp-elements-4-aa1 a:where(:not(.wp-element-button)){color:red;}',
        '.is-style-card--2-aa1{padding:1rem;}',
      ]);
    } finally {
      (globalThis as { CSSStyleSheet?: unknown }).CSSStyleSheet = original;
    }
  });

  it('marks the last page and drops off-site next links', () => {
    expect(
      parseCatalogPage(page({ context: seed({ nextUrl: '' }) }), PAGE_URL, 'a')
        ?.seed
    ).toEqual(expect.objectContaining({ nextUrl: '', allLoaded: true }));
    expect(
      parseCatalogPage(
        page({ context: seed({ nextUrl: 'https://evil.test/shop/page/3/' }) }),
        PAGE_URL,
        'a'
      )?.seed.nextUrl
    ).toBe('');
  });

  it('returns null for a page without a valid paginated catalog', () => {
    expect(
      parseCatalogPage('<html><body></body></html>', PAGE_URL, 'a')
    ).toBeNull();
    expect(
      parseCatalogPage(page({ context: '{not json' }), PAGE_URL, 'a')
    ).toBeNull();
    expect(
      parseCatalogPage(page({ context: seed({ perPage: 0 }) }), PAGE_URL, 'a')
    ).toBeNull();
  });
});

describe('findCatalogGrid', () => {
  it('ignores collections without the Load More control', () => {
    document.body.innerHTML = new DOMParser()
      .parseFromString(page(), 'text/html')
      .body.innerHTML.replace('post-11', 'post-11 first');

    expect(findCatalogGrid(document)?.querySelector('.first')).not.toBeNull();
  });
});

describe('resolveSameOriginUrl', () => {
  it('resolves relative links against the fetched page', () => {
    expect(resolveSameOriginUrl('/shop/page/3/', PAGE_URL)).toBe(
      'https://store.test/shop/page/3/'
    );
    expect(resolveSameOriginUrl('//evil.test/x', PAGE_URL)).toBe('');
    expect(resolveSameOriginUrl('', PAGE_URL)).toBe('');
  });
});

describe('scopeSelectors', () => {
  it('leaves content-hashed and longer class names alone', () => {
    expect(
      scopeSelectors(
        '.wp-elements-4,.wp-elements-45x,.wp-container-core-x-1{}',
        's'
      )
    ).toBe('.wp-elements-4-s,.wp-elements-45x,.wp-container-core-x-1{}');
  });
});
