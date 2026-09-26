/**
 * Pure cart total parsing for free-shipping blocks.
 *
 * @package Aggressive_Apparel
 */

/** Store API cart extension namespace (mirrors Free_Shipping::STORE_API_NAMESPACE). */
export const FREE_SHIPPING_EXTENSION = 'aggressive-apparel/free-shipping';

export interface CartTotals {
  total_items: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
  currency_decimal_separator?: string;
  currency_thousand_separator?: string;
}

/**
 * Customer-specific free-shipping data from the theme's cart extension.
 * Amounts are Store API minor units in the active currency; `unlocked` is
 * WooCommerce's own verdict that free shipping applies to this cart.
 */
export interface FreeShippingExtension {
  threshold: number;
  subtotal: number;
  rate: number;
  unlocked?: boolean;
}

export interface CartResponse {
  totals?: CartTotals;
  extensions?: {
    [FREE_SHIPPING_EXTENSION]?: FreeShippingExtension;
  };
}

export interface CurrencyFormat {
  currencyMinorUnit: number;
  currencyPrefix: string;
  currencySuffix: string;
  currencyDecimalSeparator: string;
  currencyThousandSeparator: string;
}

export interface ParsedCartTotals extends CurrencyFormat {
  threshold: number;
  cartTotal: number;
  remaining: number;
  complete: boolean;
}

export interface FreeShippingMessageI18n {
  progressDefault: string;
  progressCustom: string;
  unlockedDefault: string;
  unlockedCustom: string;
}

export interface FreeShippingBarI18n {
  progress: string;
  complete: string;
}

export interface FreeShippingMessageContext extends CurrencyFormat {
  remaining: number;
  complete: boolean;
  emphasisText: string;
  i18n: FreeShippingMessageI18n;
}

/**
 * Replace WordPress-style placeholders in a translated template.
 */
export function interpolateI18n(template: string, ...values: string[]): string {
  let result = template;

  values.forEach((value, index) => {
    result = result.split(`%${index + 1}$s`).join(value);
  });

  if (values.length > 0) {
    result = result.replace('%s', values[0]);
  }

  return result;
}

function isDefaultEmphasis(emphasis: string): boolean {
  if (!emphasis) {
    return true;
  }

  return emphasis.replace(/\s+/g, '').toLowerCase() === 'freeshipping';
}

/**
 * Format an amount with the currency's symbol position and separators.
 */
export function formatMoney(amount: number, format: CurrencyFormat): string {
  const [integer, fraction] = amount
    .toFixed(format.currencyMinorUnit)
    .split('.');
  const grouped = integer.replace(
    /\B(?=(\d{3})+(?!\d))/g,
    format.currencyThousandSeparator
  );
  const number = fraction
    ? `${grouped}${format.currencyDecimalSeparator}${fraction}`
    : grouped;

  return `${format.currencyPrefix}${number}${format.currencySuffix}`;
}

/**
 * Build the ticker-style free shipping message.
 */
export function formatFreeShippingMessage(
  ctx: FreeShippingMessageContext
): string {
  const emphasis = ctx.emphasisText.trim();
  const { i18n } = ctx;

  if (ctx.complete) {
    if (isDefaultEmphasis(emphasis)) {
      return i18n.unlockedDefault;
    }

    return interpolateI18n(i18n.unlockedCustom, emphasis);
  }

  const amount = formatMoney(ctx.remaining, ctx);

  if (isDefaultEmphasis(emphasis)) {
    return interpolateI18n(i18n.progressDefault, amount);
  }

  return interpolateI18n(i18n.progressCustom, amount, emphasis);
}

/**
 * Parse a Store API cart response into free-shipping progress.
 *
 * The theme's cart extension carries the customer's zone threshold and the
 * subtotal WooCommerce actually tests, both in the active currency. Without
 * it (extension unavailable) the server-rendered threshold and the raw items
 * total are used.
 *
 * @param cart            Store API cart response.
 * @param threshold       Server-rendered threshold (fallback).
 * @param fallback        Server-rendered currency format (fallback).
 * @param customThreshold Block override in store currency; 0 when unset.
 */
export function parseCartTotals(
  cart: CartResponse,
  threshold: number,
  fallback: CurrencyFormat,
  customThreshold = 0
): ParsedCartTotals | null {
  const totals = cart?.totals;
  if (!totals || totals.total_items == null || totals.total_items === '') {
    return null;
  }

  const minorUnit = totals.currency_minor_unit ?? fallback.currencyMinorUnit;
  const divisor = Math.pow(10, minorUnit);
  const extension = cart.extensions?.[FREE_SHIPPING_EXTENSION];

  let cartTotal = parseInt(totals.total_items, 10) / divisor;
  let resolvedThreshold = threshold;

  if (extension) {
    cartTotal = extension.subtotal / divisor;
    resolvedThreshold =
      customThreshold > 0
        ? Math.round(customThreshold * extension.rate * divisor) / divisor
        : extension.threshold / divisor;
  }

  // With the extension, a zone threshold completes only on WooCommerce's
  // verdict; a store-currency custom threshold (free shipping configured
  // outside WooCommerce's method) also completes when reached.
  let complete = resolvedThreshold > 0 && cartTotal >= resolvedThreshold;
  if (extension && resolvedThreshold > 0) {
    complete = extension.unlocked === true || (customThreshold > 0 && complete);
  }

  // Never claim "0.00 away" while not complete.
  const remaining =
    resolvedThreshold > 0 && !complete
      ? Math.max(1 / divisor, resolvedThreshold - cartTotal)
      : 0;

  return {
    threshold: resolvedThreshold,
    cartTotal,
    remaining,
    complete,
    currencyMinorUnit: minorUnit,
    currencyPrefix: totals.currency_prefix ?? fallback.currencyPrefix,
    currencySuffix: totals.currency_suffix ?? fallback.currencySuffix,
    currencyDecimalSeparator:
      totals.currency_decimal_separator ?? fallback.currencyDecimalSeparator,
    currencyThousandSeparator:
      totals.currency_thousand_separator ?? fallback.currencyThousandSeparator,
  };
}

/**
 * Progress for a cart known to be empty, keeping the rendered threshold.
 */
export function emptyCartTotals(
  threshold: number,
  format: CurrencyFormat
): ParsedCartTotals {
  return {
    ...pickCurrencyFormat(format),
    threshold,
    cartTotal: 0,
    remaining: Math.max(0, threshold),
    complete: false,
  };
}

function pickCurrencyFormat(format: CurrencyFormat): CurrencyFormat {
  return {
    currencyMinorUnit: format.currencyMinorUnit,
    currencyPrefix: format.currencyPrefix,
    currencySuffix: format.currencySuffix,
    currencyDecimalSeparator: format.currencyDecimalSeparator,
    currencyThousandSeparator: format.currencyThousandSeparator,
  };
}

/**
 * Whether WooCommerce reports a non-empty cart.
 *
 * WC sets `woocommerce_items_in_cart` only while the cart holds items, so its
 * absence authoritatively means "empty" for anonymous visitors.
 */
export function hasCartItemsCookie(cookie: string): boolean {
  return /(?:^|;\s*)woocommerce_items_in_cart=/.test(cookie);
}
