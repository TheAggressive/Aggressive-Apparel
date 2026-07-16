/**
 * @jest-environment jsdom
 */

import {
  resolveContinuationOrderby,
  shouldHideLoadMoreButton,
} from '../load-more-orderby';

describe('shouldHideLoadMoreButton', () => {
  it('hides the button in infinite_scroll mode even when more products remain', () => {
    expect(shouldHideLoadMoreButton('infinite_scroll', false)).toBe(true);
  });

  it('shows the button in load_more mode until the catalogue is exhausted', () => {
    expect(shouldHideLoadMoreButton('load_more', false)).toBe(false);
    expect(shouldHideLoadMoreButton('load_more', true)).toBe(true);
  });

  it('hides the button once all products are loaded in either mode', () => {
    expect(shouldHideLoadMoreButton('infinite_scroll', true)).toBe(true);
    expect(shouldHideLoadMoreButton('load_more', true)).toBe(true);
  });
});

describe('resolveContinuationOrderby', () => {
  it('prefers the SSR-seeded orderby over the sorting dropdown default', () => {
    // Regression: dropdown "Default sorting" is often menu_order while the
    // grid/cursor were seeded for date — that mismatch loads ~1 new product.
    expect(resolveContinuationOrderby('date', 'menu_order', '')).toBe('date');
  });

  it('lets an explicit URL orderby win after the shopper changes sort', () => {
    expect(resolveContinuationOrderby('date', 'menu_order', 'price')).toBe(
      'price'
    );
  });

  it('falls back to the select, then menu_order, when nothing is seeded', () => {
    expect(resolveContinuationOrderby('', 'popularity', '')).toBe('popularity');
    expect(resolveContinuationOrderby('', '', '')).toBe('menu_order');
  });

  it('ignores non-catalog select values used by search relevance UI', () => {
    expect(resolveContinuationOrderby('menu_order', 'relevance', '')).toBe(
      'menu_order'
    );
    expect(resolveContinuationOrderby('', 'featured', '')).toBe('menu_order');
  });
});
