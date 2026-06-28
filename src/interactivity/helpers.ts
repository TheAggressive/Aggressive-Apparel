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
