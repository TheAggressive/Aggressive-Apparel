import { formatWishlistItem } from '../wishlist/display';

jest.mock(
  '@aggressive-apparel/helpers',
  () => ({
    decodeEntities: (value: string) => value,
    formatVariablePriceRange: () => 'From $20.00',
  }),
  { virtual: true }
);
const label = (_key: string, fallback: string) => fallback;
const product = {
  id: 12,
  name: 'Hat',
  permalink: 'https://example.com/hat',
  prices: null,
};

it('labels every browser-only account item, including failed simple imports', () => {
  expect(
    formatWishlistItem({ ...product, type: 'simple' }, {}, true, label)
      .storageNote
  ).toBe('Saved on this device');
  expect(
    formatWishlistItem({ ...product, key: 'saved' }, {}, true, label)
      .storageNote
  ).toBe('');
});

it('keeps variation selections in the cart link and disables unavailable products', () => {
  const item = {
    ...product,
    id: 13,
    product_id: 12,
    key: 'saved',
    variation_id: 13,
    is_purchasable: true,
    variation: [
      { raw_attribute: 'attribute_size', attribute: 'Size', value: 'Large' },
    ],
  };
  const displayed = formatWishlistItem(item, {}, true, label);
  const url = new URL(displayed.addToCartUrl);
  expect(url.searchParams.get('add-to-cart')).toBe('12');
  expect(url.searchParams.get('variation_id')).toBe('13');
  expect(url.searchParams.get('attribute_size')).toBe('Large');
  expect(displayed.name).toBe('Hat / Large');
  expect(
    formatWishlistItem({ ...item, is_in_stock: false }, {}, true, label)
      .canAddToCart
  ).toBe(false);
  expect(
    formatWishlistItem({ ...product, type: 'variable' }, {}, false, label)
      .viewProductLabel
  ).toBe('Choose options');
});

it('offers a product fallback when a saved item cannot be added to the cart', () => {
  expect(
    formatWishlistItem({ ...product, type: 'variable' }, {}, true, label)
      .hasProductFallback
  ).toBe(true);
  expect(
    formatWishlistItem({ ...product, is_in_stock: false }, {}, true, label)
      .hasProductFallback
  ).toBe(true);
  expect(
    formatWishlistItem(
      { ...product, is_purchasable: true, is_in_stock: true },
      {},
      true,
      label
    ).hasProductFallback
  ).toBe(false);
});
