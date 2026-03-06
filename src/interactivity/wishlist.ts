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

import { store, getContext, getElement } from '@wordpress/interactivity';
import { decodeEntities } from '@aggressive-apparel/helpers';

const STORAGE_KEY = 'aggressive_apparel_wishlist';

interface StoreApiImage {
  src: string;
}

interface StoreApiPrices {
  price: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
}

interface StoreApiProduct {
  id: number;
  name: string;
  permalink: string;
  images?: StoreApiImage[];
  prices: StoreApiPrices;
}

interface WishlistDisplayItem {
  id: number;
  name: string;
  image: string;
  price: string;
  permalink: string;
}

interface WishlistContext {
  productId: number;
  justAdded: boolean;
  item: WishlistDisplayItem;
  loaded: boolean;
}

/**
 * Read wishlist product IDs from localStorage.
 */
function readStorage(): number[] {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
  } catch {
    return [];
  }
}

/**
 * Persist the reactive items array to localStorage.
 */
function writeStorage(items: number[]): void {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Ignore storage errors (private browsing, quota exceeded).
  }
}

/**
 * Format a Store API raw price (minor units) into a display string.
 */
function formatPrice(prices: StoreApiPrices | null): string {
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
 */
function toWishlistItem(product: StoreApiProduct): WishlistDisplayItem {
  return {
    id: product.id,
    name: decodeEntities(product.name) || '',
    image:
      product.images && product.images.length > 0 ? product.images[0].src : '',
    price: formatPrice(product.prices),
    permalink: product.permalink || '#',
  };
}

// Hydrate the reactive array from localStorage on module load.
const initialItems: number[] = readStorage();

const { state } = store('aggressive-apparel/wishlist', {
  state: {
    // Provided by PHP via wp_interactivity_state().
    productsApiUrl: '',

    // Reactive mirror of localStorage — drives all UI updates instantly.
    items: initialItems,

    // Wishlist page product objects (populated by loadWishlistPage callback).
    wishlistProducts: [] as WishlistDisplayItem[],

    get isInWishlist(): boolean {
      const ctx = getContext<WishlistContext>();
      return state.items.includes(ctx.productId);
    },

    get hasWishlistItems(): boolean {
      return state.items.length > 0;
    },
  },

  actions: {
    toggle(): void {
      const ctx = getContext<WishlistContext>();
      const idx = state.items.indexOf(ctx.productId);
      const isAdding = idx === -1;

      if (idx !== -1) {
        // Remove — create a new array so the reactive system detects the change.
        state.items = state.items.filter((id: number) => id !== ctx.productId);
      } else {
        // Add — spread into a new array for reactivity.
        state.items = [...state.items, ctx.productId];
      }

      // Persist to localStorage.
      writeStorage(state.items);

      // Trigger heartbeat animation when adding to wishlist.
      // The reactive system applies .is-beating via data-wp-class--is-beating.
      if (isAdding) {
        ctx.justAdded = true;
        setTimeout(() => {
          ctx.justAdded = false;
        }, 800);
      }
    },
  },

  callbacks: {
    syncItemImage(): void {
      const ctx = getContext<WishlistContext>();
      const { ref } = getElement();
      if (!ref || !ctx.item) return;
      (ref as HTMLImageElement).src = ctx.item.image || '';
      (ref as HTMLImageElement).alt = ctx.item.name || '';
    },

    loadWishlistPage(): void {
      const ctx = getContext<WishlistContext>();
      const ids = state.items;

      if (ids.length === 0) {
        ctx.loaded = true;
        return;
      }

      const base: string =
        state.productsApiUrl || '/wp-json/wc/store/v1/products';
      const param = ids.join(',');

      fetch(`${base}?include=${param}`)
        .then((res: Response) => res.json())
        .then((data: unknown) => {
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
