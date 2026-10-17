/**
 * Validate and apply request-scoped catalog pagination state.
 *
 * Every rendered catalog page carries its own seed as the Load More control's
 * local context (see Load_More::build_seed): on first load, in each page Load
 * More fetches, and after a router navigation (filters, sort). WordPress keeps
 * Interactivity API stores alive across navigations, so this boundary copies
 * only a complete, valid seed into the long-lived mutable store.
 */

export interface LoadMoreSeed {
  mode: 'load_more' | 'infinite_scroll';
  perPage: number;
  currentPage: number;
  totalProducts: number;
  loadedCount: number;
  /** The native "next page" link; empty on the last page. */
  nextUrl: string;
  allLoaded: boolean;
}

export interface LoadMoreSeedTarget extends LoadMoreSeed {
  isLoading: boolean;
  announcement: string;
}

function isNonNegativeInteger(value: unknown): value is number {
  return Number.isInteger(value) && Number(value) >= 0;
}

function isPositiveInteger(value: unknown): value is number {
  return Number.isInteger(value) && Number(value) > 0;
}

/** Validate that a rendered context contains a complete pagination seed. */
export function isLoadMoreSeed(candidate: unknown): candidate is LoadMoreSeed {
  if (!candidate || typeof candidate !== 'object') return false;
  const seed = candidate as Partial<LoadMoreSeed>;

  return (
    (seed.mode === 'load_more' || seed.mode === 'infinite_scroll') &&
    isPositiveInteger(seed.perPage) &&
    isPositiveInteger(seed.currentPage) &&
    isNonNegativeInteger(seed.totalProducts) &&
    isNonNegativeInteger(seed.loadedCount) &&
    seed.loadedCount <= seed.totalProducts &&
    typeof seed.nextUrl === 'string' &&
    typeof seed.allLoaded === 'boolean'
  );
}

/**
 * Return a stable identity for a complete request-scoped seed.
 *
 * WordPress can re-evaluate data-wp-watch callbacks without changing a router
 * region. Comparing the complete seed prevents those harmless evaluations from
 * aborting requests or rebuilding observers.
 */
export function getLoadMoreSeedFingerprint(candidate: unknown): string | null {
  if (!isLoadMoreSeed(candidate)) return null;

  return JSON.stringify([
    candidate.mode,
    candidate.perPage,
    candidate.currentPage,
    candidate.totalProducts,
    candidate.loadedCount,
    candidate.nextUrl,
    candidate.allLoaded,
  ]);
}

/** Apply a complete trusted seed and reset transient request state. */
export function applyLoadMoreSeed(
  target: LoadMoreSeedTarget,
  candidate: unknown
): boolean {
  if (!isLoadMoreSeed(candidate)) return false;

  target.mode = candidate.mode;
  target.perPage = candidate.perPage;
  target.currentPage = candidate.currentPage;
  target.totalProducts = candidate.totalProducts;
  target.loadedCount = candidate.loadedCount;
  target.nextUrl = candidate.nextUrl;
  target.allLoaded = candidate.allLoaded || candidate.nextUrl === '';
  target.isLoading = false;
  target.announcement = '';

  return true;
}
