import { fetchVariations } from '../quick-view/variations';
import { buildVariations } from '../quick-view/product-data';
import type { StoreApiProduct } from '../quick-view/types';

jest.mock(
  '@aggressive-apparel/helpers',
  () => ({ stripTags: (value: string) => value }),
  { virtual: true }
);

const response = (data: unknown, pages = 1) =>
  ({
    ok: true,
    status: 200,
    json: async () => data,
    headers: { get: () => String(pages) },
  }) as unknown as Response;

const variation = (id: number, extra: Partial<StoreApiProduct> = {}) =>
  ({
    id,
    name: `Tee ${id}`,
    permalink: '',
    description: '',
    prices: { price: `${id}00`, currency_minor_unit: 2 },
    is_in_stock: true,
    images: [{ src: `https://example.com/${id}.jpg`, alt: `Tee ${id}` }],
    ...extra,
  }) as unknown as StoreApiProduct;

describe('fetchVariations', () => {
  it('queries the variations of one parent, in either permalink style', async () => {
    window.fetch = jest.fn().mockResolvedValue(response([variation(11)]));

    await fetchVariations('/wp-json/wc/store/v1/products/', 7);
    await fetchVariations('/?rest_route=/wc/store/v1/products/', 7);

    const [pretty, plain] = (window.fetch as jest.Mock).mock.calls.map(
      ([url]) => new URL(url)
    );
    expect(pretty.pathname).toBe('/wp-json/wc/store/v1/products');
    expect(plain.searchParams.get('rest_route')).toBe('/wc/store/v1/products');
    for (const url of [pretty, plain]) {
      expect(url.searchParams.get('type')).toBe('variation');
      expect(url.searchParams.get('parent')).toBe('7');
      expect(url.searchParams.get('per_page')).toBe('100');
    }
  });

  it('follows every page of a large product', async () => {
    window.fetch = jest
      .fn()
      .mockResolvedValueOnce(response([variation(1)], 2))
      .mockResolvedValueOnce(response([variation(2)], 2));

    const result = await fetchVariations('/products/', 7);

    expect(result.map(v => v.id)).toEqual([1, 2]);
    expect(
      new URL((window.fetch as jest.Mock).mock.calls[1][0]).searchParams.get(
        'page'
      )
    ).toBe('2');
  });

  it('degrades to an empty list when the request fails', async () => {
    window.fetch = jest.fn().mockRejectedValue(new TypeError('offline'));

    await expect(fetchVariations('/products/', 7)).resolves.toEqual([]);
  });

  it('lets an abort through so the stale open is dropped', async () => {
    const abort = Object.assign(new Error('aborted'), { name: 'AbortError' });
    window.fetch = jest.fn().mockRejectedValue(abort);

    await expect(fetchVariations('/products/', 7)).rejects.toBe(abort);
  });
});

describe('buildVariations', () => {
  const parent = {
    id: 7,
    name: 'Tee',
    permalink: '',
    description: '',
    prices: { price: '999' },
    variations: [
      { id: 11, attributes: [{ name: 'Size', value: 's' }] },
      { id: 12, attributes: [{ name: 'Size', value: 'm' }] },
    ],
  } as unknown as StoreApiProduct;

  it('takes prices, stock and image from the Store API variation', () => {
    const [small] = buildVariations(parent, { size: 'pa_size' }, [
      variation(11, { is_in_stock: false }),
    ]);

    expect(small.attributes[0].attribute).toBe('pa_size');
    expect(small.prices).toEqual({ price: '1100', currency_minor_unit: 2 });
    expect(small.inStock).toBe(false);
    expect(small.image).toBe('https://example.com/11.jpg');
    expect(small.imageAlt).toBe('Tee 11');
  });

  it('falls back to the parent price and stays selectable without details', () => {
    const [, medium] = buildVariations(parent, {}, [variation(11)]);

    expect(medium.prices).toEqual({ price: '999' });
    expect(medium.inStock).toBe(true);
    expect(medium.image).toBe('');
  });

  it('ignores a non-http image source', () => {
    const [small] = buildVariations(parent, {}, [
      variation(11, {
        images: [{ src: 'javascript:alert(1)', alt: '' }],
      } as Partial<StoreApiProduct>),
    ]);

    expect(small.image).toBe('');
  });
});
