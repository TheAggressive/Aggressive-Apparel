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
 * Heart SVG markup shared by all wishlist toggles.
 */
const WISHLIST_HEART_SVG =
  '<svg class="aggressive-apparel-wishlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
  '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>' +
  '</svg>';

/**
 * Eye SVG markup for the Quick View trigger. Mirrors the `eye` icon
 * registered in PHP so dynamically inserted cards look identical to SSR ones.
 */
const QUICK_VIEW_EYE_SVG =
  '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
  '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
  '<circle cx="12" cy="12" r="3"/>' +
  '</svg>';

/**
 * Build the Quick View trigger button HTML for a product.
 *
 * Matches the markup emitted by `Quick_View::inject_trigger_button()`.
 * Quick View hooks into clicks via document-level delegation, so as
 * long as the class and `data-wp-context` are present the button
 * works on dynamically inserted cards.
 */
export function buildQuickViewTriggerHtml(
  productId: number,
  productName: string
): string {
  const ctx = JSON.stringify({ productId });
  const label = `Quick view ${productName}`.replace(/\s+/g, ' ').trim();
  return (
    `<button type="button" class="aggressive-apparel-quick-view__trigger" ` +
    `data-wp-interactive="aggressive-apparel/quick-view" ` +
    `data-wp-on--click="actions.open" ` +
    `data-wp-context='${escapeHtml(ctx)}' ` +
    `aria-label="${escapeHtml(label)}">${QUICK_VIEW_EYE_SVG}</button>`
  );
}

/**
 * Local storage key — must stay in sync with `wishlist.ts`.
 */
const WISHLIST_STORAGE_KEY = 'aggressive_apparel_wishlist';

/**
 * Read wishlist product IDs from localStorage (best-effort).
 */
function readWishlistIds(): number[] {
  try {
    const raw = JSON.parse(
      localStorage.getItem(WISHLIST_STORAGE_KEY) || '[]'
    ) as unknown;
    return Array.isArray(raw) ? (raw as number[]) : [];
  } catch {
    return [];
  }
}

/**
 * Build the Wishlist heart toggle HTML for a product.
 *
 * Matches the markup emitted by `Wishlist::inject_heart_icon()`.
 * Wishlist clicks on dynamically inserted hearts are handled by a
 * document-level delegate registered in `wishlist.ts`. The initial
 * `is-active` class is set here from localStorage so the heart
 * renders correctly on first paint.
 */
export function buildWishlistHeartHtml(productId: number): string {
  const isActive = readWishlistIds().includes(productId);
  const ctx = JSON.stringify({ productId, justAdded: false });
  return (
    `<button type="button" class="aggressive-apparel-wishlist__toggle${isActive ? ' is-active' : ''}" ` +
    `data-wp-interactive="aggressive-apparel/wishlist" ` +
    `data-wp-context='${escapeHtml(ctx)}' ` +
    `data-wp-on--click="actions.toggle" ` +
    `data-wp-class--is-active="state.isInWishlist" ` +
    `data-wp-class--is-beating="context.justAdded" ` +
    `data-wp-bind--aria-pressed="state.isInWishlist" ` +
    `data-aa-product-id="${productId}" ` +
    `aria-pressed="${isActive ? 'true' : 'false'}" ` +
    `aria-label="Add to wishlist" title="Wishlist">${WISHLIST_HEART_SVG}</button>`
  );
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

/* ------------------------------------------------------------------ */
/*  Server-supplied per-product card data                              */
/*                                                                     */
/*  PHP `Card_Enhancements` registers a Store API extension under the  */
/*  `aggressive-apparel/card-enhancements` namespace. AJAX card        */
/*  builders read from `product.extensions[NS]` and pass the values    */
/*  to the helpers below to mirror the SSR markup exactly.             */
/* ------------------------------------------------------------------ */

export const CARD_ENHANCEMENTS_NS = 'aggressive-apparel/card-enhancements';

export interface CardCountdown {
  end_ts: number;
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
}

export interface CardEnhancementData {
  badges_html?: string;
  view_transition_name?: string;
  countdown?: CardCountdown;
}

/**
 * Safely pull the card-enhancements extension off a Store API product.
 * Returns an empty object when the extension wasn't loaded server-side
 * (feature disabled or older WordPress) so callers can use `??` defaults.
 */
export function getCardEnhancements(product: {
  extensions?: Record<string, unknown>;
}): CardEnhancementData {
  const ext = product.extensions?.[CARD_ENHANCEMENTS_NS];
  return (ext as CardEnhancementData) || {};
}

/**
 * Inline style string for a product image's view-transition-name.
 *
 * Matches `Page_Transitions::handle_archive_image()` so the archive →
 * single-product image morph still works on cards loaded via AJAX.
 * Returns an empty string when the page-transitions feature is off.
 */
export function buildViewTransitionStyle(
  enhancements: CardEnhancementData
): string {
  const name = enhancements.view_transition_name;
  if (!name) return '';
  return `view-transition-name:${name};view-transition-class:product-img`;
}

/**
 * Wrap an `<img ...>` tag with a `style="..."` attribute so the
 * resulting HTML mirrors the SSR-injected page-transitions markup.
 * If there's no transition name to apply, the original tag is returned.
 */
export function applyViewTransitionToImgTag(
  imgTag: string,
  enhancements: CardEnhancementData
): string {
  const style = buildViewTransitionStyle(enhancements);
  if (!style) return imgTag;

  if (/\sstyle="/i.test(imgTag)) {
    return imgTag.replace(/\sstyle="([^"]*)"/i, ` style="$1;${style}"`);
  }

  return imgTag.replace(/<img\b/i, `<img style="${style}"`);
}

/**
 * Badge HTML emitted by `Product_Badges::build_badges_html()` and exposed
 * via the Store API extension. Returns an empty string when the feature
 * is disabled or no badges apply to the product.
 */
export function buildBadgesHtml(enhancements: CardEnhancementData): string {
  return enhancements.badges_html || '';
}

/**
 * Compact countdown markup matching `Countdown_Timer::render_markup()`
 * with `compact=true`. The Interactivity API doesn't hydrate elements
 * inserted via innerHTML, so this variant uses a plain
 * `data-aa-countdown` attribute that the countdown store discovers via
 * the `aa:cards-rendered` event and ticks manually.
 */
export function buildCountdownHtml(enhancements: CardEnhancementData): string {
  const countdown = enhancements.countdown;
  if (!countdown) return '';

  const segments: Array<[keyof CardCountdown, string]> = [
    ['days', 'd'],
    ['hours', 'h'],
    ['minutes', 'm'],
    ['seconds', 's'],
  ];

  const parts = segments
    .map(
      ([key, unit]) =>
        `<span class="aggressive-apparel-countdown__segment">` +
        `<span class="aggressive-apparel-countdown__value" data-aa-countdown-segment="${escapeHtml(String(key))}">${countdown[key]}</span>` +
        `<span class="aggressive-apparel-countdown__unit">${escapeHtml(unit)}</span>` +
        `</span>`
    )
    .join('');

  return (
    `<div class="aggressive-apparel-countdown aggressive-apparel-countdown--compact" ` +
    `data-aa-countdown="${countdown.end_ts}">` +
    `<span class="aggressive-apparel-countdown__label">Sale ends in</span>` +
    parts +
    `</div>`
  );
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
