import { ATTRIBUTE_FILTERS, type ProductFiltersState } from './types';

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
  if (state.currentPage > 1) params.set('pf_page', String(state.currentPage));

  const query = params.toString();
  return query ? `${basePath}?${query}` : basePath;
}
