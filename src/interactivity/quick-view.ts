/**
 * Quick View — Interactivity API Store.
 *
 * Supports simple and variable products with add-to-cart via the
 * WooCommerce Store API. Variable products display attribute swatches
 * and match variations before enabling add-to-cart.
 *
 * State values `restBase` and `cartApiUrl` are provided by PHP
 * via wp_interactivity_state().
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../types/interactivity-shared';

import { store, getContext, getElement } from '@wordpress/interactivity';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';
import {
  parsePrice,
  stripTags,
  decodeEntities,
  matchVariation,
} from '@aggressive-apparel/helpers';
import type { PriceResult, StoreApiPrices } from '@aggressive-apparel/helpers';

declare global {
  interface Window {
    SCRIPT_DEBUG?: boolean;
  }
}

/* ------------------------------------------------------------------ */
/*  Interfaces                                                         */
/* ------------------------------------------------------------------ */

interface QuickViewContext {
  productId: number;
  item?: QuickViewOption | GalleryImage;
}

interface QuickViewOption {
  name: string;
  slug: string;
  varValue?: string;
  attrSlug: string;
}

interface GalleryImage {
  id: number | string;
  src: string;
  alt: string;
  thumbnail: string;
}

interface ColorSwatchEntry {
  name?: string;
  value?: string;
}

interface StoreApiImage {
  id?: number;
  src: string;
  alt?: string;
  thumbnail?: string;
}

interface StoreApiAttribute {
  name: string;
  taxonomy?: string;
  has_variations: boolean;
  terms?: StoreApiTerm[];
}

interface StoreApiTerm {
  id?: number;
  slug: string;
  name: string;
}

interface StoreApiVariationAttribute {
  attribute?: string;
  name?: string;
  value: string;
  taxonomy?: string;
}

interface StoreApiVariation {
  id: number;
  attributes?: StoreApiVariationAttribute[];
  image?: StoreApiImage;
  prices?: Record<string, unknown>;
}

interface StoreApiProduct {
  id: number;
  name: string;
  permalink: string;
  type?: string;
  description: string;
  short_description: string;
  images?: StoreApiImage[];
  prices: StoreApiPrices & {
    price_range?: { min_amount?: string; max_amount?: string };
  };
  attributes?: StoreApiAttribute[];
  variations?: StoreApiVariation[];
  has_options?: boolean;
  is_in_stock?: boolean;
  stock_quantity?: number | null;
  extensions?: Record<string, Record<string, Record<string, unknown>>>;
}

interface StockInfo {
  status: string;
  quantity: number | null;
  label: string;
}

interface ResolvedAttribute {
  name: string;
  slug: string;
  options: ResolvedOption[];
}

interface ResolvedOption {
  name: string;
  slug: string;
  varValue: string;
  attrSlug: string;
}

interface ResolvedVariation {
  id: number;
  attributes: Array<{
    attribute: string;
    name?: string;
    value: string;
    taxonomy?: string;
  }>;
  image: string;
  imageAlt: string;
  prices: Record<string, unknown> | null;
}

interface CartAddBody {
  id: number;
  quantity: number;
  variation?: Array<{ attribute: string; value: string }>;
}

interface QuickViewLabels {
  addToCartText?: string;
  addingToCartText?: string;
  addedToCartText?: string;
  outOfStockButtonText?: string;
  variableButtonText?: string;
  buyNowText?: string;
  redirectingText?: string;
  viewCartText?: string;
  continueShoppingText?: string;
  viewProductText?: string;
  addedToCartMessage?: string;
  outOfStockLabel?: string;
  inStockLabel?: string;
  onlyNLeft?: string;
  addedSuccessAnnounce?: string;
  addToCartError?: string;
  errorAnnounce?: string;
}

interface QuickViewState {
  restBase: string;
  cartApiUrl: string;
  i18n: QuickViewLabels;
  isOpen: boolean;
  isSuccessOpen: boolean;
  isLoading: boolean;
  hasError: boolean;
  hasProduct: boolean;
  productId: number;
  productType: string;
  productImage: string;
  productImageAlt: string;
  productName: string;
  productPrice: string;
  productRegularPrice: string;
  productOnSale: boolean;
  productDescription: string;
  productLink: string;
  productAttributes: ResolvedAttribute[];
  productVariations: ResolvedVariation[];
  selectedAttributes: Record<string, string>;
  matchedVariationId: number;
  quantity: number;
  isAddingToCart: boolean;
  isCartSuccess: boolean;
  isBuyingNow: boolean;
  cartError: string;
  cartNonce: string;
  isDrawerOpen: boolean;
  productImages: GalleryImage[];
  _originalImages: GalleryImage[];
  activeImageIndex: number;
  stockStatus: string;
  stockQuantity: number | null;
  stockStatusLabel: string;
  salePercentage: number;
  productPriceRange: string;
  colorSwatchData: Record<string, ColorSwatchEntry>;
  cartUrl: string;
  checkoutUrl: string;
  announcement: string;
  readonly isVariable: boolean;
  readonly canAddToCart: boolean;
  readonly addToCartLabel: string;
  readonly buyNowLabel: string;
  readonly selectOptionsLabel: string;
  readonly viewCartLabel: string;
  readonly continueShoppingLabel: string;
  readonly viewProductLabel: string;
  readonly addedToCartMessage: string;
  readonly isOptionSelected: boolean;
  readonly currentImage: { src: string; alt: string };
  readonly hasMultipleImages: boolean;
  readonly isActiveImage: boolean;
  readonly imagePositionLabel: string;
  readonly saleBadgeText: string;
  readonly isInStock: boolean;
  readonly isLowStock: boolean;
  readonly isOutOfStock: boolean;
  readonly isNotLoading: boolean;
  readonly hasNoProduct: boolean;
  readonly isNotOnSale: boolean;
  readonly hasOneImage: boolean;
  readonly cannotAddToCart: boolean;
  readonly hasNoCartError: boolean;
  readonly hasNoError: boolean;
  readonly hideSelectOptionsBtn: boolean;
  readonly hideInlineAddToCart: boolean;
  readonly isDrawerClosed: boolean;
  readonly selectedOptionsLabel: string;
  readonly isColorAttribute: boolean;
  readonly isNotColorAttribute: boolean;
  readonly isColorSwatch: boolean;
  readonly colorSwatchValue: string;
  readonly colorSwatchName: string;
  readonly thumbnailsFitContainer: boolean;
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

/**
 * Resolve server-provided copy with a local fallback for cached markup.
 */
function getLabel(key: keyof QuickViewLabels, fallback: string): string {
  return state.i18n?.[key] || fallback;
}

/**
 * Whether an attribute slug represents the canonical color taxonomy.
 */
function isColorSlug(slug: string): boolean {
  return (slug || '').toLowerCase() === 'pa_color';
}

/**
 * Pick the best description from a Store API product.
 */
function pickDescription(product: StoreApiProduct): string {
  const short = stripTags(product.short_description);
  if (short.length > 30) {
    return short;
  }
  const full = stripTags(product.description);
  if (!full) {
    return short;
  }
  if (full.length <= 200) {
    return full;
  }
  const truncated = full.substring(0, 200);
  const lastSpace = truncated.lastIndexOf(' ');
  return (
    (lastSpace > 120 ? truncated.substring(0, lastSpace) : truncated) + '…'
  );
}

/**
 * Build gallery images array from Store API product.
 */
function buildGalleryImages(product: StoreApiProduct): GalleryImage[] {
  if (!product.images || product.images.length === 0) {
    return [];
  }
  return product.images.map((img: StoreApiImage, index: number) => ({
    id: img.id || index,
    src: img.src,
    alt: stripTags(img.alt || product.name),
    thumbnail: img.thumbnail || img.src,
  }));
}

/**
 * Get stock status info from Store API product.
 */
function getStockInfo(product: StoreApiProduct): StockInfo {
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

/**
 * Calculate sale percentage from prices.
 */
function calculateSalePercentage(
  regularPrice: number,
  salePrice: number
): number {
  if (!regularPrice || !salePrice || regularPrice <= salePrice) {
    return 0;
  }
  return Math.round(((regularPrice - salePrice) / regularPrice) * 100);
}

/**
 * Return a numeric rank for an apparel size string.
 * Handles: XS, S, M, L, XL, and multiplied variants like 2XS, 3XL, 7XL.
 * Unknown sizes get Infinity so they sort to the end.
 */
function sizeRank(size: string): number {
  const s = size.trim().toUpperCase();

  // Multiplied small: 2XS, 3XS, etc. — smaller means lower rank.
  // 3XS < 2XS < XS, so we invert: rank = -(multiplier).
  const xsMatch = s.match(/^(\d+)XS$/);
  if (xsMatch) return -parseInt(xsMatch[1], 10);

  const bases: Record<string, number> = { XS: 1, S: 2, M: 3, L: 4, XL: 5 };
  if (bases[s] !== undefined) return bases[s];

  // Multiplied large: 2XL, 3XL, 4XL, … 7XL.
  const xlMatch = s.match(/^(\d+)XL$/);
  if (xlMatch) return 5 + parseInt(xlMatch[1], 10);

  // Numeric sizes (e.g., shoe sizes): parse directly.
  const num = parseFloat(s);
  if (!isNaN(num)) return 100 + num;

  return Infinity;
}

/**
 * Check if an attribute is a size attribute.
 */
function isSizeAttr(slug: string, name: string): boolean {
  const s = (slug || '').toLowerCase();
  const n = (name || '').toLowerCase();
  return s === 'pa_size' || s === 'size' || n === 'size';
}

/**
 * Build attribute data from a Store API product for template rendering.
 */
function buildAttributes(
  product: StoreApiProduct,
  colorSwatchData: Record<string, ColorSwatchEntry>,
  variations?: StoreApiVariation[]
): ResolvedAttribute[] {
  if (!product.attributes || product.attributes.length === 0) {
    return [];
  }

  // Build a mapping from term values to the attribute keys that
  // variations actually use (e.g. "red" → "pa_color"). This lets us
  // resolve the correct slug when product-level attr.taxonomy is empty.
  const varKeyByValue: Record<string, string> = {};
  const rawVariations = variations || product.variations || [];
  if (rawVariations.length > 0) {
    for (const v of rawVariations) {
      for (const va of v.attributes || []) {
        const key = va.attribute || va.name || '';
        if (va.value && key) {
          varKeyByValue[va.value.toLowerCase()] = key;
        }
      }
    }
  }

  // Helper: get all candidate names for a term — slug, name, and the
  // display name from our Color_Data_Manager swatch data.
  const termCandidates = (term: StoreApiTerm): string[] => {
    const candidates: string[] = [];
    if (term.slug) candidates.push(term.slug.toLowerCase());
    if (term.name) candidates.push(term.name.toLowerCase());
    // Swatch data may have the real display name when slug/name are IDs.
    if (colorSwatchData) {
      const sw =
        colorSwatchData[term.slug] ||
        colorSwatchData[term.name] ||
        (term.id ? colorSwatchData[String(term.id)] : null);
      if (sw && sw.name) candidates.push(sw.name.toLowerCase());
    }
    return [...new Set(candidates)];
  };

  const attrSlugFor = (attr: StoreApiAttribute): string => {
    if (attr.taxonomy) return attr.taxonomy;
    // No taxonomy — resolve from variation data by trying every
    // candidate name for each term against the varKeyByValue map.
    for (const term of attr.terms || []) {
      for (const candidate of termCandidates(term)) {
        if (varKeyByValue[candidate]) return varKeyByValue[candidate];
      }
    }
    return attr.name;
  };

  // Collect all variation values keyed by attribute slug so we can
  // resolve each term to its variation-compatible value.
  const varValuesByAttr: Record<string, Set<string>> = {};
  for (const v of rawVariations) {
    for (const va of v.attributes || []) {
      const key = va.attribute || va.name || '';
      if (key && va.value) {
        if (!varValuesByAttr[key]) varValuesByAttr[key] = new Set();
        varValuesByAttr[key].add(va.value);
      }
    }
  }

  return product.attributes
    .filter((attr: StoreApiAttribute) => attr.has_variations)
    .map((attr: StoreApiAttribute) => {
      const slug = attrSlugFor(attr);
      const colorAttr = isColorSlug(slug);
      const varValues = varValuesByAttr[slug] || new Set<string>();

      const options: ResolvedOption[] = (attr.terms || []).map(
        (term: StoreApiTerm) => {
          const termSlug = term.slug || term.name;
          // For color attributes, prefer the display name from our
          // Color_Data_Manager swatch data.
          const swatch =
            colorAttr && colorSwatchData
              ? colorSwatchData[termSlug] ||
                (term.id ? colorSwatchData[String(term.id)] : null)
              : null;

          // Resolve the variation-compatible value for this term.
          let varValue = termSlug;
          for (const vv of varValues) {
            const vvLower = vv.toLowerCase();
            for (const candidate of termCandidates(term)) {
              if (vvLower === candidate) {
                varValue = vv;
                break;
              }
            }
            if (varValue !== termSlug) break;
          }

          return {
            name: swatch && swatch.name ? swatch.name : term.name,
            slug: termSlug,
            varValue,
            attrSlug: slug,
          };
        }
      );

      // Sort size options in logical apparel order (XS → S → M → … → 7XL).
      if (isSizeAttr(slug, attr.name)) {
        options.sort(
          (a: ResolvedOption, b: ResolvedOption) =>
            sizeRank(a.name) - sizeRank(b.name)
        );
      }

      return { name: attr.name, slug, options };
    });
}

/**
 * Build simplified variation objects from a Store API product.
 *
 * The Store API returns variation attributes with only the display
 * name (e.g. "Size") but no taxonomy slug. The `nameToSlug` map
 * (built from resolved product attributes) lets us add the taxonomy
 * slug so matchVariation() can find the correct selectedAttributes key.
 */
function buildVariations(
  product: StoreApiProduct,
  nameToSlug: Record<string, string> = {}
): ResolvedVariation[] {
  if (!product.variations || product.variations.length === 0) {
    return [];
  }

  // Per-variation prices provided by our PHP ExtendSchema extension.
  const varPrices: Record<string, Record<string, unknown>> = (product
    .extensions?.['aggressive-apparel/variation-prices'] as Record<
    string,
    Record<string, unknown>
  >) || {};

  return product.variations.map((v: StoreApiVariation) => {
    // Merge per-variation prices with parent currency metadata so
    // parsePrice() has everything it needs.
    const extPrices = varPrices[String(v.id)];
    const prices = extPrices
      ? { ...product.prices, ...extPrices }
      : v.prices || product.prices;

    return {
      id: v.id,
      attributes: (v.attributes || []).map(
        (attr: StoreApiVariationAttribute) => ({
          ...attr,
          // Add the resolved taxonomy slug so matchVariation can use it.
          attribute:
            attr.attribute ||
            nameToSlug[(attr.name || '').toLowerCase()] ||
            attr.name ||
            '',
        })
      ),
      image:
        v.image && v.image.src
          ? v.image.src
          : product.images && product.images.length > 0
            ? product.images[0].src
            : '',
      imageAlt: v.image && v.image.alt ? v.image.alt : product.name || '',
      prices: prices as Record<string, unknown>,
    };
  });
}

/* ------------------------------------------------------------------ */
/*  Store                                                              */
/* ------------------------------------------------------------------ */

interface QuickViewStore {
  state: QuickViewState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

// Store reference for focus trap cleanup.
let focusTrapCleanup: (() => void) | null = null;
let successFocusTrapCleanup: (() => void) | null = null;
let triggerElement: HTMLElement | null = null;

// AbortController for the main product fetch — cancels stale requests
// when the user opens a different product before the previous one loads.
let fetchController: AbortController | null = null;

// Cache fetched variation image data so repeated selections don't refetch.
const variationImageCache = new Map<
  number,
  { src: string; alt: string } | null
>();

// Cached media queries — avoids re-creating MediaQueryList on every call.
const prefersReducedMotion: MediaQueryList | { matches: false } =
  typeof window !== 'undefined'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };

/* Touch swipe tracking for mobile gallery. */
let touchStartX = 0;
let touchStartY = 0;
let isSwiping = false;

/**
 * Apply a variation image to the gallery.
 *
 * If the image already exists in the gallery, switch to it.
 * Otherwise, replace the first gallery entry with the variation image.
 * Passing null restores the original gallery.
 */
function applyVariationImage(img: { src: string; alt: string } | null): void {
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
function syncPriceDOM(): void {
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
 * Briefly fade the main product image to smooth gallery transitions.
 */
function fadeImage(): void {
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
function populateModalDOM(): void {
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
function populateCartSuccessDOM(): void {
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

const { state, actions } = store<QuickViewStore>(
  'aggressive-apparel/quick-view',
  {
    state: {
      // Provided by PHP via wp_interactivity_state().
      restBase: '',
      cartApiUrl: '',
      i18n: {},

      // Modal visibility.
      isOpen: false,
      isSuccessOpen: false,
      isLoading: false,
      hasError: false,
      hasProduct: false,

      // Core product info.
      productId: 0,
      productType: 'simple',
      productImage: '',
      productImageAlt: '',
      productName: '',
      productPrice: '',
      productRegularPrice: '',
      productOnSale: false,
      productDescription: '',
      productLink: '',

      // Variable product data.
      productAttributes: [],
      productVariations: [],
      selectedAttributes: {},
      matchedVariationId: 0,

      // Quantity.
      quantity: 1,

      // Cart interaction (cartNonce provided by PHP via wp_interactivity_state).
      isAddingToCart: false,
      isCartSuccess: false,
      cartError: '',

      // Variation options drawer.
      isDrawerOpen: false,

      // Gallery support.
      productImages: [],
      _originalImages: [],
      activeImageIndex: 0,

      // Stock status.
      stockStatus: 'instock',
      stockQuantity: null,
      stockStatusLabel: '',

      // Sale badge.
      salePercentage: 0,

      // Stored initial price range for variable products (e.g. "$12.00 – $15.00").
      // Used to restore the range display when a variation is deselected.
      productPriceRange: '',

      // Color swatch data (from PHP).
      colorSwatchData: {},

      cartUrl: '/cart/',

      // Accessibility announcement.
      announcement: '',

      // Derived state.
      get isVariable(): boolean {
        return state.productType === 'variable';
      },

      get canAddToCart(): boolean {
        if (
          state.isAddingToCart ||
          state.isBuyingNow ||
          state.stockStatus === 'outofstock'
        ) {
          return false;
        }
        if (state.productType === 'simple') {
          return state.hasProduct;
        }
        // Variable: all attributes must be selected.
        return state.matchedVariationId > 0;
      },

      get addToCartLabel(): string {
        if (state.isCartSuccess) {
          return getLabel('addedToCartText', '✓ Added!');
        }
        if (state.isAddingToCart) {
          return getLabel('addingToCartText', 'Adding…');
        }
        if (state.stockStatus === 'outofstock') {
          return getLabel('outOfStockButtonText', 'Out of Stock');
        }
        return getLabel('addToCartText', 'Add to Cart');
      },

      get buyNowLabel(): string {
        if (state.isBuyingNow) {
          return getLabel('redirectingText', 'Redirecting…');
        }
        return getLabel('buyNowText', 'Buy Now');
      },

      get selectOptionsLabel(): string {
        return getLabel('variableButtonText', 'Choose');
      },

      get viewCartLabel(): string {
        return getLabel('viewCartText', 'View Cart');
      },

      get continueShoppingLabel(): string {
        return getLabel('continueShoppingText', 'Continue Shopping');
      },

      get viewProductLabel(): string {
        return getLabel('viewProductText', 'View Full Product');
      },

      get addedToCartMessage(): string {
        return getLabel('addedToCartMessage', 'Added to cart!');
      },

      /**
       * Whether the current option button (set by data-wp-each) is selected.
       *
       * Used with data-wp-class--is-selected on each swatch button.
       */
      get isOptionSelected(): boolean {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption | undefined;
        if (!item || !item.attrSlug) {
          return false;
        }
        const val = item.varValue || item.slug;
        return state.selectedAttributes[item.attrSlug] === val;
      },

      /**
       * Current gallery image object.
       */
      get currentImage(): { src: string; alt: string } {
        const images = state.productImages;
        const index = state.activeImageIndex;
        if (images.length === 0) {
          return { src: state.productImage, alt: state.productImageAlt };
        }
        return images[index] || images[0] || { src: '', alt: '' };
      },

      /**
       * Whether there are multiple images to show thumbnails.
       */
      get hasMultipleImages(): boolean {
        return state.productImages.length > 1;
      },

      /**
       * Whether the current thumbnail is the active one.
       */
      get isActiveImage(): boolean {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as GalleryImage | undefined;
        if (!item) {
          return false;
        }
        const index = state.productImages.findIndex(
          (img: GalleryImage) => img.id === item.id
        );
        return index === state.activeImageIndex;
      },

      /**
       * Aria label for the current thumbnail/dot (e.g., "Image 2 of 5").
       */
      get imagePositionLabel(): string {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as GalleryImage | undefined;
        if (!item) return '';
        const index = state.productImages.findIndex(
          (img: GalleryImage) => img.id === item.id
        );
        if (index < 0) return '';
        return `Image ${index + 1} of ${state.productImages.length}`;
      },

      /**
       * Sale badge text (e.g., "-25%").
       */
      get saleBadgeText(): string {
        if (state.salePercentage > 0) {
          return `-${state.salePercentage}%`;
        }
        return '';
      },

      /**
       * Stock status helpers.
       */
      get isInStock(): boolean {
        return state.stockStatus === 'instock';
      },

      get isLowStock(): boolean {
        return state.stockStatus === 'lowstock';
      },

      get isOutOfStock(): boolean {
        return state.stockStatus === 'outofstock';
      },

      // Negated getters for data-wp-bind--hidden directives.
      get isNotLoading(): boolean {
        return !state.isLoading;
      },

      get hasNoProduct(): boolean {
        return !state.hasProduct;
      },

      get isNotOnSale(): boolean {
        return !state.productOnSale;
      },

      get hasOneImage(): boolean {
        return !state.hasMultipleImages;
      },

      get cannotAddToCart(): boolean {
        return !state.canAddToCart;
      },

      get hasNoCartError(): boolean {
        return !state.cartError;
      },

      get hasNoError(): boolean {
        return !state.hasError;
      },

      /**
       * Hide "Select Options" button for simple products.
       */
      get hideSelectOptionsBtn(): boolean {
        return !state.isVariable;
      },

      /**
       * Hide inline Add to Cart button for variable products
       * (they use the drawer's Add to Cart instead).
       */
      get hideInlineAddToCart(): boolean {
        return state.isVariable;
      },

      get isDrawerClosed(): boolean {
        return !state.isDrawerOpen;
      },

      /**
       * Label showing selected variation options (e.g. "Red / L").
       * Resolves slugs to display names from productAttributes.
       */
      get selectedOptionsLabel(): string {
        const names: string[] = [];
        for (const [attrSlug, optionValue] of Object.entries(
          state.selectedAttributes
        )) {
          if (!optionValue) continue;
          const attr = state.productAttributes.find(
            (a: ResolvedAttribute) => a.slug === attrSlug
          );
          const opt = attr?.options?.find(
            (o: ResolvedOption) => (o.varValue || o.slug) === optionValue
          );
          names.push(opt?.name || optionValue);
        }
        return names.length > 0 ? names.join(' / ') : '';
      },

      /**
       * Whether the current attribute is a color attribute.
       */
      get isColorAttribute(): boolean {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as ResolvedAttribute | undefined;
        if (!item) {
          return false;
        }
        return isColorSlug(item.slug);
      },

      /**
       * Inverse of isColorAttribute for hidden binding.
       */
      get isNotColorAttribute(): boolean {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as ResolvedAttribute | undefined;
        if (!item) {
          return true;
        }
        return !isColorSlug(item.slug);
      },

      /**
       * Whether the current option is a color swatch.
       */
      get isColorSwatch(): boolean {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption | undefined;
        if (!item || !item.slug) {
          return false;
        }
        return !!state.colorSwatchData[item.slug];
      },

      /**
       * Get the color value for the current swatch option.
       */
      get colorSwatchValue(): string {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption | undefined;
        if (!item || !item.slug) {
          return '';
        }
        const swatchData = state.colorSwatchData[item.slug];
        if (!swatchData || !swatchData.value) {
          return '';
        }
        // Validate color value to prevent CSS injection.
        const v = swatchData.value;
        if (/^#[0-9a-f]{3,8}$/i.test(v) || /^oklch\([^;{}]*\)$/i.test(v)) {
          return v;
        }
        return '';
      },

      /**
       * Get the display name for a color swatch option.
       */
      get colorSwatchName(): string {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption | undefined;
        if (!item) {
          return '';
        }
        const swatchData = state.colorSwatchData[item.slug];
        return swatchData && swatchData.name ? swatchData.name : item.name;
      },

      /**
       * Whether all thumbnails fit without scrolling (<= 6 images).
       */
      get thumbnailsFitContainer(): boolean {
        return state.productImages.length <= 6;
      },
    },

    actions: {
      open(event?: MouseEvent): void {
        // Try Interactivity API context first; fall back to reading
        // the data-wp-context attribute directly so clicks still work
        // even when hydrateRegions is aborted by a third-party error.
        let productId: number | undefined;
        try {
          const ctx = getContext<QuickViewContext>();
          productId = ctx.productId;
        } catch {
          // Context unavailable — hydration may have failed.
        }

        if (!productId && event?.target) {
          const trigger = (event.target as HTMLElement).closest<HTMLElement>(
            '[data-wp-context]'
          );
          if (trigger) {
            try {
              const raw = JSON.parse(
                trigger.getAttribute('data-wp-context') || '{}'
              ) as { productId?: number };
              productId = raw.productId;
            } catch {
              // Invalid JSON, give up.
            }
          }
        }

        // Validate productId is a positive integer to prevent path traversal.
        productId = parseInt(String(productId), 10);
        if (!productId || productId <= 0 || !Number.isFinite(productId)) {
          return;
        }

        // Store the trigger element for focus restoration.
        triggerElement =
          (event?.target as HTMLElement)?.closest<HTMLElement>('button') ||
          null;

        // Prepare overlay shell for open animation.
        const modalEl = document.getElementById(
          'aggressive-apparel-quick-view'
        );
        if (modalEl) {
          prepareOverlayOpen(modalEl);
        }

        // Reset all state.
        state.isOpen = true;
        state.isSuccessOpen = false;
        state.isLoading = true;
        state.hasError = false;
        state.hasProduct = false;
        state.productId = productId;
        state.productType = 'simple';
        state.productImage = '';
        state.productImageAlt = '';
        state.productName = '';
        state.productPrice = '';
        state.productRegularPrice = '';
        state.productOnSale = false;
        state.productDescription = '';
        state.productLink = '';
        state.productAttributes = [];
        state.productVariations = [];
        state.selectedAttributes = {};
        state.matchedVariationId = 0;
        state.quantity = 1;
        state.cartError = '';
        variationImageCache.clear();
        state.productImages = [];
        state._originalImages = [];
        state.activeImageIndex = 0;
        state.stockStatus = 'instock';
        state.stockQuantity = null;
        state.stockStatusLabel = '';
        state.salePercentage = 0;
        state.productPriceRange = '';
        state.isDrawerOpen = false;
        state.announcement = '';
        // Setup focus trap after modal renders.
        requestAnimationFrame(() => {
          const modal = document.querySelector<HTMLElement>(
            '.aggressive-apparel-quick-view__modal'
          );
          if (modal && modalEl) {
            focusTrapCleanup = activateOverlayFocus({
              shell: modalEl,
              panel: modal,
              focusSelector: '.aggressive-apparel-quick-view__close',
            });
          }
        });

        // Cancel any in-flight product fetch from a previous open.
        if (fetchController) fetchController.abort();
        fetchController = new AbortController();

        // Fetch product data.
        const base = state.restBase || '/wp-json/wc/store/v1/products/';
        const url = `${base}${productId}`;

        fetch(url, { signal: fetchController.signal })
          .then((res: Response) => {
            if (!res.ok) {
              throw new Error(`HTTP ${res.status}`);
            }
            return res.json();
          })
          .then((data: StoreApiProduct) => {
            if (!data || !data.name) {
              state.hasError = true;
              return;
            }

            state.productName = stripTags(data.name);

            // Validate permalink is a safe HTTP(S) URL.
            const permalink = data.permalink || '#';
            state.productLink =
              permalink === '#' || /^https?:\/\//i.test(permalink)
                ? permalink
                : '#';
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
              state.productImageAlt = stripTags(
                data.images[0].alt || data.name
              );
            }

            // Price — show a range for variable products with differing
            // variation prices (e.g. "$12.00 – $15.00").
            const priceData: PriceResult = parsePrice(data.prices);
            const range = data.prices?.price_range;
            if (
              data.type === 'variable' &&
              range &&
              range.min_amount &&
              range.max_amount &&
              range.min_amount !== range.max_amount
            ) {
              const mu = data.prices.currency_minor_unit ?? 2;
              const div = Math.pow(10, mu);
              const pre = data.prices.currency_prefix ?? '$';
              const suf = data.prices.currency_suffix ?? '';
              const lo = (parseInt(range.min_amount, 10) / div).toFixed(mu);
              const hi = (parseInt(range.max_amount, 10) / div).toFixed(mu);
              const rangeStr = `${pre}${lo}${suf} – ${pre}${hi}${suf}`;
              state.productPrice = rangeStr;
              state.productPriceRange = rangeStr;
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
            }

            state.hasProduct = true;
          })
          .catch((err: Error) => {
            // Aborted fetches are expected (user opened a different product).
            if (err?.name === 'AbortError') return;
            state.hasError = true;
          })
          .finally(() => {
            state.isLoading = false;
            // Fallback DOM sync for when hydration didn't run.
            populateModalDOM();
          });
      },

      close(): void {
        const trap = focusTrapCleanup;
        focusTrapCleanup = null;

        state.isOpen = false;
        state.hasProduct = false;
        state.hasError = false;
        state.isCartSuccess = false;
        state.cartError = '';
        state.isDrawerOpen = false;
        state.announcement = '';

        const modalEl = document.getElementById(
          'aggressive-apparel-quick-view'
        );
        const panel = modalEl?.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__modal'
        );

        if (modalEl && panel) {
          closeOverlay({
            shell: modalEl,
            panel,
            focusTrapCleanup: trap,
            triggerElement,
            isStillOpen: () => state.isOpen,
            onFinish: () => {
              triggerElement = null;
            },
          });
        } else {
          triggerElement?.focus();
          triggerElement = null;
        }
      },

      /**
       * Replace quick view with a standalone add-to-cart confirmation.
       * Both overlays briefly hold the scroll lock during the handoff so
       * the page cannot jump between dialogs.
       */
      openCartSuccess(): void {
        const successShell = document.getElementById(
          'aggressive-apparel-cart-success'
        );
        const successPanel = successShell?.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view-success__panel'
        );

        if (!successShell || !successPanel) {
          actions.close();
          return;
        }

        const wasQuickViewOpen = state.isOpen;

        state.isSuccessOpen = true;
        prepareOverlayOpen(successShell);
        populateCartSuccessDOM();

        const quickViewTrap = focusTrapCleanup;
        focusTrapCleanup = null;
        const quickViewShell = document.getElementById(
          'aggressive-apparel-quick-view'
        );
        const quickViewPanel = quickViewShell?.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__modal'
        );

        state.isOpen = false;
        state.isDrawerOpen = false;

        if (wasQuickViewOpen && quickViewShell && quickViewPanel) {
          closeOverlay({
            shell: quickViewShell,
            panel: quickViewPanel,
            focusTrapCleanup: quickViewTrap,
            isStillOpen: () => state.isOpen,
          });
        } else {
          quickViewTrap?.();
        }

        successFocusTrapCleanup = activateOverlayFocus({
          shell: successShell,
          panel: successPanel,
          focusSelector: '.aggressive-apparel-quick-view-success__close',
        });
      },

      /**
       * Close the standalone cart confirmation and return focus to the
       * product image that launched quick view.
       */
      closeCartSuccess(): void {
        if (!state.isSuccessOpen) return;

        const trap = successFocusTrapCleanup;
        successFocusTrapCleanup = null;
        state.isSuccessOpen = false;
        state.hasProduct = false;
        state.isCartSuccess = false;
        state.cartError = '';
        state.announcement = '';

        const successShell = document.getElementById(
          'aggressive-apparel-cart-success'
        );
        const successPanel = successShell?.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view-success__panel'
        );

        if (successShell && successPanel) {
          closeOverlay({
            shell: successShell,
            panel: successPanel,
            focusTrapCleanup: trap,
            triggerElement,
            isStillOpen: () => state.isSuccessOpen,
            onFinish: () => {
              triggerElement = null;
            },
          });
        } else {
          trap?.();
          triggerElement?.focus();
          triggerElement = null;
        }
      },

      selectAttribute(): void {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption;
        // `ctx.item` is set by data-wp-each for the option button.
        // Each option carries its parent `attrSlug`.
        const attrSlug = item.attrSlug;
        // Use varValue (the variation-compatible value) for matching.
        // Falls back to slug for attributes where they're identical.
        const optionValue = item.varValue || item.slug;

        if (!attrSlug || !optionValue) {
          return;
        }

        // Toggle: deselect if same option clicked again.
        const current = state.selectedAttributes[attrSlug];
        const newSelected: Record<string, string> = {
          ...state.selectedAttributes,
        };
        newSelected[attrSlug] = current === optionValue ? '' : optionValue;
        state.selectedAttributes = newSelected;

        // Deep-copy variations out of the Interactivity API proxy so
        // matchVariation sees plain objects (avoids potential proxy
        // iteration edge cases with nested arrays/objects).
        const plainVariations = state.productVariations.map(
          (v: ResolvedVariation) => ({
            id: v.id,
            attributes: (v.attributes || []).map(a => ({
              attribute: a.attribute,
              name: a.name,
              value: a.value,
              taxonomy: a.taxonomy,
            })),
            prices: v.prices
              ? {
                  price: (v.prices as Record<string, unknown>).price,
                  regular_price: (v.prices as Record<string, unknown>)
                    .regular_price,
                  sale_price: (v.prices as Record<string, unknown>).sale_price,
                  currency_minor_unit: (v.prices as Record<string, unknown>)
                    .currency_minor_unit,
                  currency_prefix: (v.prices as Record<string, unknown>)
                    .currency_prefix,
                  currency_suffix: (v.prices as Record<string, unknown>)
                    .currency_suffix,
                }
              : null,
            image: v.image,
            imageAlt: v.imageAlt,
          })
        );

        // Try to match a variation.
        const match = matchVariation(plainVariations, newSelected);

        if (match) {
          state.matchedVariationId = match.id;

          // Update price from the variation's own pricing data.
          if (match.prices) {
            const priceData: PriceResult = parsePrice(
              match.prices as StoreApiPrices
            );
            state.productPrice = priceData.current;
            state.productRegularPrice = priceData.regular;
            state.productOnSale = priceData.onSale;

            // Update sale badge.
            if (priceData.onSale) {
              const regular = parseInt(
                String((match.prices as Record<string, unknown>).regular_price),
                10
              );
              const sale = parseInt(
                String(
                  (match.prices as Record<string, unknown>).sale_price ||
                    (match.prices as Record<string, unknown>).price
                ),
                10
              );
              state.salePercentage = calculateSalePercentage(regular, sale);
            } else {
              state.salePercentage = 0;
            }
          }

          // Force DOM sync — ensures the price updates visually even
          // if the Interactivity API's data-wp-text reactive binding
          // doesn't trigger (e.g. inside drawers or after populateModalDOM).
          syncPriceDOM();

          // Fetch the variation's own image from the Store API.
          const vid = match.id;
          if (variationImageCache.has(vid)) {
            applyVariationImage(variationImageCache.get(vid) || null);
          } else {
            const base = state.restBase || '/wp-json/wc/store/v1/products/';
            fetch(`${base}${vid}`)
              .then((res: Response) => (res.ok ? res.json() : null))
              .then((data: StoreApiProduct | null) => {
                if (!data) return;
                const rawSrc =
                  data.images && data.images.length > 0
                    ? data.images[0].src
                    : '';
                const img =
                  rawSrc && /^https?:\/\//i.test(rawSrc)
                    ? {
                        src: rawSrc,
                        alt:
                          (data.images?.[0]?.alt as string) ||
                          state.productName,
                      }
                    : null;
                variationImageCache.set(vid, img);
                // Only apply if this variation is still the active match.
                if (state.matchedVariationId === vid) {
                  applyVariationImage(img);
                }
              })
              .catch(() => {});
          }
        } else {
          state.matchedVariationId = 0;

          // Restore range price when no variation is matched.
          if (state.productPriceRange) {
            state.productPrice = state.productPriceRange;
            state.productRegularPrice = '';
            state.productOnSale = false;
            state.salePercentage = 0;
          }

          // Restore original gallery when no variation is matched.
          if (state._originalImages.length > 0) {
            state.productImages = state._originalImages.map(
              (img: GalleryImage) => ({
                ...img,
              })
            );
            state.activeImageIndex = 0;
          }

          syncPriceDOM();
        }
      },

      /**
       * Scroll the thumbnail strip left or right.
       */
      scrollThumbnails(event: MouseEvent): void {
        const btn = (event.target as HTMLElement).closest<HTMLElement>(
          '[data-scroll-dir]'
        );
        if (!btn) {
          return;
        }
        const dir = btn.dataset.scrollDir === 'left' ? -1 : 1;
        const strip = btn
          .closest('.aggressive-apparel-quick-view__thumbnail-nav')
          ?.querySelector<HTMLElement>(
            '.aggressive-apparel-quick-view__thumbnails'
          );
        if (!strip) {
          return;
        }
        const step = 64; // 3.5rem thumb + 0.5rem gap.
        strip.scrollBy({
          left: dir * step,
          behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
        });
      },

      incrementQty(event?: Event): void {
        // Mark as handled so the delegation fallback doesn't double-fire.
        if (event) event.preventDefault();
        const max = state.stockQuantity || 9999;
        if (state.quantity < max) {
          state.quantity = state.quantity + 1;
        }
      },

      decrementQty(event?: Event): void {
        // Mark as handled so the delegation fallback doesn't double-fire.
        if (event) event.preventDefault();
        if (state.quantity > 1) {
          state.quantity = state.quantity - 1;
        }
      },

      /**
       * Set quantity from direct input.
       */
      setQuantity(event: Event): void {
        const target = event.target as HTMLInputElement;
        const value = parseInt(target.value, 10);
        const max = state.stockQuantity || 9999;
        if (!isNaN(value) && value >= 1 && value <= max) {
          state.quantity = value;
        } else {
          // Reset to valid value.
          target.value = String(state.quantity);
        }
      },

      /**
       * Select a gallery image by thumbnail or dot click.
       */
      selectImage(): void {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as GalleryImage | undefined;
        if (!item) {
          return;
        }
        const index = state.productImages.findIndex(
          (img: GalleryImage) => img.id === item.id
        );
        if (index >= 0 && index !== state.activeImageIndex) {
          fadeImage();
          state.activeImageIndex = index;
        }
      },

      /**
       * Navigate to next gallery image.
       */
      nextImage(): void {
        const max = state.productImages.length - 1;
        if (max <= 0) {
          return;
        }
        fadeImage();
        state.activeImageIndex =
          state.activeImageIndex >= max ? 0 : state.activeImageIndex + 1;
      },

      /**
       * Navigate to previous gallery image.
       */
      prevImage(): void {
        const max = state.productImages.length - 1;
        if (max <= 0) {
          return;
        }
        fadeImage();
        state.activeImageIndex =
          state.activeImageIndex <= 0 ? max : state.activeImageIndex - 1;
      },

      /**
       * Touch swipe handlers for mobile gallery navigation.
       */
      handleTouchStart(event: TouchEvent): void {
        if (!state.hasMultipleImages) return;
        const touch = event.touches[0];
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
        isSwiping = false;
      },

      handleTouchMove(event: TouchEvent): void {
        if (!state.hasMultipleImages) return;
        const touch = event.touches[0];
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;

        // Lock to horizontal swipe once threshold is met.
        if (
          !isSwiping &&
          Math.abs(deltaX) > 10 &&
          Math.abs(deltaX) > Math.abs(deltaY)
        ) {
          isSwiping = true;
        }

        // Prevent vertical scroll while swiping gallery.
        if (isSwiping) {
          event.preventDefault();
        }
      },

      handleTouchEnd(event: TouchEvent): void {
        if (!state.hasMultipleImages || !isSwiping) return;
        const touch = event.changedTouches[0];
        const deltaX = touch.clientX - touchStartX;
        const threshold = 50;

        if (Math.abs(deltaX) >= threshold) {
          if (deltaX < 0) {
            actions.nextImage();
          } else {
            actions.prevImage();
          }
        }

        isSwiping = false;
      },

      /**
       * Open the mobile bottom drawer for option selection.
       */
      openDrawer(): void {
        const drawerEl = document.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__drawer'
        );
        if (drawerEl) {
          drawerEl.hidden = false;
          void drawerEl.offsetHeight;
        }
        state.isDrawerOpen = true;
      },

      /**
       * Close the mobile bottom drawer with slide-down animation.
       */
      closeDrawer(): void {
        state.isDrawerOpen = false;

        const panel = document.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__drawer-panel'
        );
        let done = false;
        const finish = (): void => {
          if (done) return;
          done = true;
          const el = document.querySelector<HTMLElement>(
            '.aggressive-apparel-quick-view__drawer'
          );
          if (el && !state.isDrawerOpen) {
            el.hidden = true;
          }
        };

        if (panel) {
          panel.addEventListener(
            'transitionend',
            (e: Event) => {
              if ((e as TransitionEvent).propertyName === 'transform') finish();
            },
            { once: true }
          );
          setTimeout(finish, 400);
        } else {
          finish();
        }
      },

      /**
       * Handle keyboard events (ESC to close, arrows for gallery).
       */
      handleKeydown(event: KeyboardEvent): void {
        if (state.isSuccessOpen && event.key === 'Escape') {
          actions.closeCartSuccess();
          return;
        }

        if (!state.isOpen) {
          return;
        }

        if (event.key === 'Escape') {
          if (state.isDrawerOpen) {
            actions.closeDrawer();
          } else {
            actions.close();
          }
          return;
        }

        // Arrow keys for gallery navigation.
        if (state.hasMultipleImages) {
          if (event.key === 'ArrowLeft') {
            actions.prevImage();
          } else if (event.key === 'ArrowRight') {
            actions.nextImage();
          }
        }
      },

      async addToCart(event?: Event): Promise<void> {
        if (event) event.preventDefault();
        if (!state.canAddToCart) {
          return;
        }

        state.isAddingToCart = true;
        state.cartError = '';

        // Determine the ID to add (variation ID for variable, product ID for simple).
        const itemId: number =
          state.productType === 'variable' && state.matchedVariationId
            ? state.matchedVariationId
            : state.productId;

        const body: CartAddBody = {
          id: itemId,
          quantity: state.quantity,
        };

        // For variable products, send variation attributes using the keys
        // from the matched variation (which use the proper taxonomy slugs
        // like "pa_color" that the Store API expects).
        if (state.productType === 'variable' && state.matchedVariationId) {
          const matchedVar = state.productVariations.find(
            (v: ResolvedVariation) => v.id === state.matchedVariationId
          );
          if (matchedVar && matchedVar.attributes) {
            body.variation = matchedVar.attributes
              .filter(attr => attr.value)
              .map(attr => ({
                attribute: attr.attribute || attr.name || '',
                value: attr.value,
              }));
          }
        }

        const cartUrl = state.cartApiUrl || '/wp-json/wc/store/v1/cart';
        const addUrl = `${cartUrl}/add-item`;

        // Ensure we have a valid nonce before sending.
        if (!state.cartNonce) {
          try {
            const cartRes = await fetch(cartUrl, {
              credentials: 'same-origin',
            });
            const freshNonce = cartRes.headers.get('Nonce');
            if (freshNonce) {
              state.cartNonce = freshNonce;
            }
          } catch {
            // Fall through — request will fail with a clear nonce error.
          }
        }

        if (!state.cartNonce) {
          state.cartError = 'Session expired. Please reload the page.';
          state.isAddingToCart = false;
          return;
        }

        const headers: Record<string, string> = {
          'Content-Type': 'application/json',
          Nonce: state.cartNonce,
        };

        fetch(addUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers,
          body: JSON.stringify(body),
        })
          .then((res: Response) => {
            // Capture the refreshed nonce for subsequent requests.
            const newNonce = res.headers.get('Nonce');
            if (newNonce) {
              state.cartNonce = newNonce;
            }

            if (!res.ok) {
              return res.json().then((err: { message?: string }) => {
                throw new Error(err.message || `HTTP ${res.status}`);
              });
            }
            return res.json();
          })
          .then(() => {
            // Show success state on the button briefly before opening the
            // standalone cart confirmation.
            state.isCartSuccess = true;
            state.isAddingToCart = false;

            setTimeout(() => {
              state.isCartSuccess = false;

              state.announcement = getLabel(
                'addedSuccessAnnounce',
                'Product added to cart successfully'
              );
              actions.openCartSuccess();

              // Dispatch a custom event so WooCommerce mini-cart can update.
              document.body.dispatchEvent(
                new CustomEvent('wc-blocks_added_to_cart', {
                  bubbles: true,
                })
              );
            }, 800);
          })
          .catch((err: Error) => {
            if (typeof window.SCRIPT_DEBUG !== 'undefined') {
              console.error('[Quick View] Add to cart failed:', err);
            }
            state.cartError =
              decodeEntities(err.message) ||
              getLabel('addToCartError', 'Could not add to cart.');
            const errorTemplate = getLabel('errorAnnounce', 'Error: %s');
            state.announcement = errorTemplate.replace('%s', state.cartError);
            state.isAddingToCart = false;
          });
      },

      /**
       * Add item to cart and redirect to checkout immediately.
       *
       * Mirrors addToCart logic but navigates to the checkout page on
       * success instead of showing the cart confirmation.
       */
      async buyNow(event?: Event): Promise<void> {
        if (event) event.preventDefault();
        if (!state.canAddToCart) {
          return;
        }

        state.isBuyingNow = true;
        state.cartError = '';

        const itemId: number =
          state.productType === 'variable' && state.matchedVariationId
            ? state.matchedVariationId
            : state.productId;

        const body: CartAddBody = {
          id: itemId,
          quantity: state.quantity,
        };

        if (state.productType === 'variable' && state.matchedVariationId) {
          const matchedVar = state.productVariations.find(
            (v: ResolvedVariation) => v.id === state.matchedVariationId
          );
          if (matchedVar && matchedVar.attributes) {
            body.variation = matchedVar.attributes
              .filter(attr => attr.value)
              .map(attr => ({
                attribute: attr.attribute || attr.name || '',
                value: attr.value,
              }));
          }
        }

        const cartUrl = state.cartApiUrl || '/wp-json/wc/store/v1/cart';
        const addUrl = `${cartUrl}/add-item`;

        if (!state.cartNonce) {
          try {
            const cartRes = await fetch(cartUrl, {
              credentials: 'same-origin',
            });
            const freshNonce = cartRes.headers.get('Nonce');
            if (freshNonce) {
              state.cartNonce = freshNonce;
            }
          } catch {
            // Fall through.
          }
        }

        if (!state.cartNonce) {
          state.cartError = 'Session expired. Please reload the page.';
          state.isBuyingNow = false;
          return;
        }

        try {
          const res = await fetch(addUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              Nonce: state.cartNonce,
            },
            body: JSON.stringify(body),
          });

          const newNonce = res.headers.get('Nonce');
          if (newNonce) {
            state.cartNonce = newNonce;
          }

          if (!res.ok) {
            const err: { message?: string } = await res.json();
            throw new Error(err.message || `HTTP ${res.status}`);
          }

          // Redirect to checkout.
          window.location.href = state.checkoutUrl;
        } catch (err) {
          if (typeof window.SCRIPT_DEBUG !== 'undefined') {
            console.error('[Quick View] Buy now failed:', err);
          }
          state.cartError =
            decodeEntities((err as Error).message) ||
            getLabel('addToCartError', 'Could not add to cart.');
          const errorTemplate = getLabel('errorAnnounce', 'Error: %s');
          state.announcement = errorTemplate.replace('%s', state.cartError);
          state.isBuyingNow = false;
        }
      },
    },

    callbacks: {
      /**
       * Sync an img element's src/alt with state.currentImage.
       * Used on all images that display the active product image.
       * Replaces data-wp-bind--src which Breeze's DOMDocument mangles.
       */
      syncCurrentImage(): void {
        const { ref } = getElement() as { ref: HTMLImageElement | null };
        if (!ref) return;
        const img = state.currentImage;
        ref.src = img.src || '';
        ref.alt = img.alt || '';
      },

      /**
       * Sync an img element inside a data-wp-each loop with its
       * context item thumbnail. Used for gallery thumbnail buttons.
       */
      syncThumbnail(): void {
        const ctx = getContext<QuickViewContext>();
        const { ref } = getElement() as { ref: HTMLImageElement | null };
        const item = ctx.item as GalleryImage | undefined;
        if (!ref || !item) return;
        ref.src = item.thumbnail || '';
        ref.alt = item.alt || '';
      },

      /**
       * Set the --swatch-color CSS custom property on a color swatch button.
       *
       * The Interactivity API's data-wp-style-- directive uses
       * element.style[prop] assignment which doesn't work for CSS custom
       * properties (they need setProperty). This callback runs once per
       * swatch via data-wp-init to set the property correctly.
       */
      syncSwatchColor(): void {
        const ctx = getContext<QuickViewContext>();
        const { ref } = getElement() as { ref: HTMLElement | null };
        const item = ctx.item as QuickViewOption | undefined;
        if (!ref || !item) return;
        const swatchData = state.colorSwatchData[item.slug];
        if (!swatchData || !swatchData.value) return;
        const v = swatchData.value;
        if (/^#[0-9a-f]{3,8}$/i.test(v) || /^oklch\([^;{}]*\)$/i.test(v)) {
          ref.style.setProperty('--swatch-color', v);
        }
      },
    },
  }
);

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
function syncModalDOM(): void {
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
