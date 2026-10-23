import { fetchWishlistProducts } from '../wishlist/products';

const product = {
  id: 12,
  name: 'Hat',
  permalink: 'https://example.com/hat',
  prices: null,
  variation: '',
};
const response = (data: unknown) =>
  ({ ok: true, status: 200, json: async () => data }) as Response;

it('normalizes the public variation string without weakening the account schema', async () => {
  window.fetch = jest.fn().mockResolvedValue(response([product]));
  expect(
    await fetchWishlistProducts([12], '/shop/?rest_route=/wc/store/v1/products')
  ).toEqual([{ ...product, variation: [] }]);
  const url = new URL((window.fetch as jest.Mock).mock.calls[0][0]);
  expect(url.pathname).toBe('/shop/');
  expect(url.searchParams.get('rest_route')).toBe('/wc/store/v1/products');
  expect(url.searchParams.get('include')).toBe('12');
});

it('rejects invalid product details instead of breaking the rendering loop', async () => {
  window.fetch = jest
    .fn()
    .mockResolvedValue(response([{ ...product, images: [null] }]));
  await expect(fetchWishlistProducts([12], '/products')).rejects.toThrow(
    'Invalid product response'
  );
});

it('batches long lists without losing products beyond the API page size', async () => {
  const fetchMock = jest
    .fn()
    .mockResolvedValueOnce(response([product]))
    .mockResolvedValueOnce(response([{ ...product, id: 101 }]));
  window.fetch = fetchMock;
  const ids = Array.from({ length: 101 }, (_, i) => i + 1);
  expect(
    (await fetchWishlistProducts(ids, '/products')).map(item => item.id)
  ).toEqual([12, 101]);
  expect(
    new URL(fetchMock.mock.calls[0][0]).searchParams.get('include')!.split(',')
  ).toHaveLength(100);
  expect(new URL(fetchMock.mock.calls[1][0]).searchParams.get('include')).toBe(
    '101'
  );
});
