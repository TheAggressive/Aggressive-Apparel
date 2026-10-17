/**
 * What Load More keeps in its history entry, so Back can rebuild the grid.
 *
 * Browsers usually restore the whole page from the back/forward cache, but
 * not always (memory pressure, some extensions, older browsers). When they
 * reload it instead, the pages appended before leaving are fetched again and
 * the scroll position restored.
 *
 * @package Aggressive_Apparel
 */

const KEY = 'aaLoadMore';

export interface SavedCatalog {
  /** Absolute URLs of the appended pages, in order. */
  pages: string[];
  scrollY: number;
}

/** The catalog saved in a history state, if it is well formed. */
export function savedCatalog(historyState: unknown): SavedCatalog | null {
  const saved = (historyState as Record<string, unknown> | null)?.[KEY] as
    Partial<SavedCatalog> | undefined;
  if (
    !Array.isArray(saved?.pages) ||
    saved.pages.length === 0 ||
    !saved.pages.every(page => typeof page === 'string') ||
    typeof saved.scrollY !== 'number' ||
    saved.scrollY < 0
  ) {
    return null;
  }
  return { pages: saved.pages, scrollY: saved.scrollY };
}

/** A copy of the history state with the catalog saved in it. */
export function saveCatalog(
  historyState: unknown,
  catalog: SavedCatalog
): Record<string, unknown> {
  const base =
    historyState && typeof historyState === 'object'
      ? (historyState as Record<string, unknown>)
      : {};
  return { ...base, [KEY]: catalog };
}

/** Whether this page load came from Back/Forward (and not the page cache). */
export function isBackForwardLoad(): boolean {
  const entry = performance.getEntriesByType('navigation')[0] as
    PerformanceNavigationTiming | undefined;
  return entry?.type === 'back_forward';
}
