/**
 * @jest-environment jsdom
 */

import { applyLoadMoreSeed, type LoadMoreSeedTarget } from '../load-more-seed';

function seed(overrides: Partial<LoadMoreSeedTarget> = {}): LoadMoreSeedTarget {
  return {
    mode: 'infinite_scroll',
    restBase: 'https://store.test/wp-json/catalog/rendered',
    restNonce: 'nonce',
    templateSlug: 'archive-product',
    perPage: 12,
    currentPage: 1,
    totalPages: 2,
    totalProducts: 24,
    loadedCount: 12,
    allLoaded: false,
    nextCursor: 'menu-order-cursor',
    orderby: 'menu_order',
    currentTaxonomy: '',
    currentTerm: '',
    isLoading: false,
    filtersActive: false,
    announcement: '',
    ...overrides,
  };
}

describe('request-scoped load-more seed', () => {
  it('replaces stale pagination after a soft sort navigation', () => {
    const target = seed({
      currentPage: 2,
      loadedCount: 24,
      allLoaded: true,
      isLoading: true,
      filtersActive: true,
      announcement: 'Loading',
    });
    const sorted = seed({
      nextCursor: 'price-cursor',
      orderby: 'price',
    });

    expect(applyLoadMoreSeed(target, sorted)).toBe(true);
    expect(target).toMatchObject({
      currentPage: 1,
      loadedCount: 12,
      allLoaded: false,
      nextCursor: 'price-cursor',
      orderby: 'price',
      isLoading: false,
      filtersActive: false,
      announcement: '',
    });
  });

  it('rejects incomplete context without partially mutating state', () => {
    const target = seed();
    const original = { ...target };

    expect(
      applyLoadMoreSeed(target, {
        orderby: 'price',
        nextCursor: 'price-cursor',
      })
    ).toBe(false);
    expect(target).toEqual(original);
  });
});
