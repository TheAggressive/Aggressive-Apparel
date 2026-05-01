/**
 * Product Filters — Interactivity API Store
 *
 * AJAX product filtering with categories, color swatches, sizes, price range,
 * and stock status. Supports drawer, sidebar, and horizontal bar layouts.
 *
 * @package Aggressive_Apparel
 * @since 1.22.0
 */

import { store, getContext } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';
import {
  parsePrice,
  stripTags,
  setupFocusTrap,
  decodeEntities,
} from '@aggressive-apparel/helpers';
import type { PriceResult, StoreApiPrices } from '@aggressive-apparel/helpers';

interface DropdownContext {
  dropdownId: string;
}

interface PriceRange {
  min: number;
  max: number;
  currencyPrefix?: string;
  currencySuffix?: string;
}

interface CategoryTerm {
  slug: string;
  name: string;
  link?: string;
}

interface ColorTerm {
  slug: string;
  name: string;
}

interface SizeTerm {
  slug: string;
  name: string;
}

interface MappedProduct {
  id: number;
  name: string;
  permalink: string;
  image: string;
  imageAlt: string;
  price: PriceResult;
  shortDescription: string;
  stockStatus: string;
}

interface FilterPill {
  type: string;
  slug: string;
  label: string;
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

interface SortedProductsResponse {
  total: number;
  totalPages: number;
  ids: number[];
}

interface CategoryAttributeMapEntry {
  sizes?: string[];
  colors?: string[];
}

interface SortConfig {
  orderBy: string;
  orderDir: string;
  customSort?: string;
}

interface ProductFiltersState {
  selectedCategories: string[];
  selectedColors: string[];
  selectedSizes: string[];
  priceMin: number;
  priceMax: number;
  priceRange: PriceRange;
  inStockOnly: boolean;
  products: MappedProduct[];
  currentPage: number;
  totalPages: number;
  totalProducts: number;
  isLoading: boolean;
  hasError: boolean;
  isDrawerOpen: boolean;
  openDropdown: string;
  perPage: number;
  orderBy: string;
  orderDir: string;
  restBase: string;
  shopUrl: string;
  layout: string;
  categories: CategoryTerm[];
  colorTerms: ColorTerm[];
  sizeTerms: SizeTerm[];
  currentCategorySlug: string;
  categoryAttributeMap: Record<string, CategoryAttributeMapEntry>;
  _announcement: string;
  _customSort: string;
  readonly hasActiveFilters: boolean;
  readonly hasNoActiveFilters: boolean;
  readonly hasProducts: boolean;
  readonly hasSinglePage: boolean;
  readonly isNotLoading: boolean;
  readonly activeFilterCount: number | string;
  readonly priceMinDisplay: string;
  readonly priceMaxDisplay: string;
  readonly announcement: string;
  readonly isCategoryDropdownOpen: boolean;
  readonly isColorDropdownOpen: boolean;
  readonly isSizeDropdownOpen: boolean;
  readonly isPriceDropdownOpen: boolean;
  readonly isStockDropdownOpen: boolean;
}

interface ProductFiltersStore {
  state: ProductFiltersState;
  actions: Record<string, (...args: any[]) => any>;
  callbacks: Record<string, (...args: any[]) => any>;
}

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

let abortController: AbortController | null = null;

let focusTrapCleanup: (() => void) | null = null;

let drawerTrigger: HTMLElement | null = null;

/** Cross-category URL waiting to be consumed when the drawer closes. */
let pendingNavUrl: string | null = null;

/** Transition duration for drawer animations (ms). */
const TRANSITION_DURATION: number = 300;

const { state, actions } = store<ProductFiltersStore>(
  'aggressive-apparel/product-filters',
  {
    state: {
      // -- Derived getters --

      get hasActiveFilters(): boolean {
        return (
          state.selectedCategories.length > 0 ||
          state.selectedColors.length > 0 ||
          state.selectedSizes.length > 0 ||
          state.priceMin > state.priceRange.min ||
          state.priceMax < state.priceRange.max ||
          state.inStockOnly
        );
      },

      get hasNoActiveFilters(): boolean {
        return !state.hasActiveFilters;
      },

      get hasProducts(): boolean {
        return state.products.length > 0 || state.isLoading;
      },

      get hasSinglePage(): boolean {
        return state.totalPages <= 1;
      },

      get isNotLoading(): boolean {
        return !state.isLoading;
      },

      get activeFilterCount(): number | string {
        let count = 0;
        count += state.selectedCategories.length;
        count += state.selectedColors.length;
        count += state.selectedSizes.length;
        if (
          state.priceMin > state.priceRange.min ||
          state.priceMax < state.priceRange.max
        )
          count++;
        if (state.inStockOnly) count++;
        return count || '';
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
        updateAvailableFilters();
        debouncedFetch();
      },

      toggleColor(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__color-swatch'
        );
        if (!btn) return;
        const slug = btn.dataset.filterValue;
        if (slug) toggleArrayItem(state.selectedColors, slug);
        syncSwatchPressed(state.selectedColors);
        debouncedFetch();
      },

      toggleSize(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__size-chip'
        );
        if (!btn) return;
        const slug = btn.dataset.filterValue;
        if (slug) toggleArrayItem(state.selectedSizes, slug);
        syncChipPressed(state.selectedSizes);
        debouncedFetch();
      },

      setPriceMin(event: Event): void {
        const target = event.target as HTMLInputElement;
        const val = parseInt(target.value, 10);
        state.priceMin = Math.min(val, state.priceMax - 1);
        target.value = String(state.priceMin);
        target.setAttribute('aria-valuenow', String(state.priceMin));
        syncPriceRange();
        debouncedFetch();
      },

      setPriceMax(event: Event): void {
        const target = event.target as HTMLInputElement;
        const val = parseInt(target.value, 10);
        state.priceMax = Math.max(val, state.priceMin + 1);
        target.value = String(state.priceMax);
        target.setAttribute('aria-valuenow', String(state.priceMax));
        syncPriceRange();
        debouncedFetch();
      },

      toggleInStockOnly(event: Event): void {
        state.inStockOnly = (event.target as HTMLInputElement).checked;
        syncStockCheckboxes(state.inStockOnly);
        debouncedFetch();
      },

      // -- Active Filter Management --

      clearAllFilters(): void {
        state.selectedCategories = [];
        state.selectedColors = [];
        state.selectedSizes = [];
        state.priceMin = state.priceRange.min;
        state.priceMax = state.priceRange.max;
        state.inStockOnly = false;
        state.products = [];
        state.currentPage = 1;
        state.totalPages = 1;
        state.totalProducts = 0;

        syncAllControls();
        updateAvailableFilters();
        syncUrl();

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
        lockScroll();

        const drawer = document.getElementById('aa-product-filters-drawer');
        if (drawer) {
          drawer.hidden = false;
          void drawer.offsetHeight; // Force reflow for animation.
        }

        state.isDrawerOpen = true;

        requestAnimationFrame(() => {
          if (drawer) {
            focusTrapCleanup = setupFocusTrap(drawer);
            const closeBtn = drawer.querySelector<HTMLElement>(
              '.aa-product-filters__close'
            );
            closeBtn?.focus();
          }
        });
      },

      closeDrawer(): void {
        if (pendingNavUrl) {
          window.location.href = pendingNavUrl;
          return;
        }

        state.isDrawerOpen = false;

        if (focusTrapCleanup) {
          focusTrapCleanup();
          focusTrapCleanup = null;
        }

        if (drawerTrigger) {
          drawerTrigger.focus();
          drawerTrigger = null;
        }

        const drawer = document.getElementById('aa-product-filters-drawer');
        const panel = drawer?.querySelector<HTMLElement>(
          '.aa-product-filters__drawer-panel'
        );

        let done = false;
        const finish = (): void => {
          if (done || state.isDrawerOpen) return;
          done = true;
          unlockScroll();
          if (drawer) drawer.hidden = true;
        };

        if (panel) {
          panel.addEventListener(
            'transitionend',
            (e: Event) => {
              if ((e as TransitionEvent).propertyName === 'transform') finish();
            },
            { once: true }
          );
          setTimeout(finish, TRANSITION_DURATION + 50);
        } else {
          finish();
        }
      },

      // -- Horizontal Dropdowns --

      toggleDropdown(): void {
        const ctx = getContext<DropdownContext>();
        const id = ctx.dropdownId;
        state.openDropdown = state.openDropdown === id ? '' : id;
      },

      // -- Keyboard / Click Outside --

      handleKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
          if (state.isDrawerOpen) {
            actions.closeDrawer();
          }
          if (state.openDropdown) {
            state.openDropdown = '';
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
          }
        }
      },
    },

    callbacks: {
      init(): void {
        restoreFromUrl();
        captureSortDropdown();
        setupDelegatedEvents();
        setupScrollbarAutoHide();

        // Pre-select current category on taxonomy pages.
        if (
          state.currentCategorySlug &&
          state.selectedCategories.length === 0
        ) {
          state.selectedCategories = [state.currentCategorySlug];
          updateAvailableFilters();
          fetchProducts();
        }

        // Handle browser back/forward.
        window.addEventListener('popstate', () => {
          restoreFromUrl();
          if (state.hasActiveFilters) {
            fetchProducts();
          } else {
            state.products = [];
            state.currentPage = 1;
            state.totalPages = 1;
            renderProducts();
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
 * Debounced fetch — resets page to 1, waits 300ms.
 */
function debouncedFetch(): void {
  if (debounceTimer) clearTimeout(debounceTimer);
  state.currentPage = 1;
  document.dispatchEvent(new CustomEvent('aa:filters-changed'));
  debounceTimer = setTimeout(() => {
    fetchProducts();
  }, 300);
}

/**
 * Build common filter query params for Store API requests.
 */
function buildFilterParams(): URLSearchParams {
  const params = new URLSearchParams();

  if (state.selectedCategories.length > 0) {
    params.set('category', state.selectedCategories.join(','));
  }

  if (state.selectedColors.length > 0) {
    params.set('attribute', 'pa_color');
    params.set('attribute_term', state.selectedColors.join(','));
  }

  if (state.selectedSizes.length > 0) {
    if (state.selectedColors.length > 0) {
      params.append('attribute', 'pa_size');
      params.append('attribute_term', state.selectedSizes.join(','));
    } else {
      params.set('attribute', 'pa_size');
      params.set('attribute_term', state.selectedSizes.join(','));
    }
  }

  if (state.priceMin > state.priceRange.min) {
    params.set('min_price', String(state.priceMin));
  }
  if (state.priceMax < state.priceRange.max) {
    params.set('max_price', String(state.priceMax));
  }

  if (state.inStockOnly) {
    params.set('stock_status', 'instock');
  }

  return params;
}

/**
 * Fetch sorted product IDs from the custom REST endpoint, then
 * load full product data from the Store API using the `include` param.
 */
function fetchCustomSorted(sortType: string): void {
  if (abortController) abortController.abort();
  abortController = new AbortController();

  state.isLoading = true;
  state.hasError = false;

  // Derive REST base from Store API URL.
  const restBase = state.restBase.replace(/\/wc\/store\/v1\/products$/, '');
  const sortParams = new URLSearchParams();
  sortParams.set('sort', sortType);
  sortParams.set('per_page', String(state.perPage));
  sortParams.set('page', String(state.currentPage));

  if (state.selectedCategories.length > 0) {
    sortParams.set('category', state.selectedCategories.join(','));
  }

  const sortUrl = `${restBase}/aggressive-apparel/v1/sorted-products?${sortParams}`;

  fetch(sortUrl, { signal: abortController.signal })
    .then((res: Response) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then((data: SortedProductsResponse) => {
      state.totalProducts = data.total;
      state.totalPages = data.totalPages;

      if (data.ids.length === 0) {
        state.products = [];
        state.isLoading = false;
        announceResults();
        syncUrl();
        renderProducts();
        renderPills();
        renderPagination();
        renderHorizontalDropdowns();
        return;
      }

      // Fetch full product data from Store API using sorted IDs.
      const storeParams = buildFilterParams();
      storeParams.set('include', data.ids.join(','));
      storeParams.set('orderby', 'include');
      storeParams.set('per_page', String(data.ids.length));

      return fetch(`${state.restBase}?${storeParams}`, {
        signal: abortController!.signal,
      })
        .then((res2: Response) => res2.json())
        .then((products: StoreApiProduct[]) => {
          // Preserve the custom sort order from the IDs.
          const idOrder = data.ids;
          const mapped: MappedProduct[] = products.map(
            (p: StoreApiProduct) => ({
              id: p.id,
              name: decodeEntities(p.name),
              permalink: p.permalink,
              image: p.images?.[0]?.src || p.images?.[0]?.thumbnail || '',
              imageAlt: decodeEntities(p.images?.[0]?.alt || p.name),
              price: parsePrice(p.prices),
              shortDescription: stripTags(p.short_description || '').slice(
                0,
                120
              ),
              stockStatus: p.stock_status || 'instock',
            })
          );

          // Sort mapped products to match the ID order.
          state.products = idOrder
            .map((id: number) => mapped.find((p: MappedProduct) => p.id === id))
            .filter((p): p is MappedProduct => Boolean(p));

          state.isLoading = false;
          announceResults();
          syncUrl();
          renderProducts();
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

  if (abortController) abortController.abort();
  abortController = new AbortController();

  state.isLoading = true;
  state.hasError = false;

  const params = buildFilterParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(state.currentPage));
  params.set('orderby', state.orderBy);
  params.set('order', state.orderDir);

  const url = `${state.restBase}?${params.toString()}`;

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
      const mapped: MappedProduct[] = products.map((p: StoreApiProduct) => ({
        id: p.id,
        name: decodeEntities(p.name),
        permalink: p.permalink,
        image: p.images?.[0]?.src || p.images?.[0]?.thumbnail || '',
        imageAlt: decodeEntities(p.images?.[0]?.alt || p.name),
        price: parsePrice(p.prices),
        shortDescription: stripTags(p.short_description || '').slice(0, 120),
        stockStatus: p.stock_status || 'instock',
      }));

      if (append) {
        state.products = [...state.products, ...mapped];
      } else {
        state.products = mapped;
      }

      state.isLoading = false;
      announceResults();
      syncUrl();
      renderProducts({ append });
      renderPills();

      // Hide numbered pagination when load-more is active.
      if (!document.querySelector('.aa-load-more')) {
        renderPagination();
      }

      renderHorizontalDropdowns();

      // Notify load-more store.
      document.dispatchEvent(
        new CustomEvent('aa:products-fetched', {
          detail: {
            page: state.currentPage,
            totalPages: state.totalPages,
            totalProducts: state.totalProducts,
            append,
            productsCount: mapped.length,
          },
        })
      );
    })
    .catch((err: Error) => {
      if (err.name === 'AbortError') return;
      state.isLoading = false;
      state.hasError = true;
      state.products = [];
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
  let scrollTimer: ReturnType<typeof setTimeout> = 0 as unknown as ReturnType<
    typeof setTimeout
  >;
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
 */
function setupDelegatedEvents(): void {
  // Pill remove buttons.
  document
    .querySelectorAll<HTMLElement>('.aa-product-filters__pills')
    .forEach(el => {
      el.addEventListener('click', (e: MouseEvent) => {
        const btn = (e.target as HTMLElement).closest<HTMLElement>(
          '.aa-product-filters__pill'
        );
        if (!btn) return;

        const type = btn.dataset.filterType;
        const slug = btn.dataset.filterSlug;

        if (type === 'category' && slug) {
          removeArrayItem(state.selectedCategories, slug);
          syncCategoryChips(state.selectedCategories);
          updateAvailableFilters();
        } else if (type === 'color' && slug) {
          removeArrayItem(state.selectedColors, slug);
          syncSwatchPressed(state.selectedColors);
        } else if (type === 'size' && slug) {
          removeArrayItem(state.selectedSizes, slug);
          syncChipPressed(state.selectedSizes);
        } else if (type === 'price') {
          state.priceMin = state.priceRange.min;
          state.priceMax = state.priceRange.max;
          syncPriceSliders();
          syncPriceRange();
        } else if (type === 'stock') {
          state.inStockOnly = false;
          syncStockCheckboxes(false);
        }

        debouncedFetch();
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
        fetchProducts();
        scrollToGrid();
      });
    });
}

/**
 * Build HTML for a single product card.
 */
function buildCardHtml(p: MappedProduct): string {
  const priceHtml = p.price.onSale
    ? `<del>${escapeHtml(p.price.regular)}</del> <ins>${escapeHtml(p.price.current)}</ins>`
    : escapeHtml(p.price.current);

  return `<div class="aa-product-filters__product-card">
    <a href="${escapeHtml(p.permalink)}" class="aa-product-filters__product-link">
      <img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.imageAlt)}" class="aa-product-filters__product-image" loading="lazy" width="400" height="400" />
    </a>
    <h3 class="aa-product-filters__product-title">
      <a href="${escapeHtml(p.permalink)}">${escapeHtml(p.name)}</a>
    </h3>
    <div class="aa-product-filters__product-price">${priceHtml}</div>
    ${p.shortDescription ? `<p class="aa-product-filters__product-description">${escapeHtml(p.shortDescription)}</p>` : ''}
  </div>`;
}

/**
 * Render product cards into the AJAX grid container.
 */
function renderProducts({ append = false }: { append?: boolean } = {}): void {
  const container = document.querySelector<HTMLElement>(
    '.aa-product-filters__products'
  );
  if (!container) return;

  if (state.products.length === 0 && !append) {
    container.innerHTML = '';
    return;
  }

  if (append) {
    // Only render the newly added products (at the end of state.products).
    const existingCount = container.children.length;
    const newProducts = state.products.slice(existingCount);
    const html = newProducts.map(buildCardHtml).join('');
    container.insertAdjacentHTML('beforeend', html);
  } else {
    const html = state.products.map(buildCardHtml).join('');
    container.innerHTML = html;
  }
}

/**
 * Render active filter pills.
 */
function renderPills(): void {
  const containers = document.querySelectorAll<HTMLElement>(
    '.aa-product-filters__pills'
  );
  if (!containers.length) return;

  const pills: FilterPill[] = [];

  state.selectedCategories.forEach((slug: string) => {
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
    pills.push({ type: 'stock', slug: 'stock', label: 'In Stock Only' });
  }

  const html = pills
    .map(
      (p: FilterPill) =>
        `<button class="aa-product-filters__pill" data-filter-type="${escapeHtml(p.type)}" data-filter-slug="${escapeHtml(p.slug)}" aria-label="Remove ${escapeHtml(p.label)} filter">${escapeHtml(p.label)}<span class="aa-product-filters__pill-x" aria-hidden="true">&times;</span></button>`
    )
    .join('');

  containers.forEach((c: HTMLElement) => {
    c.innerHTML = html;
  });
}

/**
 * Render pagination controls.
 */
function renderPagination(): void {
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
 * Sync all filter controls to current state (used by clearAll).
 */
function syncAllControls(): void {
  syncCategoryChips([]);
  syncSwatchPressed([]);
  syncChipPressed([]);
  syncPriceSliders();
  syncPriceRange();
  syncStockCheckboxes(false);
  renderPills();
  syncUrl();
}

/**
 * Push current filter state to the URL.
 */

/** Returns the pathname of the shop root, derived from the PHP-injected shopUrl. */
function getShopPath(): string {
  try {
    return new URL(state.shopUrl).pathname;
  } catch {
    return '/shop/';
  }
}

/** Returns the pathname for a category slug using its PHP-injected permalink. */
function getCategoryPath(slug: string): string | null {
  const cat = state.categories.find((c: CategoryTerm) => c.slug === slug);
  if (!cat?.link) return null;
  try {
    return new URL(cat.link).pathname;
  } catch {
    return null;
  }
}

/**
 * Returns the category slug whose permalink matches the current URL path.
 * Used by restoreFromUrl() so that pushState navigation between category
 * pages survives browser back/forward without a full page reload.
 */
function getCategorySlugFromPath(): string | null {
  const path = window.location.pathname;
  for (const cat of state.categories) {
    if (!cat.link) continue;
    try {
      if (new URL(cat.link).pathname === path) return cat.slug;
    } catch {
      // Invalid URL — skip this category.
    }
  }
  return null;
}

function syncUrl(): void {
  const params = new URLSearchParams();

  // Determine the canonical base path:
  //   - 1 category with a known permalink → use that category's path
  //   - 0 or 2+ categories              → use the shop root
  let basePath: string;

  if (state.selectedCategories.length === 1) {
    const slug = state.selectedCategories[0];
    const catPath = getCategoryPath(slug);
    if (catPath) {
      basePath = catPath;
      // Category is implicit in the path — no ?cat= needed.
    } else {
      basePath = getShopPath();
      params.set('cat', slug);
    }
  } else if (state.selectedCategories.length > 1) {
    basePath = getShopPath();
    params.set('cat', state.selectedCategories.join(','));
  } else {
    basePath = getShopPath();
  }

  if (state.selectedColors.length > 0) {
    params.set('color', state.selectedColors.join(','));
  }
  if (state.selectedSizes.length > 0) {
    params.set('size', state.selectedSizes.join(','));
  }
  if (state.priceMin > state.priceRange.min) {
    params.set('min_price', String(state.priceMin));
  }
  if (state.priceMax < state.priceRange.max) {
    params.set('max_price', String(state.priceMax));
  }
  if (state.inStockOnly) {
    params.set('stock', 'instock');
  }
  if (state.currentPage > 1) {
    params.set('pf_page', String(state.currentPage));
  }

  const qs = params.toString();
  const url = qs ? `${basePath}?${qs}` : basePath;
  // Cross-category switches require a full page navigation so title, breadcrumbs,
  // and canonical are correct. For the drawer layout, defer until the drawer
  // closes (View Results / backdrop / Escape) so the user can finish selecting.
  // Sidebar and bar layouts have no apply step, so navigate immediately.
  if (basePath !== window.location.pathname) {
    if (state.layout === 'drawer') {
      pendingNavUrl = url;
    } else {
      window.location.href = url;
    }
  } else {
    pendingNavUrl = null;
    window.history.pushState(null, '', url);
  }
}

/**
 * Restore filter state from URL params on init.
 */
function restoreFromUrl(): void {
  const params = new URLSearchParams(window.location.search);

  // Detect active category from URL path (handles pushState navigation).
  // e.g. /shop/hoodies/ → 'hoodies', even after the page was loaded as /shop/clothing/.
  const catFromPath = getCategorySlugFromPath();
  const catFromParam = (params.get('cat') || '').split(',').filter(Boolean);
  state.selectedCategories = [
    ...new Set([...(catFromPath ? [catFromPath] : []), ...catFromParam]),
  ];
  if (params.has('color')) {
    state.selectedColors = (params.get('color') || '')
      .split(',')
      .filter(Boolean);
  }
  if (params.has('size')) {
    state.selectedSizes = (params.get('size') || '').split(',').filter(Boolean);
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
  if (params.get('stock') === 'instock') {
    state.inStockOnly = true;
  }
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
    syncPriceSliders();
    syncPriceRange();
    syncStockCheckboxes(state.inStockOnly);
    updateAvailableFilters();
  });

  // If any filters are active, fetch products immediately.
  if (state.hasActiveFilters) {
    fetchProducts();
  }
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
 * Fade and disable filter elements via the `.is-unavailable` CSS class.
 *
 * Items stay in place (no layout reflow) — just dimmed and non-interactive.
 * CSS transitions the opacity change; JS toggles the class and a11y attrs.
 */
function setFilterVisibility(
  selector: string,
  predicate: (el: HTMLElement) => boolean
): void {
  const all = document.querySelectorAll<HTMLElement>(selector);

  all.forEach(el => {
    const shouldShow = predicate(el);
    const isUnavailable = el.classList.contains('is-unavailable');

    if (!shouldShow && !isUnavailable) {
      el.classList.add('is-unavailable');
      el.setAttribute('aria-hidden', 'true');
      el.tabIndex = -1;
    } else if (shouldShow && isUnavailable) {
      el.classList.remove('is-unavailable');
      el.removeAttribute('aria-hidden');
      el.removeAttribute('tabindex');
    }
  });
}

/**
 * Update visible sizes and colors based on selected categories.
 *
 * When no categories are selected every option is shown. When one or
 * more categories are selected the visible sizes/colors are the union
 * of attribute slugs across those categories. Any currently-selected
 * size/color that becomes hidden is automatically deselected.
 */
function updateAvailableFilters(): void {
  const map = state.categoryAttributeMap || {};
  const selected = state.selectedCategories;

  // Show everything when no categories are selected.
  if (selected.length === 0) {
    setFilterVisibility('.aa-product-filters__size-chip', () => true);
    setFilterVisibility('.aa-product-filters__color-swatch', () => true);
    return;
  }

  // Build the union of available slugs across selected categories.
  const availableSizes = new Set<string>();
  const availableColors = new Set<string>();

  for (const cat of selected) {
    const entry = map[cat];
    if (entry) {
      (entry.sizes || []).forEach((s: string) => availableSizes.add(s));
      (entry.colors || []).forEach((c: string) => availableColors.add(c));
    }
  }

  setFilterVisibility('.aa-product-filters__size-chip', (el: HTMLElement) =>
    availableSizes.has(el.dataset.filterValue || '')
  );
  setFilterVisibility('.aa-product-filters__color-swatch', (el: HTMLElement) =>
    availableColors.has(el.dataset.filterValue || '')
  );

  // Deselect any items that are now hidden.
  const sizeBefore = state.selectedSizes.length;
  const colorBefore = state.selectedColors.length;

  state.selectedSizes = state.selectedSizes.filter((s: string) =>
    availableSizes.has(s)
  );
  state.selectedColors = state.selectedColors.filter((c: string) =>
    availableColors.has(c)
  );

  if (state.selectedSizes.length !== sizeBefore) {
    syncChipPressed(state.selectedSizes);
  }
  if (state.selectedColors.length !== colorBefore) {
    syncSwatchPressed(state.selectedColors);
  }
}

/**
 * Escape HTML special characters for safe template insertion.
 */
function escapeHtml(str: string): string {
  if (!str) return '';
  const el = document.createElement('span');
  el.textContent = str;
  return el.innerHTML;
}
