/**
 * Wishlist — Interactivity API Store.
 *
 * All storage is handled via localStorage. Zero database writes.
 * A reactive `state.items` array mirrors localStorage so that UI updates
 * (heart icon fill, wishlist count, etc.) happen instantly without a page refresh.
 *
 * Product details for the wishlist page are fetched from the public
 * WooCommerce Store API (read-only, no auth required).
 *
 * The `productsApiUrl` state value is provided by PHP via wp_interactivity_state().
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

const STORAGE_KEY = 'aggressive_apparel_wishlist';

/**
 * Read wishlist product IDs from localStorage.
 *
 * @return {number[]} Array of product IDs.
 */
function readStorage() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  } catch {
    return [];
  }
}

/**
 * Persist the reactive items array to localStorage.
 *
 * @param {number[]} items - Array of product IDs.
 */
function writeStorage(items) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Ignore storage errors (private browsing, quota exceeded).
  }
}

/**
 * Format a Store API raw price (minor units) into a display string.
 *
 * @param {Object} prices - The `prices` object from the Store API response.
 * @return {string} Formatted price string, e.g. "$19.99".
 */
function formatPrice(prices) {
  if (!prices || !prices.price) {
    return '';
  }
  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const value = (parseInt(prices.price, 10) / divisor).toFixed(minorUnit);
  const prefix = prices.currency_prefix ?? '$';
  const suffix = prices.currency_suffix ?? '';
  return `${prefix}${value}${suffix}`;
}

/**
 * Transform a Store API product into a simple object for template rendering.
 *
 * @param {Object} product - Store API product response.
 * @return {Object} Simplified item with name, image, price, permalink.
 */
function toWishlistItem(product) {
  return {
    id: product.id,
    name: product.name || '',
    image:
      product.images && product.images.length > 0 ? product.images[0].src : '',
    price: formatPrice(product.prices),
    permalink: product.permalink || '#',
  };
}

// Hydrate the reactive array from localStorage on module load.
const initialItems = readStorage();

const { state } = store('aggressive-apparel/wishlist', {
  state: {
    // Provided by PHP via wp_interactivity_state().
    productsApiUrl: '',

    // Reactive mirror of localStorage — drives all UI updates instantly.
    items: initialItems,

    // Wishlist page product objects (populated by loadWishlistPage callback).
    wishlistProducts: [],

    get isInWishlist() {
      const ctx = getContext();
      return state.items.includes(ctx.productId);
    },

    get hasWishlistItems() {
      return state.items.length > 0;
    },
  },

  actions: {
    toggle() {
      const ctx = getContext();
      const idx = state.items.indexOf(ctx.productId);

      if (idx !== -1) {
        // Remove — create a new array so the reactive system detects the change.
        state.items = state.items.filter(id => id !== ctx.productId);
      } else {
        // Add — spread into a new array for reactivity.
        state.items = [...state.items, ctx.productId];
      }

      // Persist to localStorage.
      writeStorage(state.items);
    },
  },

  callbacks: {
    loadWishlistPage() {
      const ctx = getContext();
      const ids = state.items;

      if (ids.length === 0) {
        ctx.loaded = true;
        return;
      }

      const base = state.productsApiUrl || '/wp-json/wc/store/v1/products';
      const param = ids.join(',');

      fetch(`${base}?include=${param}`)
        .then(res => res.json())
        .then(data => {
          if (Array.isArray(data)) {
            state.wishlistProducts = data.map(toWishlistItem);
          }
        })
        .catch(() => {})
        .finally(() => {
          ctx.loaded = true;
        });
    },
  },
});
