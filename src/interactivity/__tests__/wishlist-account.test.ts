import {
  WishlistAccount,
  readGuestItems,
  writeGuestItems,
  STORAGE_KEY,
  type WishlistProduct,
} from '../wishlist/account';

const product = (id: number): WishlistProduct => ({
  id,
  product_id: id,
  key: String(id).padStart(32, '0'),
  name: `Product ${id}`,
  permalink: `https://example.com/product/${id}`,
  prices: null,
});

function reply(body: unknown, status = 200, nonce = 'fresh'): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: { get: (name: string) => (name === 'Nonce' ? nonce : null) },
    json: async () => body,
  } as Response;
}

describe('wishlist guest-to-account migration', () => {
  let fetchMock: jest.Mock;
  let api: WishlistAccount;

  beforeEach(() => {
    localStorage.clear();
    fetchMock = jest.fn();
    window.fetch = fetchMock;
    api = new WishlistAccount({
      itemsUrl: '/wp-json/wc/store/v1/shopper-lists/wishlist/items',
      restNonce: 'rest-token',
      nonce: 'store-token',
    });
  });

  it.each(['{}', 'null', '"bad"', 'broken'])(
    'ignores malformed browser storage: %s',
    value => {
      localStorage.setItem(STORAGE_KEY, value);
      expect(readGuestItems()).toEqual([]);
    }
  );

  it('retains only unique positive integer IDs from legacy browser saves', () => {
    localStorage.setItem(STORAGE_KEY, '[4,4,-1,0,"8",null,2.5,9]');
    expect(readGuestItems()).toEqual([4, 9]);
  });

  it('imports successful saves and preserves variable parents or failures', async () => {
    writeGuestItems([1, 2, 3]);
    fetchMock.mockResolvedValueOnce(reply(product(2), 201));
    const items = await api.merge(
      [product(1)],
      [
        { id: 2, type: 'simple' },
        { id: 3, type: 'variable' },
      ]
    );
    expect(items.map(item => item.id)).toEqual([1, 2]);
    expect(readGuestItems()).toEqual([3]);
    expect(
      fetchMock.mock.calls.filter(([, init]) => init.method === 'POST')
    ).toHaveLength(1);
    expect(fetchMock.mock.calls[0][1]).toMatchObject({
      credentials: 'same-origin',
      body: JSON.stringify({ product_id: 2 }),
      headers: { 'X-WP-Nonce': 'rest-token', Nonce: 'store-token' },
    });
  });

  it('does not add an item already represented by a saved variation', async () => {
    writeGuestItems([10]);
    const saved = { ...product(11), product_id: 10, variation_id: 11 };
    expect(await api.merge([saved], [])).toEqual([saved]);
    expect(readGuestItems()).toEqual([]);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('keeps every guest save if the server cannot confirm an import', async () => {
    writeGuestItems([1, 2]);
    fetchMock.mockRejectedValue(new Error('offline'));
    expect(
      await api.merge(
        [],
        [
          { id: 1, type: 'simple' },
          { id: 2, type: 'simple' },
        ]
      )
    ).toEqual([]);
    expect(readGuestItems()).toEqual([1, 2]);
  });

  it('does not refetch a confirmed account list when there are no guest saves', async () => {
    expect(await api.merge([product(1)], [])).toEqual([product(1)]);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('keeps variable parents and unknown products without POSTing them', async () => {
    writeGuestItems([10, 99]);
    expect(await api.merge([], [{ id: 10, type: 'variable' }])).toEqual([]);
    expect(readGuestItems()).toEqual([10, 99]);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('refreshes an expired Store API nonce and retries exactly once', async () => {
    fetchMock
      .mockResolvedValueOnce(
        reply({ code: 'woocommerce_rest_invalid_nonce' }, 403, 'new-token')
      )
      .mockResolvedValueOnce(reply(product(1), 201));
    await api.add(1);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(fetchMock.mock.calls[1][1].headers.Nonce).toBe('new-token');
  });

  it('does not retry arbitrary write failures', async () => {
    fetchMock.mockResolvedValueOnce(reply({ message: 'Select options' }, 400));
    await expect(api.add(1)).rejects.toThrow('Select options');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('deletes the specific saved variation by its WooCommerce key', async () => {
    fetchMock.mockResolvedValueOnce(reply(null, 204));
    await api.remove('abc');
    expect(fetchMock.mock.calls[0][0]).toBe(
      new URL(
        '/wp-json/wc/store/v1/shopper-lists/wishlist/items/abc',
        location.href
      ).href
    );
    expect(fetchMock.mock.calls[0][1].method).toBe('DELETE');
  });
});

describe('wishlist recovery boundaries', () => {
  afterEach(() => jest.useRealTimers());

  it('bounds a stalled response body and aborts the request', async () => {
    jest.useFakeTimers();
    const fetchMock = jest.fn().mockResolvedValue({
      ...reply([]),
      json: () => new Promise(() => {}),
    });
    window.fetch = fetchMock;
    const api = new WishlistAccount({ itemsUrl: '/wishlist/items' });
    const result = expect(api.list()).rejects.toThrow('timed out');
    await jest.advanceTimersByTimeAsync(15_000);
    await result;
    expect(fetchMock.mock.calls[0][1].signal.aborted).toBe(true);
    expect(api.needsReconcile).toBe(true);
  });

  it('does not accept malformed account entries as successful writes', async () => {
    window.fetch = jest.fn().mockResolvedValue(reply({ id: 1 }, 201));
    const api = new WishlistAccount({ itemsUrl: '/wishlist/items' });
    await expect(api.add(1)).rejects.toThrow('Invalid wishlist response');
    expect(api.needsReconcile).toBe(true);
  });

  it('appends deletion keys inside a plain-permalink REST route', async () => {
    const fetchMock = jest.fn().mockResolvedValue(reply(null, 204));
    window.fetch = fetchMock;
    const api = new WishlistAccount({
      itemsUrl:
        'https://example.com/store/?rest_route=/wc/store/v1/shopper-lists/wishlist/items',
    });
    await api.remove('saved-key');
    const url = new URL(fetchMock.mock.calls[0][0]);
    expect(url.pathname).toBe('/store/');
    expect(url.searchParams.get('rest_route')).toBe(
      '/wc/store/v1/shopper-lists/wishlist/items/saved-key'
    );
  });

  it('reports denied browser writes instead of silently claiming success', () => {
    const storage = jest
      .spyOn(Storage.prototype, 'setItem')
      .mockImplementation(() => {
        throw new Error('Quota exceeded');
      });
    try {
      expect(() => writeGuestItems([1])).toThrow('Quota exceeded');
    } finally {
      storage.mockRestore();
    }
  });

  it('keeps confirmed imports when clearing browser storage fails', async () => {
    localStorage.clear();
    writeGuestItems([1]);
    window.fetch = jest.fn().mockResolvedValue(reply(product(1), 201));
    const storage = jest
      .spyOn(Storage.prototype, 'setItem')
      .mockImplementation(() => {
        throw new Error('Quota exceeded');
      });
    try {
      const api = new WishlistAccount({ itemsUrl: '/wishlist/items' });
      expect(await api.merge([], [{ id: 1, type: 'simple' }])).toEqual([
        product(1),
      ]);
      expect(api.importFailed).toBe(true);
      expect(readGuestItems()).toEqual([1]);
    } finally {
      storage.mockRestore();
    }
  });
});
