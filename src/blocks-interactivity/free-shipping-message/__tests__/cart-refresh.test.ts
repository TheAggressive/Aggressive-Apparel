/**
 * Tests for free-shipping cart parsing helpers.
 *
 * @jest-environment jsdom
 */

import {
  FREE_SHIPPING_EXTENSION,
  emptyCartTotals,
  hasCartItemsCookie,
  parseCartTotals,
  formatFreeShippingMessage,
  formatMoney,
  interpolateI18n,
  type FreeShippingMessageContext,
} from '../cart-data';

const usd = {
  currencyMinorUnit: 2,
  currencyPrefix: '$',
  currencySuffix: '',
  currencyDecimalSeparator: '.',
  currencyThousandSeparator: ',',
};

const defaultI18n = {
  progressDefault: '%s Away from FREE Shipping!',
  progressCustom: '%1$s Away from %2$s!',
  unlockedDefault: 'FREE Shipping UNLOCKED!',
  unlockedCustom: '%s UNLOCKED!',
};

describe('interpolateI18n', () => {
  it('replaces positional placeholders', () => {
    expect(
      interpolateI18n('%1$s Away from %2$s!', '$50.00', 'FREE Express')
    ).toBe('$50.00 Away from FREE Express!');
  });

  it('replaces a single %s placeholder', () => {
    expect(interpolateI18n('%s Away from FREE Shipping!', '$50.00')).toBe(
      '$50.00 Away from FREE Shipping!'
    );
  });
});

describe('formatMoney', () => {
  it('formats a prefixed currency with grouping', () => {
    expect(formatMoney(1234.5, usd)).toBe('$1,234.50');
  });

  it('formats a suffixed currency with European separators', () => {
    expect(
      formatMoney(1234.5, {
        currencyMinorUnit: 2,
        currencyPrefix: '',
        currencySuffix: ' €',
        currencyDecimalSeparator: ',',
        currencyThousandSeparator: '.',
      })
    ).toBe('1.234,50 €');
  });

  it('omits the decimal separator for zero-decimal currencies', () => {
    expect(
      formatMoney(12000, {
        ...usd,
        currencyMinorUnit: 0,
        currencyPrefix: '¥',
      })
    ).toBe('¥12,000');
  });
});

describe('parseCartTotals', () => {
  it('parses a non-zero cart total', () => {
    const parsed = parseCartTotals(
      { totals: { total_items: '5000', currency_minor_unit: 2 } },
      100,
      usd
    );

    expect(parsed).toEqual({
      ...usd,
      threshold: 100,
      cartTotal: 50,
      remaining: 50,
      complete: false,
    });
  });

  it('treats an empty cart total of zero as valid', () => {
    const parsed = parseCartTotals(
      { totals: { total_items: '0', currency_minor_unit: 2 } },
      100,
      usd
    );

    expect(parsed?.cartTotal).toBe(0);
    expect(parsed?.remaining).toBe(100);
    expect(parsed?.complete).toBe(false);
  });

  it('marks the cart complete when the threshold is met', () => {
    const parsed = parseCartTotals(
      { totals: { total_items: '10000', currency_minor_unit: 2 } },
      100,
      usd
    );

    expect(parsed?.complete).toBe(true);
    expect(parsed?.remaining).toBe(0);
  });

  it('returns null when totals are missing', () => {
    expect(parseCartTotals({}, 100, usd)).toBeNull();
  });

  it("prefers the extension's zone threshold and subtotal over SSR values", () => {
    const parsed = parseCartTotals(
      {
        totals: {
          total_items: '9999',
          currency_minor_unit: 2,
          currency_prefix: '£',
        },
        extensions: {
          [FREE_SHIPPING_EXTENSION]: {
            threshold: 12000,
            subtotal: 4500,
            rate: 0.79,
          },
        },
      },
      75,
      usd
    );

    expect(parsed?.threshold).toBe(120);
    expect(parsed?.cartTotal).toBe(45);
    expect(parsed?.remaining).toBe(75);
    expect(parsed?.currencyPrefix).toBe('£');
  });

  it('converts a store-currency custom threshold with the extension rate', () => {
    const parsed = parseCartTotals(
      {
        totals: { total_items: '0', currency_minor_unit: 2 },
        extensions: {
          [FREE_SHIPPING_EXTENSION]: {
            threshold: 12000,
            subtotal: 0,
            rate: 0.9234,
          },
        },
      },
      100,
      usd,
      100
    );

    expect(parsed?.threshold).toBe(92.34);
  });

  it('reports no threshold when the customer zone has none', () => {
    const parsed = parseCartTotals(
      {
        totals: { total_items: '5000', currency_minor_unit: 2 },
        extensions: {
          [FREE_SHIPPING_EXTENSION]: { threshold: 0, subtotal: 5000, rate: 1 },
        },
      },
      75,
      usd
    );

    expect(parsed?.threshold).toBe(0);
    expect(parsed?.remaining).toBe(0);
    expect(parsed?.complete).toBe(false);
  });
});

describe('coupon unlock', () => {
  it('completes a "minimum OR coupon" cart regardless of amount', () => {
    const parsed = parseCartTotals(
      {
        totals: { total_items: '1000', currency_minor_unit: 2 },
        extensions: {
          [FREE_SHIPPING_EXTENSION]: {
            threshold: 7500,
            subtotal: 1000,
            rate: 1,
            unlocked: true,
          },
        },
      },
      75,
      usd
    );

    expect(parsed?.complete).toBe(true);
    expect(parsed?.remaining).toBe(0);
  });
});

describe('WooCommerce verdict', () => {
  const extensionCart = (unlocked: boolean, subtotal: number) => ({
    totals: { total_items: String(subtotal), currency_minor_unit: 2 },
    extensions: {
      [FREE_SHIPPING_EXTENSION]: {
        threshold: 10000,
        subtotal,
        rate: 1,
        unlocked,
      },
    },
  });

  it('waits for WooCommerce even when the amount is reached', () => {
    // e.g. a "minimum AND coupon" rule WooCommerce hasn't satisfied yet.
    const parsed = parseCartTotals(extensionCart(false, 12000), 100, usd);

    expect(parsed?.complete).toBe(false);
    expect(parsed?.remaining).toBe(0.01);
  });

  it('completes a reached custom threshold without the verdict', () => {
    const parsed = parseCartTotals(extensionCart(false, 6000), 100, usd, 50);

    expect(parsed?.complete).toBe(true);
    expect(parsed?.remaining).toBe(0);
  });
});

describe('emptyCartTotals', () => {
  it('keeps the threshold and zeroes the cart side', () => {
    expect(emptyCartTotals(75, usd)).toEqual({
      ...usd,
      threshold: 75,
      cartTotal: 0,
      remaining: 75,
      complete: false,
    });
  });
});

describe('hasCartItemsCookie', () => {
  it('detects the WooCommerce items cookie anywhere in the jar', () => {
    expect(hasCartItemsCookie('a=1; woocommerce_items_in_cart=1')).toBe(true);
    expect(hasCartItemsCookie('woocommerce_items_in_cart=1')).toBe(true);
  });

  it('ignores lookalike cookie names', () => {
    expect(hasCartItemsCookie('x_woocommerce_items_in_cart=1')).toBe(false);
    expect(hasCartItemsCookie('')).toBe(false);
  });
});

describe('formatFreeShippingMessage', () => {
  const baseContext: FreeShippingMessageContext = {
    ...usd,
    remaining: 50,
    complete: false,
    emphasisText: 'FREE Shipping',
    i18n: defaultI18n,
  };

  it('formats default progress copy', () => {
    expect(formatFreeShippingMessage(baseContext)).toBe(
      '$50.00 Away from FREE Shipping!'
    );
  });

  it('formats default unlocked copy', () => {
    expect(
      formatFreeShippingMessage({
        ...baseContext,
        complete: true,
        remaining: 0,
      })
    ).toBe('FREE Shipping UNLOCKED!');
  });

  it('uses custom emphasis when set', () => {
    const ctx = { ...baseContext, emphasisText: 'FREE Express' };

    expect(formatFreeShippingMessage(ctx)).toBe(
      '$50.00 Away from FREE Express!'
    );
    expect(
      formatFreeShippingMessage({ ...ctx, complete: true, remaining: 0 })
    ).toBe('FREE Express UNLOCKED!');
  });

  it('uses translated templates from context', () => {
    const ctx: FreeShippingMessageContext = {
      ...baseContext,
      i18n: {
        progressDefault: '%s pour la livraison GRATUITE',
        progressCustom: '%1$s pour %2$s',
        unlockedDefault: 'Livraison GRATUITE DEBLOQUEE',
        unlockedCustom: '%s DEBLOQUE',
      },
    };

    expect(formatFreeShippingMessage(ctx)).toBe(
      '$50.00 pour la livraison GRATUITE'
    );
    expect(
      formatFreeShippingMessage({ ...ctx, complete: true, remaining: 0 })
    ).toBe('Livraison GRATUITE DEBLOQUEE');
  });
});
