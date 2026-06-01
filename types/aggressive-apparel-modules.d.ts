/**
 * Type declarations for theme script modules resolved via WordPress import maps.
 *
 * These modules are registered with wp_register_script_module() and resolved
 * at runtime by the browser's import map — not by webpack's module resolution.
 * The declarations here satisfy TypeScript's module checker.
 */

declare module '@aggressive-apparel/helpers' {
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

  export interface Variation {
    id: number;
    attributes:
      | Array<{
          attribute?: string;
          name?: string;
          taxonomy?: string;
          value?: string;
        }>
      | Record<string, string>;
    [key: string]: unknown;
  }

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

  export const CARD_ENHANCEMENTS_NS: string;

  export function parsePrice(
    prices: StoreApiPrices | null | undefined
  ): PriceResult;
  export function decodeEntities(str: string | null | undefined): string;
  export function stripTags(html: string | null | undefined): string;
  export function matchVariation(
    variations: Variation[],
    selected: Record<string, string>
  ): Variation | null;
  export function setupFocusTrap(container: HTMLElement): () => void;

  export function escapeHtml(str: string | null | undefined): string;
  export function buildQuickViewTriggerHtml(
    productId: number,
    productName: string
  ): string;
  export function buildWishlistHeartHtml(productId: number): string;
  export function notifyCardsRendered(container: HTMLElement | null): void;

  export function getCardEnhancements(product: {
    extensions?: Record<string, unknown>;
  }): CardEnhancementData;
  export function buildViewTransitionStyle(
    enhancements: CardEnhancementData
  ): string;
  export function applyViewTransitionToImgTag(
    imgTag: string,
    enhancements: CardEnhancementData
  ): string;
  export function buildBadgesHtml(enhancements: CardEnhancementData): string;
  export function buildCountdownHtml(enhancements: CardEnhancementData): string;
}

declare module '@aggressive-apparel/scroll-lock' {
  export function lockScroll(): void;
  export function unlockScroll(): void;
}

declare module '@aggressive-apparel/use-overlay' {
  export interface PrepareOverlayOpenOptions {
    manageOpenClass?: boolean;
    lockScroll?: boolean;
  }

  export interface OpenOverlayOptions {
    shell: HTMLElement;
    panel: HTMLElement;
    triggerElement?: HTMLElement | null;
    focusSelector?: string;
  }

  export interface CloseOverlayOptions {
    shell: HTMLElement;
    panel: HTMLElement;
    focusTrapCleanup?: (() => void) | null;
    triggerElement?: HTMLElement | null;
    isStillOpen?: () => boolean;
    onFinish?: () => void;
    manageOpenClass?: boolean;
    transitionProperty?: 'opacity' | 'transform';
  }

  export function prepareOverlayOpen(
    shell: HTMLElement,
    options?: PrepareOverlayOpenOptions
  ): void;
  export function activateOverlayFocus(
    options: OpenOverlayOptions
  ): () => void;
  export function closeOverlay(options: CloseOverlayOptions): void;
}
