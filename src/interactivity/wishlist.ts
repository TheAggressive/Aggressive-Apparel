/**
 * Wishlist — Interactivity API Store.
 *
 * Guest saves stay in the browser. Account saves use WooCommerce Shopper Lists.
 * The existing blocks, card actions and dynamically inserted buttons share this
 * adapter; no second account wishlist is maintained by the theme.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import {
  store,
  getContext,
  getElement,
  getConfig,
} from '@wordpress/interactivity';

import {
  WishlistAccount,
  STORAGE_KEY,
  readGuestItems,
  writeGuestItems,
  type WishlistConfig,
  type WishlistProduct,
} from './wishlist/account';
import { fetchWishlistProducts } from './wishlist/products';
import {
  formatWishlistItem,
  type WishlistDisplayItem,
  type WishlistI18n,
} from './wishlist/display';
import { observeNativeWishlist } from './wishlist/native';

const config = getConfig('aggressive-apparel/wishlist') as WishlistConfig;
const account = config.account ? new WishlistAccount(config) : null;
let accountItems: WishlistProduct[] = [];
const pendingToggles = new Map<number, boolean>();
let ready: Promise<void>;
let queue: Promise<void> = Promise.resolve();
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;

interface WishlistContext {
  item: WishlistDisplayItem;
  loaded: boolean;
}

// Hydrate the reactive array from localStorage on module load.
let initialItems: number[] = [];
try {
  initialItems = readGuestItems();
} catch {
  // Initialization reports denied browser storage after the store exists.
}

/**
 * Read the product ID from a heart button's data attribute.
 */
function readProductIdFromButton(btn: HTMLElement): number | null {
  const n = Number(btn.dataset.aaProductId);
  return Number.isSafeInteger(n) && n > 0 ? n : null;
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

    applyHeartState(btn, pendingToggles.get(id) ?? activeIds.has(id));
    if (pendingToggles.has(id)) {
      btn.setAttribute('aria-busy', 'true');
    } else {
      btn.removeAttribute('aria-busy');
    }
  });
}

/** Announce actual failures on every wishlist surface. */
function announce(message: string): void {
  let notice = document.querySelector<HTMLElement>(
    '[data-aa-wishlist-feedback]'
  );
  clearTimeout(feedbackTimer);
  if (!message.trim()) {
    if (notice) {
      notice.textContent = '';
      notice.hidden = true;
    }
    return;
  }
  if (!notice) {
    notice = document.createElement('p');
    notice.dataset.aaWishlistFeedback = '';
    notice.className = 'aa-wishlist-feedback';
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    document.body.append(notice);
  }
  notice.textContent = message;
  notice.hidden = false;
  const currentNotice = notice;
  feedbackTimer = setTimeout(() => {
    currentNotice.hidden = true;
    currentNotice.textContent = '';
  }, 8000);
}

function guestProducts(ids: number[]): Promise<WishlistProduct[]> {
  return fetchWishlistProducts(
    ids,
    config.productsUrl ||
      (state as unknown as { productsApiUrl?: string }).productsApiUrl
  );
}

function toWishlistItem(product: WishlistProduct): WishlistDisplayItem {
  return formatWishlistItem(
    product,
    state as unknown as {
      collapseVariablePrice?: boolean;
      priceStartingPrefix?: string;
    },
    Boolean(account),
    getWishlistLabel
  );
}

/** Keep each healthy source visible even when another source is unavailable. */
let localProducts: WishlistProduct[] = [];
function publishItems(guestIds: number[]): void {
  const imported = new Set(
    accountItems.flatMap(item => [item.id, item.product_id || item.id])
  );
  state.items = [...new Set([...imported, ...guestIds])];
  state.wishlistProducts = [
    ...accountItems.map(toWishlistItem),
    ...localProducts
      .filter(item => guestIds.includes(item.id) && !imported.has(item.id))
      .map(toWishlistItem),
  ];
  syncDynamicHearts();
}

/** Reconcile account and browser products independently; retain healthy caches. */
async function refresh(readAccount = true): Promise<void> {
  let storageFailed = false;
  let guestIds: number[] = [];
  try {
    guestIds = readGuestItems();
  } catch {
    storageFailed = true;
  }
  publishItems(guestIds);
  const results = await Promise.allSettled([
    (async () => {
      if (!account || !readAccount) return;
      accountItems = await account.list();
      publishItems(guestIds);
      const currentIds = readGuestItems();
      const remaining = currentIds.filter(
        id =>
          !accountItems.some(item => item.id === id || item.product_id === id)
      );
      if (remaining.length !== currentIds.length) writeGuestItems(remaining);
    })(),
    (async () => {
      if (!document.querySelector('.wp-block-aggressive-apparel-wishlist'))
        return;
      localProducts = await guestProducts(guestIds);
      publishItems(guestIds);
    })(),
  ]);
  state.loadFailed =
    storageFailed || results.some(result => result.status === 'rejected');
  try {
    guestIds = readGuestItems();
  } catch {
    state.loadFailed = true;
  }
  publishItems(guestIds);
  if (state.loadFailed) throw new Error('Wishlist partially unavailable');
}

/** Serialize writes, including repeated clicks and removals during login import. */
function enqueue(operation: () => Promise<void>): void {
  queue = queue.then(async () => {
    try {
      await ready;
      await operation();
      announce('');
    } catch {
      announce(
        getWishlistLabel(
          'errorLabel',
          'Your wishlist could not be updated. Please try again.'
        )
      );
    }
  });
}

async function removeSavedItem(id: number, key?: string): Promise<void> {
  if (account?.needsReconcile) await refresh();
  const removedIds = new Set([id]);
  if (account) {
    const matches = key
      ? accountItems.filter(item => item.key === key)
      : accountItems.filter(item => item.id === id || item.product_id === id);
    for (const item of matches) {
      removedIds.add(item.product_id || item.id);
      if (item.key) {
        await account.remove(item.key);
        accountItems = accountItems.filter(saved => saved.key !== item.key);
      }
    }
  }
  const localIds = readGuestItems();
  if (localIds.some(saved => removedIds.has(saved)))
    writeGuestItems(localIds.filter(saved => !removedIds.has(saved)));
  await refresh(false);
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
  if (!productId || pendingToggles.has(productId)) {
    return;
  }

  // Paint before waiting for initialization, queued writes or the network.
  const isAdding = button.getAttribute('aria-pressed') !== 'true';
  pendingToggles.set(productId, isAdding);
  syncDynamicHearts(document, productId);
  if (isAdding) pulseHeart(button);

  enqueue(async () => {
    try {
      if (account?.needsReconcile) await refresh();
      // Initialization may discover that this desired state is already saved.
      if (state.items.includes(productId) === isAdding) return;
      if (!isAdding) {
        await removeSavedItem(productId);
      } else if (account) {
        // Server-rendered cards carry this information. Older cached buttons
        // get a public product lookup before any account mutation is attempted.
        const product = button.dataset.aaProductType
          ? {
              type: button.dataset.aaProductType,
            }
          : (await guestProducts([productId]))[0];
        if (!product) throw new Error('Wishlist product unavailable');
        if (product.type === 'variable') {
          // WooCommerce cannot store an unconfigured parent. Keep the save on
          // this device, just as we do for guests, until options are chosen.
          writeGuestItems([...new Set([...readGuestItems(), productId])]);
          await refresh(false);
          return;
        }
        const saved = await account.add(productId);
        accountItems = [...accountItems, saved];
        await refresh(false);
      } else {
        writeGuestItems([...readGuestItems(), productId]);
        await refresh();
      }
    } finally {
      pendingToggles.delete(productId);
      syncDynamicHearts(document, productId);
    }
  });
}

const { state } = store('aggressive-apparel/wishlist', {
  state: {
    // API URL and price settings are seeded by PHP; do not override them.
    // collapseVariablePrice / priceStartingPrefix are intentionally NOT seeded
    // here — see toWishlistItem(): a client default clobbers the PHP-seeded
    // value on merge, so we omit them and read the server value via cast.
    i18n: {} as WishlistI18n,

    // Reactive mirror of localStorage — drives all UI updates instantly.
    items: initialItems,

    // Wishlist page product objects (populated by loadWishlistPage callback).
    wishlistProducts: [] as WishlistDisplayItem[],
    loadFailed: false,

    get hasWishlistItems(): boolean {
      return state.wishlistProducts.length > 0;
    },

    get isEmpty(): boolean {
      return (
        getContext<WishlistContext>().loaded &&
        !state.loadFailed &&
        !state.hasWishlistItems
      );
    },
  },

  actions: {
    retryLoad(): void {
      enqueue(initialize);
    },
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

      const key = ctx.item.key;
      enqueue(() => removeSavedItem(id, key));
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
      void ready.finally(() => {
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

/** Import only after a successful account read, then render both sources. */
async function initialize(): Promise<void> {
  let failed = false;
  let guestIds: number[] = [];
  try {
    guestIds = readGuestItems();
  } catch {
    failed = true;
  }
  const results = await Promise.allSettled([
    (async () => {
      if (account) accountItems = await account.list();
      publishItems(guestIds);
    })(),
    (async () => {
      if (
        account ||
        document.querySelector('.wp-block-aggressive-apparel-wishlist')
      ) {
        localProducts = await guestProducts(guestIds);
        publishItems(guestIds);
      }
    })(),
  ]);
  failed ||= results.some(result => result.status === 'rejected');
  if (account && results.every(result => result.status === 'fulfilled')) {
    try {
      accountItems = await account.merge(accountItems, localProducts);
      failed ||= account.importFailed;
    } catch {
      failed = true;
    }
  }
  try {
    guestIds = readGuestItems();
  } catch {
    failed = true;
  }
  publishItems(guestIds);
  state.loadFailed = failed;
  if (failed)
    announce(
      getWishlistLabel(
        'errorLabel',
        'Your wishlist could not be loaded. Please try again.'
      )
    );
}

ready = initialize();
observeNativeWishlist(() => enqueue(refresh));
window.addEventListener('focus', () => enqueue(refresh));
window.addEventListener('storage', event => {
  if (event.key === STORAGE_KEY || event.key === null) enqueue(refresh);
});
