/**
 * @jest-environment jsdom
 */

import {
  PRODUCT_GRID_MAX_PAGES,
  clearProductGridSpacer,
  pruneProductGrid,
} from '../helpers';

function makeGrid(cardCount: number): HTMLElement {
  const grid = document.createElement('ul');
  grid.className = 'wp-block-woocommerce-product-template';
  for (let i = 0; i < cardCount; i++) {
    const li = document.createElement('li');
    li.className = `wc-block-product post-${1000 + i}`;
    // Fixed height so spacer math is deterministic in jsdom (0 by default).
    Object.defineProperty(li, 'getBoundingClientRect', {
      value: () => ({
        height: 100,
        width: 100,
        top: 0,
        left: 0,
        bottom: 100,
        right: 100,
        x: 0,
        y: 0,
        toJSON: () => ({}),
      }),
    });
    grid.appendChild(li);
  }
  document.body.appendChild(grid);
  return grid;
}

describe('pruneProductGrid', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('retains enough pages for a typical shop catalogue', () => {
    expect(PRODUCT_GRID_MAX_PAGES).toBeGreaterThanOrEqual(12);
  });

  it('does not prune when the grid fits within the retention window', () => {
    const perPage = 8;
    const grid = makeGrid(perPage * PRODUCT_GRID_MAX_PAGES);
    pruneProductGrid(grid, perPage);
    expect(
      grid.querySelectorAll('li:not(.aa-product-grid__spacer)').length
    ).toBe(perPage * PRODUCT_GRID_MAX_PAGES);
    expect(grid.querySelector('.aa-product-grid__spacer')).toBeNull();
  });

  it('prunes oldest cards and inserts a leading spacer beyond the window', () => {
    const perPage = 8;
    const total = perPage * PRODUCT_GRID_MAX_PAGES + 5;
    const grid = makeGrid(total);
    const firstKeptId = `post-${1000 + 5}`;

    pruneProductGrid(grid, perPage);

    const cards = [
      ...grid.querySelectorAll('li:not(.aa-product-grid__spacer)'),
    ];
    expect(cards).toHaveLength(perPage * PRODUCT_GRID_MAX_PAGES);
    expect(cards[0].classList.contains(firstKeptId)).toBe(true);

    const spacer = grid.querySelector(
      '.aa-product-grid__spacer'
    ) as HTMLElement | null;
    expect(spacer).not.toBeNull();
    expect(spacer?.getAttribute('aria-hidden')).toBe('true');
    expect(spacer?.style.height).toBe('500px');
  });

  it('clearProductGridSpacer removes the scroll spacer', () => {
    const grid = makeGrid(perPageCards());
    pruneProductGrid(grid, 8);
    expect(grid.querySelector('.aa-product-grid__spacer')).not.toBeNull();
    clearProductGridSpacer(grid);
    expect(grid.querySelector('.aa-product-grid__spacer')).toBeNull();
  });
});

function perPageCards(): number {
  return 8 * PRODUCT_GRID_MAX_PAGES + 1;
}
