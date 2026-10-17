/**
 * @jest-environment jsdom
 */

import {
  applyLoadMoreSeed,
  getLoadMoreSeedFingerprint,
  isLoadMoreSeed,
  type LoadMoreSeedTarget,
} from '../seed';

function seed(overrides: Partial<LoadMoreSeedTarget> = {}): LoadMoreSeedTarget {
  return {
    mode: 'infinite_scroll',
    perPage: 12,
    currentPage: 1,
    totalProducts: 24,
    loadedCount: 12,
    nextUrl: 'https://store.test/shop/page/2/',
    allLoaded: false,
    isLoading: false,
    announcement: '',
    ...overrides,
  };
}

describe('request-scoped load-more seed', () => {
  it('replaces stale pagination after a soft filter navigation', () => {
    const target = seed({
      currentPage: 2,
      loadedCount: 24,
      allLoaded: true,
      isLoading: true,
      announcement: 'Loading',
    });
    const filtered = seed({
      totalProducts: 15,
      nextUrl: 'https://store.test/shop/page/2/?color=black',
    });

    expect(applyLoadMoreSeed(target, filtered)).toBe(true);
    expect(target).toMatchObject({
      currentPage: 1,
      loadedCount: 12,
      totalProducts: 15,
      allLoaded: false,
      nextUrl: 'https://store.test/shop/page/2/?color=black',
      isLoading: false,
      announcement: '',
    });
  });

  it('treats a page without a next link as fully loaded', () => {
    const target = seed();

    applyLoadMoreSeed(target, seed({ nextUrl: '', allLoaded: false }));

    expect(target.allLoaded).toBe(true);
  });

  it('rejects incomplete or inconsistent context without mutating state', () => {
    const target = seed();
    const original = { ...target };

    expect(applyLoadMoreSeed(target, { nextUrl: '/shop/page/9/' })).toBe(false);
    expect(applyLoadMoreSeed(target, seed({ loadedCount: 30 }))).toBe(false);
    expect(applyLoadMoreSeed(target, seed({ perPage: 0 }))).toBe(false);
    expect(applyLoadMoreSeed(target, null)).toBe(false);
    expect(target).toEqual(original);
  });

  it('identifies complete seeds and changes identity with the next page', () => {
    const initial = seed();
    const sorted = seed({
      nextUrl: 'https://store.test/shop/page/2/?orderby=price',
    });

    expect(isLoadMoreSeed(initial)).toBe(true);
    expect(getLoadMoreSeedFingerprint(initial)).not.toBeNull();
    expect(getLoadMoreSeedFingerprint(sorted)).not.toBe(
      getLoadMoreSeedFingerprint(initial)
    );
    expect(getLoadMoreSeedFingerprint({ nextUrl: '' })).toBeNull();
  });
});
