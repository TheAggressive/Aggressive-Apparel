/**
 * Product Filters — Interactivity API Store
 *
 * Product filtering with categories, color swatches, sizes, price range,
 * stock status, and sale status. Supports drawer, sidebar, and horizontal layouts.
 * Applying filters navigates to the filtered URL, which the server renders.
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
import { requests, filterFlags } from './product-filters/runtime';
import {
  renderHorizontalDropdowns,
  renderPills,
  setSaleStatus,
  syncAllControls,
  syncCategoryChips,
  syncChipPressed,
  syncCollectionChipPressed,
  syncOnSaleCheckboxes,
  syncPriceRange,
  syncPriceSliders,
  syncStockCheckboxes,
  syncSwatchPressed,
} from './product-filters/render';
import {
  applyStagedFilters,
  fetchFacets,
  setupApplyPrefetch,
  setupDelegatedEvents,
  setupScrollbarAutoHide,
  showFilteredCatalog,
} from './product-filters/data';
import { applySort, applyUrlState } from './product-filters/url-state';
import {
  type DropdownContext,
  type ProductFiltersStore,
} from './product-filters/types';

let focusTrapCleanup: (() => void) | null = null;

let drawerTrigger: HTMLElement | null = null;

/** Prevent duplicate global listeners if an interactive region is reprocessed. */
let initialized = false;

/** Categories that should appear as shopper-selected filters. */
export function visibleSelectedCategories(): string[] {
  return state.selectedCategories.filter(
    slug => slug !== state.salesCategorySlug
  );
}

export const { state, actions } = store<ProductFiltersStore>(
  'aggressive-apparel/product-filters',
  {
    state: {
      // -- Derived getters --

      get hasActiveFilters(): boolean {
        return (
          visibleSelectedCategories().length > 0 ||
          state.selectedColors.length > 0 ||
          state.selectedSizes.length > 0 ||
          state.selectedCollections.length > 0 ||
          state.priceMin > state.priceRange.min ||
          state.priceMax < state.priceRange.max ||
          state.inStockOnly ||
          state.onSaleOnly
        );
      },

      get hasNoActiveFilters(): boolean {
        return !state.hasActiveFilters;
      },

      get activeFilterCount(): number | string {
        let count = 0;
        count += visibleSelectedCategories().length;
        count += state.selectedColors.length;
        count += state.selectedSizes.length;
        count += state.selectedCollections.length;
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
      get isCollectionDropdownOpen(): boolean {
        return state.openDropdown === 'collection';
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
        const slug = chosenOption(event, '.aa-product-filters__category-chip');
        if (!slug) return;
        toggleArrayItem(state.selectedCategories, slug);
        syncCategoryChips(state.selectedCategories);
        stageFilterChange();
      },

      toggleColor(event: MouseEvent): void {
        const slug = chosenOption(event, '.aa-product-filters__color-swatch');
        if (!slug) return;
        toggleArrayItem(state.selectedColors, slug);
        syncSwatchPressed(state.selectedColors);
        stageFilterChange();
      },

      toggleCollection(event: MouseEvent): void {
        const slug = chosenOption(
          event,
          '.aa-product-filters__collection-chip'
        );
        if (!slug) return;
        toggleArrayItem(state.selectedCollections, slug);
        syncCollectionChipPressed(state.selectedCollections);
        stageFilterChange();
      },

      toggleSize(event: MouseEvent): void {
        const slug = chosenOption(event, '.aa-product-filters__size-chip');
        if (!slug) return;
        toggleArrayItem(state.selectedSizes, slug);
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
        state.selectedCollections = [];
        state.priceMin = state.priceRange.min;
        state.priceMax = state.priceRange.max;
        state.inStockOnly = false;
        state.onSaleOnly = false;

        // Clearing applies immediately, so nothing stays staged.
        filterFlags.staged = false;

        syncAllControls();

        // The unfiltered shop (keeping the sort): a category archive leaves
        // with a full navigation, the shop itself swaps its collection.
        if (showFilteredCatalog()) return;
        fetchFacets();

        // Announce for screen readers.
        state._announcement =
          state.i18n?.allFiltersCleared ?? 'All filters cleared.';
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
        if (initialized) return;
        initialized = true;

        // The server rendered this URL's filters and sort; mirror them in
        // the controls.
        restoreFromUrl();
        captureSortDropdown();
        setupDelegatedEvents();
        setupApplyPrefetch();
        setupScrollbarAutoHide();
        renderHorizontalDropdowns();

        // Pre-select current category on taxonomy pages.
        if (
          state.currentCategorySlug &&
          state.selectedCategories.length === 0
        ) {
          state.selectedCategories = [state.currentCategorySlug];
        }

        // Back/forward: WordPress's router restores the collection for the
        // entry; bring the controls back in line with its URL.
        window.addEventListener('popstate', () => {
          restoreFromUrl();
        });
      },
    },
  }
);

// -- Helper Functions --

/**
 * The value of the filter option a click activated, or '' when it hit no
 * option or an unavailable one (`aria-disabled` does not block activation).
 */
function chosenOption(event: MouseEvent, selector: string): string {
  const option = (event.target as HTMLElement).closest<HTMLElement>(selector);
  if (!option || option.getAttribute('aria-disabled') === 'true') return '';
  return option.dataset.filterValue ?? '';
}

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
export function removeArrayItem(arr: string[], item: string): void {
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
export function stageFilterChange(): void {
  filterFlags.staged = true;
  renderPills();
  // Availability is independent of the deferred results — refresh which
  // colour/size/collection options are still in stock for the new selection right away.
  scheduleFacetsUpdate();
}

/**
 * Debounce the faceted-availability request so rapid selections coalesce.
 */
export function scheduleFacetsUpdate(): void {
  requests.scheduleFacets(fetchFacets);
}

/**
 * Restore filter state from the URL (init and back/forward).
 */
function restoreFromUrl(): void {
  applyUrlState(state, new URLSearchParams(window.location.search));

  // Sync DOM controls to restored state.
  requestAnimationFrame(() => {
    syncCategoryChips(state.selectedCategories);
    syncSwatchPressed(state.selectedColors);
    syncChipPressed(state.selectedSizes);
    syncCollectionChipPressed(state.selectedCollections);
    syncPriceSliders();
    syncPriceRange();
    syncStockCheckboxes(state.inStockOnly);
    syncOnSaleCheckboxes(state.onSaleOnly);
    ensureSizeListVisible();
    // Initial (and post-popstate) availability for the restored selection.
    fetchFacets();
    renderPills();
  });
}

/**
 * Keep the shopper's sort for the next filter change.
 *
 * WooCommerce's Catalog Sorting block navigates itself, keeping the URL's
 * filters. It stops the change event from bubbling, so listen in capture.
 */
function captureSortDropdown(): void {
  document.addEventListener(
    'change',
    (e: Event) => {
      const target = e.target as HTMLSelectElement;
      if (
        target.matches('.woocommerce-ordering select, select[name="orderby"]')
      ) {
        applySort(state, target.value);
      }
    },
    true
  );
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
