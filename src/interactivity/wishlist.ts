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
    addToCartUrl: product.add_to_cart?.url || '',
  };
}

// Hydrate the reactive array from localStorage on module load.
const initialItems: number[] = readStorage();

/**
 * Toggle a product ID in the wishlist + persist + re-sync hearts.
 *
 * Mirrors the IA `actions.toggle` so the document-level delegate can
 * call into the same code path without depending on getContext.
 */
function toggleWishlistId(productId: number): boolean {
  const current = readStorage();
  const idx = current.indexOf(productId);
  const isAdding = idx === -1;
  const next = isAdding
    ? [...current, productId]
    : current.filter(id => id !== productId);
  writeStorage(next);
  // Mirror into the reactive store so SSR hearts also update instantly.
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
 * Sync `is-active` / `aria-pressed` on every heart button currently in
 * the DOM with the latest wishlist contents. Targets both SSR-rendered
 * (`data-wp-context` JSON) and AJAX-rendered (`data-aa-product-id`)
 * markup so the two render paths stay in lockstep.
 */
function syncDynamicHearts(root: ParentNode = document): void {
  const ids = readStorage();
  const set = new Set<number>(ids);
  root
    .querySelectorAll<HTMLElement>('.aggressive-apparel-wishlist__toggle')
    .forEach(btn => {
      const productId = readProductIdFromButton(btn);
      if (!productId) return;
      const isActive = set.has(productId);
      btn.classList.toggle('is-active', isActive);
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

/**
 * Read the productId that a heart button refers to. Tries the explicit
 * `data-aa-product-id` attribute first (AJAX cards), then falls back to
 * parsing the IA `data-wp-context` JSON used by SSR cards.
 */
function readProductIdFromButton(btn: HTMLElement): number | null {
  const direct = btn.dataset.aaProductId;
  if (direct) {
    const n = parseInt(direct, 10);
    if (Number.isFinite(n) && n > 0) return n;
  }
  const ctxAttr = btn.getAttribute('data-wp-context');
  if (ctxAttr) {
    try {
      const ctx = JSON.parse(ctxAttr) as { productId?: number };
      if (typeof ctx.productId === 'number' && ctx.productId > 0) {
        return ctx.productId;
      }
    } catch {
      // Invalid JSON — fall through.
    }
  }
  return null;
}

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
    /**
     * Remove a specific item from the wishlist page grid.
     * Uses context.item.id (set by data-wp-each) rather than context.productId.
     */
    removeItem(): void {
      const ctx = getContext<WishlistContext>();
      const id = ctx.item?.id;
      if (!id) return;
      state.items = state.items.filter((i: number) => i !== id);
      writeStorage(state.items);
      // Re-sync state so the grid re-renders without the removed item.
      state.wishlistProducts = state.wishlistProducts.filter(
        (p: WishlistDisplayItem) => p.id !== id
      );
    },

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

/* ------------------------------------------------------------------ */
/*  Dynamic-card support                                               */
/*                                                                     */
/*  AJAX-rendered product cards (product-filters, load-more) inject    */
/*  heart buttons via innerHTML, which the Interactivity API does NOT  */
/*  hydrate. The document-level delegate below handles their clicks,   */
/*  and `syncDynamicHearts` keeps their visual state in sync with      */
/*  localStorage when new cards are rendered.                          */
/* ------------------------------------------------------------------ */

document.addEventListener('click', (e: MouseEvent) => {
  const btn = (e.target as HTMLElement).closest<HTMLElement>(
    '.aggressive-apparel-wishlist__toggle'
  );
  if (!btn) return;

  // Only handle dynamic cards; SSR ones are managed by the IA so we
  // skip them here to avoid double-toggling.
  if (!btn.dataset.aaProductId) return;

  const productId = readProductIdFromButton(btn);
  if (!productId) return;

  e.preventDefault();
  const isAdding = toggleWishlistId(productId);
  syncDynamicHearts();
  if (isAdding) {
    pulseHeart(btn);
  }
});

document.addEventListener('aa:cards-rendered', ((
  e: CustomEvent<{ container: HTMLElement | null }>
) => {
  const container = e.detail?.container ?? document;
  syncDynamicHearts(container);
}) as EventListener);

// Initial sync so any wishlist hearts already in the DOM (e.g. from
// page-transitions cache) reflect the current localStorage state.
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => syncDynamicHearts());
  } else {
    syncDynamicHearts();
  }
}
