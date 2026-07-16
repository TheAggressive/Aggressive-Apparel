/**
 * Shared Helpers — Interactivity API Utilities.
 *
 * Common utilities used across multiple interactivity stores.
 * Registered as @aggressive-apparel/helpers script module.
 *
 * @package Aggressive_Apparel
 * @since 1.22.0
 */

export interface StoreApiPrices {
  price: string;
  regular_price?: string;
  sale_price?: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
}

export interface PriceResult {
  current: string;
  regular: string;
  onSale: boolean;
}

interface StoreApiAttribute {
  attribute?: string;
  name?: string;
  taxonomy?: string;
  value?: string;
}

export interface Variation {
  id: number;
  attributes: StoreApiAttribute[] | Record<string, string>;
  [key: string]: unknown;
}

export function parsePrice(
  prices: StoreApiPrices | null | undefined
): PriceResult {
  const result: PriceResult = { current: '', regular: '', onSale: false };
  if (!prices || !prices.price) {
    return result;
  }

  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const prefix = prices.currency_prefix ?? '$';
  const suffix = prices.currency_suffix ?? '';

  const currentVal = (parseInt(prices.price, 10) / divisor).toFixed(minorUnit);
  result.current = `${prefix}${currentVal}${suffix}`;

  if (
    prices.regular_price &&
    prices.sale_price &&
    prices.regular_price !== prices.sale_price
  ) {
    const regularVal = (parseInt(prices.regular_price, 10) / divisor).toFixed(
      minorUnit
    );
    result.regular = `${prefix}${regularVal}${suffix}`;
    result.onSale = true;
  }
  return result;
}

export function decodeEntities(str: string | null | undefined): string {
  if (!str) {
    return '';
  }
  return str
    .replace(/&#x([0-9a-f]+);/gi, (_, hex: string) =>
      String.fromCodePoint(parseInt(hex, 16))
    )
    .replace(/&#(\d+);/g, (_, dec: string) =>
      String.fromCodePoint(parseInt(dec, 10))
    )
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&nbsp;/g, ' ');
}

export function stripTags(html: string | null | undefined): string {
  if (!html) {
    return '';
  }
  return decodeEntities(html.replace(/<[^>]*>/g, '')).trim();
}

export function matchVariation(
  variations: Variation[],
  selected: Record<string, string>
): Variation | null {
  const activeEntries = Object.entries(selected).filter(([, v]) => v);
  if (activeEntries.length === 0) {
    return null;
  }

  const norm: Record<string, string> = {};
  for (const [key, value] of activeEntries) {
    const lower = key.toLowerCase();
    norm[lower] = value;
    if (!lower.startsWith('attribute_')) {
      norm[`attribute_${lower}`] = value;
    }
  }

  return (
    variations.find(v => {
      const attrs = v.attributes;

      if (Array.isArray(attrs)) {
        return attrs.every(attr => {
          if (!attr.value) return true;

          const possibleKeys = [
            attr.attribute,
            attr.name,
            attr.taxonomy,
          ].filter(Boolean) as string[];
          let val: string | undefined;
          for (const k of possibleKeys) {
            val = norm[k.toLowerCase()];
            if (val) break;
          }

          return val && val.toLowerCase() === attr.value.toLowerCase();
        });
      }

      return Object.entries(attrs as Record<string, string>).every(
        ([key, vValue]) => {
          if (!vValue) return true;
          const selectedValue = norm[key.toLowerCase()];
          if (!selectedValue) return false;
          return selectedValue.toLowerCase() === vValue.toLowerCase();
        }
      );
    }) || null
  );
}

/* ------------------------------------------------------------------ */
/*  Product Card Enhancements                                          */
/*                                                                     */
/*  Helpers used by AJAX-rendered card builders (product-filters and   */
/*  load-more) so that dynamically inserted cards get the same         */
/*  Quick View and Wishlist UI as server-rendered cards.               */
/*                                                                     */
/*  SSR cards receive these enhancements via PHP `render_block`        */
/*  filters, but client-rendered cards bypass that pipeline entirely.  */
/* ------------------------------------------------------------------ */

/**
 * Escape HTML for safe insertion via innerHTML/template strings.
 */
export function escapeHtml(str: string | null | undefined): string {
  if (!str) return '';
  const el = document.createElement('span');
  el.textContent = str;
  return el.innerHTML;
}

/**
 * Notify enhancement listeners that new product cards have been
 * rendered. Wishlist syncs heart states; countdown starts new tickers;
 * future enhancements can subscribe similarly.
 *
 * @param container Element containing the freshly rendered cards.
 */
export function notifyCardsRendered(container: HTMLElement | null): void {
  document.dispatchEvent(
    new CustomEvent('aa:cards-rendered', {
      detail: { container },
    })
  );
}

/**
 * How many product pages to retain in the DOM during infinite scroll.
 *
 * Keep this high enough that typical shop catalogues (≈50–100 products) stay
 * fully browsable when scrolling back up. Pruning only kicks in for very long
 * sessions so memory stays bounded.
 */
export const PRODUCT_GRID_MAX_PAGES = 12;

const GRID_SPACER_CLASS = 'aa-product-grid__spacer';

/**
 * Keep only the newest N pages of product cards in the DOM.
 *
 * Removed height is accumulated on a leading spacer so scroll position stays
 * stable while memory and layout cost remain bounded.
 *
 * @param grid    Product template list element.
 * @param perPage Cards per loaded page.
 */
export function pruneProductGrid(grid: HTMLElement, perPage: number): void {
  const maxCards = Math.max(perPage, PRODUCT_GRID_MAX_PAGES * perPage);
  const cards = Array.from(
    grid.querySelectorAll<HTMLElement>(`:scope > li:not(.${GRID_SPACER_CLASS})`)
  );
  if (cards.length <= maxCards) {
    return;
  }

  const removeCount = cards.length - maxCards;
  let removedHeight = 0;
  for (let i = 0; i < removeCount; i++) {
    removedHeight += cards[i].getBoundingClientRect().height;
    cards[i].remove();
  }

  let spacer = grid.querySelector<HTMLElement>(
    `:scope > .${GRID_SPACER_CLASS}`
  );
  if (!spacer) {
    spacer = document.createElement('li');
    spacer.className = GRID_SPACER_CLASS;
    spacer.setAttribute('aria-hidden', 'true');
    grid.prepend(spacer);
  }
  const previous = parseFloat(spacer.style.height || '0') || 0;
  spacer.style.height = `${previous + removedHeight}px`;
  spacer.style.listStyle = 'none';
  spacer.style.margin = '0';
  spacer.style.padding = '0';
  spacer.style.border = '0';
  spacer.style.pointerEvents = 'none';
}

/** Remove the scroll-preserving spacer (filter resets / full grid replace). */
export function clearProductGridSpacer(grid: HTMLElement | null): void {
  grid?.querySelector(`:scope > .${GRID_SPACER_CLASS}`)?.remove();
}

export interface DynamicStyleAsset {
  id: string;
  css: string;
  nonce?: string;
}

const installedDynamicStyles = new Map<string, HTMLStyleElement>();
const MAX_RETAINED_DYNAMIC_STYLES = 64;

/** Remove old style assets only after no matching element remains in the DOM. */
function pruneUnusedDynamicStyles(): void {
  if (installedDynamicStyles.size <= MAX_RETAINED_DYNAMIC_STYLES) return;

  for (const [id, style] of installedDynamicStyles) {
    const selectors = Array.from(
      style.textContent?.matchAll(/\.(wp-elements-[a-f0-9]+)/g) || []
    ).map(match => `.${match[1]}`);
    const isUsed = selectors.some(selector => document.querySelector(selector));
    if (!isUsed) {
      style.remove();
      installedDynamicStyles.delete(id);
    }
    if (installedDynamicStyles.size <= MAX_RETAINED_DYNAMIC_STYLES) break;
  }
}

/**
 * Install deterministic block-support assets returned with dynamic markup.
 * Each ID is immutable and installed once. A nonce is forwarded for sites
 * enforcing a nonce-based Content-Security-Policy.
 */
export function installBlockSupportStyles(
  assets?: readonly DynamicStyleAsset[]
): void {
  if (!assets?.length) return;

  for (const asset of assets) {
    if (
      !/^[a-f0-9]{64}$/.test(asset.id) ||
      !asset.css ||
      installedDynamicStyles.has(asset.id)
    ) {
      continue;
    }

    const style = document.createElement('style');
    style.id = `aggressive-apparel-dynamic-style-${asset.id}`;
    style.dataset.dynamicStyleId = asset.id;
    if (asset.nonce) style.nonce = asset.nonce;
    style.textContent = asset.css;
    document.head.appendChild(style);
    installedDynamicStyles.set(asset.id, style);
  }

  pruneUnusedDynamicStyles();
}

/**
 * Whether the user prefers reduced motion.
 */
export function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Whether unsolicited overlays (exit intent, scroll depth, open on load) may run.
 */
export function shouldAllowAutoOpenOverlay(): boolean {
  return !prefersReducedMotion();
}

/**
 * Toggle inert on the main page content so focus stays in an overlay.
 */
export function setMainContentInert(inert: boolean): void {
  if (!('inert' in HTMLElement.prototype)) {
    return;
  }

  const mainContent = document.querySelector(
    '.wp-site-blocks'
  ) as HTMLElement | null;

  if (mainContent) {
    mainContent.inert = inert;

    // When making the page inert, any visible overlays (modals, drawers)
    // that are already open inside .wp-site-blocks must remain interactive
    // so the user can still close them.
    if (inert) {
      document
        .querySelectorAll<HTMLElement>(
          '.aggressive-apparel-overlay:not([hidden])'
        )
        .forEach(overlay => {
          overlay.inert = false;
        });
    }
  }
}

export function setupFocusTrap(container: HTMLElement): () => void {
  const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), ' +
    'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const handleKeydown = (e: KeyboardEvent): void => {
    if (e.key !== 'Tab') {
      return;
    }

    const focusable = Array.from(
      container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)
    ).filter(el => !el.closest('[hidden]') && !el.closest('[inert]'));

    if (focusable.length === 0) {
      e.preventDefault();
      return;
    }

    const currentIndex = focusable.indexOf(
      document.activeElement as HTMLElement
    );
    let nextIndex: number;

    if (e.shiftKey) {
      nextIndex = currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1;
    } else {
      nextIndex = currentIndex >= focusable.length - 1 ? 0 : currentIndex + 1;
    }

    e.preventDefault();
    focusable[nextIndex].focus();
  };

  container.addEventListener('keydown', handleKeydown);

  return () => {
    container.removeEventListener('keydown', handleKeydown);
  };
}
