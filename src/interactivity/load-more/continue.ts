/**
 * Pure Load More decisions, kept free of the store so Jest can test them.
 */

/** How far below the viewport infinite scroll starts the next load. */
export const SENTINEL_ROOT_MARGIN_PX = 400;

export function isSentinelWithinRootMargin(
  sentinelTop: number,
  viewportHeight: number,
  rootMarginPx: number = SENTINEL_ROOT_MARGIN_PX
): boolean {
  return sentinelTop <= viewportHeight + rootMarginPx;
}

/**
 * Whether infinite scroll should chain another page after an append.
 *
 * IntersectionObserver only fires on edge transitions; if the sentinel
 * remains near the viewport after cards are inserted, we must continue.
 */
export function shouldContinueInfiniteScroll(options: {
  mode: string;
  isLoading: boolean;
  allLoaded: boolean;
  nextUrl: string;
  sentinelHidden: boolean;
  sentinelTop: number;
  viewportHeight: number;
  msSinceLastFetch: number;
  cooldownMs: number;
}): 'load' | 'wait' | 'stop' {
  if (
    options.mode !== 'infinite_scroll' ||
    options.isLoading ||
    options.allLoaded ||
    !options.nextUrl ||
    options.sentinelHidden
  ) {
    return 'stop';
  }

  if (
    !isSentinelWithinRootMargin(options.sentinelTop, options.viewportHeight)
  ) {
    return 'stop';
  }

  if (options.msSinceLastFetch < options.cooldownMs) {
    return 'wait';
  }

  return 'load';
}

/**
 * Whether the Load More button should be hidden for the current UI mode.
 *
 * Infinite scroll uses the sentinel + spinner; the button is only for
 * explicit `load_more` mode (or when the store falls back to it).
 */
export function shouldHideLoadMoreButton(
  mode: string,
  allLoaded: boolean
): boolean {
  return mode === 'infinite_scroll' || allLoaded;
}
