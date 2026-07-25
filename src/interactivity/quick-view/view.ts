/**
 * Quick View — DOM/view layer.
 *
 * Store-coupled DOM helpers (fallback population, price/image sync), stock/
 * availability derivation, and the capture-phase event delegation that is the
 * sole open/close path for card triggers. Extracted from quick-view.ts so the
 * store entry stays under the file-length cap. Imports live state/actions from
 * the entry (circular but runtime-only, resolved when handlers fire).
 *
 * @package Aggressive_Apparel
 */

import {
  formatVariablePriceRange,
  isOptionAvailable,
  parsePrice,
  stripTags,
} from '@aggressive-apparel/helpers';
import type { PriceResult, Variation } from '@aggressive-apparel/helpers';
import {
  buildAttributes,
  buildGalleryImages,
  buildVariations,
  calculateSalePercentage,
  pickDescription,
} from './product-data';
import type {
  GalleryImage,
  ResolvedAttribute,
  ResolvedVariation,
  StockInfo,
  StoreApiProduct,
} from './types';
import { state, actions, getLabel } from '../quick-view';

export function getStockInfo(product: StoreApiProduct): StockInfo {
  const lowThreshold = 3;
  const isInStock = product.is_in_stock !== false;
  const qty = product.stock_quantity;

  if (!isInStock) {
    return {
      status: 'outofstock',
      quantity: 0,
      label: getLabel('outOfStockLabel', 'Out of Stock'),
    };
  }

  if (qty !== null && qty !== undefined && qty <= lowThreshold && qty > 0) {
    const template = getLabel('onlyNLeft', 'Only %d left!');
    return {
      status: 'lowstock',
      quantity: qty,
      label: template.replace('%d', String(qty)),
    };
  }

  return {
    status: 'instock',
    quantity: qty ?? null,
    label: getLabel('inStockLabel', 'In Stock'),
  };
}

export function applyVariationImage(
  img: { src: string; alt: string } | null
): void {
  if (!img || !img.src) return;

  const galleryIndex = state.productImages.findIndex(
    (i: GalleryImage) => i.src === img.src
  );

  if (galleryIndex >= 0) {
    state.activeImageIndex = galleryIndex;
  } else if (state.productImages.length > 0) {
    fadeImage();
    state.productImages = [
      { ...state.productImages[0], src: img.src, alt: img.alt },
      ...state.productImages.slice(1),
    ];
    state.activeImageIndex = 0;
  }

  state.productImage = img.src;
  state.productImageAlt = img.alt;
}

/**
 * Force-sync price elements in the Quick View modal DOM.
 *
 * Belt-and-suspenders fallback for the data-wp-text reactive binding.
 * Ensures the price visually updates even if the Interactivity API's
 * reactivity has an edge case issue (e.g. inside hidden drawers or
 * after populateModalDOM runs).
 */
export function syncPriceDOM(): void {
  const modal = document.getElementById('aggressive-apparel-quick-view');
  if (!modal) return;
  modal
    .querySelectorAll<HTMLElement>(
      '.aggressive-apparel-quick-view__price-current'
    )
    .forEach(el => {
      el.textContent = state.productPrice;
    });
  modal
    .querySelectorAll<HTMLElement>(
      '.aggressive-apparel-quick-view__price-regular'
    )
    .forEach(el => {
      el.textContent = state.productRegularPrice;
      el.hidden = !state.productOnSale;
    });
  // Sale badge — data-wp-text has the same reactivity issue as prices.
  const badge = modal.querySelector<HTMLElement>(
    '.aggressive-apparel-quick-view__sale-badge'
  );
  if (badge) {
    badge.hidden = !state.productOnSale;
    badge.textContent =
      state.salePercentage > 0 ? `-${state.salePercentage}%` : '';
  }
}

/**
 * Recompute which attribute options are still selectable given the current
 * selection, keyed by attribute slug (values lowercased). An option is
 * available when at least one IN-STOCK variation matches this option plus every
 * OTHER currently selected attribute — the same disjunctive rule the archive
 * filters use, computed here from the client-side variation matrix.
 */
export function computeAvailableOptions(): Record<string, string[]> {
  // Plain copy out of the Interactivity proxy so nested reads stay stable.
  const plain: Variation[] = state.productVariations.map(
    (v: ResolvedVariation) => ({
      id: v.id,
      inStock: v.inStock,
      attributes: (v.attributes || []).map(a => ({
        attribute: a.attribute,
        name: a.name,
        value: a.value,
        taxonomy: a.taxonomy,
      })),
    })
  );

  const result: Record<string, string[]> = {};
  for (const attr of state.productAttributes) {
    const available: string[] = [];
    for (const opt of attr.options) {
      const value = opt.varValue || opt.slug;
      if (
        isOptionAvailable(plain, attr.slug, value, state.selectedAttributes)
      ) {
        available.push(value.toLowerCase());
      }
    }
    result[attr.slug] = available;
  }
  return result;
}

/**
 * Briefly fade the main product image to smooth gallery transitions.
 */
export function fadeImage(): void {
  const img = document.querySelector<HTMLElement>(
    '.aggressive-apparel-quick-view__main-image'
  );
  if (!img) return;
  img.classList.add('is-fading');
  setTimeout(() => img.classList.remove('is-fading'), 150);
}

/**
 * Directly populate the Quick View modal DOM from current store state.
 *
 * Acts as a fallback for when the Interactivity API's data-wp-text and
 * data-wp-bind directives were never hydrated (e.g. because another
 * block's hydration crashed and aborted the loop).
 *
 * Safe to call even when hydration DID succeed — the values are
 * identical so there is no visual flicker.
 */
export function populateModalDOM(): void {
  const modal = document.querySelector<HTMLElement>(
    '.aggressive-apparel-quick-view__modal'
  );
  if (!modal) {
    return;
  }

  const q = <T extends HTMLElement>(sel: string): T | null =>
    modal.querySelector<T>(sel);

  // Skeleton / content / error visibility.
  const skeleton = q('.aggressive-apparel-quick-view__skeleton');
  const content = q('.aggressive-apparel-quick-view__content');
  const error = q('.aggressive-apparel-quick-view__error');

  if (skeleton) {
    skeleton.hidden = !state.isLoading;
  }
  if (content) {
    content.hidden = !state.hasProduct;
  }
  if (error) {
    error.hidden = !state.hasError;
  }

  if (!state.hasProduct) {
    return;
  }

  // Product details.
  const name = q('.aggressive-apparel-quick-view__name');
  if (name) {
    name.textContent = state.productName;
  }

  modal
    .querySelectorAll<HTMLElement>(
      '.aggressive-apparel-quick-view__price-current'
    )
    .forEach(el => {
      el.textContent = state.productPrice;
    });

  modal
    .querySelectorAll<HTMLElement>(
      '.aggressive-apparel-quick-view__price-regular'
    )
    .forEach(el => {
      el.textContent = state.productRegularPrice;
      el.hidden = !state.productOnSale;
    });

  const desc = q('.aggressive-apparel-quick-view__description');
  if (desc) {
    desc.textContent = state.productDescription;
  }

  // Main image.
  const img = q<HTMLImageElement>(
    '.aggressive-apparel-quick-view__main-image img'
  );
  if (img && state.productImage) {
    img.src = state.productImage;
    img.alt = state.productImageAlt;
  }

  // Stock label (element only exists when stock_status feature is enabled).
  const stockEl = q('.aggressive-apparel-quick-view__stock');
  if (stockEl) {
    stockEl.classList.toggle('is-in-stock', state.stockStatus === 'instock');
    stockEl.classList.toggle('is-low-stock', state.stockStatus === 'lowstock');
    stockEl.classList.toggle(
      'is-out-of-stock',
      state.stockStatus === 'outofstock'
    );
  }
  const stockLabel = q('.aggressive-apparel-quick-view__stock-label');
  if (stockLabel) {
    stockLabel.textContent = state.stockStatusLabel;
  }

  // Sale badge.
  const badge = q('.aggressive-apparel-quick-view__sale-badge');
  if (badge) {
    badge.hidden = !state.productOnSale;
    badge.textContent =
      state.salePercentage > 0 ? `-${state.salePercentage}%` : '';
  }

  // Product link.
  const link = q<HTMLAnchorElement>('.aggressive-apparel-quick-view__link');
  if (link) {
    link.href = state.productLink;
  }

  // Add to Cart button.
  const addBtn = q<HTMLButtonElement>(
    '.aggressive-apparel-quick-view__add-to-cart'
  );
  if (addBtn) {
    addBtn.disabled = !state.canAddToCart;
    addBtn.textContent = state.addToCartLabel;
  }
}

/**
 * Populate the standalone cart confirmation when hydration is unavailable.
 */
export function populateCartSuccessDOM(): void {
  const dialog = document.getElementById('aggressive-apparel-cart-success');
  if (!dialog) return;

  const image = dialog.querySelector<HTMLImageElement>(
    '.aggressive-apparel-quick-view-success__image'
  );
  if (image) {
    const currentImage = state.currentImage;
    image.src = currentImage.src || '';
    image.alt = currentImage.alt || '';
  }

  const title = dialog.querySelector<HTMLElement>(
    '.aggressive-apparel-quick-view-success__title'
  );
  if (title) title.textContent = state.addedToCartMessage;

  const productName = dialog.querySelector<HTMLElement>(
    '.aggressive-apparel-quick-view-success__product-name'
  );
  if (productName) productName.textContent = state.productName;

  const options = dialog.querySelector<HTMLElement>(
    '.aggressive-apparel-quick-view-success__options'
  );
  if (options) options.textContent = state.selectedOptionsLabel;

  const continueButton = dialog.querySelector<HTMLButtonElement>(
    '.aggressive-apparel-quick-view-success__button--continue'
  );
  if (continueButton) continueButton.textContent = state.continueShoppingLabel;

  const cartLink = dialog.querySelector<HTMLAnchorElement>(
    '.aggressive-apparel-quick-view-success__button--view-cart'
  );
  if (cartLink) {
    cartLink.href = state.cartUrl;
    cartLink.textContent = state.viewCartLabel;
  }
}

/* ------------------------------------------------------------------ */
/*  Hydration Fallback                                                 */
/*                                                                     */
/*  WordPress Interactivity API has no per-region error isolation.      */
/*  If ANY interactive block (e.g. woocommerce/product-button) throws  */
/*  during hydrateRegions, every region later in the DOM is skipped.   */
/*  The Quick View modal lives in wp_footer (very end of DOM) so it    */
/*  is particularly vulnerable.                                        */
/*                                                                     */
/*  This fallback uses event delegation and direct DOM manipulation    */
/*  so the feature works regardless of hydration status.               */
/* ------------------------------------------------------------------ */

/**
 * Sync the Quick View modal DOM with store state.
 *
 * Handles the subset of directives that are critical for visibility
 * (hidden/is-open). Product content is populated via the fetch
 * callbacks in `actions.open` which set state — reactive bindings
 * handle the rest when hydration succeeds, and the fetch callbacks
 * also write directly to the DOM via this helper when it doesn't.
 */
export function syncModalDOM(): void {
  const el = document.getElementById('aggressive-apparel-quick-view');
  if (!el) {
    return;
  }

  if (state.isOpen) {
    el.hidden = false;
    void el.offsetHeight; // force reflow so transition plays
    el.classList.add('is-open');
  } else {
    el.classList.remove('is-open');
    setTimeout(() => {
      if (!state.isOpen) el.hidden = true;
    }, 300);
  }
}

// Event delegation: sole click path for card triggers (capture phase).
// Markup intentionally omits data-wp-on--click so hydration cannot double-open.
document.addEventListener(
  'click',
  (e: MouseEvent) => {
    const trigger = (e.target as HTMLElement).closest<HTMLElement>(
      '.aggressive-apparel-quick-view__trigger'
    );
    if (!trigger) {
      return;
    }

    e.preventDefault();
    e.stopPropagation();

    if (state.isOpen || state.isSuccessOpen) {
      return;
    }

    actions.open(e);
    syncModalDOM();
  },
  true
);

// Also handle close clicks via delegation.
document.addEventListener('click', (e: MouseEvent) => {
  if (!state.isOpen) {
    return;
  }
  const backdrop = (e.target as HTMLElement).closest(
    '.aggressive-apparel-quick-view__backdrop'
  );
  const closeBtn = (e.target as HTMLElement).closest(
    '.aggressive-apparel-quick-view__close'
  );
  if (backdrop || closeBtn) {
    actions.close();
    syncModalDOM();
  }
});

// ESC key to close (fallback for data-wp-on-document--keydown).
document.addEventListener('keydown', (e: KeyboardEvent) => {
  if (state.isSuccessOpen && e.key === 'Escape') {
    actions.closeCartSuccess();
    return;
  }

  if (state.isOpen && e.key === 'Escape') {
    if (state.isDrawerOpen) {
      actions.closeDrawer();
    } else {
      actions.close();
      syncModalDOM();
    }
  }
});

// Standalone cart confirmation delegation.
document.addEventListener('click', (e: MouseEvent) => {
  if (!state.isSuccessOpen) return;

  const successDialog = document.getElementById(
    'aggressive-apparel-cart-success'
  );
  if (!successDialog || !successDialog.contains(e.target as Node)) return;

  const target = e.target as HTMLElement;
  if (
    target.closest('.aggressive-apparel-quick-view-success__backdrop') ||
    target.closest('.aggressive-apparel-quick-view-success__close') ||
    target.closest('.aggressive-apparel-quick-view-success__button--continue')
  ) {
    actions.closeCartSuccess();
  }
});

// Add to Cart, quantity, continue-shopping, and drawer delegation.
document.addEventListener('click', (e: MouseEvent) => {
  if (!state.isOpen) {
    return;
  }

  const modal = document.getElementById('aggressive-apparel-quick-view');
  if (!modal || !modal.contains(e.target as Node)) {
    return;
  }

  // Select Options button.
  if (
    (e.target as HTMLElement).closest(
      '.aggressive-apparel-quick-view__select-options'
    )
  ) {
    actions.openDrawer();
    return;
  }

  // Drawer scrim close.
  if (
    (e.target as HTMLElement).closest(
      '.aggressive-apparel-quick-view__drawer-scrim'
    )
  ) {
    actions.closeDrawer();
    return;
  }

  // Add to Cart — skip if the Interactivity API already handled it.
  if (
    (e.target as HTMLElement).closest(
      '.aggressive-apparel-quick-view__add-to-cart'
    )
  ) {
    if (!e.defaultPrevented) actions.addToCart();
    return;
  }

  // Quantity buttons — skip if the Interactivity API already handled it.
  const qtyBtn = (e.target as HTMLElement).closest<HTMLElement>(
    '.aggressive-apparel-quick-view__qty-btn'
  );
  if (qtyBtn) {
    if (e.defaultPrevented) {
      return;
    }
    if (
      qtyBtn.textContent?.includes('\u2212') ||
      qtyBtn.textContent?.includes('-')
    ) {
      actions.decrementQty();
    } else {
      actions.incrementQty();
    }
    const input = modal.querySelector<HTMLInputElement>(
      '.aggressive-apparel-quick-view__qty-input'
    );
    if (input) {
      input.value = String(state.quantity);
    }
    return;
  }
});

/**
 * Apply a fetched Store API product to the Quick View store state.
 * Extracted from actions.open()'s fetch handler.
 */
export function applyProductResponse(data: StoreApiProduct): void {
  if (!data || !data.name) {
    state.hasError = true;
    return;
  }

  state.productName = stripTags(data.name);

  // Validate permalink is a safe HTTP(S) URL.
  const permalink = data.permalink || '#';
  state.productLink =
    permalink === '#' || /^https?:\/\//i.test(permalink) ? permalink : '#';
  state.productDescription = pickDescription(data);
  state.productType = data.type || 'simple';

  // Gallery images.
  const gallery = buildGalleryImages(data);
  state.productImages = gallery;
  state._originalImages = gallery.map((img: GalleryImage) => ({
    ...img,
  }));
  state.activeImageIndex = 0;

  // Fallback for single image.
  if (data.images && data.images.length > 0) {
    state.productImage = data.images[0].src;
    state.productImageAlt = stripTags(data.images[0].alt || data.name);
  }

  // Price — variable products with differing variation prices show
  // either a "From $X" starting price (when Smart Price Display is
  // collapsing ranges) or the native "$12.00 – $15.00" range. Either
  // way the exact variation price replaces it once options are chosen.
  const priceData: PriceResult = parsePrice(data.prices);
  const variableDisplay =
    data.type === 'variable'
      ? formatVariablePriceRange(data.prices, {
          collapse: state.collapseVariablePrice,
          prefix: state.priceStartingPrefix,
        })
      : null;
  if (variableDisplay !== null) {
    state.productPrice = variableDisplay;
    state.productPriceRange = variableDisplay;
    state.productRegularPrice = '';
    state.productOnSale = false;
    state.salePercentage = 0;
  } else {
    state.productPrice = priceData.current;
    state.productRegularPrice = priceData.regular;
    state.productOnSale = priceData.onSale;
    state.productPriceRange = '';

    // Sale percentage.
    if (data.prices) {
      const regular = parseInt(data.prices.regular_price || '0', 10);
      const sale = parseInt(
        data.prices.sale_price || data.prices.price || '0',
        10
      );
      state.salePercentage = calculateSalePercentage(regular, sale);
    }
  }

  // Stock status.
  const stockInfo = getStockInfo(data);
  state.stockStatus = stockInfo.status;
  state.stockQuantity = stockInfo.quantity;
  state.stockStatusLabel = stockInfo.label;

  // Variable product data.
  if (data.type === 'variable' && data.has_options) {
    // Build attributes first — this resolves display names
    // (e.g. "Size") to taxonomy slugs (e.g. "pa_size").
    const resolvedAttrs = buildAttributes(
      data,
      state.colorSwatchData,
      data.variations
    );
    state.productAttributes = resolvedAttrs;

    // Build a display-name → slug map so buildVariations can
    // enrich each variation attribute with the taxonomy slug.
    const nameToSlug: Record<string, string> = {};
    for (const attr of resolvedAttrs) {
      nameToSlug[attr.name.toLowerCase()] = attr.slug;
    }
    state.productVariations = buildVariations(data, nameToSlug);

    // Initialise selectedAttributes with empty values.
    const sel: Record<string, string> = {};
    resolvedAttrs.forEach((attr: ResolvedAttribute) => {
      sel[attr.slug] = '';
    });
    state.selectedAttributes = sel;

    // Seed availability so options with no in-stock variation at all
    // (e.g. a fully sold-out colour) start dimmed before any pick.
    state.availableOptions = computeAvailableOptions();
  }

  state.hasProduct = true;
}
