import { applyUrlState, buildFilterUrl } from '../url-state';
import type { ProductFiltersState } from '../types';

function state(
  overrides: Partial<ProductFiltersState> = {}
): ProductFiltersState {
  return {
    selectedCategories: [],
    selectedColors: [],
    selectedSizes: [],
    selectedCollections: [],
    priceMin: 0,
    priceMax: 100,
    priceRange: { min: 0, max: 100 },
    salesCategorySlug: 'sales',
    currentCategorySlug: '',
    inStockOnly: false,
    onSaleOnly: false,
    currentPage: 1,
    shopUrl: 'https://example.com/shop/',
    salesCategoryUrl: 'https://example.com/shop/sales/',
    categories: [
      {
        slug: 'shirts',
        name: 'Shirts',
        link: 'https://example.com/shop/shirts/',
      },
      {
        slug: 'tank-tops',
        name: 'Tank Tops',
        link: 'https://example.com/shop/tank-tops/',
      },
    ],
    ...overrides,
  } as ProductFiltersState;
}

describe('product-filter URL state', () => {
  beforeEach(() => window.history.replaceState({}, '', '/shop/'));

  it('uses the native sales archive and preserves refinements', () => {
    const url = buildFilterUrl(
      state({
        selectedCategories: ['sales', 'shirts'],
        selectedColors: ['black'],
        onSaleOnly: true,
      }),
      ['shirts']
    );

    expect(url).toBe('/shop/sales/?cat=shirts&color=black');
  });

  it('uses a category permalink without a redundant category parameter', () => {
    expect(
      buildFilterUrl(state({ selectedCategories: ['shirts'] }), ['shirts'])
    ).toBe('/shop/shirts/');
  });

  it('writes the canonical collection parameter', () => {
    expect(buildFilterUrl(state({ selectedCollections: ['womens'] }), [])).toBe(
      '/shop/?collection=womens'
    );
  });

  it('combines collection with category, color, size, price and stock', () => {
    expect(
      buildFilterUrl(
        state({
          selectedCategories: ['tank-tops'],
          selectedCollections: ['womens', 'unisex'],
          selectedColors: ['black'],
          selectedSizes: ['m'],
          priceMin: 10,
          inStockOnly: true,
        }),
        ['tank-tops']
      )
    ).toBe(
      '/shop/tank-tops/?color=black&size=m&collection=womens%2Cunisex&min_price=10&stock=instock'
    );
  });

  it('never writes the legacy fit parameter', () => {
    const url = buildFilterUrl(state({ selectedCollections: ['mens'] }), []);
    expect(new URL(url, 'https://example.com').searchParams.has('fit')).toBe(
      false
    );
  });
});

describe('applyUrlState', () => {
  it('restores collection from a navigation URL with its category', () => {
    window.history.replaceState({}, '', '/shop/tank-tops/?collection=womens');
    const s = state();

    applyUrlState(s, new URLSearchParams(window.location.search));

    expect(s.selectedCategories).toEqual(['tank-tops']);
    expect(s.selectedCollections).toEqual(['womens']);
  });

  it('round-trips through buildFilterUrl', () => {
    const original = state({
      selectedCollections: ['mens', 'unisex'],
      selectedSizes: ['l'],
      priceMax: 80,
      currentPage: 2,
    });
    const url = buildFilterUrl(original, []);
    window.history.replaceState({}, '', url);
    const restored = state();

    applyUrlState(restored, new URLSearchParams(window.location.search));

    expect(restored.selectedCollections).toEqual(['mens', 'unisex']);
    expect(restored.selectedSizes).toEqual(['l']);
    expect(restored.priceMax).toBe(80);
    expect(restored.currentPage).toBe(2);
  });

  it('clears refinements absent from the URL (back/forward)', () => {
    const s = state({
      selectedCollections: ['womens'],
      selectedColors: ['black'],
      priceMin: 20,
      priceMax: 60,
      inStockOnly: true,
      currentPage: 3,
    });

    applyUrlState(s, new URLSearchParams(''));

    expect(s.selectedCollections).toEqual([]);
    expect(s.selectedColors).toEqual([]);
    expect(s.priceMin).toBe(0);
    expect(s.priceMax).toBe(100);
    expect(s.inStockOnly).toBe(false);
    expect(s.currentPage).toBe(1);
  });

  it('does not read the legacy fit parameter as a collection', () => {
    const s = state();

    applyUrlState(s, new URLSearchParams('fit=mens'));

    expect(s.selectedCollections).toEqual([]);
  });

  it('derives the sale flag from the URL on popstate, not the initial page', () => {
    // Loaded on the Sales archive, then navigated to Shirts without on_sale.
    const s = state({ currentCategorySlug: 'sales' });
    window.history.replaceState({}, '', '/shop/shirts/');

    applyUrlState(s, new URLSearchParams(''));

    expect(s.onSaleOnly).toBe(false);
    expect(s.selectedCategories).toEqual(['shirts']);

    window.history.replaceState({}, '', '/shop/sales/');
    applyUrlState(s, new URLSearchParams(''));

    expect(s.onSaleOnly).toBe(true);
  });
});
