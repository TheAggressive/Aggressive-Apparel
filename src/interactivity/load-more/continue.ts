/**
 * Pure Load More decisions, kept free of the store so Jest can test them.
 */

/** Automatic retries of a page before offering the next page as a link. */
export const MAX_RETRIES = 3;

/**
 * How long to wait before retrying a page.
 *
 * Honours the server's `Retry-After` (seconds or an HTTP date); otherwise
 * backs off exponentially from 2s. Bounded to 1–10s so a shopper is never
 * left waiting indefinitely.
 *
 * @param retryAfter `Retry-After` header value, if any.
 * @param attempt    Retries already made (0 for the first).
 * @param now        Current time in ms.
 */
export function retryDelayMs(
  retryAfter: string | null,
  attempt: number,
  now: number = Date.now()
): number {
  let delay = 2000 * 2 ** attempt;
  const value = retryAfter?.trim() ?? '';
  if (/^\d+$/.test(value)) {
    delay = Number(value) * 1000;
  } else if (value) {
    const date = Date.parse(value);
    if (!Number.isNaN(date)) delay = date - now;
  }

  return Math.min(10_000, Math.max(1000, delay));
}

/** How far below the viewport infinite scroll starts the next load. */
export const SENTINEL_ROOT_MARGIN_PX = 800;

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
