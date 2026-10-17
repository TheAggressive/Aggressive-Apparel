/**
 * Product Filters — pill / control render + sync layer.
 *
 * Pure state→DOM rendering for the filter pills, dropdowns and the
 * control-sync helpers. Extracted from product-filters.ts to stay under the
 * file-length cap; touches only the store state (imported) and the DOM.
 *
 * @package Aggressive_Apparel
 */

import { escapeHtml } from './dom';
import type {
  CategoryTerm,
  ColorTerm,
  FilterPill,
  CollectionTerm,
  SizeTerm,
} from './types';
import { state, visibleSelectedCategories } from '../product-filters';

export const MAX_VISIBLE_PILLS = 3;

export function renderPillButton(pill: FilterPill): string {
  const removeTemplate = state.i18n?.removeFilterAria ?? 'Remove %s filter';
  const ariaLabel = removeTemplate.replace('%s', pill.label);
  return `<button class="aa-filter-active-bar__pill" data-filter-type="${escapeHtml(pill.type)}" data-filter-slug="${escapeHtml(pill.slug)}" aria-label="${escapeHtml(ariaLabel)}">${escapeHtml(pill.label)}<span class="aa-filter-active-bar__pill-x" aria-hidden="true">&times;</span></button>`;
}

export function renderPillOverflowBadge(overflowPills: FilterPill[]): string {
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
export function renderPills(): void {
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

  state.selectedCollections.forEach((slug: string) => {
    const term = state.collectionTerms.find(
      (t: CollectionTerm) => t.slug === slug
    );
    pills.push({ type: 'collection', slug, label: term?.name || slug });
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
 * Render content inside horizontal bar dropdowns.
 */
export function renderHorizontalDropdowns(): void {
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
export function syncPressed(selector: string, selected: string[]): void {
  document.querySelectorAll<HTMLElement>(selector).forEach(el => {
    const slug = el.dataset.filterValue;
    const isSelected = slug !== undefined && selected.includes(slug);
    el.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    el.classList.toggle('is-selected', isSelected);
  });
}

/** Convenience wrappers for readability at call sites. */
export const syncCategoryChips = (selected: string[]): void =>
  syncPressed('.aa-product-filters__category-chip', selected);
export const syncSwatchPressed = (selected: string[]): void =>
  syncPressed('.aa-product-filters__color-swatch', selected);
export const syncChipPressed = (selected: string[]): void =>
  syncPressed('.aa-product-filters__size-chip', selected);
export const syncCollectionChipPressed = (selected: string[]): void =>
  syncPressed('.aa-product-filters__collection-chip', selected);

/**
 * Sync the visual position of the price range highlight.
 */
export function syncPriceRange(): void {
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
export function syncPriceSliders(): void {
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
export function syncStockCheckboxes(checked: boolean): void {
  document
    .querySelectorAll<HTMLInputElement>('.aa-product-filters__stock-checkbox')
    .forEach(el => {
      el.checked = checked;
    });
}

/**
 * Sync sale-status checkboxes.
 */
export function syncOnSaleCheckboxes(checked: boolean): void {
  document
    .querySelectorAll<HTMLInputElement>('.aa-product-filters__on-sale-checkbox')
    .forEach(el => {
      el.checked = checked;
    });
}

/**
 * Sync all filter controls to current state (used by clearAll).
 */
export function syncAllControls(): void {
  syncCategoryChips([]);
  syncSwatchPressed([]);
  syncChipPressed([]);
  syncCollectionChipPressed([]);
  syncPriceSliders();
  syncPriceRange();
  syncStockCheckboxes(false);
  syncOnSaleCheckboxes(false);
  renderPills();
}

/**
 * Stage sale status as native catalogue context without navigating immediately.
 */
export function setSaleStatus(enabled: boolean): void {
  const categoryFilters = visibleSelectedCategories();

  state.onSaleOnly = enabled;
  state.selectedCategories = enabled
    ? [state.salesCategorySlug, ...categoryFilters]
    : categoryFilters;

  syncCategoryChips(state.selectedCategories);
  syncOnSaleCheckboxes(enabled);
}
