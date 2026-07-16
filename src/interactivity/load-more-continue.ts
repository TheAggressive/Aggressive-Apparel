/**
 * Pure helpers extracted for infinite-scroll continuation tests.
 *
 * Kept free of Interactivity store coupling so Jest can lock the mobile
 * "sentinel stayed intersecting" regression without booting the full module.
 */

/** Matches load-more.ts SENTINEL_ROOT_MARGIN_PX. */
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
  nextCursor: string;
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
    !options.nextCursor ||
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
