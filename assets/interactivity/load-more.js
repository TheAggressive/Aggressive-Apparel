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
import { parsePrice } from '@aggressive-apparel/helpers';

/** @type {AbortController|null} */
let abortController = null;

/** @type {IntersectionObserver|null} */
let observer = null;

const { state } = store('aggressive-apparel/load-more', {
  state: {
    get hideButton() {
      return state.mode !== 'load_more' || state.allLoaded;
    },

    get hideSentinel() {
      return state.mode !== 'infinite_scroll' || state.allLoaded;
    },

    get notAllLoaded() {
      return !state.allLoaded;
    },

    get statusText() {
      if (state.totalProducts === 0) return '';
      return `Showing ${state.loadedCount} of ${state.totalProducts} products`;
    },
  },

  actions: {
    loadMore() {
      if (state.isLoading || state.allLoaded) return;
      loadNextPage();
    },
  },

  callbacks: {
    init() {
      // Set up IntersectionObserver for infinite scroll.
      if (state.mode === 'infinite_scroll') {
        setupIntersectionObserver();
      }

      // Listen for product-filters events.
      document.addEventListener('aa:products-fetched', handleProductsFetched);
      document.addEventListener('aa:filters-changed', handleFiltersChanged);
    },
  },
});

/**
 * Handle product-filters fetch completion.
 *
 * @param {CustomEvent} e
 */
function handleProductsFetched(e) {
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
function handleFiltersChanged() {
  state.currentPage = 1;
  state.allLoaded = false;
  state.filtersActive = true;
}

/**
 * Load the next page of products.
 */
function loadNextPage() {
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
 *
 * @param {number} page
 */
function loadSsrPage(page) {
  if (abortController) abortController.abort();
  abortController = new AbortController();

  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));

  // Respect current sort from the dropdown.
  const sortSelect = document.querySelector(
    '.woocommerce-ordering select, select[name="orderby"]'
  );
  if (sortSelect) {
    const sortMap = {
      popularity: { orderby: 'popularity', order: 'desc' },
      rating: { orderby: 'rating', order: 'desc' },
      date: { orderby: 'date', order: 'desc' },
      price: { orderby: 'price', order: 'asc' },
      'price-desc': { orderby: 'price', order: 'desc' },
      'title-asc': { orderby: 'title', order: 'asc' },
      'title-desc': { orderby: 'title', order: 'desc' },
    };
    const sort = sortMap[sortSelect.value] || {
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
    .then(res => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      state.totalProducts = parseInt(res.headers.get('X-WP-Total') || '0', 10);
      state.totalPages = parseInt(
        res.headers.get('X-WP-TotalPages') || '1',
        10
      );

      return res.json();
    })
    .then(products => {
      appendProductsToSsrGrid(products);
      state.currentPage = page;
      state.loadedCount += products.length;
      state.allLoaded = page >= state.totalPages;
      state.isLoading = false;
      state.announcement = `${products.length} more products loaded.`;
    })
    .catch(err => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
    });
}

/**
 * Decode HTML entities in a string.
 *
 * @param {string} str
 * @returns {string}
 */
function decodeHtml(str) {
  if (!str) return '';
  const el = document.createElement('textarea');
  el.innerHTML = str;
  return el.value;
}

/**
 * Escape HTML special characters.
 *
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
  if (!str) return '';
  const el = document.createElement('div');
  el.appendChild(document.createTextNode(str));
  return el.innerHTML;
}

/**
 * Append products to the SSR grid container.
 *
 * @param {Array} products Store API product objects.
 */
function appendProductsToSsrGrid(products) {
  const ssrGrid = document.querySelector(
    '.wp-block-woocommerce-product-template'
  );
  if (!ssrGrid) return;

  products.forEach(p => {
    const name = decodeHtml(p.name);
    const imgSrc = p.images?.[0]?.src || p.images?.[0]?.thumbnail || '';
    const imgAlt = decodeHtml(p.images?.[0]?.alt || p.name);
    const price = parsePrice(p.prices);
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
function setupIntersectionObserver() {
  const sentinel = document.querySelector('.aa-load-more__sentinel');
  if (!sentinel) return;

  // Respect reduced motion preference — fall back to button.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    state.mode = 'load_more';
    return;
  }

  observer = new IntersectionObserver(
    entries => {
      if (entries[0].isIntersecting && !state.isLoading && !state.allLoaded) {
        loadNextPage();
      }
    },
    { rootMargin: '200px' }
  );

  observer.observe(sentinel);
}
