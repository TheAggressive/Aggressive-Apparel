/**
 * Wishlist — Interactivity API Store.
 *
 * All storage is handled via localStorage. Zero database writes.
 * A reactive `state.items` array mirrors localStorage so that UI updates
 * (heart icon fill, wishlist count, etc.) happen instantly without a page refresh.
 *
 * Toggle clicks on every `.aggressive-apparel-wishlist__toggle` heart—whether
 * rendered automatically on a single product or by the Wishlist Button block—
 * are handled by one document delegate. This avoids double-toggles when
 * Interactivity API hydration overlaps with dynamic markup.
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

interface WishlistI18n {
  addLabel?: string;
  removeLabel?: string;
}

interface StoreApiImage {
  src: string;
}

interface StoreApiPrices {
  price: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
}

interface StoreApiAddToCart {
  url?: string;
}

interface StoreApiProduct {
  id: number;
  name: string;
  permalink: string;
  images?: StoreApiImage[];
  prices: StoreApiPrices;
  add_to_cart?: StoreApiAddToCart;
}

interface WishlistDisplayItem {
  id: number;
  name: string;
  image: string;
  price: string;
  permalink: string;
  addToCartUrl: string;
}

interface WishlistContext {
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
    addToCartUrl: product.add_to_cart?.url || '',
  };
}

// Hydrate the reactive array from localStorage on module load.
const initialItems: number[] = readStorage();

/**
 * Read the product ID from a heart button's data attribute.
 */
function readProductIdFromButton(btn: HTMLElement): number | null {
  const n = parseInt(btn.dataset.aaProductId || '', 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

/**
 * Apply active/inactive UI state to a single heart button.
 */
function applyHeartState(btn: HTMLElement, isActive: boolean): void {
  btn.classList.toggle('is-active', isActive);
  btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');

  // Preserve custom accessible names on labeled Wishlist Button blocks.
  if (!btn.querySelector('.aggressive-apparel-wishlist__label')) {
    btn.setAttribute(
      'aria-label',
      isActive
        ? getWishlistLabel('removeLabel', 'Remove from wishlist')
        : getWishlistLabel('addLabel', 'Add to wishlist')
    );
  }
}

/**
 * Sync heart button UI with the current wishlist state.
 *
 * @param root       Scope for querySelector (default: document).
 * @param productId  When set, only hearts for this product are updated.
 */
function syncDynamicHearts(
  root: ParentNode = document,
  productId?: number
): void {
  const activeIds = new Set<number>(state.items);

  const selector =
    productId !== undefined
      ? `.aggressive-apparel-wishlist__toggle[data-aa-product-id="${productId}"]`
      : '.aggressive-apparel-wishlist__toggle';

  root.querySelectorAll<HTMLElement>(selector).forEach(btn => {
    const id = readProductIdFromButton(btn);
    if (!id) {
      return;
    }

    applyHeartState(btn, activeIds.has(id));
  });
}

/**
 * Toggle a product ID in the wishlist + persist.
 *
 * @return True when the product was added, false when removed.
 */
function toggleWishlistId(productId: number): boolean {
  const idx = state.items.indexOf(productId);
  const isAdding = idx === -1;
  const next = isAdding
    ? [...state.items, productId]
    : state.items.filter(id => id !== productId);
  writeStorage(next);
  state.items = next;
  return isAdding;
}

/**
 * Animate a heart with the heartbeat effect briefly after adding.
 */
function pulseHeart(button: HTMLElement): void {
  button.classList.add('is-beating');
  setTimeout(() => button.classList.remove('is-beating'), 800);
}

/**
 * Handle a click on any wishlist heart toggle.
 */
function handleWishlistToggleClick(button: HTMLElement): void {
  const productId = readProductIdFromButton(button);
  if (!productId) {
    return;
  }

  const isAdding = toggleWishlistId(productId);
  syncDynamicHearts(document, productId);
  if (isAdding) {
    pulseHeart(button);
  }
}

const { state } = store('aggressive-apparel/wishlist', {
  state: {
    // Provided by PHP via wp_interactivity_state().
    productsApiUrl: '',
    i18n: {} as WishlistI18n,

    // Reactive mirror of localStorage — drives all UI updates instantly.
    items: initialItems,

    // Wishlist page product objects (populated by loadWishlistPage callback).
    wishlistProducts: [] as WishlistDisplayItem[],

    get hasWishlistItems(): boolean {
      return state.items.length > 0;
    },
  },

  actions: {
    /**
     * Remove a specific item from the wishlist page grid.
     * Uses context.item.id (set by data-wp-each).
     */
    removeItem(): void {
      const ctx = getContext<WishlistContext>();
      const id = ctx.item?.id;
      if (!id) {
        return;
      }

      state.items = state.items.filter((i: number) => i !== id);
      writeStorage(state.items);
      state.wishlistProducts = state.wishlistProducts.filter(
        (p: WishlistDisplayItem) => p.id !== id
      );
      syncDynamicHearts(document, id);
    },
  },

  callbacks: {
    syncItemImage(): void {
      const ctx = getContext<WishlistContext>();
      const { ref } = getElement();
      if (!ref || !ctx.item) {
        return;
      }
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

/**
 * Resolve server-provided copy with a local English fallback for cached markup.
 */
function getWishlistLabel(key: keyof WishlistI18n, fallback: string): string {
  return state.i18n?.[key] || fallback;
}

/* ------------------------------------------------------------------ */
/*  Unified toggle delegate                                            */
/*                                                                     */
/*  Every heart — SSR, block, or AJAX — uses `.aggressive-apparel-   */
/*  wishlist__toggle` + `data-aa-product-id`. Capture phase runs       */
/*  before nested card links.                                          */
/* ------------------------------------------------------------------ */

document.addEventListener(
  'click',
  (e: MouseEvent) => {
    const btn = (e.target as HTMLElement).closest<HTMLElement>(
      '.aggressive-apparel-wishlist__toggle'
    );
    if (!btn) {
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    handleWishlistToggleClick(btn);
  },
  true
);

document.addEventListener('aa:cards-rendered', ((
  e: CustomEvent<{ container: HTMLElement | null }>
) => {
  const container = e.detail?.container ?? document;
  syncDynamicHearts(container);
}) as EventListener);

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => syncDynamicHearts());
  } else {
    syncDynamicHearts();
  }
}
