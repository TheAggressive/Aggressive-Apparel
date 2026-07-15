/**
 * Load More / Infinite Scroll — Interactivity API Store
 *
 * Replaces standard pagination with a Load More button or automatic
 * infinite scroll via IntersectionObserver. Coordinates with product-filters
 * via custom events when AJAX filtering is active.
 *
 * For SSR mode, pages are fetched from the theme's custom REST endpoint
 * (aggressive-apparel/v1/products/rendered) which runs the full block
 * pipeline server-side — identical markup to the initial page render.
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
  installBlockSupportStyles,
  notifyCardsRendered,
} from '@aggressive-apparel/helpers';

interface LoadMoreState {
  mode: string;
  allLoaded: boolean;
  isLoading: boolean;
  totalProducts: number;
  totalPages: number;
  currentPage: number;
  loadedCount: number;
  perPage: number;
  restBase: string;
  restNonce: string;
  templateSlug: string;
  currentTaxonomy: string;
  currentTerm: string;
  filtersActive: boolean;
  announcement: string;
  loadingText: string;
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
}

/** Response shape from aggressive-apparel/v1/products/rendered */
interface RenderedEntry {
  html: string;
  styles?: Array<{ id: string; css: string; nonce?: string }>;
  total_products: number;
  total_pages: number;
}

interface LoadMoreStore {
  state: LoadMoreState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

let abortController: AbortController | null = null;
let prefetchController: AbortController | null = null;

/** Cache of pre-fetched rendered pages keyed by page number. */
const prefetchCache = new Map<number, RenderedEntry>();

let observer: IntersectionObserver | null = null;

const { state } = store<LoadMoreStore>('aggressive-apparel/load-more', {
  state: {
    get hideButton(): boolean {
      // Kept visible (disabled) while loading so keyboard focus isn't lost; the
      // button shows an in-place spinner via its is-loading class.
      return state.mode !== 'load_more' || state.allLoaded;
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
      if (state.isLoading || state.allLoaded) return;
      loadNextPage();
    },
  },

  callbacks: {
    init(): void {
      if (state.mode === 'infinite_scroll') {
        setupIntersectionObserver();
      }

      if (!state.allLoaded && !state.filtersActive) {
        setTimeout(prefetchNextPage, 150);
      }

      document.addEventListener(
        'aa:products-fetched',
        handleProductsFetched as EventListener
      );
      document.addEventListener('aa:filters-changed', handleFiltersChanged);
    },
  },
});

/**
 * Handle product-filters fetch completion.
 */
function handleProductsFetched(e: CustomEvent<ProductsFetchedDetail>): void {
  const { page, totalPages, totalProducts, append, productsCount } = e.detail;
  state.filtersActive = true;
  state.currentPage = page;
  state.totalPages = totalPages;
  state.totalProducts = totalProducts;
  state.allLoaded = page >= totalPages;
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
  state.currentPage = 1;
  state.allLoaded = false;
  state.filtersActive = true;
  prefetchCache.clear();
  if (prefetchController) {
    prefetchController.abort();
    prefetchController = null;
  }
}

/**
 * Load the next page of products.
 */
function loadNextPage(): void {
  state.isLoading = true;
  // Announce the wait to assistive tech (the spinner itself is aria-hidden).
  state.announcement = state.loadingText;
  const nextPage = state.currentPage + 1;

  if (state.filtersActive) {
    document.dispatchEvent(
      new CustomEvent('aa:load-more-page', { detail: { page: nextPage } })
    );
    return;
  }

  loadSsrPage(nextPage);
}

/**
 * Build the REST endpoint URL for the given page, matching current sort/category.
 */
function buildUrl(page: number): string {
  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));

  const sortSelect = document.querySelector<HTMLSelectElement>(
    '.woocommerce-ordering select, select[name="orderby"]'
  );
  if (sortSelect?.value) {
    params.set('orderby', sortSelect.value);
  }

  if (state.currentTaxonomy && state.currentTerm) {
    params.set('taxonomy', state.currentTaxonomy);
    params.set('term', state.currentTerm);
  }

  if (state.templateSlug) {
    params.set('template', state.templateSlug);
  }

  return `${state.restBase}?${params}`;
}

/**
 * Silently prefetch the next page in the background so it is ready
 * before the IntersectionObserver fires.
 */
async function prefetchNextPage(): Promise<void> {
  if (state.filtersActive || state.allLoaded) return;

  const nextPage = state.currentPage + 1;
  if (nextPage > state.totalPages || prefetchCache.has(nextPage)) return;

  if (prefetchController) prefetchController.abort();
  prefetchController = new AbortController();

  try {
    const res = await fetch(buildUrl(nextPage), {
      signal: prefetchController.signal,
      priority: 'low',
      headers: state.restNonce ? { 'X-WP-Nonce': state.restNonce } : {},
    } as RequestInit);

    if (!res.ok) return;

    const data = (await res.json()) as RenderedEntry;
    prefetchCache.set(nextPage, data);
  } catch {
    // Silently discard — loadSsrPage will fetch again when needed.
  }
}

/**
 * Fetch (or serve from cache) a rendered page and insert it into the grid.
 */
function loadSsrPage(page: number): void {
  const cached = prefetchCache.get(page);
  if (cached) {
    prefetchCache.delete(page);
    applyRenderedPage(page, cached);
    return;
  }

  if (abortController) abortController.abort();
  abortController = new AbortController();

  fetch(buildUrl(page), {
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
    return;
  }

  installBlockSupportStyles(data.styles);

  // Count inserted <li> elements to update loadedCount accurately.
  const temp = document.createElement('ul');
  temp.innerHTML = data.html;
  const count = temp.children.length;

  grid.insertAdjacentHTML('beforeend', data.html);
  notifyCardsRendered(grid);

  state.totalProducts = data.total_products;
  state.totalPages = data.total_pages;
  state.currentPage = page;
  state.loadedCount += count;
  state.allLoaded = page >= data.total_pages;
  state.isLoading = false;
  state.announcement = state.loadedFormat.replace('%d', String(count));

  prefetchNextPage();
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

  observer = new IntersectionObserver(
    (entries: IntersectionObserverEntry[]) => {
      if (entries[0].isIntersecting && !state.isLoading && !state.allLoaded) {
        loadNextPage();
      }
    },
    { rootMargin: '500px' }
  );

  observer.observe(sentinel);
}
