import { getCartSavings } from '../_cart-savings-total';

const item = (regular: string, price: string, quantity: number) => ({
  quantity,
  prices: { regular_price: regular, price },
});

describe('getCartSavings', () => {
  it('sums the per-unit saving times quantity across items', () => {
    expect(
      getCartSavings([item('1800', '1500', 2), item('1500', '1200', 1)])
    ).toBe(900);
  });

  it('is 0 when nothing is on sale', () => {
    expect(getCartSavings([item('2500', '2500', 3)])).toBe(0);
  });

  it('is 0 for an empty cart', () => {
    expect(getCartSavings([])).toBe(0);
  });

  it('never counts a price above the regular price as negative savings', () => {
    expect(
      getCartSavings([item('1000', '1200', 1), item('1800', '1500', 1)])
    ).toBe(300);
  });

  it('skips items with unparseable prices', () => {
    expect(getCartSavings([item('', '1500', 1), item('1800', '1500', 1)])).toBe(
      300
    );
  });
});
