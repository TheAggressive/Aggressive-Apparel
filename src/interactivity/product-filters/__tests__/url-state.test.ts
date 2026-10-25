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
    archiveTerm: { taxonomy: '', slug: '', link: '' },
    inStockOnly: false,
    onSaleOnly: false,
    orderby: '',
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
      orderby: 'savings',
    });
    const url = buildFilterUrl(original, []);
    window.history.replaceState({}, '', url);
    const restored = state();

    applyUrlState(restored, new URLSearchParams(window.location.search));

    expect(restored.selectedCollections).toEqual(['mens', 'unisex']);
    expect(restored.selectedSizes).toEqual(['l']);
    expect(restored.priceMax).toBe(80);
    expect(restored.orderby).toBe('savings');
  });

  it('clears refinements absent from the URL (back/forward)', () => {
    const s = state({
      selectedCollections: ['womens'],
      selectedColors: ['black'],
      priceMin: 20,
      priceMax: 60,
      inStockOnly: true,
      orderby: 'price',
    });

    applyUrlState(s, new URLSearchParams(''));

    expect(s.selectedCollections).toEqual([]);
    expect(s.selectedColors).toEqual([]);
    expect(s.priceMin).toBe(0);
    expect(s.priceMax).toBe(100);
    expect(s.inStockOnly).toBe(false);
    expect(s.orderby).toBe('');
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

  it('carries the WooCommerce sort in the URL so the server applies it', () => {
    const s = state();
    window.history.replaceState({}, '', '/shop/shirts/');

    applyUrlState(s, new URLSearchParams('orderby=price-desc'));
    expect(s.orderby).toBe('price-desc');

    expect(buildFilterUrl(state({ orderby: 'featured' }), [])).toBe(
      '/shop/?orderby=featured'
    );
  });

  it('drops unknown sorts instead of sending them to the server', () => {
    const s = state({ orderby: 'price' });

    applyUrlState(s, new URLSearchParams('orderby=bogus'));

    expect(s.orderby).toBe('');
  });

  it('never writes the retired filter pagination parameter', () => {
    expect(buildFilterUrl(state({ selectedSizes: ['m'] }), [])).toBe(
      '/shop/?size=m'
    );
  });
});

describe('attribute and tag archives', () => {
  const womens = {
    archiveTerm: {
      taxonomy: 'pa_collection',
      slug: 'womens',
      link: 'https://example.com/collection/womens/',
    },
  };

  beforeEach(() => window.history.replaceState({}, '', '/collection/womens/'));

  it('pre-selects the collection archive term from the path', () => {
    const s = state(womens);

    applyUrlState(s, new URLSearchParams(''));

    expect(s.selectedCollections).toEqual(['womens']);
    expect(s.selectedCategories).toEqual([]);
  });

  it('pre-selects the term on paginated archive pages', () => {
    window.history.replaceState({}, '', '/collection/womens/page/2/');
    const s = state(womens);

    applyUrlState(s, new URLSearchParams(''));

    expect(s.selectedCollections).toEqual(['womens']);
  });

  it('keeps the collection path when refining it', () => {
    expect(
      buildFilterUrl(
        state({
          ...womens,
          selectedCollections: ['womens'],
          selectedColors: ['black'],
          orderby: 'price',
        }),
        []
      )
    ).toBe('/collection/womens/?color=black&orderby=price');
  });

  it('round-trips a refined collection archive', () => {
    const url = buildFilterUrl(
      state({
        ...womens,
        selectedCollections: ['womens'],
        selectedSizes: ['m'],
      }),
      []
    );
    window.history.replaceState({}, '', url);
    const restored = state(womens);

    applyUrlState(restored, new URLSearchParams(window.location.search));

    expect(restored.selectedCollections).toEqual(['womens']);
    expect(restored.selectedSizes).toEqual(['m']);
  });

  it('leaves the archive when its term is deselected or widened', () => {
    expect(
      buildFilterUrl(
        state({ ...womens, selectedCollections: [], selectedColors: ['red'] }),
        []
      )
    ).toBe('/shop/?color=red');
    expect(
      buildFilterUrl(
        state({ ...womens, selectedCollections: ['womens', 'mens'] }),
        []
      )
    ).toBe('/shop/?collection=womens%2Cmens');
  });

  it('keeps the collection as a parameter on a category route', () => {
    expect(
      buildFilterUrl(
        state({
          ...womens,
          selectedCategories: ['shirts'],
          selectedCollections: ['womens'],
        }),
        ['shirts']
      )
    ).toBe('/shop/shirts/?collection=womens');
  });

  it('clearing everything on a collection archive returns to the shop', () => {
    expect(buildFilterUrl(state(womens), [])).toBe('/shop/');
  });

  it('keeps a tag archive path while refining it', () => {
    window.history.replaceState({}, '', '/tag/summer/');
    const tag = {
      archiveTerm: {
        taxonomy: 'product_tag',
        slug: 'summer',
        link: 'https://example.com/tag/summer/',
      },
    };
    const s = state(tag);

    applyUrlState(s, new URLSearchParams('color=black'));

    expect(s.selectedCollections).toEqual([]);
    expect(buildFilterUrl(s, [])).toBe('/tag/summer/?color=black');
  });

  it('ignores the archive term away from its path', () => {
    window.history.replaceState({}, '', '/shop/');
    const s = state(womens);

    applyUrlState(s, new URLSearchParams(''));

    expect(s.selectedCollections).toEqual([]);
  });
});
