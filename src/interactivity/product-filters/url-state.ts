import {
  ATTRIBUTE_FILTERS,
  type AttributeFilterConfig,
  type ProductFiltersState,
} from './types';

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

/** The Collection filter, which landing pages carry in their path. */
const COLLECTION_FILTER = ATTRIBUTE_FILTERS.find(
  filter => filter.urlParam === 'collection'
);

/**
 * The audience and category of a landing pathname (/shop/mens/ or
 * /shop/mens/t-shirts/, any page of it), or null for any other pathname.
 * Mirrors Audience_Landing_Pages::rules().
 */
export function parseLandingPath(
  state: ProductFiltersState,
  pathname: string
): { audience: string; category: string | null } | null {
  if (!state.audienceBase || !pathname.startsWith(state.audienceBase)) {
    return null;
  }
  const parts = pathname
    .slice(state.audienceBase.length)
    .replace(/(^|\/)page\/\d+\/?$/, '')
    .split('/')
    .filter(Boolean);
  if (parts.length < 1 || parts.length > 2) return null;
  if (!(state.audiences ?? []).includes(parts[0])) return null;
  return { audience: parts[0], category: parts[1] ?? null };
}

/** A landing page pathname. */
export function getLandingPath(
  state: ProductFiltersState,
  audience: string,
  category: string | null
): string {
  return `${state.audienceBase}${audience}/${category ? `${category}/` : ''}`;
}

/** Category slug represented by the current pathname. */
export function getCategorySlugFromPath(
  state: ProductFiltersState
): string | null {
  const landing = parseLandingPath(state, window.location.pathname);
  if (landing) return landing.category;
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

/** Pathname of the tag / brand / attribute archive the page was loaded on. */
export function getArchivePath(state: ProductFiltersState): string | null {
  if (!state.archiveTerm.link) return null;
  try {
    return new URL(state.archiveTerm.link, window.location.origin).pathname;
  } catch {
    return null;
  }
}

/** The attribute filter an attribute archive's own term belongs to. */
function getArchiveAttribute(
  state: ProductFiltersState
): AttributeFilterConfig | undefined {
  return ATTRIBUTE_FILTERS.find(
    filter => filter.taxonomy === state.archiveTerm.taxonomy
  );
}

/** Whether the current pathname is the archive (any page of it). */
function isOnArchivePath(state: ProductFiltersState): boolean {
  const archivePath = getArchivePath(state);
  if (!archivePath) return false;
  const path = window.location.pathname.replace(/page\/\d+\/?$/, '');
  return path === archivePath;
}

/**
 * The archive path when it can still carry the selection: no category or
 * Sales route applies, and on an attribute archive (e.g. /collection/womens/)
 * its term is that attribute's only pick — deselecting it or adding a sibling
 * widens the result beyond the archive, so those go through the shop.
 */
function getScopingArchivePath(
  state: ProductFiltersState,
  salesPath: string | null
): string | null {
  const archivePath = getArchivePath(state);
  if (
    !archivePath ||
    state.selectedCategories.length > 0 ||
    (state.onSaleOnly && salesPath)
  ) {
    return null;
  }

  const attribute = getArchiveAttribute(state);
  if (!attribute) return archivePath;

  const selected = state[attribute.stateKey];
  return selected.length === 1 && selected[0] === state.archiveTerm.slug
    ? archivePath
    : null;
}

/** Build the canonical shareable URL for the current filter state. */
export function buildFilterUrl(
  state: ProductFiltersState,
  visibleCategories: string[]
): string {
  const params = new URLSearchParams();
  const salesPath = getSalesCategoryPath(state);
  const archivePath = getScopingArchivePath(state, salesPath);
  // The archive path already names its own term; don't repeat it as a param.
  const archiveAttribute = archivePath ? getArchiveAttribute(state) : undefined;
  let basePath: string;
  // The category a landing page would name: null for the shop, undefined
  // where no landing page applies (another archive, or no category path).
  let landingCategory: string | null | undefined;

  if (archivePath) {
    basePath = archivePath;
  } else if (state.onSaleOnly && salesPath) {
    basePath = salesPath;
    landingCategory = state.salesCategorySlug || undefined;
    if (visibleCategories.length > 0) {
      params.set('cat', visibleCategories.join(','));
    }
  } else if (state.selectedCategories.length === 1) {
    const categoryPath = getCategoryPath(state, state.selectedCategories[0]);
    if (categoryPath) {
      basePath = categoryPath;
      landingCategory = state.selectedCategories[0];
    } else {
      basePath = getShopPath(state);
      params.set('cat', state.selectedCategories[0]);
    }
  } else {
    basePath = getShopPath(state);
    landingCategory = null;
    if (state.selectedCategories.length > 1) {
      params.set('cat', state.selectedCategories.join(','));
    }
  }

  // One audience picked: its landing page names it in the path.
  const audiences = COLLECTION_FILTER ? state[COLLECTION_FILTER.stateKey] : [];
  const landingFilter =
    landingCategory !== undefined &&
    audiences.length === 1 &&
    !!state.audienceBase &&
    (state.audiences ?? []).includes(audiences[0])
      ? COLLECTION_FILTER
      : undefined;
  if (landingFilter) {
    basePath = getLandingPath(state, audiences[0], landingCategory ?? null);
  }

  for (const filter of ATTRIBUTE_FILTERS) {
    if (filter === archiveAttribute || filter === landingFilter) continue;
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
  // A landing page's audience, from the path (/shop/mens/t-shirts/ →
  // collection 'mens').
  const landing = parseLandingPath(state, window.location.pathname);
  if (landing && COLLECTION_FILTER) {
    state[COLLECTION_FILTER.stateKey] = [
      ...new Set([landing.audience, ...state[COLLECTION_FILTER.stateKey]]),
    ];
  }
  // An attribute archive's own term, from the path (/collection/womens/ →
  // collection 'womens'), so its chip shows selected and facets stay scoped.
  const archiveAttribute = getArchiveAttribute(state);
  if (archiveAttribute && isOnArchivePath(state)) {
    state[archiveAttribute.stateKey] = [
      ...new Set([state.archiveTerm.slug, ...state[archiveAttribute.stateKey]]),
    ];
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
