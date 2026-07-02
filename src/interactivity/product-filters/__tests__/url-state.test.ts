import { buildFilterUrl } from '../url-state';
import type { ProductFiltersState } from '../types';

function state(
  overrides: Partial<ProductFiltersState> = {}
): ProductFiltersState {
  return {
    selectedCategories: [],
    selectedColors: [],
    selectedSizes: [],
    selectedFit: [],
    priceMin: 0,
    priceMax: 100,
    priceRange: { min: 0, max: 100 },
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
});
