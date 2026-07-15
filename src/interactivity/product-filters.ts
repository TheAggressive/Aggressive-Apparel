/**
 * Product Filters — Interactivity API Store
 *
 * AJAX product filtering with categories, color swatches, sizes, price range,
 * stock status, and sale status. Supports drawer, sidebar, and horizontal layouts.
 *
 * @package Aggressive_Apparel
 * @since 1.22.0
 */

import { store, getContext } from '@wordpress/interactivity';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';
import {
  installBlockSupportStyles,
  notifyCardsRendered,
} from '@aggressive-apparel/helpers';
import { FilterRequestManager } from './product-filters/request-manager';
import { escapeHtml, setFilterVisibility } from './product-filters/dom';
import {
  buildFilterUrl,
  getCanonicalShopUrl,
  getCategorySlugFromPath,
  getShopPath,
} from './product-filters/url-state';
import {
  ATTRIBUTE_FILTERS,
  type CategoryTerm,
  type ColorTerm,
  type DropdownContext,
  type Facets,
  type FitTerm,
  type FilterPill,
  type ProductFiltersStore,
  type RenderedResponse,
  type SizeTerm,
  type SortedProductsResponse,
  type SortConfig,
} from './product-filters/types';

/** True when filter selections have changed but not yet been applied (fetched). */
let filtersStaged = false;

const requests = new FilterRequestManager();

let focusTrapCleanup: (() => void) | null = null;

let drawerTrigger: HTMLElement | null = null;

/**
 * The original (unfiltered) server-rendered grid markup, captured once so it
 * can be restored verbatim when all filters are cleared.
 */
let originalGridHtml: string | null = null;

/**
 * The real, native product-collection grid `<ul>` — the same element the
 * infinite-scroll/load-more store appends to. Filtered results are injected
 * here so columns, gap, alignment and content-width stay exactly as the editor
 * configured them.
 */
function gridUl(): HTMLElement | null {
  return document.querySelector<HTMLElement>(
    '.aa-product-filters__grid .wp-block-woocommerce-product-template'
  );
}

/**
 * Restore the unfiltered grid (called when filters are cleared).
 */
function restoreOriginalGrid(): void {
  const ul = gridUl();
  if (ul && originalGridHtml !== null) {
    ul.innerHTML = originalGridHtml;
    notifyCardsRendered(ul);
  }
  state.products = [];
}

/** Cross-category URL waiting to be consumed when the drawer closes. */
let pendingNavUrl: string | null = null;

/** Categories that should appear as shopper-selected filters. */
function visibleSelectedCategories(): string[] {
  return state.selectedCategories.filter(
    slug => slug !== state.salesCategorySlug
  );
}

/** Whether the restored URL needs AJAX beyond its native archive query. */
function hasAdditionalRefinements(): boolean {
  return (
    visibleSelectedCategories().length > 0 ||
    state.selectedColors.length > 0 ||
    state.selectedSizes.length > 0 ||
    state.selectedFit.length > 0 ||
    state.priceMin > state.priceRange.min ||
    state.priceMax < state.priceRange.max ||
    state.inStockOnly ||
    state.currentPage > 1
  );
}

const { state, actions } = store<ProductFiltersStore>(
  'aggressive-apparel/product-filters',
  {
    state: {
      // -- Derived getters --

      get hasActiveFilters(): boolean {
        return (
          visibleSelectedCategories().length > 0 ||
          state.selectedColors.length > 0 ||
          state.selectedSizes.length > 0 ||
          state.selectedFit.length > 0 ||
          state.priceMin > state.priceRange.min ||
          state.priceMax < state.priceRange.max ||
          state.inStockOnly ||
          state.onSaleOnly
        );
      },

      get hasNoActiveFilters(): boolean {
        return !state.hasActiveFilters;
      },

      get hasProducts(): boolean {
        return state.products.length > 0 || state.isLoading;
      },

      // No-results message: only while filters are active, not loading, not
      // errored, and the filtered grid came back empty. Hidden on the unfiltered
      // initial view (where `products` is empty because the grid is SSR'd).
      get hideNoResults(): boolean {
        if (!state.hasActiveFilters || state.isLoading || state.hasError) {
          return true;
        }
        return state.products.length > 0;
      },

      // Error notice — shown only when a request failed and we're not mid-retry.
      get hideError(): boolean {
        return !state.hasError || state.isLoading;
      },

      get hasSinglePage(): boolean {
        return state.totalPages <= 1;
      },

      get activeFilterCount(): number | string {
        let count = 0;
        count += visibleSelectedCategories().length;
        count += state.selectedColors.length;
        count += state.selectedSizes.length;
        count += state.selectedFit.length;
        if (
          state.priceMin > state.priceRange.min ||
          state.priceMax < state.priceRange.max
        )
          count++;
        if (state.inStockOnly) count++;
        if (state.onSaleOnly) count++;
        return count || '';
      },

      /*
       * Localized accessible-name suffix for the filter trigger button.
       * Empty when no filters are active so the button's accessible name
       * stays as just its visible label. When filters are applied this
       * appends e.g. "(3 filters applied)" — wrapped in parentheses by
       * the i18n template so AT renders a natural pause between the
       * label and the count.
       */
      get triggerCountLabel(): string {
        const count = state.activeFilterCount;
        if (typeof count !== 'number' || count === 0) return '';

        const template =
          count === 1
            ? (state.i18n?.filtersAppliedSingular ?? '(%s filter applied)')
            : (state.i18n?.filtersAppliedPlural ?? '(%s filters applied)');

        return ' ' + template.replace('%s', String(count));
      },

      get priceMinDisplay(): string {
        const prefix = state.priceRange?.currencyPrefix || '$';
        const suffix = state.priceRange?.currencySuffix || '';
        return `${prefix}${state.priceMin}${suffix}`;
      },

      get priceMaxDisplay(): string {
        const prefix = state.priceRange?.currencyPrefix || '$';
        const suffix = state.priceRange?.currencySuffix || '';
        return `${prefix}${state.priceMax}${suffix}`;
      },

      get announcement(): string {
        return state._announcement || '';
      },

      // Horizontal bar dropdown getters.
      get isCategoryDropdownOpen(): boolean {
        return state.openDropdown === 'categories';
      },
      get isColorDropdownOpen(): boolean {
        return state.openDropdown === 'colors';
      },
      get isSizeDropdownOpen(): boolean {
        return state.openDropdown === 'sizes';
      },
      get isFitDropdownOpen(): boolean {
        return state.openDropdown === 'fit';
      },
      get isPriceDropdownOpen(): boolean {
        return state.openDropdown === 'price';
      },
      get isStockDropdownOpen(): boolean {
        return state.openDropdown === 'stock';
      },
    },

    actions: {
      // -- Filter Toggles --

      toggleCategory(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__category-chip'
        );
        if (!btn) return;
        const slug = btn.dataset.filterValue;
        if (slug) toggleArrayItem(state.selectedCategories, slug);
        syncCategoryChips(state.selectedCategories);
        stageFilterChange();
      },

      toggleColor(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__color-swatch'
        );
        if (!btn) return;
        const slug = btn.dataset.filterValue;
        if (slug) toggleArrayItem(state.selectedColors, slug);
        syncSwatchPressed(state.selectedColors);
        stageFilterChange();
      },

      toggleFit(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__fit-chip'
        );
        if (!btn) return;
        const slug = btn.dataset.filterValue;
        if (slug) toggleArrayItem(state.selectedFit, slug);
        syncFitChipPressed(state.selectedFit);
        stageFilterChange();
      },

      toggleSize(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__size-chip'
        );
        if (!btn) return;
        const slug = btn.dataset.filterValue;
        if (slug) toggleArrayItem(state.selectedSizes, slug);
        syncChipPressed(state.selectedSizes);
        stageFilterChange();
      },

      setPriceMin(event: Event): void {
        const target = event.target as HTMLInputElement;
        const val = parseInt(target.value, 10);
        state.priceMin = Math.min(val, state.priceMax - 1);
        target.value = String(state.priceMin);
        target.setAttribute('aria-valuenow', String(state.priceMin));
        syncPriceRange();
        stageFilterChange();
      },

      setPriceMax(event: Event): void {
        const target = event.target as HTMLInputElement;
        const val = parseInt(target.value, 10);
        state.priceMax = Math.max(val, state.priceMin + 1);
        target.value = String(state.priceMax);
        target.setAttribute('aria-valuenow', String(state.priceMax));
        syncPriceRange();
        stageFilterChange();
      },

      toggleInStockOnly(event: Event): void {
        state.inStockOnly = (event.target as HTMLInputElement).checked;
        syncStockCheckboxes(state.inStockOnly);
        stageFilterChange();
      },

      toggleOnSaleOnly(event: Event): void {
        setSaleStatus((event.target as HTMLInputElement).checked);
        stageFilterChange();
      },

      // -- Active Filter Management --

      clearAllFilters(): void {
        state.selectedCategories = [];
        state.selectedColors = [];
        state.selectedSizes = [];
        state.selectedFit = [];
        state.priceMin = state.priceRange.min;
        state.priceMax = state.priceRange.max;
        state.inStockOnly = false;
        state.onSaleOnly = false;
        state.currentPage = 1;
        state.totalPages = 1;
        state.totalProducts = 0;

        // Clearing applies immediately, so nothing stays staged.
        filtersStaged = false;

        // Filters gone — put the original, unfiltered grid back verbatim.
        restoreOriginalGrid();

        syncAllControls();

        const shopPath = getShopPath(state);
        const leavingShopContext =
          window.location.pathname !== shopPath ||
          window.location.search !== '';
        if (!leavingShopContext) {
          fetchFacets();
        }

        navigateToShopAfterClear();

        // Announce for screen readers.
        state._announcement = 'All filters cleared.';
      },

      // -- Section Collapse --

      toggleSection(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__section-toggle'
        );
        if (!btn) return;

        const isExpanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
      },

      // -- Drawer --

      openDrawer(): void {
        drawerTrigger = document.activeElement as HTMLElement | null;

        const drawer = document.getElementById('aa-product-filters-drawer');
        if (drawer) {
          prepareOverlayOpen(drawer, { manageOpenClass: false });
        }

        state.isDrawerOpen = true;

        const panel = drawer?.querySelector<HTMLElement>(
          '.aa-product-filters__drawer-panel'
        );

        if (panel) {
          focusTrapCleanup = activateOverlayFocus({
            shell: drawer!,
            panel,
            focusSelector: '.aa-product-filters__close',
          });
        }
      },

      closeDrawer(): void {
        // Closing the drawer is the "apply" action — refresh results (or do a
        // full cross-category navigation) for whatever was staged.
        if (applyStagedFilters()) {
          return;
        }

        state.isDrawerOpen = false;

        const drawer = document.getElementById('aa-product-filters-drawer');
        const panel = drawer?.querySelector<HTMLElement>(
          '.aa-product-filters__drawer-panel'
        );

        if (!drawer || !panel) {
          focusTrapCleanup?.();
          focusTrapCleanup = null;
          drawerTrigger = null;
          return;
        }

        closeOverlay({
          shell: drawer,
          panel,
          focusTrapCleanup,
          triggerElement: drawerTrigger,
          manageOpenClass: false,
          transitionProperty: 'transform',
          isStillOpen: () => state.isDrawerOpen,
        });

        focusTrapCleanup = null;
        drawerTrigger = null;
      },

      // -- Horizontal Dropdowns --

      toggleDropdown(): void {
        const ctx = getContext<DropdownContext>();
        const id = ctx.dropdownId;
        const previous = state.openDropdown;
        state.openDropdown = previous === id ? '' : id;

        // A dropdown just closed (toggled off, or switched away) — apply its
        // staged selections.
        if (previous && previous !== state.openDropdown) {
          applyStagedFilters();
        }
      },

      // Sidebar layout has no panel to close, so it applies via an explicit
      // "View Results" button.
      applyFilters(): void {
        applyStagedFilters();
      },

      // Re-run the current selection after a failed request (error notice).
      retry(): void {
        state.hasError = false;
        fetchProducts();
      },

      // -- Keyboard / Click Outside --

      handleKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
          if (state.isDrawerOpen) {
            actions.closeDrawer();
          }
          if (state.openDropdown) {
            state.openDropdown = '';
            applyStagedFilters();
          }
        }
      },

      handleClickOutside(event: MouseEvent): void {
        // Close horizontal dropdowns on outside click.
        if (state.openDropdown) {
          const barItem = (event.target as HTMLElement).closest(
            '.aa-product-filters__bar-item'
          );
          if (!barItem) {
            state.openDropdown = '';
            applyStagedFilters();
          }
        }
      },
    },

    callbacks: {
      init(): void {
        // Capture the unfiltered grid before any AJAX fetch can replace it, so
        // "Clear All" can restore the native server-rendered cards verbatim.
        originalGridHtml = gridUl()?.innerHTML ?? null;

        restoreFromUrl();
        captureSortDropdown();
        setupDelegatedEvents();
        setupScrollbarAutoHide();
        hideFilterPagination();

        // Pre-select current category on taxonomy pages.
        if (
          state.currentCategorySlug &&
          state.selectedCategories.length === 0
        ) {
          state.selectedCategories = [state.currentCategorySlug];
          if (
            state.currentCategorySlug !== state.salesCategorySlug ||
            hasAdditionalRefinements()
          ) {
            fetchProducts();
          }
        }

        // Handle browser back/forward.
        window.addEventListener('popstate', () => {
          const fetched = restoreFromUrl();
          if (!fetched) {
            state.currentPage = 1;
            state.totalPages = 1;
            restoreOriginalGrid();
            renderPills();
            renderPagination();
          }
        });

        // Listen for load-more requesting the next page.
        document.addEventListener('aa:load-more-page', ((
          e: CustomEvent<{ page: number }>
        ) => {
          const { page } = e.detail;
          state.currentPage = page;
          fetchProducts({ append: true });
        }) as EventListener);
      },
    },
  }
);

// -- Helper Functions --

/**
 * Toggle an item in an array (add if absent, remove if present).
 */
function toggleArrayItem(arr: string[], item: string): void {
  const idx = arr.indexOf(item);
  if (idx === -1) {
    arr.push(item);
  } else {
    arr.splice(idx, 1);
  }
}

/**
 * Remove an item from an array.
 */
function removeArrayItem(arr: string[], item: string): void {
  const idx = arr.indexOf(item);
  if (idx !== -1) arr.splice(idx, 1);
}

/**
 * Stage a filter change without fetching.
 *
 * Selections update the pills/active-bar immediately so the user sees what
 * they've picked, but the results grid is NOT refreshed until the filter panel
 * is dismissed (drawer "View Results"/close, horizontal dropdown close, sidebar
 * apply button) — see {@link applyStagedFilters}. This avoids the grid churning
 * on every click while the user is still choosing.
 */
function stageFilterChange(): void {
  filtersStaged = true;
  state.currentPage = 1;
  renderPills();
  // Availability is independent of the deferred results — refresh which
  // colour/size/fit options are still in stock for the new selection right away.
  scheduleFacetsUpdate();
}

/**
 * Debounce the faceted-availability request so rapid selections coalesce.
 */
function scheduleFacetsUpdate(): void {
  requests.scheduleFacets(fetchFacets);
}

/**
 * Fetch the set of attribute terms that still have matching products for the
 * current selection, then enable/disable swatches and chips accordingly. This
 * is a lightweight request (no card HTML) so it can run live while the results
 * grid stays deferred until the panel is dismissed.
 */
function fetchFacets(): void {
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
function applyFacets(facets: Facets): void {
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
function applyStagedFilters(): boolean {
  if (!filtersStaged) return false;
  filtersStaged = false;

  document.dispatchEvent(new CustomEvent('aa:filters-changed'));

  // syncUrl() pushes same-path changes and, for a cross-category switch, stages
  // a full navigation in pendingNavUrl (drawer) or navigates immediately.
  if (syncUrl()) return true;
  if (pendingNavUrl) {
    hardNavigate(pendingNavUrl);
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
function authFetchInit(signal: AbortSignal): RequestInit {
  return {
    signal,
    headers: state.restNonce ? { 'X-WP-Nonce': state.restNonce } : {},
  };
}

function renderedEndpoint(): string {
  const root = state.restBase.replace(/\/wc\/store\/v1\/products$/, '');
  return `${root}/aggressive-apparel/v1/products/rendered`;
}

/**
 * Map the filter UI's orderBy/orderDir to the rendered endpoint's sort enum.
 */
function mapOrderBy(): string {
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
    default:
      return 'date';
  }
}

/**
 * Build query params for the rendered-products endpoint from active filters.
 */
function buildRenderedParams(page: number): URLSearchParams {
  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(page));
  params.set('orderby', mapOrderBy());

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
function fetchCustomSorted(sortType: string): void {
  const signal = requests.beginProducts();

  state.isLoading = true;
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
        return;
      }

      // Render the sorted IDs, in order, through the block pipeline.
      const params = new URLSearchParams();
      params.set('include', data.ids.join(','));
      params.set('per_page', String(data.ids.length));

      return fetch(`${renderedEndpoint()}?${params}`, authFetchInit(signal))
        .then((res2: Response) => res2.json() as Promise<RenderedResponse>)
        .then((rendered: RenderedResponse) => {
          installBlockSupportStyles(rendered.styles);
          injectProductsHtml(rendered.html, false);
          state.isLoading = false;
          announceResults();
          renderPills();
          renderPagination();
          renderHorizontalDropdowns();
        });
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
      state.hasError = true;
      state.products = [];
      state._announcement = 'Something went wrong loading products.';
    });
}

/**
 * Announce product count to screen readers.
 */
function announceResults(): void {
  if (state.totalProducts === 0) {
    state._announcement = 'No products found.';
  } else if (state.totalProducts === 1) {
    state._announcement = '1 product found.';
  } else {
    state._announcement = `${state.totalProducts} products found.`;
  }
}

/**
 * Fetch products from the WooCommerce Store API.
 */
function fetchProducts({ append = false }: { append?: boolean } = {}): void {
  // Delegate to custom sort handler for featured/savings.
  if (state._customSort) {
    fetchCustomSorted(state._customSort);
    return;
  }

  const signal = requests.beginProducts();

  // For an append (infinite scroll / load more) the existing cards must stay on
  // screen — toggling `isLoading` would hide the whole grid (it's bound to
  // `data-wp-bind--hidden`), collapsing the page mid-scroll and yanking the
  // viewport. Only show the skeleton/hide the grid for a full replace.
  if (!append) {
    state.isLoading = true;
  }
  state.hasError = false;

  const url = `${renderedEndpoint()}?${buildRenderedParams(state.currentPage)}`;

  fetch(url, authFetchInit(signal))
    .then((res: Response) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json() as Promise<RenderedResponse>;
    })
    .then((data: RenderedResponse) => {
      state.totalProducts = data.total_products;
      state.totalPages = data.total_pages;

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
      document.dispatchEvent(
        new CustomEvent('aa:products-fetched', {
          detail: {
            page: state.currentPage,
            totalPages: state.totalPages,
            totalProducts: state.totalProducts,
            append,
            productsCount: added,
          },
        })
      );
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
      state.hasError = true;
      state.products = [];
      state._announcement = 'Something went wrong loading products.';
    });
}

/**
 * Set up event delegation for dynamically rendered content.
 * Auto-hide scrollbar on the drawer body.
 *
 * Adds the `is-scrolling` class while actively scrolling, then removes it
 * after a short idle period. CSS uses this class to reveal the scrollbar thumb.
 */
function setupScrollbarAutoHide(): void {
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
function setupDelegatedEvents(): void {
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
function injectProductsHtml(html: string, append: boolean): number {
  const container = gridUl();
  if (!container) {
    state.products = [];
    return 0;
  }

  const before = append ? container.children.length : 0;

  if (append) {
    container.insertAdjacentHTML('beforeend', html);
  } else {
    container.innerHTML = html;
  }

  const items = Array.from(
    container.querySelectorAll<HTMLElement>(':scope > li')
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

/**
 * Maximum removable pills shown before collapsing the rest into a "+N" badge.
 */
const MAX_VISIBLE_PILLS = 3;

function renderPillButton(pill: FilterPill): string {
  return `<button class="aa-filter-active-bar__pill" data-filter-type="${escapeHtml(pill.type)}" data-filter-slug="${escapeHtml(pill.slug)}" aria-label="Remove ${escapeHtml(pill.label)} filter">${escapeHtml(pill.label)}<span class="aa-filter-active-bar__pill-x" aria-hidden="true">&times;</span></button>`;
}

function renderPillOverflowBadge(overflowPills: FilterPill[]): string {
  const count = overflowPills.length;
  if (count === 0) {
    return '';
  }

  const labelList = overflowPills.map(pill => pill.label).join(', ');
  const tooltipTemplate =
    state.i18n?.activeFiltersOverflowTooltip ?? 'Additional filters: %s';
  const ariaLabel = tooltipTemplate.replace('%s', labelList);

  return `<span class="aa-filter-active-bar__overflow" tabindex="0" role="note" aria-label="${escapeHtml(ariaLabel)}" data-tooltip="${escapeHtml(labelList)}">+${count}</span>`;
}

/**
 * Render active filter pills.
 */
function renderPills(): void {
  const containers = document.querySelectorAll<HTMLElement>(
    '.aa-filter-active-bar__pills'
  );
  if (!containers.length) return;

  const pills: FilterPill[] = [];

  visibleSelectedCategories().forEach((slug: string) => {
    const cat = state.categories.find((c: CategoryTerm) => c.slug === slug);
    pills.push({ type: 'category', slug, label: cat?.name || slug });
  });

  state.selectedColors.forEach((slug: string) => {
    const col = state.colorTerms.find((c: ColorTerm) => c.slug === slug);
    pills.push({ type: 'color', slug, label: col?.name || slug });
  });

  state.selectedSizes.forEach((slug: string) => {
    const sz = state.sizeTerms.find((s: SizeTerm) => s.slug === slug);
    pills.push({ type: 'size', slug, label: sz?.name || slug });
  });

  state.selectedFit.forEach((slug: string) => {
    const term = state.fitTerms.find((t: FitTerm) => t.slug === slug);
    pills.push({ type: 'fit', slug, label: term?.name || slug });
  });

  if (
    state.priceMin > state.priceRange.min ||
    state.priceMax < state.priceRange.max
  ) {
    const prefix = state.priceRange.currencyPrefix || '$';
    pills.push({
      type: 'price',
      slug: 'price',
      label: `${prefix}${state.priceMin} – ${prefix}${state.priceMax}`,
    });
  }

  if (state.inStockOnly) {
    pills.push({
      type: 'stock',
      slug: 'stock',
      label: state.i18n?.inStockLabel ?? 'In stock',
    });
  }
  if (state.onSaleOnly) {
    pills.push({
      type: 'sale',
      slug: 'sale',
      label: state.i18n?.onSaleLabel ?? 'On sale',
    });
  }

  const visiblePills = pills.slice(0, MAX_VISIBLE_PILLS);
  const overflowPills = pills.slice(MAX_VISIBLE_PILLS);

  let html = visiblePills.map(renderPillButton).join('');
  if (overflowPills.length > 0) {
    html += renderPillOverflowBadge(overflowPills);
  }

  containers.forEach((c: HTMLElement) => {
    c.innerHTML = html;
  });
}

/**
 * Hide numbered filter pagination when Load More / Infinite Scroll is active.
 */
function hideFilterPagination(): void {
  if (!document.querySelector('.aa-product-filters .aa-load-more')) {
    return;
  }

  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__pagination-nav')
    .forEach(nav => {
      nav.hidden = true;
    });

  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__pagination')
    .forEach(container => {
      container.innerHTML = '';
    });
}

/**
 * Render pagination controls.
 */
function renderPagination(): void {
  if (document.querySelector('.aa-product-filters .aa-load-more')) {
    hideFilterPagination();
    return;
  }

  const container = document.querySelector<HTMLElement>(
    '.aa-product-filters__pagination'
  );
  if (!container) return;

  if (state.totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  const pages: string[] = [];
  for (let i = 1; i <= state.totalPages; i++) {
    const isCurrent = i === state.currentPage;
    const ariaLabel = isCurrent ? `Page ${i}, current page` : `Go to page ${i}`;
    pages.push(
      `<button class="aa-product-filters__page-btn${isCurrent ? ' is-current' : ''}" data-page="${i}" aria-label="${escapeHtml(ariaLabel)}" ${isCurrent ? 'aria-current="page"' : ''}>${i}</button>`
    );
  }

  container.innerHTML = pages.join('');
}

/**
 * Render content inside horizontal bar dropdowns.
 */
function renderHorizontalDropdowns(): void {
  if (state.layout !== 'horizontal') return;

  const dropdowns = document.querySelectorAll<HTMLElement>(
    '.aa-product-filters__bar-dropdown'
  );
  dropdowns.forEach((dd: HTMLElement) => {
    const item = dd.closest<HTMLElement>('.aa-product-filters__bar-item');
    if (!item) return;

    const wpContext = (
      item as HTMLElement & {
        __wp_context?: Record<string, Record<string, string>>;
      }
    ).__wp_context;
    const ctx = wpContext?.['aggressive-apparel/product-filters'];
    const id: string | undefined = ctx?.dropdownId || item.dataset?.wpContext;

    if (!id) return;

    // Use the drawer sections as canonical source — clone their content.
    const section = document.querySelector<HTMLElement>(
      `.aa-product-filters__drawer-body [data-section="${id}"] .aa-product-filters__section-body`
    );

    if (section && dd.children.length === 0) {
      for (const child of section.childNodes) {
        dd.appendChild(child.cloneNode(true));
      }
    }
  });
}

/**
 * Sync aria-pressed and is-selected class on toggle elements.
 */
function syncPressed(selector: string, selected: string[]): void {
  document.querySelectorAll<HTMLElement>(selector).forEach(el => {
    const slug = el.dataset.filterValue;
    const isSelected = slug !== undefined && selected.includes(slug);
    el.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    el.classList.toggle('is-selected', isSelected);
  });
}

/** Convenience wrappers for readability at call sites. */
const syncCategoryChips = (selected: string[]): void =>
  syncPressed('.aa-product-filters__category-chip', selected);
const syncSwatchPressed = (selected: string[]): void =>
  syncPressed('.aa-product-filters__color-swatch', selected);
const syncChipPressed = (selected: string[]): void =>
  syncPressed('.aa-product-filters__size-chip', selected);
const syncFitChipPressed = (selected: string[]): void =>
  syncPressed('.aa-product-filters__fit-chip', selected);

/**
 * Sync the visual position of the price range highlight.
 */
function syncPriceRange(): void {
  const range = state.priceRange;
  if (!range || range.max <= range.min) return;

  const total = range.max - range.min;
  const minPct = ((state.priceMin - range.min) / total) * 100;
  const maxPct = ((state.priceMax - range.min) / total) * 100;

  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__price-range')
    .forEach(el => {
      el.style.left = `${minPct}%`;
      el.style.right = `${100 - maxPct}%`;
    });

  // Update tooltip positions via CSS custom properties.
  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__price-slider')
    .forEach(el => {
      el.style.setProperty('--pf-min-pct', String(minPct));
      el.style.setProperty('--pf-max-pct', String(maxPct));
    });
}

/**
 * Reset the price sliders to current state values.
 */
function syncPriceSliders(): void {
  document
    .querySelectorAll<HTMLInputElement>('.aa-product-filters__price-thumb--min')
    .forEach(el => {
      el.value = String(state.priceMin);
    });
  document
    .querySelectorAll<HTMLInputElement>('.aa-product-filters__price-thumb--max')
    .forEach(el => {
      el.value = String(state.priceMax);
    });
}

/**
 * Sync stock checkboxes.
 */
function syncStockCheckboxes(checked: boolean): void {
  document
    .querySelectorAll<HTMLInputElement>('.aa-product-filters__stock-checkbox')
    .forEach(el => {
      el.checked = checked;
    });
}

/**
 * Sync sale-status checkboxes.
 */
function syncOnSaleCheckboxes(checked: boolean): void {
  document
    .querySelectorAll<HTMLInputElement>('.aa-product-filters__on-sale-checkbox')
    .forEach(el => {
      el.checked = checked;
    });
}

/**
 * Sync all filter controls to current state (used by clearAll).
 */
function syncAllControls(): void {
  syncCategoryChips([]);
  syncSwatchPressed([]);
  syncChipPressed([]);
  syncFitChipPressed([]);
  syncPriceSliders();
  syncPriceRange();
  syncStockCheckboxes(false);
  syncOnSaleCheckboxes(false);
  renderPills();
}

/**
 * Stage sale status as native catalogue context without navigating immediately.
 */
function setSaleStatus(enabled: boolean): void {
  const categoryFilters = visibleSelectedCategories();

  state.onSaleOnly = enabled;
  state.currentPage = 1;
  state.selectedCategories = enabled
    ? [state.salesCategorySlug, ...categoryFilters]
    : categoryFilters;

  syncCategoryChips(state.selectedCategories);
  syncOnSaleCheckboxes(enabled);
}

/**
 * After Clear All, land on the unfiltered shop URL so the address bar matches
 * what recipients see. Same-path clears strip query params via replaceState;
 * category archives and other paths get a full navigation to the shop root.
 */
function navigateToShopAfterClear(): void {
  pendingNavUrl = null;
  const shopPath = getShopPath(state);

  if (window.location.pathname !== shopPath || window.location.search !== '') {
    hardNavigate(getCanonicalShopUrl(state));
    return;
  }

  window.history.replaceState(null, '', shopPath);
}

function syncUrl(): boolean {
  const url = buildFilterUrl(state, visibleSelectedCategories());
  const basePath = url.split('?')[0] ?? url;

  // Cross-category switches require a full page navigation so title, breadcrumbs,
  // and canonical are correct. For the drawer layout, defer until the drawer
  // closes (View Results / backdrop / Escape) so the user can finish selecting.
  // Sidebar and bar layouts have no apply step, so navigate immediately.
  if (basePath !== window.location.pathname) {
    if (state.layout === 'drawer') {
      pendingNavUrl = url;
    } else {
      hardNavigate(url);
      return true;
    }
  } else {
    pendingNavUrl = null;
    window.history.pushState(null, '', url);
  }

  return false;
}

/** Abort requests that cannot be used, then begin a full page navigation. */
function hardNavigate(url: string): void {
  requests.cancelAll();
  window.location.assign(url);
}

/**
 * Restore filter state from URL params on init.
 */
function restoreFromUrl(): boolean {
  const params = new URLSearchParams(window.location.search);

  // Detect active category from URL path (handles pushState navigation).
  // e.g. /shop/hoodies/ → 'hoodies', even after the page was loaded as /shop/clothing/.
  const catFromPath = getCategorySlugFromPath(state);
  const catFromParam = (params.get('cat') || '').split(',').filter(Boolean);
  state.selectedCategories = [
    ...new Set([...(catFromPath ? [catFromPath] : []), ...catFromParam]),
  ];
  for (const filter of ATTRIBUTE_FILTERS) {
    const raw = params.get(filter.urlParam) || '';
    if (raw) {
      state[filter.stateKey] = raw.split(',').filter(Boolean);
    }
  }
  if (params.has('min_price')) {
    state.priceMin = Math.max(
      parseInt(params.get('min_price') || '0', 10) || 0,
      state.priceRange.min
    );
  }
  if (params.has('max_price')) {
    state.priceMax = Math.min(
      parseInt(params.get('max_price') || '', 10) || state.priceRange.max,
      state.priceRange.max
    );
  }
  state.inStockOnly = params.get('stock') === 'instock';
  state.onSaleOnly =
    params.get('on_sale') === '1' ||
    catFromPath === state.salesCategorySlug ||
    state.currentCategorySlug === state.salesCategorySlug;
  if (params.has('pf_page')) {
    state.currentPage = Math.max(
      parseInt(params.get('pf_page') || '1', 10) || 1,
      1
    );
  }

  // Sync DOM controls to restored state.
  requestAnimationFrame(() => {
    syncCategoryChips(state.selectedCategories);
    syncSwatchPressed(state.selectedColors);
    syncChipPressed(state.selectedSizes);
    syncFitChipPressed(state.selectedFit);
    syncPriceSliders();
    syncPriceRange();
    syncStockCheckboxes(state.inStockOnly);
    syncOnSaleCheckboxes(state.onSaleOnly);
    ensureSizeListVisible();
    // Initial (and post-popstate) availability for the restored selection.
    fetchFacets();
  });

  // The native Sales archive already has the correct indexed taxonomy query.
  // Avoid replacing its SSR grid until another filter genuinely requires AJAX.
  const isNativeSalesArchive =
    catFromPath === state.salesCategorySlug ||
    state.currentCategorySlug === state.salesCategorySlug;
  const shouldFetch =
    state.hasActiveFilters &&
    (!isNativeSalesArchive || hasAdditionalRefinements());

  if (shouldFetch) {
    fetchProducts();
  }

  return shouldFetch;
}

/**
 * Capture sort dropdown changes and re-fetch.
 */
function captureSortDropdown(): void {
  document.addEventListener('change', (e: Event) => {
    const target = e.target as HTMLSelectElement;
    if (
      target.matches('.woocommerce-ordering select, select[name="orderby"]')
    ) {
      const val = target.value;
      const sortMap: Record<string, SortConfig> = {
        popularity: { orderBy: 'popularity', orderDir: 'desc' },
        rating: { orderBy: 'rating', orderDir: 'desc' },
        date: { orderBy: 'date', orderDir: 'desc' },
        price: { orderBy: 'price', orderDir: 'asc' },
        'price-desc': { orderBy: 'price', orderDir: 'desc' },
        'title-asc': { orderBy: 'title', orderDir: 'asc' },
        'title-desc': { orderBy: 'title', orderDir: 'desc' },
        featured: {
          orderBy: 'include',
          orderDir: 'asc',
          customSort: 'featured',
        },
        savings: { orderBy: 'include', orderDir: 'asc', customSort: 'savings' },
      };
      const sort = sortMap[val] || { orderBy: 'date', orderDir: 'desc' };
      state.orderBy = sort.orderBy;
      state.orderDir = sort.orderDir;
      state._customSort = sort.customSort || '';

      if (state.hasActiveFilters) {
        state.currentPage = 1;
        fetchProducts();
      }
    }
  });
}

/**
 * Scroll to the top of the product grid.
 */
function scrollToGrid(): void {
  const grid = document.querySelector<HTMLElement>('.aa-product-filters');
  if (grid) {
    const prefersReducedMotion = window.matchMedia(
      '(prefers-reduced-motion: reduce)'
    ).matches;
    grid.scrollIntoView({
      behavior: prefersReducedMotion ? 'auto' : 'smooth',
      block: 'start',
    });
  }
}

/**
 * Reveal the full size list (no longer gated behind a category).
 *
 * Which individual options are enabled is driven by live faceted availability
 * — see {@link applyFacets} — not by a static category→attribute map, so the
 * enabled set is always accurate for the current selection.
 */
function ensureSizeListVisible(): void {
  const sizeHint = document.querySelector<HTMLElement>(
    '.aa-product-filters__size-hint'
  );
  const sizeList = document.querySelector<HTMLElement>(
    '.aa-product-filters__size-list'
  );

  if (sizeList) sizeList.hidden = false;
  if (sizeHint) sizeHint.hidden = true;
}
