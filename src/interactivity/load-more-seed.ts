/**
 * Validate and apply request-scoped catalog pagination state.
 *
 * WordPress keeps Interactivity API stores alive across router navigations.
 * The load-more control therefore carries a fresh local context on every
 * render, and this boundary copies only a complete, valid seed into the
 * long-lived mutable store.
 */

export interface LoadMoreSeed {
  mode: 'load_more' | 'infinite_scroll';
  restBase: string;
  restNonce: string;
  templateSlug: string;
  perPage: number;
  currentPage: number;
  totalPages: number;
  totalProducts: number;
  loadedCount: number;
  allLoaded: boolean;
  nextCursor: string;
  orderby: string;
  currentTaxonomy: string;
  currentTerm: string;
}

export interface LoadMoreSeedTarget extends LoadMoreSeed {
  isLoading: boolean;
  filtersActive: boolean;
  announcement: string;
}

function isNonNegativeInteger(value: unknown): value is number {
  return Number.isInteger(value) && Number(value) >= 0;
}

function isPositiveInteger(value: unknown): value is number {
  return Number.isInteger(value) && Number(value) > 0;
}

/** Validate that a router-provided context contains a complete pagination seed. */
function isLoadMoreSeed(
  candidate: Partial<LoadMoreSeed>
): candidate is LoadMoreSeed {
  return !(
    (candidate.mode !== 'load_more' && candidate.mode !== 'infinite_scroll') ||
    typeof candidate.restBase !== 'string' ||
    candidate.restBase.length === 0 ||
    typeof candidate.restNonce !== 'string' ||
    typeof candidate.templateSlug !== 'string' ||
    !isPositiveInteger(candidate.perPage) ||
    !isPositiveInteger(candidate.currentPage) ||
    !isPositiveInteger(candidate.totalPages) ||
    !isNonNegativeInteger(candidate.totalProducts) ||
    !isNonNegativeInteger(candidate.loadedCount) ||
    candidate.loadedCount > candidate.totalProducts ||
    typeof candidate.allLoaded !== 'boolean' ||
    typeof candidate.nextCursor !== 'string' ||
    typeof candidate.orderby !== 'string' ||
    candidate.orderby.length === 0 ||
    typeof candidate.currentTaxonomy !== 'string' ||
    typeof candidate.currentTerm !== 'string'
  );
}

/**
 * Return a stable identity for a complete request-scoped seed.
 *
 * WordPress can re-evaluate data-wp-watch callbacks without changing a router
 * region. Comparing the complete seed prevents those harmless evaluations from
 * aborting requests or rebuilding observers.
 */
export function getLoadMoreSeedFingerprint(
  candidate: Partial<LoadMoreSeed>
): string | null {
  if (!isLoadMoreSeed(candidate)) return null;

  return JSON.stringify([
    candidate.mode,
    candidate.restBase,
    candidate.restNonce,
    candidate.templateSlug,
    candidate.perPage,
    candidate.currentPage,
    candidate.totalPages,
    candidate.totalProducts,
    candidate.loadedCount,
    candidate.allLoaded,
    candidate.nextCursor,
    candidate.orderby,
    candidate.currentTaxonomy,
    candidate.currentTerm,
  ]);
}

/** Apply a complete trusted seed and reset transient request state. */
export function applyLoadMoreSeed(
  target: LoadMoreSeedTarget,
  candidate: Partial<LoadMoreSeed>
): boolean {
  if (!isLoadMoreSeed(candidate)) return false;

  target.mode = candidate.mode;
  target.restBase = candidate.restBase;
  target.restNonce = candidate.restNonce;
  target.templateSlug = candidate.templateSlug;
  target.perPage = candidate.perPage;
  target.currentPage = candidate.currentPage;
  target.totalPages = candidate.totalPages;
  target.totalProducts = candidate.totalProducts;
  target.loadedCount = candidate.loadedCount;
  target.allLoaded = candidate.allLoaded;
  target.nextCursor = candidate.nextCursor;
  target.orderby = candidate.orderby;
  target.currentTaxonomy = candidate.currentTaxonomy;
  target.currentTerm = candidate.currentTerm;
  target.isLoading = false;
  target.filtersActive = false;
  target.announcement = '';

  return true;
}
