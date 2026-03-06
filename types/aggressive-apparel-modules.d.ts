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
}

declare module '@aggressive-apparel/scroll-lock' {
  export function lockScroll(): void;
  export function unlockScroll(): void;
}
