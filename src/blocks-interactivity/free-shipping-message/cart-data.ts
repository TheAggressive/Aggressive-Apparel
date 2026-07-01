/**
 * Pure cart total parsing for free-shipping blocks.
 *
 * @package Aggressive_Apparel
 */

export interface CartTotals {
  total_items: string;
  currency_minor_unit?: number;
  currency_prefix?: string;
  currency_suffix?: string;
}

export interface CartResponse {
  totals?: CartTotals;
}

export interface ParsedCartTotals {
  cartTotal: number;
  remaining: number;
  complete: boolean;
  currencyMinorUnit: number;
  currencyPrefix: string;
  currencySuffix: string;
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

export interface FreeShippingMessageContext {
  remaining: number;
  complete: boolean;
  currencyPrefix: string;
  currencySuffix: string;
  currencyMinorUnit: number;
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

function formatRemainingAmount(ctx: FreeShippingMessageContext): string {
  const amount = ctx.remaining.toFixed(ctx.currencyMinorUnit);
  return `${ctx.currencyPrefix}${amount}${ctx.currencySuffix}`;
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

  const amount = formatRemainingAmount(ctx);

  if (isDefaultEmphasis(emphasis)) {
    return interpolateI18n(i18n.progressDefault, amount);
  }

  return interpolateI18n(i18n.progressCustom, amount, emphasis);
}

/**
 * Parse Store API cart totals against a free-shipping threshold.
 */
export function parseCartTotals(
  cart: CartResponse,
  threshold: number,
  fallback: {
    currencyMinorUnit: number;
    currencyPrefix: string;
    currencySuffix: string;
  }
): ParsedCartTotals | null {
  const totals = cart?.totals;
  if (!totals || totals.total_items == null || totals.total_items === '') {
    return null;
  }

  const minorUnit = totals.currency_minor_unit ?? fallback.currencyMinorUnit;
  const divisor = Math.pow(10, minorUnit);
  const cartTotal = parseInt(totals.total_items, 10) / divisor;
  const remaining = Math.max(0, threshold - cartTotal);

  return {
    cartTotal,
    remaining,
    complete: remaining <= 0,
    currencyMinorUnit: minorUnit,
    currencyPrefix: totals.currency_prefix ?? fallback.currencyPrefix,
    currencySuffix: totals.currency_suffix ?? fallback.currencySuffix,
  };
}
