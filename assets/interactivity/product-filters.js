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
import { parsePrice, setupFocusTrap } from '@aggressive-apparel/helpers';

/** @type {number|null} */
let debounceTimer = null;

/** @type {AbortController|null} */
let abortController = null;

/** @type {Function|null} */
let focusTrapCleanup = null;

/** @type {HTMLElement|null} */
let drawerTrigger = null;

/** Transition duration for drawer animations (ms). */
const TRANSITION_DURATION = 300;

const { state, actions } = store('aggressive-apparel/product-filters', {
  state: {
    // ── Derived getters ─────────────────────────────

    get hasActiveFilters() {
      return (
        state.selectedCategories.length > 0 ||
        state.selectedColors.length > 0 ||
        state.selectedSizes.length > 0 ||
        state.priceMin > state.priceRange.min ||
        state.priceMax < state.priceRange.max ||
        state.inStockOnly
      );
    },

    get hasNoActiveFilters() {
      return !state.hasActiveFilters;
    },

    get hasProducts() {
      return state.products.length > 0 || state.isLoading;
    },

    get hasSinglePage() {
      return state.totalPages <= 1;
    },

    get isNotLoading() {
      return !state.isLoading;
    },

    get activeFilterCount() {
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

    get priceMinDisplay() {
      const prefix = state.priceRange?.currencyPrefix || '$';
      const suffix = state.priceRange?.currencySuffix || '';
      return `${prefix}${state.priceMin}${suffix}`;
    },

    get priceMaxDisplay() {
      const prefix = state.priceRange?.currencyPrefix || '$';
      const suffix = state.priceRange?.currencySuffix || '';
      return `${prefix}${state.priceMax}${suffix}`;
    },

    get announcement() {
      return state._announcement || '';
    },

    // Horizontal bar dropdown getters.
    get isCategoryDropdownOpen() {
      return state.openDropdown === 'categories';
    },
    get isColorDropdownOpen() {
      return state.openDropdown === 'colors';
    },
    get isSizeDropdownOpen() {
      return state.openDropdown === 'sizes';
    },
    get isPriceDropdownOpen() {
      return state.openDropdown === 'price';
    },
    get isStockDropdownOpen() {
      return state.openDropdown === 'stock';
    },
  },

  actions: {
    // ── Filter Toggles ──────────────────────────────

    toggleCategory(event) {
      const btn = event.target.closest('.aa-product-filters__category-chip');
      if (!btn) return;
      const slug = btn.dataset.filterValue;
      toggleArrayItem(state.selectedCategories, slug);
      syncCategoryChips(state.selectedCategories);
      updateAvailableFilters();
      debouncedFetch();
    },

    toggleColor(event) {
      const btn = event.target.closest('.aa-product-filters__color-swatch');
      if (!btn) return;
      const slug = btn.dataset.filterValue;
      toggleArrayItem(state.selectedColors, slug);
      syncSwatchPressed(state.selectedColors);
      debouncedFetch();
    },

    toggleSize(event) {
      const btn = event.target.closest('.aa-product-filters__size-chip');
      if (!btn) return;
      const slug = btn.dataset.filterValue;
      toggleArrayItem(state.selectedSizes, slug);
      syncChipPressed(state.selectedSizes);
      debouncedFetch();
    },

    setPriceMin(event) {
      const val = parseInt(event.target.value, 10);
      state.priceMin = Math.min(val, state.priceMax - 1);
      event.target.value = state.priceMin;
      event.target.setAttribute('aria-valuenow', state.priceMin);
      syncPriceRange();
      debouncedFetch();
    },

    setPriceMax(event) {
      const val = parseInt(event.target.value, 10);
      state.priceMax = Math.max(val, state.priceMin + 1);
      event.target.value = state.priceMax;
      event.target.setAttribute('aria-valuenow', state.priceMax);
      syncPriceRange();
      debouncedFetch();
    },

    toggleInStockOnly(event) {
      state.inStockOnly = event.target.checked;
      syncStockCheckboxes(state.inStockOnly);
      debouncedFetch();
    },

    // ── Active Filter Management ────────────────────

    clearAllFilters() {
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

    // ── Section Collapse ────────────────────────────

    toggleSection(event) {
      const btn = event.target.closest('.aa-product-filters__section-toggle');
      if (!btn) return;

      const isExpanded = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
    },

    // ── Drawer ──────────────────────────────────────

    openDrawer() {
      drawerTrigger = document.activeElement;
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
          const closeBtn = drawer.querySelector('.aa-product-filters__close');
          closeBtn?.focus();
        }
      });
    },

    closeDrawer() {
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
      const panel = drawer?.querySelector('.aa-product-filters__drawer-panel');

      let done = false;
      const finish = () => {
        if (done || state.isDrawerOpen) return;
        done = true;
        unlockScroll();
        if (drawer) drawer.hidden = true;
      };

      if (panel) {
        panel.addEventListener(
          'transitionend',
          e => {
            if (e.propertyName === 'transform') finish();
          },
          { once: true }
        );
        setTimeout(finish, TRANSITION_DURATION + 50);
      } else {
        finish();
      }
    },

    // ── Horizontal Dropdowns ────────────────────────

    toggleDropdown() {
      const ctx = getContext();
      const id = ctx.dropdownId;
      state.openDropdown = state.openDropdown === id ? '' : id;
    },

    // ── Keyboard / Click Outside ────────────────────

    handleKeydown(event) {
      if (event.key === 'Escape') {
        if (state.isDrawerOpen) {
          actions.closeDrawer();
        }
        if (state.openDropdown) {
          state.openDropdown = '';
        }
      }
    },

    handleClickOutside(event) {
      // Close horizontal dropdowns on outside click.
      if (state.openDropdown) {
        const barItem = event.target.closest('.aa-product-filters__bar-item');
        if (!barItem) {
          state.openDropdown = '';
        }
      }
    },
  },

  callbacks: {
    init() {
      restoreFromUrl();
      captureSortDropdown();
      setupDelegatedEvents();
      setupScrollbarAutoHide();

      // Pre-select current category on taxonomy pages.
      if (state.currentCategorySlug && state.selectedCategories.length === 0) {
        state.selectedCategories = [state.currentCategorySlug];
        updateAvailableFilters();
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
    },
  },
});

// ── Helper Functions ──────────────────────────────────

/**
 * Toggle an item in an array (add if absent, remove if present).
 *
 * @param {string[]} arr  - State array (modified in place).
 * @param {string}   item - Item to toggle.
 */
function toggleArrayItem(arr, item) {
  const idx = arr.indexOf(item);
  if (idx === -1) {
    arr.push(item);
  } else {
    arr.splice(idx, 1);
  }
}

/**
 * Remove an item from an array.
 *
 * @param {string[]} arr  - State array (modified in place).
 * @param {string}   item - Item to remove.
 */
function removeArrayItem(arr, item) {
  const idx = arr.indexOf(item);
  if (idx !== -1) arr.splice(idx, 1);
}

/**
 * Debounced fetch — resets page to 1, waits 300ms.
 */
function debouncedFetch() {
  if (debounceTimer) clearTimeout(debounceTimer);
  state.currentPage = 1;
  debounceTimer = setTimeout(() => {
    fetchProducts();
  }, 300);
}

/**
 * Fetch products from the WooCommerce Store API.
 */
function fetchProducts() {
  if (abortController) abortController.abort();
  abortController = new AbortController();

  state.isLoading = true;
  state.hasError = false;

  const params = new URLSearchParams();
  params.set('per_page', String(state.perPage));
  params.set('page', String(state.currentPage));
  params.set('orderby', state.orderBy);
  params.set('order', state.orderDir);

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

  const url = `${state.restBase}?${params.toString()}`;

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
      state.products = products.map(p => ({
        id: p.id,
        name: decodeHtml(p.name),
        permalink: p.permalink,
        image: p.images?.[0]?.src || p.images?.[0]?.thumbnail || '',
        imageAlt: decodeHtml(p.images?.[0]?.alt || p.name),
        price: parsePrice(p.prices),
        stockStatus: p.stock_status || 'instock',
      }));

      state.isLoading = false;

      // Announce results for screen readers.
      if (state.totalProducts === 0) {
        state._announcement = 'No products found.';
      } else if (state.totalProducts === 1) {
        state._announcement = '1 product found.';
      } else {
        state._announcement = `${state.totalProducts} products found.`;
      }

      syncUrl();
      renderProducts();
      renderPills();
      renderPagination();
      renderHorizontalDropdowns();
    })
    .catch(err => {
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
function setupScrollbarAutoHide() {
  let scrollTimer = 0;
  document.querySelectorAll('.aa-product-filters__drawer-body').forEach(el => {
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
function setupDelegatedEvents() {
  // Pill remove buttons.
  document.querySelectorAll('.aa-product-filters__pills').forEach(el => {
    el.addEventListener('click', e => {
      const btn = e.target.closest('.aa-product-filters__pill');
      if (!btn) return;

      const type = btn.dataset.filterType;
      const slug = btn.dataset.filterSlug;

      if (type === 'category') {
        removeArrayItem(state.selectedCategories, slug);
        syncCategoryChips(state.selectedCategories);
        updateAvailableFilters();
      } else if (type === 'color') {
        removeArrayItem(state.selectedColors, slug);
        syncSwatchPressed(state.selectedColors);
      } else if (type === 'size') {
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
  document.querySelectorAll('.aa-product-filters__pagination').forEach(el => {
    el.addEventListener('click', e => {
      const btn = e.target.closest('[data-page]');
      if (!btn) return;
      const page = parseInt(btn.dataset.page, 10);
      if (page < 1 || page > state.totalPages) return;

      state.currentPage = page;
      fetchProducts();
      scrollToGrid();
    });
  });
}

/**
 * Render product cards into the AJAX grid container.
 */
function renderProducts() {
  const container = document.querySelector('.aa-product-filters__products');
  if (!container) return;

  if (state.products.length === 0) {
    container.innerHTML = '';
    return;
  }

  const html = state.products
    .map(p => {
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
      </div>`;
    })
    .join('');

  container.innerHTML = html;
}

/**
 * Render active filter pills.
 */
function renderPills() {
  const containers = document.querySelectorAll('.aa-product-filters__pills');
  if (!containers.length) return;

  const pills = [];

  state.selectedCategories.forEach(slug => {
    const cat = state.categories.find(c => c.slug === slug);
    pills.push({ type: 'category', slug, label: cat?.name || slug });
  });

  state.selectedColors.forEach(slug => {
    const col = state.colorTerms.find(c => c.slug === slug);
    pills.push({ type: 'color', slug, label: col?.name || slug });
  });

  state.selectedSizes.forEach(slug => {
    const sz = state.sizeTerms.find(s => s.slug === slug);
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
      p =>
        `<button class="aa-product-filters__pill" data-filter-type="${escapeHtml(p.type)}" data-filter-slug="${escapeHtml(p.slug)}" aria-label="Remove ${escapeHtml(p.label)} filter">${escapeHtml(p.label)}<span class="aa-product-filters__pill-x" aria-hidden="true">&times;</span></button>`
    )
    .join('');

  containers.forEach(c => {
    c.innerHTML = html;
  });
}

/**
 * Render pagination controls.
 */
function renderPagination() {
  const container = document.querySelector('.aa-product-filters__pagination');
  if (!container) return;

  if (state.totalPages <= 1) {
    container.innerHTML = '';
    return;
  }

  const pages = [];
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
function renderHorizontalDropdowns() {
  if (state.layout !== 'horizontal') return;

  const dropdowns = document.querySelectorAll(
    '.aa-product-filters__bar-dropdown'
  );
  dropdowns.forEach(dd => {
    const item = dd.closest('.aa-product-filters__bar-item');
    if (!item) return;

    const ctx = item.__wp_context?.['aggressive-apparel/product-filters'];
    const id = ctx?.dropdownId || item.dataset?.wpContext;

    if (!id) return;

    // Use the drawer sections as canonical source — clone their content.
    const section = document.querySelector(
      `.aa-product-filters__drawer-body [data-section="${id}"] .aa-product-filters__section-body`
    );

    if (section && dd.children.length === 0) {
      dd.innerHTML = section.innerHTML;
    }
  });
}

/**
 * Sync aria-pressed and is-selected class on toggle elements.
 *
 * @param {string}   selector - CSS selector for the toggle elements.
 * @param {string[]} selected - Array of selected slugs.
 */
function syncPressed(selector, selected) {
  document.querySelectorAll(selector).forEach(el => {
    const slug = el.dataset.filterValue;
    const isSelected = selected.includes(slug);
    el.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    el.classList.toggle('is-selected', isSelected);
  });
}

/** Convenience wrappers for readability at call sites. */
const syncCategoryChips = selected =>
  syncPressed('.aa-product-filters__category-chip', selected);
const syncSwatchPressed = selected =>
  syncPressed('.aa-product-filters__color-swatch', selected);
const syncChipPressed = selected =>
  syncPressed('.aa-product-filters__size-chip', selected);

/**
 * Sync the visual position of the price range highlight.
 */
function syncPriceRange() {
  const range = state.priceRange;
  if (!range || range.max <= range.min) return;

  const total = range.max - range.min;
  const minPct = ((state.priceMin - range.min) / total) * 100;
  const maxPct = ((state.priceMax - range.min) / total) * 100;

  document.querySelectorAll('.aa-product-filters__price-range').forEach(el => {
    el.style.left = `${minPct}%`;
    el.style.right = `${100 - maxPct}%`;
  });

  // Update tooltip positions via CSS custom properties.
  document.querySelectorAll('.aa-product-filters__price-slider').forEach(el => {
    el.style.setProperty('--pf-min-pct', minPct);
    el.style.setProperty('--pf-max-pct', maxPct);
  });
}

/**
 * Reset the price sliders to current state values.
 */
function syncPriceSliders() {
  document
    .querySelectorAll('.aa-product-filters__price-thumb--min')
    .forEach(el => {
      el.value = state.priceMin;
    });
  document
    .querySelectorAll('.aa-product-filters__price-thumb--max')
    .forEach(el => {
      el.value = state.priceMax;
    });
}

/**
 * Sync stock checkboxes.
 *
 * @param {boolean} checked - Whether in-stock-only is active.
 */
function syncStockCheckboxes(checked) {
  document
    .querySelectorAll('.aa-product-filters__stock-checkbox')
    .forEach(el => {
      el.checked = checked;
    });
}

/**
 * Sync all filter controls to current state (used by clearAll).
 */
function syncAllControls() {
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
function syncUrl() {
  const params = new URLSearchParams(window.location.search);

  // Remove old filter params.
  [
    'cat',
    'color',
    'size',
    'min_price',
    'max_price',
    'stock',
    'pf_page',
  ].forEach(k => params.delete(k));

  if (state.selectedCategories.length > 0) {
    params.set('cat', state.selectedCategories.join(','));
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
  const url = qs
    ? `${window.location.pathname}?${qs}`
    : window.location.pathname;

  window.history.pushState(null, '', url);
}

/**
 * Restore filter state from URL params on init.
 */
function restoreFromUrl() {
  const params = new URLSearchParams(window.location.search);

  if (params.has('cat')) {
    state.selectedCategories = params.get('cat').split(',').filter(Boolean);
  }
  if (params.has('color')) {
    state.selectedColors = params.get('color').split(',').filter(Boolean);
  }
  if (params.has('size')) {
    state.selectedSizes = params.get('size').split(',').filter(Boolean);
  }
  if (params.has('min_price')) {
    state.priceMin = Math.max(
      parseInt(params.get('min_price'), 10) || 0,
      state.priceRange.min
    );
  }
  if (params.has('max_price')) {
    state.priceMax = Math.min(
      parseInt(params.get('max_price'), 10) || state.priceRange.max,
      state.priceRange.max
    );
  }
  if (params.get('stock') === 'instock') {
    state.inStockOnly = true;
  }
  if (params.has('pf_page')) {
    state.currentPage = Math.max(parseInt(params.get('pf_page'), 10) || 1, 1);
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
function captureSortDropdown() {
  document.addEventListener('change', e => {
    if (
      e.target.matches('.woocommerce-ordering select, select[name="orderby"]')
    ) {
      const val = e.target.value;
      const sortMap = {
        popularity: { orderBy: 'popularity', orderDir: 'desc' },
        rating: { orderBy: 'rating', orderDir: 'desc' },
        date: { orderBy: 'date', orderDir: 'desc' },
        price: { orderBy: 'price', orderDir: 'asc' },
        'price-desc': { orderBy: 'price', orderDir: 'desc' },
      };
      const sort = sortMap[val] || { orderBy: 'date', orderDir: 'desc' };
      state.orderBy = sort.orderBy;
      state.orderDir = sort.orderDir;

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
function scrollToGrid() {
  const grid = document.querySelector('.aa-product-filters');
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

/** Stagger delay (ms) between each filter element animation. */
const FILTER_STAGGER_MS = 40;

/** Duration (ms) for a single filter element enter/exit animation. */
const FILTER_ANIM_MS = 250;

/**
 * Check whether the user prefers reduced motion.
 *
 * @return {boolean}
 */
function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Hide or show filter elements with staggered animations.
 *
 * Elements leaving shrink out one-by-one, then elements entering pop in
 * with a cascading stagger. Falls back to instant toggle when the user
 * prefers reduced motion.
 *
 * @param {string}   selector  - CSS selector for filter elements.
 * @param {Function} predicate - Receives the element, returns true to show.
 */
function setFilterVisibility(selector, predicate) {
  const all = document.querySelectorAll(selector);
  const toShow = [];
  const toHide = [];

  all.forEach(el => {
    const shouldShow = predicate(el);
    const isVisible = !el.hidden;

    if (shouldShow && !isVisible) toShow.push(el);
    else if (!shouldShow && isVisible) toHide.push(el);
  });

  // Nothing changed — bail early.
  if (toShow.length === 0 && toHide.length === 0) return;

  // Instant toggle for reduced motion.
  if (prefersReducedMotion()) {
    toHide.forEach(el => {
      el.hidden = true;
      el.classList.remove('is-filter-entering', 'is-filter-exiting');
    });
    toShow.forEach(el => {
      el.hidden = false;
      el.classList.remove('is-filter-entering', 'is-filter-exiting');
    });
    return;
  }

  // Phase 1: Stagger exits.
  toHide.forEach((el, i) => {
    setTimeout(() => {
      el.classList.add('is-filter-exiting');
      setTimeout(() => {
        el.hidden = true;
        el.classList.remove('is-filter-exiting');
      }, FILTER_ANIM_MS);
    }, i * FILTER_STAGGER_MS);
  });

  // Phase 2: Stagger entrances after all exits complete.
  const exitDuration = toHide.length * FILTER_STAGGER_MS + FILTER_ANIM_MS;
  const enterDelay = toHide.length > 0 ? exitDuration : 0;

  toShow.forEach((el, i) => {
    setTimeout(
      () => {
        el.classList.add('is-filter-entering');
        el.hidden = false;
        // Force reflow so the browser paints the "from" state.
        void el.offsetHeight;
        // Remove the class on the next frame to trigger the transition.
        requestAnimationFrame(() => {
          el.classList.remove('is-filter-entering');
        });
      },
      enterDelay + i * FILTER_STAGGER_MS
    );
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
function updateAvailableFilters() {
  const map = state.categoryAttributeMap || {};
  const selected = state.selectedCategories;

  // Show everything when no categories are selected.
  if (selected.length === 0) {
    setFilterVisibility('.aa-product-filters__size-chip', () => true);
    setFilterVisibility('.aa-product-filters__color-swatch', () => true);
    return;
  }

  // Build the union of available slugs across selected categories.
  const availableSizes = new Set();
  const availableColors = new Set();

  for (const cat of selected) {
    const entry = map[cat];
    if (entry) {
      (entry.sizes || []).forEach(s => availableSizes.add(s));
      (entry.colors || []).forEach(c => availableColors.add(c));
    }
  }

  setFilterVisibility('.aa-product-filters__size-chip', el =>
    availableSizes.has(el.dataset.filterValue)
  );
  setFilterVisibility('.aa-product-filters__color-swatch', el =>
    availableColors.has(el.dataset.filterValue)
  );

  // Deselect any items that are now hidden.
  const sizeBefore = state.selectedSizes.length;
  const colorBefore = state.selectedColors.length;

  state.selectedSizes = state.selectedSizes.filter(s => availableSizes.has(s));
  state.selectedColors = state.selectedColors.filter(c =>
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
 * Decode HTML entities (e.g. &#8220; → ") to real characters.
 *
 * The WooCommerce Store API returns names with pre-encoded entities
 * from wptexturize(). Decode once at data-ingestion time so that
 * escapeHtml() at render time works on actual characters.
 *
 * @param {string} str - Entity-encoded string.
 * @return {string} Decoded string.
 */
function decodeHtml(str) {
  if (!str) return '';
  const el = document.createElement('textarea');
  el.innerHTML = str;
  return el.value;
}

/**
 * Escape HTML special characters for safe template insertion.
 *
 * @param {string} str - Raw string.
 * @return {string} Escaped string.
 */
function escapeHtml(str) {
  if (!str) return '';
  const el = document.createElement('span');
  el.textContent = str;
  return el.innerHTML;
}
