/**
 * Product Filters — fetch / AJAX + result-injection layer.
 *
 * Facet + product fetching (keyset + custom sort), rendered-endpoint params,
 * delegated events, and grid injection. Extracted from product-filters.ts to
 * stay under the file-length cap. Shares mutable runtime state via ./runtime
 * and drives the DOM via ./render; imports live state/actions from the entry.
 *
 * @package Aggressive_Apparel
 */

import {
  clearProductGridSpacer,
  installBlockSupportStyles,
  notifyCardsRendered,
  pruneProductGrid,
} from '@aggressive-apparel/helpers';
import { setFilterVisibility } from './dom';
import { requests, filterFlags } from './runtime';
import {
  renderHorizontalDropdowns,
  renderPagination,
  renderPills,
  setSaleStatus,
  syncCategoryChips,
  syncChipPressed,
  syncFitChipPressed,
  syncPressed,
  syncPriceRange,
  syncPriceSliders,
  syncStockCheckboxes,
  syncSwatchPressed,
} from './render';
import { ATTRIBUTE_FILTERS } from './types';
import type { Facets, RenderedResponse, SortedProductsResponse } from './types';
import {
  state,
  gridUl,
  removeArrayItem,
  scheduleFacetsUpdate,
  stageFilterChange,
  visibleSelectedCategories,
  hardNavigate,
  scrollToGrid,
  syncUrl,
} from '../product-filters';

export function fetchFacets(): void {
  const signal = requests.beginFacets();

  const params = buildRenderedParams(1);
  params.set('facets_only', '1');

  fetch(`${renderedEndpoint()}?${params}`, authFetchInit(signal))
    .then(res =>
      res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))
    )
    .then((data: { facets?: Facets }) => {
      if (data.facets) applyFacets(data.facets);
    })
    .catch(() => {
      // On failure leave availability as-is (fail open: everything selectable).
    });
}

/**
 * Enable only the colour/size/fit options that still have matching products.
 *
 * An option stays enabled if it's available OR currently selected, so a shopper
 * can always toggle their own picks back off without hitting a dead end.
 */
export function applyFacets(facets: Facets): void {
  let deselected = false;

  for (const filter of ATTRIBUTE_FILTERS) {
    const slugs = facets[filter.taxonomy];

    // Server couldn't evaluate this taxonomy — leave its options as-is.
    if (slugs === undefined) continue;

    const set = new Set(slugs);
    const selected = state[filter.stateKey];

    // Drop any selected value that no longer has matching products (e.g. a
    // colour that isn't available in the category just chosen). Disjunctive
    // faceting guarantees a still-valid selection stays in its own set, so a
    // miss here is genuinely unavailable.
    const stillValid = selected.filter(slug => set.has(slug));
    if (stillValid.length !== selected.length) {
      state[filter.stateKey] = stillValid;
      syncPressed(filter.selector, stillValid);
      deselected = true;
    }

    // Enable only the available options; everything else is dimmed/disabled.
    setFilterVisibility(filter.selector, (el: HTMLElement) =>
      set.has(el.dataset.filterValue || '')
    );
  }

  if (deselected) {
    // Reflect the dropped pills and re-settle the other facets, which may widen
    // now that an over-constraining pick is gone. Converges (selection shrinks).
    renderPills();
    scheduleFacetsUpdate();
  }
}

/**
 * Apply staged filter selections: sync the URL, then refresh the grid (or do a
 * full navigation for a cross-category change). No-op when nothing is staged.
 *
 * @return Whether a hard navigation was started.
 */
export function applyStagedFilters(): boolean {
  if (!filterFlags.staged) return false;
  filterFlags.staged = false;

  document.dispatchEvent(new CustomEvent('aa:filters-changed'));

  // syncUrl() pushes same-path changes and, for a cross-category switch, stages
  // a full navigation in filterFlags.pendingNavUrl (drawer) or navigates immediately.
  if (syncUrl()) return true;
  if (filterFlags.pendingNavUrl) {
    hardNavigate(filterFlags.pendingNavUrl);
    return true;
  }

  fetchProducts();
  return false;
}

/**
 * Absolute URL of the theme's block-rendered products endpoint.
 *
 * Derived from the Store API base so it works regardless of the site's REST
 * URL structure (plain vs. pretty permalinks).
 */
/**
 * Fetch init carrying the REST nonce so a logged-in shop manager/admin is
 * authenticated — required for previewing the gated catalogue while the store
 * is in "coming soon" mode (an unauthenticated fetch returns no products).
 */
export function authFetchInit(signal: AbortSignal): RequestInit {
  return {
    signal,
    headers: state.restNonce ? { 'X-WP-Nonce': state.restNonce } : {},
  };
}

export function renderedEndpoint(): string {
  const root = state.restBase.replace(/\/wc\/store\/v1\/products$/, '');
  return `${root}/aggressive-apparel/v1/products/rendered`;
}

/**
 * Map the filter UI's orderBy/orderDir to the rendered endpoint's sort enum.
 */
export function mapOrderBy(): string {
  const asc = state.orderDir === 'asc';
  switch (state.orderBy) {
    case 'price':
      return asc ? 'price' : 'price-desc';
    case 'title':
      return asc ? 'title-asc' : 'title-desc';
    case 'popularity':
      return 'popularity';
    case 'rating':
      return 'rating';
    case 'menu_order':
      return 'menu_order';
    default:
      return 'date';
  }
}

/**
 * Build query params for the rendered-products endpoint from active filters.
 */
export function buildRenderedParams(
  page: number,
  cursor = ''
): URLSearchParams {
  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));
  params.set('orderby', mapOrderBy());
  if (cursor) {
    params.set('cursor', cursor);
  }

  // Render from the current page's template (e.g. the category template) so the
  // filtered cards match what the block editor configured for this page.
  if (state.templateSlug) {
    params.set('template', state.templateSlug);
  }

  const requestCategories = state.onSaleOnly
    ? visibleSelectedCategories()
    : state.selectedCategories;
  if (requestCategories.length > 0) {
    params.set('category', requestCategories.join(','));
  }
  for (const filter of ATTRIBUTE_FILTERS) {
    const selected = state[filter.stateKey];
    if (selected.length > 0) {
      params.set(`attributes[${filter.taxonomy}]`, selected.join(','));
    }
  }

  // Prices in major units; the endpoint queries the `_price` meta directly.
  if (state.priceMin > state.priceRange.min) {
    params.set('min_price', String(state.priceMin));
  }
  if (state.priceMax < state.priceRange.max) {
    params.set('max_price', String(state.priceMax));
  }

  if (state.inStockOnly) {
    params.set('stock', 'instock');
  }
  if (state.onSaleOnly) {
    params.set('on_sale', '1');
  }

  return params;
}

/**
 * Custom sort (featured / savings): resolve the ordered, paginated IDs from the
 * sorted-products endpoint, then render exactly those through the block pipeline
 * via the rendered endpoint's `include` param — so the cards match the editor.
 */
export function fetchCustomSorted(sortType: string, append = false): void {
  const signal = requests.beginProducts();

  if (!append) {
    state.isLoading = true;
  }
  state.hasError = false;

  const restRoot = state.restBase.replace(/\/wc\/store\/v1\/products$/, '');
  const sortParams = new URLSearchParams();
  sortParams.set('sort', sortType);
  sortParams.set('per_page', String(state.perPage));
  sortParams.set('page', String(state.currentPage));

  const requestCategories = state.onSaleOnly
    ? visibleSelectedCategories()
    : state.selectedCategories;
  if (requestCategories.length > 0) {
    sortParams.set('category', requestCategories.join(','));
  }
  if (state.onSaleOnly) {
    sortParams.set('on_sale', '1');
  }
  for (const filter of ATTRIBUTE_FILTERS) {
    const selected = state[filter.stateKey];
    if (selected.length > 0) {
      sortParams.set(`attributes[${filter.taxonomy}]`, selected.join(','));
    }
  }
  if (state.priceMin > state.priceRange.min) {
    sortParams.set('min_price', String(state.priceMin));
  }
  if (state.priceMax < state.priceRange.max) {
    sortParams.set('max_price', String(state.priceMax));
  }
  if (state.inStockOnly) {
    sortParams.set('stock', 'instock');
  }

  const sortUrl = `${restRoot}/aggressive-apparel/v1/sorted-products?${sortParams}`;

  fetch(sortUrl, authFetchInit(signal))
    .then((res: Response) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then((data: SortedProductsResponse) => {
      state.totalProducts = data.total;
      state.totalPages = data.totalPages;

      if (data.ids.length === 0) {
        injectProductsHtml('', false);
        state.isLoading = false;
        announceResults();
        renderPills();
        renderPagination();
        renderHorizontalDropdowns();
        notifyProductsFetched(append, 0);
        return;
      }

      // Render the sorted IDs, in order, through the block pipeline.
      const params = new URLSearchParams();
      params.set('include', data.ids.join(','));
      params.set('per_page', String(data.ids.length));
      if (state.templateSlug) {
        params.set('template', state.templateSlug);
      }

      return fetch(`${renderedEndpoint()}?${params}`, authFetchInit(signal))
        .then((res2: Response) => res2.json() as Promise<RenderedResponse>)
        .then((rendered: RenderedResponse) => {
          installBlockSupportStyles(rendered.styles);
          const added = injectProductsHtml(rendered.html, append);
          state.isLoading = false;
          announceResults();
          renderPills();
          renderPagination();
          renderHorizontalDropdowns();
          notifyProductsFetched(append, added);
        });
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
      state.hasError = true;
      state.products = [];
      state._announcement =
        state.i18n?.loadError ?? 'Something went wrong loading products.';
      notifyProductsFetchFailed();
    });
}

/**
 * Announce product count to screen readers.
 */
export function announceResults(): void {
  if (state.totalProducts === 0) {
    state._announcement = state.i18n?.noProductsFound ?? 'No products found.';
  } else if (state.totalProducts === 1) {
    state._announcement = state.i18n?.oneProductFound ?? '1 product found.';
  } else {
    const template = state.i18n?.productsFound ?? '%d products found.';
    state._announcement = template.replace('%d', String(state.totalProducts));
  }
}

/**
 * Fetch products from the WooCommerce Store API.
 */
export function fetchProducts({
  append = false,
}: { append?: boolean } = {}): void {
  // Delegate to custom sort handler for featured/savings.
  if (state._customSort) {
    fetchCustomSorted(state._customSort, append);
    return;
  }

  const signal = requests.beginProducts();

  // For an append (infinite scroll / load more) the existing cards must stay on
  // screen — toggling `isLoading` would hide the whole grid (it's bound to
  // `data-wp-bind--hidden`), collapsing the page mid-scroll and yanking the
  // viewport. Only show the skeleton/hide the grid for a full replace.
  if (!append) {
    state.isLoading = true;
    state.nextCursor = '';
  }
  state.hasError = false;

  const cursor = append ? state.nextCursor : '';
  const url = `${renderedEndpoint()}?${buildRenderedParams(state.currentPage, cursor)}`;

  fetch(url, authFetchInit(signal))
    .then((res: Response) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json() as Promise<RenderedResponse>;
    })
    .then((data: RenderedResponse) => {
      state.totalProducts = data.total_products;
      state.totalPages = data.total_pages;
      state.nextCursor = data.next_cursor || '';

      installBlockSupportStyles(data.styles);
      const added = injectProductsHtml(data.html, append);

      if (!append) {
        state.isLoading = false;
      }
      announceResults();
      renderPills();

      renderPagination();
      renderHorizontalDropdowns();

      // Notify load-more store.
      notifyProductsFetched(append, added);
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
      state.hasError = true;
      state.products = [];
      state.nextCursor = '';
      state._announcement =
        state.i18n?.loadError ?? 'Something went wrong loading products.';
      notifyProductsFetchFailed();
    });
}

/** Keep the load-more store synchronized with every rendered-products request. */
export function notifyProductsFetched(
  append: boolean,
  productsCount: number
): void {
  document.dispatchEvent(
    new CustomEvent('aa:products-fetched', {
      detail: {
        page: state.currentPage,
        totalPages: state.totalPages,
        totalProducts: state.totalProducts,
        append,
        productsCount,
        nextCursor: state.nextCursor,
      },
    })
  );
}

/** Ensure the load-more control never remains busy after a request failure. */
export function notifyProductsFetchFailed(): void {
  document.dispatchEvent(new CustomEvent('aa:products-fetch-failed'));
}

/**
 * Set up event delegation for dynamically rendered content.
 * Auto-hide scrollbar on the drawer body.
 *
 * Adds the `is-scrolling` class while actively scrolling, then removes it
 * after a short idle period. CSS uses this class to reveal the scrollbar thumb.
 */
export function setupScrollbarAutoHide(): void {
  let scrollTimer: ReturnType<typeof setTimeout> | undefined;
  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__drawer-body')
    .forEach(el => {
      el.addEventListener(
        'scroll',
        () => {
          el.classList.add('is-scrolling');
          clearTimeout(scrollTimer);
          scrollTimer = setTimeout(() => {
            el.classList.remove('is-scrolling');
          }, 800);
        },
        { passive: true }
      );
    });
}

/**
 * Pills, pagination, and horizontal dropdown content use innerHTML,
 * so data-wp-on--click directives won't be processed by the Interactivity API.
 * Visibility bindings live on wrapper elements so imperative DOM updates stay intact.
 */
export function setupDelegatedEvents(): void {
  // Pill remove buttons.
  document
    .querySelectorAll<HTMLElement>('.aa-filter-active-bar__pills')
    .forEach(el => {
      el.addEventListener('click', (e: MouseEvent) => {
        const btn = (e.target as HTMLElement).closest<HTMLElement>(
          '.aa-filter-active-bar__pill'
        );
        if (!btn) return;

        const type = btn.dataset.filterType;
        const slug = btn.dataset.filterSlug;

        if (type === 'category' && slug) {
          removeArrayItem(state.selectedCategories, slug);
          syncCategoryChips(state.selectedCategories);
        } else if (type === 'color' && slug) {
          removeArrayItem(state.selectedColors, slug);
          syncSwatchPressed(state.selectedColors);
        } else if (type === 'size' && slug) {
          removeArrayItem(state.selectedSizes, slug);
          syncChipPressed(state.selectedSizes);
        } else if (type === 'fit' && slug) {
          removeArrayItem(state.selectedFit, slug);
          syncFitChipPressed(state.selectedFit);
        } else if (type === 'price') {
          state.priceMin = state.priceRange.min;
          state.priceMax = state.priceRange.max;
          syncPriceSliders();
          syncPriceRange();
        } else if (type === 'stock') {
          state.inStockOnly = false;
          syncStockCheckboxes(false);
        } else if (type === 'sale') {
          setSaleStatus(false);
        }

        // Pills live in the active bar (outside the filter panel), so removing
        // one applies right away rather than waiting for a panel close.
        stageFilterChange();
        applyStagedFilters();
      });
    });

  // Pagination buttons.
  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__pagination')
    .forEach(el => {
      el.addEventListener('click', (e: MouseEvent) => {
        const btn = (e.target as HTMLElement).closest<HTMLElement>(
          '[data-page]'
        );
        if (!btn) return;
        const page = parseInt(btn.dataset.page || '', 10);
        if (page < 1 || page > state.totalPages) return;

        state.currentPage = page;
        if (syncUrl()) return;
        fetchProducts();
        scrollToGrid();
      });
    });
}

/**
 * Inject server-rendered product cards into the AJAX grid container.
 *
 * The HTML comes from the theme's /products/rendered endpoint, which renders
 * each card through the full block pipeline — so AJAX cards are byte-identical
 * to the editor's product-template output (Quick View, badges, hover image,
 * sale countdown, etc. all included). `state.products` tracks the rendered IDs
 * so reactive getters (e.g. `hasProducts`) and append accounting stay correct.
 *
 * @param html   Server-rendered `<li>` markup (empty string clears the grid).
 * @param append Whether to append (load-more) or replace the grid.
 * @return Number of cards added by this call.
 */
export function injectProductsHtml(html: string, append: boolean): number {
  const container = gridUl();
  if (!container) {
    state.products = [];
    return 0;
  }

  const before = append
    ? container.querySelectorAll(':scope > li:not(.aa-product-grid__spacer)')
        .length
    : 0;

  if (append) {
    const knownKeys = new Set(
      Array.from(
        container.querySelectorAll<HTMLElement>(
          ':scope > li:not(.aa-product-grid__spacer)'
        )
      )
        .map(productCardKey)
        .filter(Boolean)
    );
    const incomingKeys = new Set<string>();
    const fragment = document.createElement('ul');
    fragment.innerHTML = html;
    Array.from(fragment.querySelectorAll<HTMLElement>(':scope > li')).forEach(
      card => {
        const key = productCardKey(card);
        if (!key || knownKeys.has(key) || incomingKeys.has(key)) {
          card.remove();
          return;
        }
        incomingKeys.add(key);
      }
    );
    container.insertAdjacentHTML('beforeend', fragment.innerHTML);
    pruneProductGrid(container, state.perPage);
  } else {
    clearProductGridSpacer(container);
    container.innerHTML = html;
  }

  const items = Array.from(
    container.querySelectorAll<HTMLElement>(
      ':scope > li:not(.aa-product-grid__spacer)'
    )
  );
  state.products = items.map(
    li =>
      parseInt(
        (li.getAttribute('data-wp-key') || '').replace('product-item-', ''),
        10
      ) || 0
  );

  notifyCardsRendered(container);

  return items.length - before;
}

/** Stable identity used to make dynamic card insertion idempotent. */
export function productCardKey(card: HTMLElement): string {
  const postClass = [...card.classList].find(className =>
    /^post-\d+$/.test(className)
  );
  if (postClass) return postClass;

  return card.getAttribute('data-wp-key') ?? '';
}
