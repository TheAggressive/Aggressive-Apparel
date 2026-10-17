import { ATTRIBUTE_FILTERS, type ProductFiltersState } from './types';

/**
 * WooCommerce `orderby` values the server applies (mirrors
 * Catalog_Archive_Filters::SORTS).
 */
export const SORTS: ReadonlySet<string> = new Set([
  'menu_order',
  'date',
  'popularity',
  'rating',
  'price',
  'price-desc',
  'title-asc',
  'title-desc',
  'featured',
  'savings',
]);

/** Apply a WooCommerce `orderby` value; '' or unknown resets to the default. */
export function applySort(
  state: ProductFiltersState,
  orderby: string
): boolean {
  const known = SORTS.has(orderby);
  state.orderby = known ? orderby : '';
  return known;
}

/** Canonical WooCommerce shop pathname. */
export function getShopPath(state: ProductFiltersState): string {
  try {
    return new URL(state.shopUrl, window.location.origin).pathname;
  } catch {
    return '/shop/';
  }
}

/** Absolute WooCommerce shop URL. */
export function getCanonicalShopUrl(state: ProductFiltersState): string {
  try {
    return new URL(state.shopUrl, window.location.origin).href;
  } catch {
    return `${window.location.origin}${getShopPath(state)}`;
  }
}

/** Native Sales category pathname. */
export function getSalesCategoryPath(
  state: ProductFiltersState
): string | null {
  if (!state.salesCategoryUrl) return null;
  try {
    return new URL(state.salesCategoryUrl, window.location.origin).pathname;
  } catch {
    return null;
  }
}

/** Canonical category pathname. */
export function getCategoryPath(
  state: ProductFiltersState,
  slug: string
): string | null {
  const category = state.categories.find(item => item.slug === slug);
  if (!category?.link) return null;
  try {
    return new URL(category.link, window.location.origin).pathname;
  } catch {
    return null;
  }
}

/**
 * Whether the current pathname is the native Sales archive. Decided from the
 * URL, not `currentCategorySlug`: that is seeded once for the initially loaded
 * page, so on popstate it would describe a different entry.
 */
export function isSalesArchive(
  state: ProductFiltersState,
  catFromPath: string | null
): boolean {
  if (!state.salesCategorySlug) return false;
  if (catFromPath === state.salesCategorySlug) return true;
  const salesPath = getSalesCategoryPath(state);
  if (salesPath) return window.location.pathname === salesPath;
  // No Sales URL to compare against; only the initial archive can say.
  return state.currentCategorySlug === state.salesCategorySlug;
}

/** Category slug represented by the current pathname. */
export function getCategorySlugFromPath(
  state: ProductFiltersState
): string | null {
  for (const category of state.categories) {
    if (!category.link) continue;
    try {
      if (
        new URL(category.link, window.location.origin).pathname ===
        window.location.pathname
      ) {
        return category.slug;
      }
    } catch {
      // Invalid configured URL; omit it from route matching.
    }
  }
  return null;
}

/** Build the canonical shareable URL for the current filter state. */
export function buildFilterUrl(
  state: ProductFiltersState,
  visibleCategories: string[]
): string {
  const params = new URLSearchParams();
  const salesPath = getSalesCategoryPath(state);
  let basePath: string;

  if (state.onSaleOnly && salesPath) {
    basePath = salesPath;
    if (visibleCategories.length > 0) {
      params.set('cat', visibleCategories.join(','));
    }
  } else if (state.selectedCategories.length === 1) {
    const categoryPath = getCategoryPath(state, state.selectedCategories[0]);
    if (categoryPath) {
      basePath = categoryPath;
    } else {
      basePath = getShopPath(state);
      params.set('cat', state.selectedCategories[0]);
    }
  } else {
    basePath = getShopPath(state);
    if (state.selectedCategories.length > 1) {
      params.set('cat', state.selectedCategories.join(','));
    }
  }

  for (const filter of ATTRIBUTE_FILTERS) {
    const selected = state[filter.stateKey];
    if (selected.length > 0) params.set(filter.urlParam, selected.join(','));
  }
  if (state.priceMin > state.priceRange.min) {
    params.set('min_price', String(state.priceMin));
  }
  if (state.priceMax < state.priceRange.max) {
    params.set('max_price', String(state.priceMax));
  }
  if (state.inStockOnly) params.set('stock', 'instock');
  if (state.onSaleOnly && !salesPath) params.set('on_sale', '1');
  if (state.orderby) params.set('orderby', state.orderby);

  const query = params.toString();
  return query ? `${basePath}?${query}` : basePath;
}

/**
 * Apply a filter URL to state (init and popstate). Every refinement is
 * assigned whether or not its param is present: on popstate an absent param
 * means "cleared", so keeping the previous entry's value would leak it.
 *
 * @return The category slug represented by the current pathname, if any.
 */
export function applyUrlState(
  state: ProductFiltersState,
  params: URLSearchParams
): string | null {
  // Active category from the path (handles pushState navigation), e.g.
  // /shop/hoodies/ → 'hoodies' even after loading /shop/clothing/.
  const catFromPath = getCategorySlugFromPath(state);
  const catFromParam = (params.get('cat') || '').split(',').filter(Boolean);
  state.selectedCategories = [
    ...new Set([...(catFromPath ? [catFromPath] : []), ...catFromParam]),
  ];
  for (const filter of ATTRIBUTE_FILTERS) {
    state[filter.stateKey] = (params.get(filter.urlParam) || '')
      .split(',')
      .filter(Boolean);
  }
  state.priceMin = Math.max(
    parseInt(params.get('min_price') || '0', 10) || 0,
    state.priceRange.min
  );
  state.priceMax = Math.min(
    parseInt(params.get('max_price') || '', 10) || state.priceRange.max,
    state.priceRange.max
  );
  state.inStockOnly = params.get('stock') === 'instock';
  state.onSaleOnly =
    params.get('on_sale') === '1' || isSalesArchive(state, catFromPath);
  applySort(state, params.get('orderby') || '');
  return catFromPath;
}
