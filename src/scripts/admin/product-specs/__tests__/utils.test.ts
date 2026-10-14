import { appliesToCategories } from '../_utils';
import type { Category } from '../_types';

// Apparel › Tees, Apparel › Hoodies, Hats.
const categories: Category[] = [
  { id: 1, name: 'Apparel', parent: 0 },
  { id: 2, name: 'Apparel › Tees', parent: 1 },
  { id: 3, name: 'Apparel › Hoodies', parent: 1 },
  { id: 4, name: 'Hats', parent: 0 },
];

describe('appliesToCategories', () => {
  it('applies everywhere when unscoped', () => {
    expect(
      appliesToCategories({ scope: 'all', categories: [] }, [4], categories)
    ).toBe(true);
    expect(
      appliesToCategories({ scope: 'only', categories: [] }, [4], categories)
    ).toBe(true);
  });

  it('matches "only" scopes on the category, its ancestors, or its children', () => {
    const tees = { scope: 'only' as const, categories: [2] };
    expect(appliesToCategories(tees, [2], categories)).toBe(true);
    expect(appliesToCategories(tees, [1], categories)).toBe(true);
    expect(appliesToCategories(tees, [3], categories)).toBe(false);
    expect(appliesToCategories(tees, [4], categories)).toBe(false);
    expect(
      appliesToCategories({ scope: 'only', categories: [1] }, [3], categories)
    ).toBe(true);
  });

  it('excludes "except" scopes on the category or an ancestor', () => {
    const notApparel = { scope: 'except' as const, categories: [1] };
    expect(appliesToCategories(notApparel, [2], categories)).toBe(false);
    expect(appliesToCategories(notApparel, [4], categories)).toBe(true);
    expect(appliesToCategories(notApparel, [2, 4], categories)).toBe(true);
    // Excluding only Tees still leaves Apparel's other products.
    expect(
      appliesToCategories({ scope: 'except', categories: [2] }, [1], categories)
    ).toBe(true);
  });
});
