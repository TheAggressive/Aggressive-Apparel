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

import { store, getContext, getElement } from '@wordpress/interactivity';
import { describeUnavailableOption } from '@aggressive-apparel/helpers';
import { isColorSlug } from './quick-view/product-data';
import type {
  GalleryImage,
  QuickViewContext,
  QuickViewLabels,
  QuickViewOption,
  QuickViewStore,
  ResolvedAttribute,
  ResolvedOption,
} from './quick-view/types';
// Side-effect imports: view.ts registers the capture-phase click delegation;
// actions.ts registers the store's actions via a second store() call.
import './quick-view/view';
import './quick-view/actions';

declare global {
  interface Window {
    SCRIPT_DEBUG?: boolean;
  }
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

/**
 * Resolve server-provided copy with a local fallback for cached markup.
 */
export function getLabel(key: keyof QuickViewLabels, fallback: string): string {
  return state.i18n?.[key] || fallback;
}

/* ------------------------------------------------------------------ */
/*  Store                                                              */
/* ------------------------------------------------------------------ */

/**
 * Apply a variation image to the gallery.
 *
 * If the image already exists in the gallery, switch to it.
 * Otherwise, replace the first gallery entry with the variation image.
 * Passing null restores the original gallery.
 */
export const { state, actions } = store<QuickViewStore>(
  'aggressive-apparel/quick-view',
  {
    state: {
      // Provided by PHP via wp_interactivity_state().
      restBase: '',
      cartApiUrl: '',
      // collapseVariablePrice / priceStartingPrefix are intentionally NOT
      // seeded here: the client store literal is deep-merged with override=true
      // *over* the server state, so a default here would clobber the
      // PHP-seeded value (that's why the "From $X" collapse never applied in
      // Quick View). Omitting them — like cartNonce — lets the server value win.
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
      availableOptions: {},

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
       * Whether the current option button (data-wp-each context) has no
       * in-stock variation for the current selection. Bound to `disabled` and
       * the `is-unavailable` class. The currently-selected option is never
       * marked unavailable, so a shopper can always toggle their pick back off.
       */
      get isOptionUnavailable(): boolean {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption | undefined;
        if (!item || !item.attrSlug) {
          return false;
        }
        const value = (item.varValue || item.slug || '').toLowerCase();
        if (
          (state.selectedAttributes[item.attrSlug] || '').toLowerCase() ===
          value
        ) {
          return false;
        }
        const available = state.availableOptions[item.attrSlug];
        // Not computed yet (still loading) — never disable.
        if (!available) {
          return false;
        }
        return !available.includes(value);
      },

      /**
       * Accessible name for the current option button — the display name, with
       * a translated "Unavailable" suffix appended when it has no in-stock
       * variation. Options use `aria-disabled` (not `disabled`) so keyboard and
       * screen-reader users can still reach the control and hear *why* it's off.
       */
      get optionAccessibleName(): string {
        const ctx = getContext<QuickViewContext>();
        const item = ctx.item as QuickViewOption | undefined;
        if (!item) {
          return '';
        }
        const swatch = state.colorSwatchData[item.slug || ''];
        const base = swatch && swatch.name ? swatch.name : item.name;
        return state.isOptionUnavailable
          ? describeUnavailableOption(
              base,
              getLabel('unavailableLabel', 'Unavailable')
            )
          : base;
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
