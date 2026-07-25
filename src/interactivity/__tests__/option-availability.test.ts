import { isOptionAvailable } from '../helpers';
import type { Variation } from '../helpers';

/**
 * A tiny apparel matrix used across the suite:
 *  - Red / S   (in stock)
 *  - Red / M   (out of stock)
 *  - Blue / S  (in stock)
 * So Blue has no M variation at all, and Red/M exists but is sold out.
 */
const variations: Variation[] = [
  {
    id: 1,
    inStock: true,
    attributes: { attribute_pa_color: 'red', attribute_pa_size: 's' },
  },
  {
    id: 2,
    inStock: false,
    attributes: { attribute_pa_color: 'red', attribute_pa_size: 'm' },
  },
  {
    id: 3,
    inStock: true,
    attributes: { attribute_pa_color: 'blue', attribute_pa_size: 's' },
  },
];

describe('isOptionAvailable', () => {
  it('treats every option with an in-stock variation as available before any pick', () => {
    const none: Record<string, string> = {};
    expect(isOptionAvailable(variations, 'pa_color', 'red', none)).toBe(true);
    expect(isOptionAvailable(variations, 'pa_color', 'blue', none)).toBe(true);
    expect(isOptionAvailable(variations, 'pa_size', 's', none)).toBe(true);
  });

  it('marks an option with only out-of-stock variations unavailable', () => {
    // The only M variation (Red/M) is out of stock.
    expect(isOptionAvailable(variations, 'pa_size', 'm', {})).toBe(false);
  });

  it('dims a size that has no in-stock variation for the chosen colour', () => {
    // Blue only comes in S; M for Blue does not exist → unavailable.
    const selected = { pa_color: 'blue' };
    expect(isOptionAvailable(variations, 'pa_size', 's', selected)).toBe(true);
    expect(isOptionAvailable(variations, 'pa_size', 'm', selected)).toBe(false);
  });

  it('dims a colour that has no in-stock variation for the chosen size', () => {
    // Picking M: Red/M is sold out and Blue/M does not exist → both colours dim.
    const selected = { pa_size: 'm' };
    expect(isOptionAvailable(variations, 'pa_color', 'red', selected)).toBe(
      false
    );
    expect(isOptionAvailable(variations, 'pa_color', 'blue', selected)).toBe(
      false
    );
  });

  it('ignores the attribute being evaluated when reading the current selection', () => {
    // With Red already selected, Red must still evaluate as available so the
    // shopper can see/keep it (callers additionally exempt the selected option).
    const selected = { pa_color: 'red' };
    expect(isOptionAvailable(variations, 'pa_color', 'blue', selected)).toBe(
      true
    );
  });

  it('can ignore stock when requireStock is false', () => {
    // Red/M exists but is sold out; without the stock gate it counts.
    expect(isOptionAvailable(variations, 'pa_size', 'm', {}, false)).toBe(true);
  });

  it('matches array-form variation attributes and bare/prefixed keys alike', () => {
    const arrayForm: Variation[] = [
      {
        id: 10,
        inStock: true,
        attributes: [
          { attribute: 'pa_color', value: 'green' },
          { attribute: 'pa_size', value: 'l' },
        ],
      },
    ];
    // Bare key + attribute_-prefixed selection key both resolve.
    expect(isOptionAvailable(arrayForm, 'pa_color', 'green', {})).toBe(true);
    expect(
      isOptionAvailable(arrayForm, 'pa_size', 'l', {
        attribute_pa_color: 'green',
      })
    ).toBe(true);
    expect(
      isOptionAvailable(arrayForm, 'pa_size', 'xl', {
        attribute_pa_color: 'green',
      })
    ).toBe(false);
  });

  it('treats an "Any" (empty) variation value as a wildcard', () => {
    const anyColor: Variation[] = [
      {
        id: 20,
        inStock: true,
        attributes: { attribute_pa_color: '', attribute_pa_size: 'l' },
      },
    ];
    // Empty colour matches any requested colour for size L.
    expect(
      isOptionAvailable(anyColor, 'pa_color', 'red', { pa_size: 'l' })
    ).toBe(true);
  });

  it('assumes in stock when the variation omits a stock flag', () => {
    const noFlag: Variation[] = [
      { id: 30, attributes: { attribute_pa_size: 's' } },
    ];
    expect(isOptionAvailable(noFlag, 'pa_size', 's', {})).toBe(true);
  });
});
