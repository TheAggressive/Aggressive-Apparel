/**
 * Load More / Infinite Scroll — Interactivity API Store
 *
 * Replaces standard pagination with a Load More button or automatic
 * infinite scroll via IntersectionObserver. Coordinates with product-filters
 * via custom events when AJAX filtering is active.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

import { store } from '@wordpress/interactivity';
import {
  parsePrice,
  decodeEntities,
  buildQuickViewTriggerHtml,
  buildWishlistHeartHtml,
  notifyCardsRendered,
  getCardEnhancements,
  applyViewTransitionToImgTag,
  buildBadgesHtml,
  buildCountdownHtml,
} from '@aggressive-apparel/helpers';
import type { PriceResult, StoreApiPrices } from '@aggressive-apparel/helpers';

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
  currentCategory: string;
  filtersActive: boolean;
  announcement: string;
  hoverImageAnimation: string;
  readonly hideButton: boolean;
  readonly hideSentinel: boolean;
  readonly statusText: string;
}

interface ProductsFetchedDetail {
  page: number;
  totalPages: number;
  totalProducts: number;
  append: boolean;
  productsCount: number;
}

interface StoreApiProduct {
  id: number;
  name: string;
  permalink: string;
  images?: Array<{ src?: string; thumbnail?: string; alt?: string }>;
  prices: StoreApiPrices;
  short_description?: string;
  stock_status?: string;
  extensions?: Record<string, unknown>;
}

interface SortEntry {
  orderby: string;
  order: string;
}

interface LoadMoreStore {
  state: LoadMoreState;
  actions: Record<string, (...args: any[]) => any>;
  callbacks: Record<string, (...args: any[]) => any>;
}

interface PrefetchEntry {
  products: StoreApiProduct[];
  totalProducts: number;
  totalPages: number;
}

/** Sort param map — module-level so buildUrl and loadSsrPage share it. */
const SORT_MAP: Record<string, SortEntry> = {
  popularity: { orderby: 'popularity', order: 'desc' },
  rating: { orderby: 'rating', order: 'desc' },
  date: { orderby: 'date', order: 'desc' },
  price: { orderby: 'price', order: 'asc' },
  'price-desc': { orderby: 'price', order: 'desc' },
  'title-asc': { orderby: 'title', order: 'asc' },
  'title-desc': { orderby: 'title', order: 'desc' },
};

let abortController: AbortController | null = null;
let prefetchController: AbortController | null = null;

/** Cache of pre-fetched pages keyed by page number. */
const prefetchCache = new Map<number, PrefetchEntry>();

let observer: IntersectionObserver | null = null;

const { state } = store<LoadMoreStore>('aggressive-apparel/load-more', {
  state: {
    get hideButton(): boolean {
      return state.mode !== 'load_more' || state.allLoaded;
    },

    get hideSentinel(): boolean {
      return state.mode !== 'infinite_scroll' || state.allLoaded;
    },

    get statusText(): string {
      if (state.totalProducts === 0) return '';
      return `Showing ${state.loadedCount} of ${state.totalProducts} products`;
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
      // Set up IntersectionObserver for infinite scroll.
      if (state.mode === 'infinite_scroll') {
        setupIntersectionObserver();
      }

      // Prefetch page 2 in the background so the first scroll trigger is
      // near-instant. Delay slightly so the initial paint isn't competing.
      if (!state.allLoaded && !state.filtersActive) {
        setTimeout(prefetchNextPage, 150);
      }

      // Listen for product-filters events.
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

  if (append) {
    state.loadedCount += productsCount || 0;
  } else {
    state.loadedCount = productsCount || 0;
  }
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
  const nextPage = state.currentPage + 1;

  if (state.filtersActive) {
    // Delegate to product-filters for filtered results.
    document.dispatchEvent(
      new CustomEvent('aa:load-more-page', { detail: { page: nextPage } })
    );
    return;
  }

  // SSR mode — fetch directly from Store API.
  loadSsrPage(nextPage);
}

/**
 * Build a Store API URL for the given page, matching current sort/category.
 */
function buildUrl(page: number): string {
  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));

  const sortSelect = document.querySelector<HTMLSelectElement>(
    '.woocommerce-ordering select, select[name="orderby"]'
  );
  if (sortSelect) {
    const sort: SortEntry = SORT_MAP[sortSelect.value] || {
      orderby: 'date',
      order: 'desc',
    };
    params.set('orderby', sort.orderby);
    params.set('order', sort.order);
  }

  if (state.currentCategory) {
    params.set('category', state.currentCategory);
  }

  return `${state.restBase}?${params}`;
}

/**
 * Silently prefetch the next page in the background so it is ready
 * before the IntersectionObserver fires. Uses fetch priority "low" to
 * avoid competing with foreground requests.
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
    } as RequestInit);

    if (!res.ok) return;

    const totalProducts = parseInt(res.headers.get('X-WP-Total') || '0', 10);
    const totalPages = parseInt(res.headers.get('X-WP-TotalPages') || '1', 10);
    const products: StoreApiProduct[] = await res.json();

    prefetchCache.set(nextPage, { products, totalProducts, totalPages });
  } catch {
    // Silently discard — loadSsrPage will fetch again when needed.
  }
}

/**
 * Fetch a page of products in SSR mode (no filters active).
 * Serves from the prefetch cache when available for near-instant rendering.
 */
function loadSsrPage(page: number): void {
  // Serve instantly from prefetch cache if the page is already in memory.
  const cached = prefetchCache.get(page);
  if (cached) {
    prefetchCache.delete(page);
    state.totalProducts = cached.totalProducts;
    state.totalPages = cached.totalPages;
    appendProductsToSsrGrid(cached.products);
    state.currentPage = page;
    state.loadedCount += cached.products.length;
    state.allLoaded = page >= state.totalPages;
    state.isLoading = false;
    state.announcement = `${cached.products.length} more products loaded.`;
    prefetchNextPage();
    return;
  }

  if (abortController) abortController.abort();
  abortController = new AbortController();

  fetch(buildUrl(page), { signal: abortController.signal })
    .then((res: Response) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      state.totalProducts = parseInt(res.headers.get('X-WP-Total') || '0', 10);
      state.totalPages = parseInt(
        res.headers.get('X-WP-TotalPages') || '1',
        10
      );

      return res.json();
    })
    .then((products: StoreApiProduct[]) => {
      appendProductsToSsrGrid(products);
      state.currentPage = page;
      state.loadedCount += products.length;
      state.allLoaded = page >= state.totalPages;
      state.isLoading = false;
      state.announcement = `${products.length} more products loaded.`;
      prefetchNextPage();
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
    });
}

/**
 * Escape HTML special characters.
 */
function escapeHtml(str: string): string {
  if (!str) return '';
  const el = document.createElement('div');
  el.appendChild(document.createTextNode(str));
  return el.innerHTML;
}

/**
 * Append products to the SSR grid container.
 *
 * Mirrors the SSR card output enough to inherit existing styling,
 * and embeds every card-level enhancement (Quick View trigger,
 * Wishlist heart, Product Badges, view-transition name, sale Countdown)
 * so dynamically appended cards behave identically to the initial
 * server-rendered ones.
 *
 * The image wrapper carries `wp-block-post-featured-image` so the
 * existing positioning/hover CSS for those buttons applies cleanly.
 */
function appendProductsToSsrGrid(products: StoreApiProduct[]): void {
  const ssrGrid = document.querySelector<HTMLElement>(
    '.wp-block-woocommerce-product-template'
  );
  if (!ssrGrid) return;

  // Collect all cards into a fragment before touching the live DOM so
  // the browser only does one reflow/repaint for the whole batch.
  const fragment = document.createDocumentFragment();

  products.forEach((p: StoreApiProduct) => {
    const name = decodeEntities(p.name);
    const imgSrc = p.images?.[0]?.src || p.images?.[0]?.thumbnail || '';
    const imgAlt = decodeEntities(p.images?.[0]?.alt || p.name);
    const price: PriceResult = parsePrice(p.prices);
    const priceHtml = price.onSale
      ? `<del>${escapeHtml(price.regular)}</del> <ins>${escapeHtml(price.current)}</ins>`
      : escapeHtml(price.current);

    const enhancements = getCardEnhancements(p);
    const quickViewBtn = buildQuickViewTriggerHtml(p.id, name);
    const wishlistBtn = buildWishlistHeartHtml(p.id);
    const badgesHtml = buildBadgesHtml(enhancements);
    const countdownHtml = buildCountdownHtml(enhancements);

    const imgTag = applyViewTransitionToImgTag(
      `<img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(imgAlt)}" loading="lazy" width="400" height="400" />`,
      enhancements
    );

    // Mirror the PHP hover-image injection for dynamically loaded cards.
    // images[0] is the featured image; images[1] is the first gallery image.
    // Prefer src (medium/full) over thumbnail to avoid upscale blurriness.
    const hoverSrc = p.images?.[1]?.src || p.images?.[1]?.thumbnail || '';
    const hoverImgHtml =
      state.hoverImageAnimation && hoverSrc
        ? `<div class="aa-hover-img aa-hover-img--${escapeHtml(state.hoverImageAnimation)}" aria-hidden="true">` +
          `<img class="aa-hover-img__secondary" src="${escapeHtml(hoverSrc)}" alt="" loading="lazy" width="400" height="400" />` +
          `</div>`
        : '';

    const li = document.createElement('li');
    li.className = 'product wc-block-product';
    li.innerHTML =
      `<figure class="wc-block-components-product-image wp-block-post-featured-image">` +
      `<a href="${escapeHtml(p.permalink)}">${imgTag}</a>` +
      `${badgesHtml}${quickViewBtn}${wishlistBtn}${hoverImgHtml}` +
      `</figure>` +
      `<h3 class="wc-block-components-product-name"><a href="${escapeHtml(p.permalink)}">${escapeHtml(name)}</a></h3>` +
      `<div class="wc-block-components-product-price">${priceHtml}${countdownHtml}</div>`;
    fragment.appendChild(li);
  });

  ssrGrid.appendChild(fragment);
  notifyCardsRendered(ssrGrid);
}

/**
 * Set up IntersectionObserver for infinite scroll.
 */
function setupIntersectionObserver(): void {
  const sentinel = document.querySelector<HTMLElement>(
    '.aa-load-more__sentinel'
  );
  if (!sentinel) return;

  // Respect reduced motion preference — fall back to button.
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
