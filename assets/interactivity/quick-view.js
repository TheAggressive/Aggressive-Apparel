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
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';
import {
  parsePrice,
  stripTags,
  decodeEntities,
  setupFocusTrap,
} from '@aggressive-apparel/helpers';

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

/**
 * Whether an attribute slug (or name) represents a color attribute.
 *
 * Handles taxonomy slugs (`pa_color`), plain names (`Color`), and
 * British spelling (`colour`) — all case-insensitively.
 *
 * @param {string} slug - Attribute slug or taxonomy (e.g. `pa_color`).
 * @param {string} [name] - Human-readable attribute name (e.g. `Color`).
 * @return {boolean}
 */
function isColorSlug(slug, name) {
  const s = (slug || '').toLowerCase();
  const n = (name || '').toLowerCase();
  return (
    s === 'pa_color' ||
    s === 'color' ||
    s === 'pa_colour' ||
    s === 'colour' ||
    n === 'color' ||
    n === 'colour'
  );
}

/**
 * Pick the best description from a Store API product.
 *
 * @param {Object} product - Store API product object.
 * @return {string} Plain-text description.
 */
function pickDescription(product) {
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
 *
 * @param {Object} product - Store API product object.
 * @return {Array} Array of image objects with src, alt, thumbnail.
 */
function buildGalleryImages(product) {
  if (!product.images || product.images.length === 0) {
    return [];
  }
  return product.images.map((img, index) => ({
    id: img.id || index,
    src: img.src,
    alt: stripTags(img.alt || product.name),
    thumbnail: img.thumbnail || img.src,
  }));
}

/**
 * Get stock status info from Store API product.
 *
 * @param {Object} product - Store API product object.
 * @return {Object} Stock info with status, quantity, and label.
 */
function getStockInfo(product) {
  const lowThreshold = 3;
  const isInStock = product.is_in_stock !== false;
  const qty = product.stock_quantity;

  if (!isInStock) {
    return {
      status: 'outofstock',
      quantity: 0,
      label: 'Out of Stock',
    };
  }

  if (qty !== null && qty !== undefined && qty <= lowThreshold && qty > 0) {
    return {
      status: 'lowstock',
      quantity: qty,
      label: `Only ${qty} left!`,
    };
  }

  return {
    status: 'instock',
    quantity: qty,
    label: 'In Stock',
  };
}

/**
 * Calculate sale percentage from prices.
 *
 * @param {number} regularPrice - Regular price in minor units.
 * @param {number} salePrice    - Sale price in minor units.
 * @return {number} Percentage discount (0-100).
 */
function calculateSalePercentage(regularPrice, salePrice) {
  if (!regularPrice || !salePrice || regularPrice <= salePrice) {
    return 0;
  }
  return Math.round(((regularPrice - salePrice) / regularPrice) * 100);
}

/**
 * Return a numeric rank for an apparel size string.
 * Handles: XS, S, M, L, XL, and multiplied variants like 2XS, 3XL, 7XL.
 * Unknown sizes get Infinity so they sort to the end.
 *
 * @param {string} size - Size label (case-insensitive).
 * @return {number} Sort rank.
 */
function sizeRank(size) {
  const s = size.trim().toUpperCase();

  // Multiplied small: 2XS, 3XS, etc. — smaller means lower rank.
  // 3XS < 2XS < XS, so we invert: rank = -(multiplier).
  const xsMatch = s.match(/^(\d+)XS$/);
  if (xsMatch) return -parseInt(xsMatch[1], 10);

  const bases = { XS: 1, S: 2, M: 3, L: 4, XL: 5 };
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
 *
 * @param {string} slug - Attribute slug (e.g. "pa_size").
 * @param {string} name - Attribute display name (e.g. "Size").
 * @return {boolean}
 */
function isSizeAttr(slug, name) {
  const s = (slug || '').toLowerCase();
  const n = (name || '').toLowerCase();
  return s === 'pa_size' || s === 'size' || n === 'size';
}

/**
 * Build attribute data from a Store API product for template rendering.
 *
 * @param {Object} product - Store API product response.
 * @return {Array} Array of `{ name, slug, options: [{ name, slug }] }`.
 */
function buildAttributes(product, colorSwatchData, variations) {
  if (!product.attributes || product.attributes.length === 0) {
    return [];
  }

  // Build a mapping from term values to the attribute keys that
  // variations actually use (e.g. "red" → "pa_color"). This lets us
  // resolve the correct slug when product-level attr.taxonomy is empty.
  // Build a lowercase mapping so lookups are case-insensitive:
  // e.g. variation value "Red" → stored as "red" → "pa_color".
  const varKeyByValue = {};
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
  // display name from our Color_Data_Manager swatch data. On some hosts
  // both term.slug and term.name are numeric IDs ("237") while the
  // swatch data holds the real name ("Red").
  const termCandidates = term => {
    const candidates = [];
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

  const attrSlugFor = attr => {
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
  const varValuesByAttr = {};
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
    .filter(attr => attr.has_variations)
    .map(attr => {
      const slug = attrSlugFor(attr);
      const colorAttr = isColorSlug(slug, attr.name);
      const varValues = varValuesByAttr[slug] || new Set();

      const options = (attr.terms || []).map(term => {
        const termSlug = term.slug || term.name;
        // For color attributes, prefer the display name from our
        // Color_Data_Manager swatch data over the Store API term name
        // which may return numeric IDs on some configurations.
        // Try slug first, then fall back to term ID.
        const swatch =
          colorAttr && colorSwatchData
            ? colorSwatchData[termSlug] ||
              (term.id ? colorSwatchData[String(term.id)] : null)
            : null;

        // Resolve the variation-compatible value for this term.
        // Try all candidate names (slug, name, swatch name) against
        // the actual variation values for this attribute.
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
      });

      // Sort size options in logical apparel order (XS → S → M → … → 7XL).
      if (isSizeAttr(slug, attr.name)) {
        options.sort((a, b) => sizeRank(a.name) - sizeRank(b.name));
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
 *
 * @param {Object} product     - Store API product response.
 * @param {Object} nameToSlug  - Map of lowercase display name → taxonomy slug.
 * @return {Array} Array of variation objects.
 */
function buildVariations(product, nameToSlug = {}) {
  if (!product.variations || product.variations.length === 0) {
    return [];
  }

  return product.variations.map(v => ({
    id: v.id,
    attributes: (v.attributes || []).map(attr => ({
      ...attr,
      // Add the resolved taxonomy slug so matchVariation can use it.
      attribute:
        attr.attribute ||
        nameToSlug[(attr.name || '').toLowerCase()] ||
        attr.name,
    })),
    image:
      v.image && v.image.src
        ? v.image.src
        : product.images && product.images.length > 0
          ? product.images[0].src
          : '',
    imageAlt: v.image && v.image.alt ? v.image.alt : product.name || '',
    prices: v.prices || product.prices,
  }));
}

/**
 * Find a variation matching the selected attributes.
 *
 * @param {Array}  variations - Array from buildVariations().
 * @param {Object} selected   - Map of slug -> value.
 * @return {Object|null} Matching variation or null.
 */
function matchVariation(variations, selected) {
  const selectedKeys = Object.keys(selected).filter(k => selected[k]);
  if (selectedKeys.length === 0) {
    return null;
  }

  // Build a case-insensitive lookup so that keys like "Color" match
  // variation attribute keys like "pa_color" or "Color".
  const selectedLower = {};
  for (const [key, value] of Object.entries(selected)) {
    selectedLower[key.toLowerCase()] = value;
  }

  return (
    variations.find(v =>
      v.attributes.every(attr => {
        // Try every possible key the variation attribute might use.
        const possibleKeys = [attr.attribute, attr.name, attr.taxonomy].filter(
          Boolean
        );

        let val;
        for (const k of possibleKeys) {
          val = selected[k] || selectedLower[k.toLowerCase()];
          if (val) break;
        }

        // "Any" attributes match everything.
        if (!attr.value) {
          return true;
        }
        // Case-insensitive value comparison: term slugs may be
        // lowercase ("red") while variation values may differ ("Red").
        return val && val.toLowerCase() === attr.value.toLowerCase();
      })
    ) || null
  );
}

/* ------------------------------------------------------------------ */
/*  Store                                                              */
/* ------------------------------------------------------------------ */

// Store reference for focus trap cleanup.
let focusTrapCleanup = null;
let triggerElement = null;

// Cache fetched variation image data so repeated selections don't refetch.
const variationImageCache = new Map();

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
 *
 * @param {{ src: string, alt: string } | null} img
 */
function applyVariationImage(img) {
  if (!img || !img.src) return;

  const galleryIndex = state.productImages.findIndex(
    i => i.src === img.src
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
 * Briefly fade the main product image to smooth gallery transitions.
 */
function fadeImage() {
  const img = document.querySelector(
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
function populateModalDOM() {
  const modal = document.querySelector('.aggressive-apparel-quick-view__modal');
  if (!modal) {
    return;
  }

  const q = sel => modal.querySelector(sel);

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

  const priceCurrent = q('.aggressive-apparel-quick-view__price-current');
  if (priceCurrent) {
    priceCurrent.textContent = state.productPrice;
  }

  const priceRegular = q('.aggressive-apparel-quick-view__price-regular');
  if (priceRegular) {
    priceRegular.textContent = state.productRegularPrice;
    priceRegular.hidden = !state.productOnSale;
  }

  const desc = q('.aggressive-apparel-quick-view__description');
  if (desc) {
    desc.textContent = state.productDescription;
  }

  // Main image.
  const img = q('.aggressive-apparel-quick-view__main-image img');
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
  const link = q('.aggressive-apparel-quick-view__link');
  if (link) {
    link.href = state.productLink;
  }

  // Cart row (quantity + add-to-cart).
  const cartRow = q('.aggressive-apparel-quick-view__cart-row');
  if (cartRow) {
    cartRow.hidden = state.showPostCartActions;
  }

  // Add to Cart button.
  const addBtn = q('.aggressive-apparel-quick-view__add-to-cart');
  if (addBtn) {
    addBtn.disabled = !state.canAddToCart;
    addBtn.textContent = state.addToCartLabel;
  }
}

const { state, actions } = store('aggressive-apparel/quick-view', {
  state: {
    // Provided by PHP via wp_interactivity_state().
    restBase: '',
    cartApiUrl: '',

    // Modal visibility.
    isOpen: false,
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

    // Cart interaction.
    cartNonce: '',
    isAddingToCart: false,
    isCartSuccess: false,
    addedToCart: false,
    cartError: '',

    // Mobile drawer.
    isDrawerOpen: false,
    drawerView: 'selection', // 'selection' | 'success'

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

    // Color swatch data (from PHP).
    colorSwatchData: {},

    // Post-cart actions.
    showPostCartActions: false,
    cartUrl: '/cart/',

    // Accessibility announcement.
    announcement: '',

    // Derived state.
    get isVariable() {
      return state.productType === 'variable';
    },

    get canAddToCart() {
      if (state.isAddingToCart || state.stockStatus === 'outofstock') {
        return false;
      }
      if (state.productType === 'simple') {
        return state.hasProduct;
      }
      // Variable: all attributes must be selected.
      return state.matchedVariationId > 0;
    },

    get addToCartLabel() {
      if (state.isCartSuccess) {
        return '✓ Added!';
      }
      if (state.isAddingToCart) {
        return 'Adding…';
      }
      if (state.stockStatus === 'outofstock') {
        return 'Out of Stock';
      }
      return 'Add to Cart';
    },

    /**
     * Whether the current option button (set by data-wp-each) is selected.
     *
     * Used with data-wp-class--is-selected on each swatch button.
     *
     * @return {boolean}
     */
    get isOptionSelected() {
      const ctx = getContext();
      if (!ctx.item || !ctx.item.attrSlug) {
        return false;
      }
      const val = ctx.item.varValue || ctx.item.slug;
      return state.selectedAttributes[ctx.item.attrSlug] === val;
    },

    /**
     * Current gallery image object.
     */
    get currentImage() {
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
    get hasMultipleImages() {
      return state.productImages.length > 1;
    },

    /**
     * Whether the current thumbnail is the active one.
     */
    get isActiveImage() {
      const ctx = getContext();
      if (!ctx.item) {
        return false;
      }
      const index = state.productImages.findIndex(
        img => img.id === ctx.item.id
      );
      return index === state.activeImageIndex;
    },

    /**
     * Sale badge text (e.g., "-25%").
     */
    get saleBadgeText() {
      if (state.salePercentage > 0) {
        return `-${state.salePercentage}%`;
      }
      return '';
    },

    /**
     * Stock status helpers.
     */
    get isInStock() {
      return state.stockStatus === 'instock';
    },

    get isLowStock() {
      return state.stockStatus === 'lowstock';
    },

    get isOutOfStock() {
      return state.stockStatus === 'outofstock';
    },

    // Negated getters for data-wp-bind--hidden directives.
    get isNotLoading() {
      return !state.isLoading;
    },

    get hasNoProduct() {
      return !state.hasProduct;
    },

    get isNotOnSale() {
      return !state.productOnSale;
    },

    get hasOneImage() {
      return !state.hasMultipleImages;
    },

    get cannotAddToCart() {
      return !state.canAddToCart;
    },

    get hidePostCartActions() {
      return !state.showPostCartActions;
    },

    get hasNoCartError() {
      return !state.cartError;
    },

    get hasNoError() {
      return !state.hasError;
    },

    /**
     * Whether the viewport matches mobile breakpoint (≤768px).
     */
    get isMobile() {
      return (
        typeof window !== 'undefined' &&
        window.matchMedia('(max-width: 768px)').matches
      );
    },

    /**
     * Hide "Select Options" button when not needed:
     * simple products or post-cart showing.
     */
    get hideSelectOptionsBtn() {
      return (
        !state.isVariable || state.showPostCartActions || state.addedToCart
      );
    },

    /**
     * Hide inline cart-row when post-cart panel is showing.
     * The row stays visible for both simple and variable products;
     * which *button* inside it is visible is controlled by
     * hideInlineAddToCart / hideSelectOptionsBtn.
     */
    get hideInlineCartRow() {
      return state.showPostCartActions;
    },

    /**
     * Hide inline Add to Cart button for variable products
     * (they use the drawer's Add to Cart instead).
     */
    get hideInlineAddToCart() {
      return state.isVariable;
    },

    get isDrawerClosed() {
      return !state.isDrawerOpen;
    },

    get isDrawerSuccessView() {
      return state.drawerView !== 'selection';
    },

    get hideDrawerSuccess() {
      return state.drawerView !== 'success';
    },

    /**
     * Label showing selected variation options (e.g. "Red / L").
     * Resolves slugs to display names from productAttributes.
     */
    get selectedOptionsLabel() {
      const names = [];
      for (const [attrSlug, optionValue] of Object.entries(
        state.selectedAttributes
      )) {
        if (!optionValue) continue;
        const attr = state.productAttributes.find(a => a.slug === attrSlug);
        const opt = attr?.options?.find(
          o => (o.varValue || o.slug) === optionValue
        );
        names.push(opt?.name || optionValue);
      }
      return names.length > 0 ? names.join(' / ') : '';
    },

    /**
     * Whether the current attribute is a color attribute.
     */
    get isColorAttribute() {
      const ctx = getContext();
      if (!ctx.item) {
        return false;
      }
      return isColorSlug(ctx.item.slug, ctx.item.name);
    },

    /**
     * Inverse of isColorAttribute for hidden binding.
     */
    get isNotColorAttribute() {
      const ctx = getContext();
      if (!ctx.item) {
        return true;
      }
      return !isColorSlug(ctx.item.slug, ctx.item.name);
    },

    /**
     * Whether the current option is a color swatch.
     */
    get isColorSwatch() {
      const ctx = getContext();
      if (!ctx.item || !ctx.item.slug) {
        return false;
      }
      return !!state.colorSwatchData[ctx.item.slug];
    },

    /**
     * Get the color value for the current swatch option.
     */
    get colorSwatchValue() {
      const ctx = getContext();
      if (!ctx.item || !ctx.item.slug) {
        return '';
      }
      const swatchData = state.colorSwatchData[ctx.item.slug];
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
    get colorSwatchName() {
      const ctx = getContext();
      if (!ctx.item) {
        return '';
      }
      const swatchData = state.colorSwatchData[ctx.item.slug];
      return swatchData && swatchData.name ? swatchData.name : ctx.item.name;
    },

    /**
     * Whether all thumbnails fit without scrolling (≤ 6 images).
     */
    get thumbnailsFitContainer() {
      return state.productImages.length <= 6;
    },
  },

  actions: {
    open(event) {
      // Try Interactivity API context first; fall back to reading
      // the data-wp-context attribute directly so clicks still work
      // even when hydrateRegions is aborted by a third-party error.
      let productId;
      try {
        const ctx = getContext();
        productId = ctx.productId;
      } catch {
        // Context unavailable — hydration may have failed.
      }

      if (!productId && event?.target) {
        const trigger = event.target.closest('[data-wp-context]');
        if (trigger) {
          try {
            const raw = JSON.parse(
              trigger.getAttribute('data-wp-context') || '{}'
            );
            productId = raw.productId;
          } catch {
            // Invalid JSON, give up.
          }
        }
      }

      // Validate productId is a positive integer to prevent path traversal.
      productId = parseInt(productId, 10);
      if (!productId || productId <= 0 || !Number.isFinite(productId)) {
        return;
      }

      // Store the trigger element for focus restoration.
      triggerElement = event?.target?.closest('button') || null;

      // Remove hidden and force a reflow so the browser renders the
      // "before" state (opacity 0, scale 0.95) before .is-open is added.
      const modalEl = document.getElementById('aggressive-apparel-quick-view');
      if (modalEl) {
        modalEl.hidden = false;
        void modalEl.offsetHeight; // eslint-disable-line no-void
      }

      // Reset all state.
      variationImageCache.clear();
      state.isOpen = true;
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
      state.addedToCart = false;
      state.cartError = '';
      state.productImages = [];
      state._originalImages = [];
      state.activeImageIndex = 0;
      state.stockStatus = 'instock';
      state.stockQuantity = null;
      state.stockStatusLabel = '';
      state.salePercentage = 0;
      state.showPostCartActions = false;
      state.isDrawerOpen = false;
      state.drawerView = 'selection';
      state.announcement = '';
      lockScroll();

      // Setup focus trap after modal renders.
      requestAnimationFrame(() => {
        const modal = document.querySelector(
          '.aggressive-apparel-quick-view__modal'
        );
        if (modal) {
          focusTrapCleanup = setupFocusTrap(modal);
          const closeBtn = modal.querySelector(
            '.aggressive-apparel-quick-view__close'
          );
          closeBtn?.focus();
        }
      });

      // Fetch the Store API nonce lazily on first open.
      if (!state.cartNonce) {
        const cartUrl = state.cartApiUrl || '/wp-json/wc/store/v1/cart';
        fetch(cartUrl, { credentials: 'same-origin' })
          .then(res => {
            const nonce = res.headers.get('Nonce');
            if (nonce) {
              state.cartNonce = nonce;
            }
          })
          .catch(() => {});
      }

      // Fetch product data.
      const base = state.restBase || '/wp-json/wc/store/v1/products/';
      const url = `${base}${productId}`;

      fetch(url)
        .then(res => {
          if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
          }
          return res.json();
        })
        .then(data => {
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
          state._originalImages = gallery.map(img => ({ ...img }));
          state.activeImageIndex = 0;

          // Fallback for single image.
          if (data.images && data.images.length > 0) {
            state.productImage = data.images[0].src;
            state.productImageAlt = stripTags(data.images[0].alt || data.name);
          }

          // Price.
          const priceData = parsePrice(data.prices);
          state.productPrice = priceData.current;
          state.productRegularPrice = priceData.regular;
          state.productOnSale = priceData.onSale;

          // Sale percentage.
          if (data.prices) {
            const regular = parseInt(data.prices.regular_price, 10);
            const sale = parseInt(
              data.prices.sale_price || data.prices.price,
              10
            );
            state.salePercentage = calculateSalePercentage(regular, sale);
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
            // Store in a local variable BEFORE assigning to state,
            // because state values become Interactivity API Proxies
            // that may not iterate correctly with for..of.
            const resolvedAttrs = buildAttributes(
              data,
              state.colorSwatchData,
              data.variations
            );
            state.productAttributes = resolvedAttrs;

            // Build a display-name → slug map so buildVariations can
            // enrich each variation attribute with the taxonomy slug.
            const nameToSlug = {};
            for (const attr of resolvedAttrs) {
              nameToSlug[attr.name.toLowerCase()] = attr.slug;
            }
            state.productVariations = buildVariations(data, nameToSlug);

            // Initialise selectedAttributes with empty values.
            const sel = {};
            resolvedAttrs.forEach(attr => {
              sel[attr.slug] = '';
            });
            state.selectedAttributes = sel;
          }

          state.hasProduct = true;
        })
        .catch(() => {
          state.hasError = true;
        })
        .finally(() => {
          state.isLoading = false;
          // Fallback DOM sync for when hydration didn't run.
          populateModalDOM();
        });
    },

    close() {
      // Cleanup focus trap.
      if (focusTrapCleanup) {
        focusTrapCleanup();
        focusTrapCleanup = null;
      }

      state.isOpen = false;
      state.hasProduct = false;
      state.hasError = false;
      state.addedToCart = false;
      state.isCartSuccess = false;
      state.cartError = '';
      state.showPostCartActions = false;
      state.isDrawerOpen = false;
      state.drawerView = 'selection';
      state.announcement = '';

      // Restore focus to trigger element.
      if (triggerElement) {
        triggerElement.focus();
        triggerElement = null;
      }

      // Unlock scroll + hide the instant the fade-out transition ends so
      // the scrollbar doesn't reappear and shift layout mid-animation.
      const modalEl = document.getElementById('aggressive-apparel-quick-view');
      const panel = modalEl?.querySelector(
        '.aggressive-apparel-quick-view__modal'
      );

      let done = false;
      const finish = () => {
        if (done || state.isOpen) return;
        done = true;
        unlockScroll();
        if (modalEl) modalEl.hidden = true;
      };

      if (panel) {
        panel.addEventListener(
          'transitionend',
          e => {
            if (e.propertyName === 'opacity') finish();
          },
          { once: true }
        );

        // Safety fallback if transitionend never fires (reduced motion, etc.).
        setTimeout(finish, 350);
      } else {
        finish();
      }
    },

    selectAttribute() {
      const ctx = getContext();
      // `ctx.item` is set by data-wp-each for the option button.
      // Each option carries its parent `attrSlug`.
      const attrSlug = ctx.item.attrSlug;
      // Use varValue (the variation-compatible value) for matching.
      // Falls back to slug for attributes where they're identical.
      const optionValue = ctx.item.varValue || ctx.item.slug;

      if (!attrSlug || !optionValue) {
        return;
      }

      // Toggle: deselect if same option clicked again.
      const current = state.selectedAttributes[attrSlug];
      const newSelected = { ...state.selectedAttributes };
      newSelected[attrSlug] = current === optionValue ? '' : optionValue;
      state.selectedAttributes = newSelected;

      // Try to match a variation.
      const match = matchVariation(state.productVariations, newSelected);

      if (match) {
        state.matchedVariationId = match.id;

        // Update price if the variation has different pricing.
        if (match.prices) {
          const priceData = parsePrice(match.prices);
          state.productPrice = priceData.current;
          state.productRegularPrice = priceData.regular;
          state.productOnSale = priceData.onSale;
        }

        // Fetch the variation's own image from the Store API.
        // The parent product response doesn't include per-variation
        // images, so we fetch GET /products/{variationId} to get it.
        const vid = match.id;
        if (variationImageCache.has(vid)) {
          applyVariationImage(variationImageCache.get(vid));
        } else {
          const base =
            state.restBase || '/wp-json/wc/store/v1/products/';
          fetch(`${base}${vid}`)
            .then(res => (res.ok ? res.json() : null))
            .then(data => {
              if (!data) return;
              const img =
                data.images && data.images.length > 0
                  ? {
                      src: data.images[0].src,
                      alt: data.images[0].alt || state.productName,
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
        // Restore original gallery when no variation is matched.
        if (state._originalImages.length > 0) {
          state.productImages = state._originalImages.map(img => ({
            ...img,
          }));
          state.activeImageIndex = 0;
        }
      }
    },

    /**
     * Scroll the thumbnail strip left or right.
     */
    scrollThumbnails(event) {
      const btn = event.target.closest('[data-scroll-dir]');
      if (!btn) {
        return;
      }
      const dir = btn.dataset.scrollDir === 'left' ? -1 : 1;
      const strip = btn
        .closest('.aggressive-apparel-quick-view__thumbnail-nav')
        ?.querySelector('.aggressive-apparel-quick-view__thumbnails');
      if (!strip) {
        return;
      }
      const step = 64; // 3.5rem thumb + 0.5rem gap.
      const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;
      strip.scrollBy({
        left: dir * step,
        behavior: prefersReducedMotion ? 'auto' : 'smooth',
      });
    },

    incrementQty(event) {
      // Mark as handled so the delegation fallback doesn't double-fire.
      if (event) event.preventDefault();
      const max = state.stockQuantity || 9999;
      if (state.quantity < max) {
        state.quantity = state.quantity + 1;
      }
    },

    decrementQty(event) {
      // Mark as handled so the delegation fallback doesn't double-fire.
      if (event) event.preventDefault();
      if (state.quantity > 1) {
        state.quantity = state.quantity - 1;
      }
    },

    /**
     * Set quantity from direct input.
     */
    setQuantity(event) {
      const value = parseInt(event.target.value, 10);
      const max = state.stockQuantity || 9999;
      if (!isNaN(value) && value >= 1 && value <= max) {
        state.quantity = value;
      } else {
        // Reset to valid value.
        event.target.value = state.quantity;
      }
    },

    /**
     * Select a gallery image by thumbnail or dot click.
     */
    selectImage() {
      const ctx = getContext();
      if (!ctx.item) {
        return;
      }
      const index = state.productImages.findIndex(
        img => img.id === ctx.item.id
      );
      if (index >= 0 && index !== state.activeImageIndex) {
        fadeImage();
        state.activeImageIndex = index;
      }
    },

    /**
     * Navigate to next gallery image.
     */
    nextImage() {
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
    prevImage() {
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
    handleTouchStart(event) {
      if (!state.hasMultipleImages) return;
      const touch = event.touches[0];
      touchStartX = touch.clientX;
      touchStartY = touch.clientY;
      isSwiping = false;
    },

    handleTouchMove(event) {
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

    handleTouchEnd(event) {
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
    openDrawer() {
      const drawerEl = document.querySelector(
        '.aggressive-apparel-quick-view__drawer'
      );
      if (drawerEl) {
        drawerEl.hidden = false;
        void drawerEl.offsetHeight; // eslint-disable-line no-void
      }
      state.isDrawerOpen = true;
      state.drawerView = 'selection';
    },

    /**
     * Close the mobile bottom drawer with slide-down animation.
     */
    closeDrawer() {
      state.isDrawerOpen = false;

      const panel = document.querySelector(
        '.aggressive-apparel-quick-view__drawer-panel'
      );
      let done = false;
      const finish = () => {
        if (done) return;
        done = true;
        const el = document.querySelector(
          '.aggressive-apparel-quick-view__drawer'
        );
        if (el && !state.isDrawerOpen) {
          el.hidden = true;
        }
        state.drawerView = 'selection';
      };

      if (panel) {
        panel.addEventListener(
          'transitionend',
          e => {
            if (e.propertyName === 'transform') finish();
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
    handleKeydown(event) {
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

    /**
     * Continue shopping (close modal after adding to cart).
     */
    continueShopping() {
      if (state.isDrawerOpen) {
        actions.closeDrawer();
        setTimeout(() => {
          state.showPostCartActions = false;
          state.addedToCart = false;
          actions.close();
        }, 300);
        return;
      }
      state.showPostCartActions = false;
      state.addedToCart = false;
      actions.close();
    },

    /**
     * Navigate to cart page.
     */
    viewCart() {
      window.location.href = state.cartUrl;
    },

    addToCart(event) {
      if (event) event.preventDefault();
      if (!state.canAddToCart) {
        return;
      }

      state.isAddingToCart = true;
      state.addedToCart = false;
      state.cartError = '';

      // Determine the ID to add (variation ID for variable, product ID for simple).
      const itemId =
        state.productType === 'variable' && state.matchedVariationId
          ? state.matchedVariationId
          : state.productId;

      const body = {
        id: itemId,
        quantity: state.quantity,
      };

      // For variable products, send variation attributes using the keys
      // from the matched variation (which use the proper taxonomy slugs
      // like "pa_color" that the Store API expects).
      if (state.productType === 'variable' && state.matchedVariationId) {
        const matchedVar = state.productVariations.find(
          v => v.id === state.matchedVariationId
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

      const headers = {
        'Content-Type': 'application/json',
      };

      if (state.cartNonce) {
        headers.Nonce = state.cartNonce;
      }

      fetch(addUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(body),
      })
        .then(res => {
          // Capture the refreshed nonce for subsequent requests.
          const newNonce = res.headers.get('Nonce');
          if (newNonce) {
            state.cartNonce = newNonce;
          }

          if (!res.ok) {
            return res.json().then(err => {
              throw new Error(err.message || `HTTP ${res.status}`);
            });
          }
          return res.json();
        })
        .then(() => {
          // Show success state on the button briefly before revealing
          // the post-cart panel for a more satisfying interaction.
          state.isCartSuccess = true;
          state.isAddingToCart = false;

          setTimeout(() => {
            state.isCartSuccess = false;

            if (state.isDrawerOpen) {
              // Variable product (any screen) — switch drawer to success view.
              state.drawerView = 'success';
            } else if (state.isMobile) {
              // Simple product on mobile — open drawer with success view.
              state.drawerView = 'success';
              const drawerEl = document.querySelector(
                '.aggressive-apparel-quick-view__drawer'
              );
              if (drawerEl) {
                drawerEl.hidden = false;
                void drawerEl.offsetHeight; // eslint-disable-line no-void
              }
              state.isDrawerOpen = true;
            } else {
              // Simple product on desktop — inline post-cart panel.
              state.addedToCart = true;
              state.showPostCartActions = true;
            }

            state.announcement = 'Product added to cart successfully';

            // Dispatch a custom event so WooCommerce mini-cart can update.
            document.body.dispatchEvent(
              new CustomEvent('wc-blocks_added_to_cart', {
                bubbles: true,
              })
            );
          }, 800);
        })
        .catch(err => {
          console.error('[Quick View] Add to cart failed:', err);
          state.cartError =
            decodeEntities(err.message) || 'Could not add to cart.';
          state.announcement = `Error: ${state.cartError}`;
          state.isAddingToCart = false;
        });
    },
  },

  callbacks: {
    /**
     * Sync an img element's src/alt with state.currentImage.
     * Used on all images that display the active product image.
     * Replaces data-wp-bind--src which Breeze's DOMDocument mangles.
     */
    syncCurrentImage() {
      const { ref } = getElement();
      if (!ref) return;
      const img = state.currentImage;
      ref.src = img.src || '';
      ref.alt = img.alt || '';
    },

    /**
     * Sync an img element inside a data-wp-each loop with its
     * context item thumbnail. Used for gallery thumbnail buttons.
     */
    syncThumbnail() {
      const ctx = getContext();
      const { ref } = getElement();
      if (!ref || !ctx.item) return;
      ref.src = ctx.item.thumbnail || '';
      ref.alt = ctx.item.alt || '';
    },
  },
});

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
function syncModalDOM() {
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

// Event delegation: catch clicks on trigger buttons even if their
// data-wp-on--click directive was never hydrated.
document.addEventListener('click', e => {
  const trigger = e.target.closest('.aggressive-apparel-quick-view__trigger');
  if (!trigger) {
    return;
  }

  // Prevent duplicate firing if the Interactivity API already handled it.
  if (state.isOpen) {
    return;
  }

  actions.open(e);
  // Ensure the modal becomes visible even without reactive bindings.
  syncModalDOM();
});

// Also handle close clicks via delegation.
document.addEventListener('click', e => {
  if (!state.isOpen) {
    return;
  }
  const backdrop = e.target.closest('.aggressive-apparel-quick-view__backdrop');
  const closeBtn = e.target.closest('.aggressive-apparel-quick-view__close');
  if (backdrop || closeBtn) {
    actions.close();
    syncModalDOM();
  }
});

// ESC key to close (fallback for data-wp-on-document--keydown).
document.addEventListener('keydown', e => {
  if (state.isOpen && e.key === 'Escape') {
    if (state.isDrawerOpen) {
      actions.closeDrawer();
    } else {
      actions.close();
      syncModalDOM();
    }
  }
});

// Add to Cart, quantity, continue-shopping, and drawer delegation.
document.addEventListener('click', e => {
  if (!state.isOpen) {
    return;
  }

  const modal = document.getElementById('aggressive-apparel-quick-view');
  if (!modal || !modal.contains(e.target)) {
    return;
  }

  // Select Options button.
  if (e.target.closest('.aggressive-apparel-quick-view__select-options')) {
    actions.openDrawer();
    return;
  }

  // Drawer scrim close.
  if (e.target.closest('.aggressive-apparel-quick-view__drawer-scrim')) {
    actions.closeDrawer();
    return;
  }

  // Add to Cart — skip if the Interactivity API already handled it.
  if (e.target.closest('.aggressive-apparel-quick-view__add-to-cart')) {
    if (!e.defaultPrevented) actions.addToCart();
    return;
  }

  // Quantity buttons — skip if the Interactivity API already handled it.
  const qtyBtn = e.target.closest('.aggressive-apparel-quick-view__qty-btn');
  if (qtyBtn) {
    if (e.defaultPrevented) {
      return;
    }
    if (
      qtyBtn.textContent.includes('\u2212') ||
      qtyBtn.textContent.includes('-')
    ) {
      actions.decrementQty();
    } else {
      actions.incrementQty();
    }
    const input = modal.querySelector(
      '.aggressive-apparel-quick-view__qty-input'
    );
    if (input) {
      input.value = state.quantity;
    }
    return;
  }

  // Continue shopping.
  if (e.target.closest('.aggressive-apparel-quick-view__btn--continue')) {
    actions.continueShopping();
    syncModalDOM();
  }
});
