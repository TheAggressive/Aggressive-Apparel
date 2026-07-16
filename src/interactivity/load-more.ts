/**
 * Load More / Infinite Scroll — Interactivity API Store
 *
 * Replaces standard pagination with a Load More button or automatic
 * infinite scroll via IntersectionObserver. Continuations use opaque
 * keyset cursors from the rendered-products endpoint (no deep OFFSET).
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../types/interactivity-shared';

import { store } from '@wordpress/interactivity';
import {
  clearProductGridSpacer,
  installBlockSupportStyles,
  notifyCardsRendered,
  pruneProductGrid,
} from '@aggressive-apparel/helpers';
import {
  resolveContinuationOrderby,
  shouldHideLoadMoreButton,
} from './load-more-orderby';

interface LoadMoreState {
  mode: string;
  allLoaded: boolean;
  isLoading: boolean;
  totalProducts: number;
  totalPages: number;
  currentPage: number;
  loadedCount: number;
  perPage: number;
  orderby: string;
  restBase: string;
  restNonce: string;
  templateSlug: string;
  currentTaxonomy: string;
  currentTerm: string;
  filtersActive: boolean;
  nextCursor: string;
  announcement: string;
  loadingText: string;
  errorText: string;
  statusFormat: string;
  loadedFormat: string;
  readonly hideButton: boolean;
  readonly hideSentinel: boolean;
  readonly showSentinelLoader: boolean;
  readonly statusText: string;
}

interface ProductsFetchedDetail {
  page: number;
  totalPages: number;
  totalProducts: number;
  append: boolean;
  productsCount: number;
  nextCursor?: string;
}

/** Response shape from aggressive-apparel/v1/products/rendered */
interface RenderedEntry {
  html: string;
  styles?: Array<{ id: string; css: string; nonce?: string }>;
  total_products: number;
  total_pages: number;
  next_cursor?: string;
}

interface LoadMoreStore {
  state: LoadMoreState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

let abortController: AbortController | null = null;
let prefetchController: AbortController | null = null;

/** Cache of pre-fetched rendered pages keyed by cursor token. */
const prefetchCache = new Map<string, RenderedEntry>();

/** In-flight prefetch promise so loadSsrPage can adopt it instead of double-fetching. */
let inflightPrefetch: {
  cursor: string;
  promise: Promise<RenderedEntry | null>;
} | null = null;

let observer: IntersectionObserver | null = null;
let lastFetchAt = 0;
let prefetchArmed = false;

/** Matches the IntersectionObserver rootMargin used for infinite scroll. */
const SENTINEL_ROOT_MARGIN_PX = 400;

/** Elements that have already run callbacks.init (soft-nav safe). */
const initializedRoots = new WeakSet<Element>();

/** Document-level listeners are registered once per module lifetime. */
let documentListenersBound = false;

const FETCH_COOLDOWN_MS = 400;

const { state } = store<LoadMoreStore>('aggressive-apparel/load-more', {
  state: {
    get hideButton(): boolean {
      // Infinite scroll hides the button; reduced-motion / observer failure
      // flips mode to `load_more` so the button reappears as a fallback.
      return shouldHideLoadMoreButton(state.mode, state.allLoaded);
    },

    get hideSentinel(): boolean {
      return state.mode !== 'infinite_scroll' || state.allLoaded;
    },

    // Standalone spinner is only for infinite-scroll mode (button mode shows its
    // own in-place spinner).
    get showSentinelLoader(): boolean {
      return state.isLoading && state.mode === 'infinite_scroll';
    },

    get statusText(): string {
      if (state.totalProducts === 0) return '';
      return state.statusFormat
        .replace('%1$d', String(state.loadedCount))
        .replace('%2$d', String(state.totalProducts));
    },
  },

  actions: {
    loadMore(): void {
      if (state.isLoading || state.allLoaded || !state.nextCursor) return;
      loadNextPage();
    },
  },

  callbacks: {
    init(): void {
      const root = document.querySelector(
        '[data-wp-interactive="aggressive-apparel/load-more"]'
      );
      if (root && initializedRoots.has(root)) return;
      if (root) initializedRoots.add(root);

      synchronizeInitialGridState();

      if (state.mode === 'infinite_scroll') {
        setupIntersectionObserver();
      }

      armPrefetchOnScroll();

      if (!documentListenersBound) {
        documentListenersBound = true;
        document.addEventListener(
          'aa:products-fetched',
          handleProductsFetched as EventListener
        );
        document.addEventListener(
          'aa:products-fetch-failed',
          handleFetchFailure
        );
        document.addEventListener('aa:filters-changed', handleFiltersChanged);
      }
    },
  },
});

/** Align pagination with the Product Collection actually rendered by the template. */
function synchronizeInitialGridState(): void {
  const grid = document.querySelector<HTMLElement>(
    '.wp-block-woocommerce-product-template'
  );
  if (!grid) return;

  const renderedCount = grid.querySelectorAll(
    ':scope > li:not(.aa-product-grid__spacer)'
  ).length;
  if (renderedCount < 1) return;

  state.perPage = renderedCount;
  state.loadedCount = renderedCount;
  state.totalPages = Math.max(
    1,
    Math.ceil(state.totalProducts / renderedCount)
  );
}

/**
 * Handle product-filters fetch completion.
 */
function handleProductsFetched(e: CustomEvent<ProductsFetchedDetail>): void {
  const { page, totalPages, totalProducts, append, productsCount, nextCursor } =
    e.detail;
  state.filtersActive = true;
  state.currentPage = page;
  state.totalPages = totalPages;
  state.totalProducts = totalProducts;
  // Only overwrite when the filter response includes a cursor field — an
  // omitted value must not wipe the SSR-seeded continuation token.
  if (typeof nextCursor === 'string') {
    state.nextCursor = nextCursor;
  }
  state.allLoaded =
    page >= totalPages ||
    (typeof nextCursor === 'string' ? nextCursor === '' : state.allLoaded);
  state.loadedCount = append
    ? state.loadedCount + (productsCount || 0)
    : productsCount || 0;
  // The filter-driven fetch has completed — clear the loading state so the
  // spinner hides and the button re-enables.
  state.isLoading = false;
}

/**
 * Handle filter change — reset to page 1 and discard stale prefetch cache.
 */
function handleFiltersChanged(): void {
  if (abortController) {
    abortController.abort();
    abortController = null;
  }
  state.currentPage = 1;
  state.allLoaded = false;
  state.nextCursor = '';
  state.filtersActive = true;
  state.isLoading = false;
  prefetchCache.clear();
  if (prefetchController) {
    prefetchController.abort();
    prefetchController = null;
  }
  inflightPrefetch = null;
  const grid = document.querySelector<HTMLElement>(
    '.wp-block-woocommerce-product-template'
  );
  clearProductGridSpacer(grid);
}

/** Release loading state when a coordinated filter/sort request fails. */
function handleFetchFailure(): void {
  state.isLoading = false;
  state.announcement = '';
}

/**
 * Load the next page of products.
 */
function loadNextPage(): void {
  if (!state.nextCursor || state.isLoading || state.allLoaded) return;

  state.isLoading = true;
  // Announce the wait to assistive tech (the spinner itself is aria-hidden).
  state.announcement = state.loadingText;
  const nextPage = state.currentPage + 1;
  lastFetchAt = Date.now();

  if (state.filtersActive) {
    document.dispatchEvent(
      new CustomEvent('aa:load-more-page', {
        detail: { page: nextPage, cursor: state.nextCursor },
      })
    );
    return;
  }

  loadSsrPage(nextPage, state.nextCursor);
}

/**
 * Build the REST endpoint URL for the given page/cursor.
 */
function buildUrl(page: number, cursor: string): string {
  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));
  if (cursor) {
    params.set('cursor', cursor);
  }

  // Prefer SSR-seeded orderby over the dropdown's "Default sorting" value so
  // a menu_order select cannot remount a date-seeded cursor (sparse dedupe).
  const sortSelect = document.querySelector<HTMLSelectElement>(
    '.woocommerce-ordering select, select[name="orderby"]'
  );
  const orderby = resolveContinuationOrderby(
    state.orderby,
    sortSelect?.value ?? '',
    new URLSearchParams(window.location.search).get('orderby') ?? ''
  );
  params.set('orderby', orderby);

  if (state.currentTaxonomy && state.currentTerm) {
    params.set('taxonomy', state.currentTaxonomy);
    params.set('term', state.currentTerm);
  }

  if (state.templateSlug) {
    params.set('template', state.templateSlug);
  }

  return `${state.restBase}?${params}`;
}

/** Prefetch only after the shopper shows intent to scroll the catalog. */
function armPrefetchOnScroll(): void {
  if (prefetchArmed || state.allLoaded || state.filtersActive) return;

  const arm = (): void => {
    if (prefetchArmed) return;
    prefetchArmed = true;
    window.removeEventListener('scroll', arm, {
      capture: true,
    } as EventListenerOptions);
    prefetchNextPage();
  };

  window.addEventListener('scroll', arm, { passive: true, once: true });
}

/**
 * Silently prefetch the next page in the background so it is ready
 * before the IntersectionObserver fires.
 */
async function prefetchNextPage(): Promise<void> {
  if (state.filtersActive || state.allLoaded || !state.nextCursor) return;

  const cursor = state.nextCursor;
  if (prefetchCache.has(cursor) || inflightPrefetch?.cursor === cursor) return;

  if (prefetchController) prefetchController.abort();
  prefetchController = new AbortController();
  const signal = prefetchController.signal;

  const promise = (async (): Promise<RenderedEntry | null> => {
    try {
      const res = await fetch(buildUrl(state.currentPage + 1, cursor), {
        signal,
        priority: 'low',
        headers: state.restNonce ? { 'X-WP-Nonce': state.restNonce } : {},
      } as RequestInit);

      if (!res.ok) return null;

      const data = (await res.json()) as RenderedEntry;
      prefetchCache.set(cursor, data);
      return data;
    } catch {
      // Silently discard — loadSsrPage will fetch again when needed.
      return null;
    } finally {
      if (inflightPrefetch?.cursor === cursor) {
        inflightPrefetch = null;
      }
    }
  })();

  inflightPrefetch = { cursor, promise };
  await promise;
}

/**
 * Fetch (or serve from cache / in-flight prefetch) a rendered page and insert it.
 */
function loadSsrPage(page: number, cursor: string): void {
  const cached = prefetchCache.get(cursor);
  if (cached) {
    prefetchCache.delete(cursor);
    applyRenderedPage(page, cached);
    return;
  }

  if (inflightPrefetch?.cursor === cursor) {
    inflightPrefetch.promise
      .then(data => {
        if (data) {
          prefetchCache.delete(cursor);
          applyRenderedPage(page, data);
          return;
        }
        fetchRenderedPage(page, cursor);
      })
      .catch(() => {
        fetchRenderedPage(page, cursor);
      });
    return;
  }

  fetchRenderedPage(page, cursor);
}

/**
 * Network fetch for a rendered page (used when prefetch is unavailable).
 */
function fetchRenderedPage(page: number, cursor: string): void {
  if (abortController) abortController.abort();
  abortController = new AbortController();

  fetch(buildUrl(page, cursor), {
    signal: abortController.signal,
    headers: state.restNonce ? { 'X-WP-Nonce': state.restNonce } : {},
  })
    .then((res: Response) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json() as Promise<RenderedEntry>;
    })
    .then((data: RenderedEntry) => {
      applyRenderedPage(page, data);
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
      state.mode = 'load_more';
      state.announcement = state.errorText;
    });
}

/**
 * Insert server-rendered product HTML into the grid and update state.
 */
function applyRenderedPage(page: number, data: RenderedEntry): void {
  const grid = document.querySelector<HTMLElement>(
    '.wp-block-woocommerce-product-template'
  );
  if (!grid || !data.html) {
    // Empty page (no more products, or gated) — settle the UI cleanly.
    state.isLoading = false;
    state.allLoaded = true;
    state.nextCursor = '';
    return;
  }

  installBlockSupportStyles(data.styles);

  // Count inserted <li> elements to update loadedCount accurately.
  const temp = document.createElement('ul');
  temp.innerHTML = data.html;
  const knownKeys = new Set(
    Array.from(
      grid.querySelectorAll<HTMLElement>(
        ':scope > li:not(.aa-product-grid__spacer)'
      )
    )
      .map(productCardKey)
      .filter(Boolean)
  );
  const incomingKeys = new Set<string>();
  Array.from(temp.querySelectorAll<HTMLElement>(':scope > li')).forEach(
    card => {
      const key = productCardKey(card);
      if (!key || knownKeys.has(key) || incomingKeys.has(key)) {
        card.remove();
        return;
      }
      incomingKeys.add(key);
    }
  );
  const count = temp.children.length;

  if (count > 0) {
    grid.insertAdjacentHTML('beforeend', temp.innerHTML);
    pruneProductGrid(grid, state.perPage);
    notifyCardsRendered(grid);
  }

  state.totalProducts = data.total_products;
  state.totalPages = data.total_pages;
  state.currentPage = page;
  state.loadedCount += count;
  state.nextCursor = data.next_cursor || '';
  state.allLoaded = !state.nextCursor || page >= data.total_pages;
  state.isLoading = false;
  state.announcement = state.loadedFormat.replace('%d', String(count));

  prefetchNextPage();

  // IntersectionObserver only re-fires on edge transitions. After appending
  // cards the sentinel often stays inside rootMargin, so chain another load
  // when it is still near the viewport (fixes mobile stalls at ~16 of N).
  requestAnimationFrame(() => {
    continueInfiniteScrollIfNeeded();
  });
}

/**
 * Whether the infinite-scroll sentinel is within the observer's root margin.
 */
function isSentinelNearViewport(): boolean {
  const sentinel = document.querySelector<HTMLElement>(
    '.aa-load-more__sentinel'
  );
  if (!sentinel || sentinel.hasAttribute('hidden')) {
    return false;
  }

  const rect = sentinel.getBoundingClientRect();
  const viewport = window.innerHeight || 0;
  return rect.top <= viewport + SENTINEL_ROOT_MARGIN_PX;
}

/**
 * Continue loading while the sentinel remains near the viewport.
 */
function continueInfiniteScrollIfNeeded(): void {
  if (
    state.mode !== 'infinite_scroll' ||
    state.isLoading ||
    state.allLoaded ||
    !state.nextCursor
  ) {
    return;
  }

  if (!isSentinelNearViewport()) {
    return;
  }

  if (Date.now() - lastFetchAt < FETCH_COOLDOWN_MS) {
    window.setTimeout(() => {
      continueInfiniteScrollIfNeeded();
    }, FETCH_COOLDOWN_MS);
    return;
  }

  loadNextPage();
}

/** Stable identity used to make dynamic card insertion idempotent. */
function productCardKey(card: HTMLElement): string {
  const postClass = [...card.classList].find(className =>
    /^post-\d+$/.test(className)
  );
  if (postClass) return postClass;

  return card.getAttribute('data-wp-key') ?? '';
}

/**
 * Set up IntersectionObserver for infinite scroll.
 */
function setupIntersectionObserver(): void {
  const sentinel = document.querySelector<HTMLElement>(
    '.aa-load-more__sentinel'
  );
  if (!sentinel) return;

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    state.mode = 'load_more';
    return;
  }

  if (observer) {
    observer.disconnect();
    observer = null;
  }

  observer = new IntersectionObserver(
    (entries: IntersectionObserverEntry[]) => {
      if (
        !entries[0]?.isIntersecting ||
        state.isLoading ||
        state.allLoaded ||
        !state.nextCursor
      ) {
        return;
      }
      if (Date.now() - lastFetchAt < FETCH_COOLDOWN_MS) {
        return;
      }
      loadNextPage();
    },
    { rootMargin: `${SENTINEL_ROOT_MARGIN_PX}px 0px` }
  );

  observer.observe(sentinel);

  // If the sentinel is already near the viewport (short catalogues), the
  // observer may not deliver an immediate callback — kick one check after layout.
  requestAnimationFrame(() => {
    continueInfiniteScrollIfNeeded();
  });
}
