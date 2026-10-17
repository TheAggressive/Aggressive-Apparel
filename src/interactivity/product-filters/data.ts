/**
 * Product Filters — facets, navigation and delegated events.
 *
 * Applying filters or a sort navigates to the filtered URL; the archive
 * renders it on the server (Catalog_Archive_Filters) and WordPress's router
 * swaps the product collection's region, so the grid, pagination and Load
 * More always come from a real page render. Facet availability is the only
 * data fetched separately, live while the shopper is still choosing.
 * Extracted from product-filters.ts to stay under the file-length cap.
 *
 * @package Aggressive_Apparel
 */

import { setFilterVisibility } from './dom';
import { requests, filterFlags } from './runtime';
import {
  renderPills,
  setSaleStatus,
  syncCategoryChips,
  syncChipPressed,
  syncCollectionChipPressed,
  syncPressed,
  syncPriceRange,
  syncPriceSliders,
  syncStockCheckboxes,
  syncSwatchPressed,
} from './render';
import { ATTRIBUTE_FILTERS } from './types';
import type { Facets } from './types';
import { buildFilterUrl } from './url-state';
import {
  state,
  removeArrayItem,
  scheduleFacetsUpdate,
  stageFilterChange,
  visibleSelectedCategories,
} from '../product-filters';

/** The router module, loaded on first navigation. */
type Router = typeof import('@wordpress/interactivity-router');

export function fetchFacets(): void {
  const signal = requests.beginFacets();

  fetch(`${state.facetsUrl}?${buildFacetParams()}`, {
    signal,
    headers: state.restNonce ? { 'X-WP-Nonce': state.restNonce } : {},
  })
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
 * Query params for the facets endpoint from the current selection.
 */
export function buildFacetParams(): URLSearchParams {
  const params = new URLSearchParams();

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
 * Enable only the colour/size/collection options that still have matching products.
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
 * Apply staged filter selections. No-op when nothing is staged.
 *
 * @return Whether a full page navigation was started.
 */
export function applyStagedFilters(): boolean {
  if (!filterFlags.staged) return false;
  filterFlags.staged = false;

  return showFilteredCatalog();
}

/**
 * Show the catalog for the current filters and sort.
 *
 * A different archive (category or Sales path) is a full navigation, so its
 * title, breadcrumbs and canonical are server-rendered; the same archive
 * swaps only the product collection.
 *
 * @return Whether a full page navigation was started.
 */
export function showFilteredCatalog(): boolean {
  const url = buildFilterUrl(state, visibleSelectedCategories());
  const target = new URL(url, window.location.href);

  if (target.pathname !== window.location.pathname) {
    hardNavigate(target.href);
    return true;
  }

  if (target.href !== window.location.href) {
    void navigateCatalog(target.href);
  }
  return false;
}

/** Abort requests that cannot be used, then begin a full page navigation. */
export function hardNavigate(url: string): void {
  requests.cancelAll();
  window.location.assign(url);
}

/**
 * Navigate to a URL on this archive with WordPress's router, which fetches
 * the page, replaces the product collection's region (grid, pagination and
 * Load More) and records history. Without a router region (the collection
 * forces full page reloads) or when the router fails, load the page instead.
 */
async function navigateCatalog(url: string): Promise<void> {
  if (!document.querySelector('.aa-product-filters [data-wp-router-region]')) {
    hardNavigate(url);
    return;
  }

  state.isLoading = true;
  let router: Router;
  try {
    router = await import('@wordpress/interactivity-router');
  } catch {
    hardNavigate(url);
    return;
  }

  try {
    await router.actions.navigate(url);
  } catch {
    hardNavigate(url);
    return;
  } finally {
    state.isLoading = false;
  }

  // A newer navigation has superseded this one.
  if (window.location.href !== url) return;
  announceResults();
}

/**
 * Announce the rendered result count to screen readers.
 *
 * The total comes from the new page's Load More context; without Load More
 * only an empty result can be described with certainty.
 */
function announceResults(): void {
  const total = renderedTotal();
  if (total === 0) {
    state._announcement = state.i18n?.noProductsFound ?? 'No products found.';
  } else if (total === 1) {
    state._announcement = state.i18n?.oneProductFound ?? '1 product found.';
  } else if (total !== null) {
    const template = state.i18n?.productsFound ?? '%d products found.';
    state._announcement = template.replace('%d', String(total));
  }
}

/** Total products of the rendered collection, when it is known. */
function renderedTotal(): number | null {
  const control = document.querySelector<HTMLElement>(
    '.aa-product-filters .aa-load-more[data-wp-context]'
  );
  if (control) {
    try {
      const total: unknown = JSON.parse(
        control.dataset.wpContext ?? ''
      ).totalProducts;
      if (Number.isInteger(total)) return total as number;
    } catch {
      // Fall through to the rendered grid.
    }
  }

  return document.querySelector(
    '.aa-product-filters__grid .wp-block-woocommerce-product-template > li'
  )
    ? null
    : 0;
}

/**
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
 * Pills use innerHTML, so data-wp-on--click directives won't be processed by
 * the Interactivity API; their clicks are delegated here.
 */
export function setupDelegatedEvents(): void {
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
        } else if (type === 'collection' && slug) {
          removeArrayItem(state.selectedCollections, slug);
          syncCollectionChipPressed(state.selectedCollections);
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
}
