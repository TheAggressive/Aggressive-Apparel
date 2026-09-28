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

/** One styled run of the message (mirrors Free_Shipping_Message::segments). */
export interface FreeShippingMessageSegment {
  key: string;
  text: string;
  amount: boolean;
  emphasis: boolean;
}

type SegmentKind = 'text' | 'emphasis' | 'amount';

/**
 * Template tokens: printf args, a literal percent, and the `{em}`…`{em_end}`
 * highlight pair. Mirrors Free_Shipping_Message::MESSAGE_TOKEN.
 */
const MESSAGE_TOKEN = /%%|%(?:(\d+)\$)?s|\{em\}|\{em_end\}/g;

export interface FreeShippingMessageContext extends CurrencyFormat {
  remaining: number;
  complete: boolean;
  emphasisText: string;
  i18n: FreeShippingMessageI18n;
}

/** Whether a legacy emphasis phrase is blank or the stock label. */
export function isDefaultEmphasis(emphasis: string): boolean {
  if (!emphasis) {
    return true;
  }

  return emphasis.replace(/\s+/g, '').toLowerCase() === 'freeshipping';
}

/**
 * Format an amount with the currency's symbol position and separators.
 *
 * A whole amount drops its zero decimals ($104, not $104.00). Mirrors
 * Free_Shipping_Message::format_amount() so server HTML matches live updates.
 */
export function formatMoney(amount: number, format: CurrencyFormat): string {
  const [integer, fraction = ''] = amount
    .toFixed(format.currencyMinorUnit)
    .split('.');
  const grouped = integer.replace(
    /\B(?=(\d{3})+(?!\d))/g,
    format.currencyThousandSeparator
  );
  const number = /[1-9]/.test(fraction)
    ? `${grouped}${format.currencyDecimalSeparator}${fraction}`
    : grouped;

  return `${format.currencyPrefix}${number}${format.currencySuffix}`;
}

/**
 * Tokenize a message template into segments.
 *
 * Unbalanced or missing `{em}` markers degrade to plain text; a missing
 * printf argument renders nothing.
 */
function splitTemplate(
  template: string,
  args: Array<[string, SegmentKind]>
): FreeShippingMessageSegment[] {
  const parts: Array<[string, SegmentKind]> = [];
  const push = (text: string, kind: SegmentKind): void => {
    if (!text) {
      return;
    }
    const last = parts[parts.length - 1];
    if (last && kind !== 'amount' && last[1] === kind) {
      last[0] += text;
      return;
    }
    parts.push([text, kind]);
  };

  let emphasized = false;
  let nextArg = 0;
  let offset = 0;

  for (const match of template.matchAll(MESSAGE_TOKEN)) {
    const [token, argNumber] = match;
    const literal: SegmentKind = emphasized ? 'emphasis' : 'text';

    push(template.slice(offset, match.index), literal);
    offset = (match.index ?? 0) + token.length;

    if (token === '{em}' || token === '{em_end}') {
      emphasized = token === '{em}';
    } else if (token === '%%') {
      push('%', literal);
    } else {
      const arg = args[argNumber ? parseInt(argNumber, 10) - 1 : nextArg++];
      if (arg) {
        push(arg[0], arg[1]);
      }
    }
  }

  push(template.slice(offset), emphasized ? 'emphasis' : 'text');

  return parts.map(([text, kind], index) => ({
    key: `${index}-${kind}`,
    text,
    amount: kind === 'amount',
    emphasis: kind === 'emphasis',
  }));
}

/**
 * Build the ticker-style free shipping message as styled segments.
 */
export function buildFreeShippingSegments(
  ctx: FreeShippingMessageContext
): FreeShippingMessageSegment[] {
  const emphasis = ctx.emphasisText.trim();
  const isDefault = isDefaultEmphasis(emphasis);
  const { i18n } = ctx;
  const phrase: [string, SegmentKind] = [emphasis, 'emphasis'];

  if (ctx.complete) {
    return isDefault
      ? splitTemplate(i18n.unlockedDefault, [])
      : splitTemplate(i18n.unlockedCustom, [phrase]);
  }

  const amount: [string, SegmentKind] = [
    formatMoney(ctx.remaining, ctx),
    'amount',
  ];

  return isDefault
    ? splitTemplate(i18n.progressDefault, [amount])
    : splitTemplate(i18n.progressCustom, [amount, phrase]);
}

/**
 * Build the ticker-style free shipping message as plain text.
 */
export function formatFreeShippingMessage(
  ctx: FreeShippingMessageContext
): string {
  return buildFreeShippingSegments(ctx)
    .map(segment => segment.text)
    .join('');
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
 * The cart carried by a Store API mutation response, if any.
 *
 * Cart routes (add-item, update-item, remove-item, coupons, update-customer)
 * answer with the full cart, extensions included; the batch route wraps one
 * response per request, and its last successful cart is the newest state.
 * Anything else (wc-ajax fragments) returns null, so the caller refetches.
 */
export function cartFromMutationBody(body: unknown): CartResponse | null {
  const isCart = (value: unknown): value is CartResponse =>
    typeof value === 'object' &&
    value !== null &&
    typeof (value as CartResponse).totals?.total_items === 'string';

  if (isCart(body)) {
    return body;
  }

  const responses = (body as { responses?: unknown } | null)?.responses;
  if (!Array.isArray(responses)) {
    return null;
  }

  for (let i = responses.length - 1; i >= 0; i--) {
    const entry = responses[i] as { status?: number; body?: unknown } | null;
    const status = entry?.status ?? 0;
    if (status >= 200 && status < 300 && isCart(entry?.body)) {
      return entry.body;
    }
  }

  return null;
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
