/**
 * @jest-environment jsdom
 */

import { saveCatalog, savedCatalog } from '../history';

describe('saved catalog in history state', () => {
  it('round-trips without touching the rest of the state', () => {
    const state = saveCatalog(
      { router: 'kept' },
      { pages: ['https://store.test/shop/page/2/'], scrollY: 1800 }
    );

    expect(state.router).toBe('kept');
    expect(savedCatalog(state)).toEqual({
      pages: ['https://store.test/shop/page/2/'],
      scrollY: 1800,
    });
  });

  it('ignores missing or malformed entries', () => {
    expect(savedCatalog(null)).toBeNull();
    expect(savedCatalog({})).toBeNull();
    expect(savedCatalog({ aaLoadMore: { pages: [], scrollY: 0 } })).toBeNull();
    expect(
      savedCatalog({ aaLoadMore: { pages: [42], scrollY: 0 } })
    ).toBeNull();
    expect(
      savedCatalog({ aaLoadMore: { pages: ['/p/2/'], scrollY: -1 } })
    ).toBeNull();
  });
});
