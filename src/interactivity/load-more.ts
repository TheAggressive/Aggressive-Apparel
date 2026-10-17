/**
 * Load More / Infinite Scroll — Interactivity API Store
 *
 * Replaces standard pagination with a Load More button or automatic
 * infinite scroll via IntersectionObserver. Each load fetches the archive's
 * own next page (the native pagination link) and appends its product cards,
 * so appended cards always match what that page renders.
 *
 * This file is the DOM side; when and what to request is CatalogPager's.
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
import { fetchPageHtml, PageLoadError } from './load-more/request';
import { CatalogPager } from './load-more/pager';
import {
  isBackForwardLoad,
  saveCatalog,
  savedCatalog,
  type SavedCatalog,
} from './load-more/history';

interface LoadMoreState {
  mode: LoadMoreSeed['mode'];
  allLoaded: boolean;
  isLoading: boolean;
  loadFailed: boolean;
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

const FETCH_COOLDOWN_MS = 400;

/** Marks cards appended by Load More (not part of the server render). */
const APPENDED = 'data-aa-appended';

let observer: IntersectionObserver | null = null;

/** Unique suffix for each fetched page's per-request class names. */
let pageScope = 0;

/** Complete request seed currently installed in the long-lived module store. */
let activeSeedFingerprint: string | null = null;

/** Pages appended to the current catalog, saved for Back. */
let appendedPages: string[] = [];

/** Pages still to re-append while rebuilding the grid after Back. */
let restoring: SavedCatalog | null = null;

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
      pager.load();
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

        const firstRender = activeSeedFingerprint === null;
        if (!firstRender) {
          removeAppendedCards();
        }
        pager.reset();
        observer?.disconnect();
        observer = null;
        window.removeEventListener('scroll', prefetchOnScroll, true);
        applyLoadMoreSeed(state, seed);
        activeSeedFingerprint = fingerprint;
        appendedPages = [];

        if (firstRender && startRestore()) return;
        startLoading();
      }, [fingerprint, seed]);
    },
  },
});

const pager = new CatalogPager(state, {
  request: requestPage,
  append: appendPage,
  started: () => {
    // Announce the wait to assistive tech (the spinner itself is aria-hidden).
    state.announcement = state.loadingText;
  },
  failed: () => {
    state.mode = 'load_more';
    state.announcement = state.errorText;
    finishRestore();
  },
  prefetched: () => {
    // A fast prefetch can finish after the observer's last edge event.
    // Recheck the sentinel so the final page cannot stay stranded.
    const token = pager.token;
    requestAnimationFrame(() => continueInfiniteScrollIfNeeded(token));
  },
});

// Save where the shopper was, for Back.
window.addEventListener('pagehide', () => {
  if (appendedPages.length && !restoring) saveForBack();
});

/** Watch the sentinel (infinite scroll) and prefetch on the first scroll. */
function startLoading(): void {
  if (state.mode === 'infinite_scroll') {
    setupIntersectionObserver();
  }
  if (!state.allLoaded) {
    window.addEventListener('scroll', prefetchOnScroll, {
      capture: true,
      passive: true,
      once: true,
    });
  }
}

function prefetchOnScroll(): void {
  pager.prefetch();
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

/**
 * Fetch and parse one catalog page.
 *
 * @throws {PageLoadError} When it failed, or is not a catalog page (e.g. a
 *                         firewall challenge or maintenance page served
 *                         with 200) — never mistaken for the end.
 */
async function requestPage(
  url: string,
  signal: AbortSignal,
  priority: 'auto' | 'low'
): Promise<CatalogPage> {
  const response = await fetchPageHtml(url, signal, priority);

  pageScope += 1;
  const page = parseCatalogPage(response.html, response.url, `aa${pageScope}`);
  if (!page) {
    throw new PageLoadError('Not a catalog page', true);
  }
  return page;
}

/**
 * Append a fetched page's new cards and advance to the page after it.
 *
 * @param url  URL the page was requested from.
 * @param page Parsed page.
 */
function appendPage(url: string, page: CatalogPage): void {
  const grid = findCatalogGrid(document);
  if (!grid) {
    // The catalog left the page (e.g. replaced by a navigation).
    state.isLoading = false;
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

  appendedPages.push(new URL(url, window.location.href).href);
  if (restoring) {
    continueRestore();
    return;
  }
  saveForBack();
  pager.prefetch();

  // IntersectionObserver only re-fires on edge transitions. After appending
  // cards the sentinel often stays inside rootMargin, so chain another load
  // when it is still near the viewport (fixes mobile stalls at ~16 of N).
  const token = pager.token;
  requestAnimationFrame(() => continueInfiniteScrollIfNeeded(token));
}

/** Record the appended pages and scroll position in this history entry. */
function saveForBack(): void {
  window.history.replaceState(
    saveCatalog(window.history.state, {
      pages: appendedPages,
      scrollY: window.scrollY,
    }),
    ''
  );
}

/**
 * After Back reloads the page (no back/forward cache), re-append the pages
 * the shopper had loaded, then return to where they were.
 *
 * @return Whether a restore started.
 */
function startRestore(): boolean {
  const saved = isBackForwardLoad() ? savedCatalog(window.history.state) : null;
  if (!saved || !sameUrl(saved.pages[0], state.nextUrl)) return false;

  restoring = saved;
  window.history.scrollRestoration = 'manual';
  pager.load();
  return true;
}

/** Load the next saved page; finish when none is left or the catalog changed. */
function continueRestore(): void {
  const pages = restoring?.pages ?? [];
  const next = pages[appendedPages.length];
  if (next && sameUrl(next, state.nextUrl)) {
    pager.load();
    return;
  }
  finishRestore();
}

function finishRestore(): void {
  if (!restoring) return;

  const { scrollY } = restoring;
  restoring = null;
  saveForBack();
  window.history.scrollRestoration = 'auto';
  window.scrollTo(0, scrollY);
  startLoading();
}

function sameUrl(a: string | undefined, b: string): boolean {
  if (!a || !b) return false;
  return (
    new URL(a, window.location.href).href ===
    new URL(b, window.location.href).href
  );
}

/**
 * Continue loading while the sentinel remains near the viewport.
 *
 * @param token Pager token the check was scheduled for.
 */
function continueInfiniteScrollIfNeeded(token = pager.token): void {
  if (!pager.isCurrent(token) || restoring) return;

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
    msSinceLastFetch: pager.msSinceLastLoad,
    cooldownMs: FETCH_COOLDOWN_MS,
  });

  if (decision === 'wait') {
    window.setTimeout(() => {
      continueInfiniteScrollIfNeeded(token);
    }, FETCH_COOLDOWN_MS);
    return;
  }

  if (decision === 'load') {
    pager.load();
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
    entries => {
      // The shared decision also retries after the cooldown: a sentinel that
      // enters the margin mid-cooldown may never re-intersect.
      if (entries[0]?.isIntersecting) continueInfiniteScrollIfNeeded();
    },
    { rootMargin: `${SENTINEL_ROOT_MARGIN_PX}px 0px` }
  );

  observer.observe(sentinel);

  // If the sentinel is already near the viewport (short catalogues), the
  // observer may not deliver an immediate callback — kick one check after layout.
  const token = pager.token;
  requestAnimationFrame(() => continueInfiniteScrollIfNeeded(token));
}
