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
import { parsePrice, decodeEntities } from '@aggressive-apparel/helpers';
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

let abortController: AbortController | null = null;

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
 * Handle filter change — reset to page 1.
 */
function handleFiltersChanged(): void {
  state.currentPage = 1;
  state.allLoaded = false;
  state.filtersActive = true;
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
 * Fetch a page of products in SSR mode (no filters active).
 */
function loadSsrPage(page: number): void {
  if (abortController) abortController.abort();
  abortController = new AbortController();

  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));

  // Respect current sort from the dropdown.
  const sortSelect = document.querySelector<HTMLSelectElement>(
    '.woocommerce-ordering select, select[name="orderby"]'
  );
  if (sortSelect) {
    const sortMap: Record<string, SortEntry> = {
      popularity: { orderby: 'popularity', order: 'desc' },
      rating: { orderby: 'rating', order: 'desc' },
      date: { orderby: 'date', order: 'desc' },
      price: { orderby: 'price', order: 'asc' },
      'price-desc': { orderby: 'price', order: 'desc' },
      'title-asc': { orderby: 'title', order: 'asc' },
      'title-desc': { orderby: 'title', order: 'desc' },
    };
    const sort: SortEntry = sortMap[sortSelect.value] || {
      orderby: 'date',
      order: 'desc',
    };
    params.set('orderby', sort.orderby);
    params.set('order', sort.order);
  }

  // Include current category if on taxonomy page.
  if (state.currentCategory) {
    params.set('category', state.currentCategory);
  }

  const url = `${state.restBase}?${params}`;

  fetch(url, { signal: abortController.signal })
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
 */
function appendProductsToSsrGrid(products: StoreApiProduct[]): void {
  const ssrGrid = document.querySelector<HTMLElement>(
    '.wp-block-woocommerce-product-template'
  );
  if (!ssrGrid) return;

  products.forEach((p: StoreApiProduct) => {
    const name = decodeEntities(p.name);
    const imgSrc = p.images?.[0]?.src || p.images?.[0]?.thumbnail || '';
    const imgAlt = decodeEntities(p.images?.[0]?.alt || p.name);
    const price: PriceResult = parsePrice(p.prices);
    const priceHtml = price.onSale
      ? `<del>${escapeHtml(price.regular)}</del> <ins>${escapeHtml(price.current)}</ins>`
      : escapeHtml(price.current);

    const li = document.createElement('li');
    li.className = 'product wc-block-product';
    li.innerHTML = `<figure class="wc-block-components-product-image"><a href="${escapeHtml(p.permalink)}"><img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(imgAlt)}" loading="lazy" width="400" height="400" /></a></figure>
      <h3 class="wc-block-components-product-name"><a href="${escapeHtml(p.permalink)}">${escapeHtml(name)}</a></h3>
      <div class="wc-block-components-product-price">${priceHtml}</div>`;
    ssrGrid.appendChild(li);
  });
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
    { rootMargin: '200px' }
  );

  observer.observe(sentinel);
}
