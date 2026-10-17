/**
 * Catalog paging: which page to request, when, and what to do when it fails.
 *
 * Kept apart from the store and the DOM (both reached through `hooks`), so
 * every rule here — reusing a prefetch, retrying politely, ignoring work for
 * a catalog that has since been replaced — is unit-tested.
 *
 * @package Aggressive_Apparel
 */

import type { CatalogPage } from './catalog-page';
import { MAX_RETRIES, PageLoadError, retryDelayMs } from './request';

/** The store state the pager reads and drives. */
export interface PagerState {
  nextUrl: string;
  allLoaded: boolean;
  isLoading: boolean;
  /** Retries ran out: the next page is offered as a plain link. */
  loadFailed: boolean;
}

export interface PagerHooks {
  /** Fetch and parse one page. */
  request: (
    url: string,
    signal: AbortSignal,
    priority: 'auto' | 'low'
  ) => Promise<CatalogPage>;
  /** Show a loaded page and advance `nextUrl`. */
  append: (url: string, page: CatalogPage) => void;
  /** A load started (announce it). */
  started: () => void;
  /** Retries ran out (announce it; the fallback link shows). */
  failed: () => void;
  /** A prefetch finished, possibly after the sentinel's last edge event. */
  prefetched: () => void;
}

/** Timers and connectivity, replaceable in tests. */
export interface PagerClock {
  now: () => number;
  after: (delayMs: number, run: () => void) => void;
  online: () => boolean;
  whenOnline: (run: () => void) => void;
}

const browserClock: PagerClock = {
  now: () => Date.now(),
  after: (delayMs, run) => {
    window.setTimeout(run, delayMs);
  },
  online: () => navigator.onLine !== false,
  whenOnline: run => window.addEventListener('online', run, { once: true }),
};

export class CatalogPager {
  /** Bumped by reset(): work started for an older catalog is ignored. */
  private generation = 0;
  private loadController: AbortController | null = null;
  private prefetchController: AbortController | null = null;
  private pending: { url: string; promise: Promise<CatalogPage> } | null = null;
  private lastLoadAt = 0;

  constructor(
    private readonly state: PagerState,
    private readonly hooks: PagerHooks,
    private readonly clock: PagerClock = browserClock
  ) {}

  /** Identifies the current catalog; compare with isCurrent(). */
  get token(): number {
    return this.generation;
  }

  /** Whether a token still belongs to the current catalog. */
  isCurrent(token: number): boolean {
    return token === this.generation;
  }

  get msSinceLastLoad(): number {
    return this.clock.now() - this.lastLoadAt;
  }

  /** Abandon all work for the previous catalog (a router navigation). */
  reset(): void {
    this.generation += 1;
    this.loadController?.abort();
    this.loadController = null;
    this.prefetchController?.abort();
    this.prefetchController = null;
    this.pending = null;
    this.state.loadFailed = false;
  }

  /**
   * Load the next page, reusing its prefetch when one is pending or done.
   *
   * A failure that can pass (rate limit, server error, timeout, dropped
   * connection) is retried after the server's `Retry-After` or a backoff —
   * once back online when offline — while the control stays busy, so neither
   * clicks nor infinite scroll add requests. When retries run out, `failed`
   * runs and the next page is offered as a link.
   *
   * @param attempt Retries already made for this page.
   */
  load(attempt = 0): void {
    const { state } = this;
    if (
      (state.isLoading && attempt === 0) ||
      state.allLoaded ||
      !state.nextUrl
    ) {
      return;
    }

    const url = state.nextUrl;
    const token = this.generation;
    state.isLoading = true;
    state.loadFailed = false;
    this.lastLoadAt = this.clock.now();
    this.hooks.started();

    // A prefetch that failed is this load's first attempt: requesting again
    // at once would only repeat it. One that was cancelled is re-requested.
    const prefetch =
      this.pending?.url === url
        ? this.pending.promise.catch((error: Error) =>
            error instanceof PageLoadError ? Promise.reject(error) : null
          )
        : Promise.resolve(null);
    this.pending = null;

    prefetch
      .then(page => {
        if (page) return page;
        this.loadController?.abort();
        this.loadController = new AbortController();
        return this.hooks.request(url, this.loadController.signal, 'auto');
      })
      .then(page => {
        if (this.isCurrent(token)) this.hooks.append(url, page);
      })
      .catch((error: Error) => {
        if (error.name === 'AbortError' || !this.isCurrent(token)) return;

        if (
          error instanceof PageLoadError &&
          error.retryable &&
          attempt < MAX_RETRIES
        ) {
          this.retry(
            () => this.load(attempt + 1),
            retryDelayMs(error.retryAfter, attempt),
            token
          );
          return;
        }

        state.isLoading = false;
        state.loadFailed = true;
        this.hooks.failed();
      });
  }

  /**
   * Fetch the next page in the background so it is ready before the
   * shopper reaches it. A load in flight is already fetching it.
   */
  prefetch(): void {
    const { state } = this;
    const url = state.nextUrl;
    if (
      state.isLoading ||
      state.allLoaded ||
      !url ||
      this.pending?.url === url
    ) {
      return;
    }

    const token = this.generation;
    this.prefetchController?.abort();
    this.prefetchController = new AbortController();

    const promise = this.hooks
      .request(url, this.prefetchController.signal, 'low')
      .then(page => {
        if (this.isCurrent(token)) this.hooks.prefetched();
        return page;
      });
    // Failures are handled by the load that adopts this request.
    promise.catch(() => undefined);

    this.pending = { url, promise };
  }

  /** Run a retry after a delay, or once back online, for this catalog only. */
  private retry(run: () => void, delayMs: number, token: number): void {
    const guarded = (): void => {
      if (this.isCurrent(token)) run();
    };

    if (!this.clock.online()) {
      this.clock.whenOnline(guarded);
      return;
    }
    this.clock.after(delayMs, guarded);
  }
}
