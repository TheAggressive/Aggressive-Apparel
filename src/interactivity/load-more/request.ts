/**
 * Fetch a catalog page, classifying every failure.
 *
 * Load More must never leave a shopper without the rest of the catalog, so
 * each failure says whether trying again can help: rate limits, server
 * errors, timeouts and dropped connections can; a refusal (403, 404) cannot.
 *
 * @package Aggressive_Apparel
 */

/** Statuses worth retrying: timeouts, rate limits and server errors. */
const RETRYABLE_STATUSES: ReadonlySet<number> = new Set([
  408, 425, 429, 500, 502, 503, 504,
]);

/** A request that takes longer than this is abandoned and retried. */
export const PAGE_TIMEOUT_MS = 15_000;

/** Why a page could not be loaded. */
export class PageLoadError extends Error {
  constructor(
    message: string,
    /** Whether trying again later can succeed. */
    readonly retryable: boolean,
    /** The server's `Retry-After`, if it sent one. */
    readonly retryAfter: string | null = null
  ) {
    super(message);
    this.name = 'PageLoadError';
  }
}

/**
 * Fetch a same-origin page's HTML.
 *
 * @param url      Page URL.
 * @param signal   Cancels the request (rethrown as the caller's AbortError).
 * @param priority Fetch priority.
 * @return The HTML and the final URL (after redirects).
 * @throws {PageLoadError} When the page could not be loaded.
 */
export async function fetchPageHtml(
  url: string,
  signal: AbortSignal,
  priority: 'auto' | 'low' = 'auto'
): Promise<{ html: string; url: string }> {
  const request = new AbortController();
  const cancel = (): void => request.abort();
  const timer = setTimeout(cancel, PAGE_TIMEOUT_MS);
  signal.addEventListener('abort', cancel, { once: true });

  try {
    const response = await fetch(url, {
      signal: request.signal,
      priority,
      mode: 'same-origin',
      credentials: 'same-origin',
      headers: { Accept: 'text/html' },
    } as RequestInit);
    if (!response.ok) {
      throw new PageLoadError(
        `HTTP ${response.status}`,
        RETRYABLE_STATUSES.has(response.status),
        response.headers.get('Retry-After')
      );
    }

    return { html: await response.text(), url: response.url || url };
  } catch (error) {
    if (signal.aborted || error instanceof PageLoadError) throw error;
    // Timed out, offline, or the connection dropped mid-response.
    throw new PageLoadError('Network error', true);
  } finally {
    clearTimeout(timer);
    signal.removeEventListener('abort', cancel);
  }
}
