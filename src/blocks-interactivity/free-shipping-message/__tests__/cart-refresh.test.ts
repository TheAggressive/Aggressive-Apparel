/**
 * Tests for free-shipping cart parsing helpers.
 *
 * @jest-environment jsdom
 */

import {
  parseCartTotals,
  formatFreeShippingMessage,
  interpolateI18n,
  type FreeShippingMessageContext,
} from '../cart-data';

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

describe('parseCartTotals', () => {
  const fallback = {
    currencyMinorUnit: 2,
    currencyPrefix: '$',
    currencySuffix: '',
  };

  it('parses a non-zero cart total', () => {
    const parsed = parseCartTotals(
      { totals: { total_items: '5000', currency_minor_unit: 2 } },
      100,
      fallback
    );

    expect(parsed).toEqual({
      cartTotal: 50,
      remaining: 50,
      complete: false,
      currencyMinorUnit: 2,
      currencyPrefix: '$',
      currencySuffix: '',
    });
  });

  it('treats an empty cart total of zero as valid', () => {
    const parsed = parseCartTotals(
      { totals: { total_items: '0', currency_minor_unit: 2 } },
      100,
      fallback
    );

    expect(parsed).toEqual({
      cartTotal: 0,
      remaining: 100,
      complete: false,
      currencyMinorUnit: 2,
      currencyPrefix: '$',
      currencySuffix: '',
    });
  });

  it('marks the cart complete when the threshold is met', () => {
    const parsed = parseCartTotals(
      { totals: { total_items: '10000', currency_minor_unit: 2 } },
      100,
      fallback
    );

    expect(parsed?.complete).toBe(true);
    expect(parsed?.remaining).toBe(0);
  });

  it('returns null when totals are missing', () => {
    expect(parseCartTotals({}, 100, fallback)).toBeNull();
  });
});

describe('formatFreeShippingMessage', () => {
  const baseContext: FreeShippingMessageContext = {
    remaining: 50,
    complete: false,
    currencyPrefix: '$',
    currencySuffix: '',
    currencyMinorUnit: 2,
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
