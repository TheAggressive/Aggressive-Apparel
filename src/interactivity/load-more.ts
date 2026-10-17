/**
 * Load More / Infinite Scroll — Interactivity API Store
 *
 * Replaces standard pagination with a Load More button or automatic
 * infinite scroll via IntersectionObserver. Each load fetches the archive's
 * own next page (the native pagination link) and appends its product cards,
 * so appended cards always match what that page renders.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../types/interactivity-shared';

import {
  getServerContext,
  store,
  useLayoutEffect,
} from '@wordpress/interactivity';
import {
  notifyCardsRendered,
  pruneProductGrid,
} from '@aggressive-apparel/helpers';
import {
  SENTINEL_ROOT_MARGIN_PX,
  shouldContinueInfiniteScroll,
  shouldHideLoadMoreButton,
} from './load-more/continue';
import {
  applyLoadMoreSeed,
  getLoadMoreSeedFingerprint,
  type LoadMoreSeed,
} from './load-more/seed';
import {
  catalogCards,
  findCatalogGrid,
  installCatalogStyles,
  parseCatalogPage,
  type CatalogPage,
} from './load-more/catalog-page';

interface LoadMoreState {
  mode: LoadMoreSeed['mode'];
  allLoaded: boolean;
  isLoading: boolean;
  totalProducts: number;
  currentPage: number;
  loadedCount: number;
  perPage: number;
  nextUrl: string;
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

interface LoadMoreStore {
  state: LoadMoreState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

/** A page request, shared by the scroll prefetch and the load that uses it. */
interface PageRequest {
  url: string;
  generation: number;
  promise: Promise<CatalogPage | null>;
}

let loadController: AbortController | null = null;
let prefetchController: AbortController | null = null;
let prefetched: PageRequest | null = null;

let observer: IntersectionObserver | null = null;
let lastFetchAt = 0;
let prefetchArmed = false;
let prefetchScrollBound = false;
let paginationGeneration = 0;

/** Unique suffix for each fetched page's per-request class names. */
let pageScope = 0;

/** Complete request seed currently installed in the long-lived module store. */
let activeSeedFingerprint: string | null = null;

const FETCH_COOLDOWN_MS = 400;

/** Marks cards appended by Load More (not part of the server render). */
const APPENDED = 'data-aa-appended';

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
      loadNextPage();
    },
  },

  callbacks: {
    /**
     * Install the request-scoped seed on first render and after each router
     * navigation (filters, sort, back/forward).
     *
     * Unlike getContext(), getServerContext() is invalidated by the WordPress
     * router when it renders a region, also when it reuses this element.
     * Runs in render (`data-wp-run`), as a hook, so the layout effect applies
     * the new page before the browser paints it — see removeAppendedCards().
     */
    useServerSeed(): void {
      const seed = getServerContext<Partial<LoadMoreSeed>>();
      const fingerprint = getLoadMoreSeedFingerprint(seed);

      useLayoutEffect(() => {
        if (!fingerprint || fingerprint === activeSeedFingerprint) return;

        if (activeSeedFingerprint !== null) {
          removeAppendedCards();
        }
        resetPaginationRuntime(seed);
        activeSeedFingerprint = fingerprint;
        synchronizeInitialGridState();

        if (state.mode === 'infinite_scroll') {
          setupIntersectionObserver();
        }

        armPrefetchOnScroll();
      }, [fingerprint, seed]);
    },
  },
});

/**
 * Replace long-lived store state with the seed attached to this rendered root.
 *
 * Router navigation preserves the JS module but replaces the archive markup.
 * Invalidating all asynchronous work here prevents a page fetched for the
 * previous catalog from being appended to the new one.
 */
function resetPaginationRuntime(seed: Partial<LoadMoreSeed>): void {
  paginationGeneration += 1;

  loadController?.abort();
  loadController = null;
  prefetchController?.abort();
  prefetchController = null;
  prefetched = null;
  observer?.disconnect();
  observer = null;
  prefetchArmed = false;

  if (prefetchScrollBound) {
    window.removeEventListener('scroll', handlePrefetchScroll, true);
    prefetchScrollBound = false;
  }

  applyLoadMoreSeed(state, seed);
}

/**
 * Remove the cards (and prune spacer) Load More added to the previous page.
 *
 * The router re-renders a region with Preact, which keeps DOM nodes it did
 * not create: without this, the previous page's appended cards would stay
 * beside the new page's cards.
 */
function removeAppendedCards(): void {
  document
    .querySelectorAll(`[${APPENDED}], .aa-product-grid__spacer`)
    .forEach(node => node.remove());
}

/** Align page size with the Product Collection actually rendered. */
function synchronizeInitialGridState(): void {
  const grid = findCatalogGrid(document);
  const renderedCount = grid ? catalogCards(grid).length : 0;
  if (renderedCount > 0) {
    state.perPage = renderedCount;
  }
}

/** Fetch and parse one catalog page. */
async function requestPage(
  url: string,
  signal: AbortSignal,
  priority: 'auto' | 'low' = 'auto'
): Promise<CatalogPage | null> {
  const response = await fetch(url, {
    signal,
    priority,
    mode: 'same-origin',
    credentials: 'same-origin',
    headers: { Accept: 'text/html' },
  } as RequestInit);
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  pageScope += 1;
  return parseCatalogPage(
    await response.text(),
    response.url || url,
    `aa${pageScope}`
  );
}

/** Load the next page, reusing its prefetch when one is pending or done. */
function loadNextPage(): void {
  if (state.isLoading || state.allLoaded || !state.nextUrl) return;

  const url = state.nextUrl;
  const generation = paginationGeneration;
  state.isLoading = true;
  // Announce the wait to assistive tech (the spinner itself is aria-hidden).
  state.announcement = state.loadingText;
  lastFetchAt = Date.now();

  const pending =
    prefetched?.url === url && prefetched.generation === generation
      ? prefetched.promise
      : Promise.resolve(null);
  prefetched = null;

  pending
    .catch(() => null)
    .then(page => {
      if (page) return page;
      loadController?.abort();
      loadController = new AbortController();
      return requestPage(url, loadController.signal);
    })
    .then(page => {
      if (generation !== paginationGeneration) return;
      appendPage(url, page, generation);
    })
    .catch((error: Error) => {
      if (error.name === 'AbortError' || generation !== paginationGeneration) {
        return;
      }
      state.isLoading = false;
      state.mode = 'load_more';
      state.announcement = state.errorText;
    });
}

/** Prefetch only after the shopper shows intent to scroll the catalog. */
function armPrefetchOnScroll(): void {
  if (prefetchArmed || prefetchScrollBound || state.allLoaded) return;

  prefetchScrollBound = true;
  window.addEventListener('scroll', handlePrefetchScroll, {
    capture: true,
    passive: true,
  });
}

/** Arm prefetch exactly once for the current pagination generation. */
function handlePrefetchScroll(): void {
  window.removeEventListener('scroll', handlePrefetchScroll, true);
  prefetchScrollBound = false;
  if (prefetchArmed) return;

  prefetchArmed = true;
  prefetchNextPage();
}

/**
 * Fetch the next page in the background so it is ready before the
 * IntersectionObserver (or the button) asks for it.
 */
function prefetchNextPage(): void {
  const url = state.nextUrl;
  const generation = paginationGeneration;
  // A load in flight is already fetching this URL.
  if (state.isLoading || state.allLoaded || !url) return;
  if (prefetched?.url === url && prefetched.generation === generation) return;

  prefetchController?.abort();
  prefetchController = new AbortController();

  const promise = requestPage(url, prefetchController.signal, 'low').then(
    page => {
      // A fast prefetch can finish after the observer's last edge event.
      // Recheck the sentinel so the final page cannot stay stranded.
      requestAnimationFrame(() => {
        continueInfiniteScrollIfNeeded(generation);
      });
      return page;
    }
  );
  // Failures are retried by the load itself.
  promise.catch(() => undefined);

  prefetched = { url, generation, promise };
}

/**
 * Append a fetched page's new cards and advance to the page after it.
 *
 * @param url        URL the page was requested from.
 * @param page       Parsed page, or null when it has no catalog (past the end).
 * @param generation Pagination generation the request belongs to.
 */
function appendPage(
  url: string,
  page: CatalogPage | null,
  generation: number
): void {
  const grid = findCatalogGrid(document);
  if (!grid || !page) {
    // Nothing more to show — settle the UI cleanly.
    state.isLoading = false;
    state.allLoaded = true;
    state.nextUrl = '';
    state.announcement = '';
    return;
  }

  // Cards already shown (a product published or moved since the previous
  // page was rendered shifts later pages) are skipped, never repeated.
  const known = new Set(catalogCards(grid).map(productCardKey));
  const cards = page.cards.filter(card => {
    const key = productCardKey(card);
    if (!key || known.has(key)) return false;
    known.add(key);
    return true;
  });

  installCatalogStyles(page);
  cards.forEach(card => card.setAttribute(APPENDED, ''));
  if (cards.length > 0) {
    grid.append(...cards);
    pruneProductGrid(grid, state.perPage);
    notifyCardsRendered(grid);
  }

  // A next link pointing back at this page would loop forever.
  const nextUrl = page.seed.nextUrl === url ? '' : page.seed.nextUrl;

  state.totalProducts = page.seed.totalProducts;
  state.currentPage = page.seed.currentPage;
  state.loadedCount = Math.min(
    state.loadedCount + cards.length,
    state.totalProducts
  );
  state.nextUrl = nextUrl;
  state.allLoaded = page.seed.allLoaded || nextUrl === '';
  state.isLoading = false;
  state.announcement = state.loadedFormat.replace('%d', String(cards.length));

  prefetchNextPage();

  // IntersectionObserver only re-fires on edge transitions. After appending
  // cards the sentinel often stays inside rootMargin, so chain another load
  // when it is still near the viewport (fixes mobile stalls at ~16 of N).
  requestAnimationFrame(() => {
    continueInfiniteScrollIfNeeded(generation);
  });
}

/**
 * Continue loading while the sentinel remains near the viewport.
 */
function continueInfiniteScrollIfNeeded(
  generation = paginationGeneration
): void {
  if (generation !== paginationGeneration) return;

  const sentinel = document.querySelector<HTMLElement>(
    '.aa-load-more__sentinel'
  );
  const decision = shouldContinueInfiniteScroll({
    mode: state.mode,
    isLoading: state.isLoading,
    allLoaded: state.allLoaded,
    nextUrl: state.nextUrl,
    sentinelHidden: !sentinel || sentinel.hasAttribute('hidden'),
    sentinelTop:
      sentinel?.getBoundingClientRect().top ?? Number.POSITIVE_INFINITY,
    viewportHeight: window.innerHeight || 0,
    msSinceLastFetch: Date.now() - lastFetchAt,
    cooldownMs: FETCH_COOLDOWN_MS,
  });

  if (decision === 'wait') {
    window.setTimeout(() => {
      continueInfiniteScrollIfNeeded(generation);
    }, FETCH_COOLDOWN_MS);
    return;
  }

  if (decision === 'load') {
    loadNextPage();
  }
}

/** Stable identity used to make card insertion idempotent. */
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

  observer?.disconnect();
  observer = new IntersectionObserver(
    (entries: IntersectionObserverEntry[]) => {
      if (!entries[0]?.isIntersecting) return;

      // Use the shared continuation decision instead of duplicating a
      // fail-open cooldown check here. When the sentinel enters the root margin
      // during the cooldown it may remain intersecting indefinitely, so an
      // immediate return would strand the catalog until the user scrolls away
      // and back. The helper schedules a post-cooldown retry for that state.
      continueInfiniteScrollIfNeeded();
    },
    { rootMargin: `${SENTINEL_ROOT_MARGIN_PX}px 0px` }
  );

  observer.observe(sentinel);

  // If the sentinel is already near the viewport (short catalogues), the
  // observer may not deliver an immediate callback — kick one check after layout.
  const generation = paginationGeneration;
  requestAnimationFrame(() => {
    continueInfiniteScrollIfNeeded(generation);
  });
}
