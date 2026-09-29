/**
 * storeApiPost: Store API cart mutations survive a stale page-cached nonce.
 *
 * A page served from a full-page cache carries the nonce seeded when it was
 * cached. Once that expires the Store API answers 403
 * `woocommerce_rest_invalid_nonce` but sends a fresh nonce, so the helper must
 * adopt it and retry exactly once — never loop, and never retry other errors.
 */

import { storeApiPost } from '../helpers';

interface FakeReply {
  status: number;
  nonce?: string;
  body?: unknown;
}

function reply({ status, nonce, body = {} }: FakeReply): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: {
      get: (name: string) => (name === 'Nonce' ? (nonce ?? null) : null),
    },
    clone: () => ({ json: async () => body }),
    json: async () => body,
  } as unknown as Response;
}

function nonceState(initial: string) {
  const box = { value: initial };
  return {
    box,
    accessors: {
      get: () => box.value,
      set: (value: string) => {
        box.value = value;
      },
    },
  };
}

const sentNonces = (mock: jest.Mock): string[] =>
  mock.mock.calls.map(
    ([, init]) =>
      (init as RequestInit & { headers: Record<string, string> }).headers.Nonce
  );

describe('storeApiPost', () => {
  let fetchMock: jest.Mock;

  beforeEach(() => {
    fetchMock = jest.fn();
    window.fetch = fetchMock;
  });

  it('sends once and adopts the refreshed nonce on success', async () => {
    fetchMock.mockResolvedValueOnce(reply({ status: 201, nonce: 'fresh' }));
    const { box, accessors } = nonceState('seeded');

    const res = await storeApiPost('/cart/add-item', { id: 1 }, accessors);

    expect(res.status).toBe(201);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(sentNonces(fetchMock)).toEqual(['seeded']);
    expect(box.value).toBe('fresh');
  });

  it.each(['woocommerce_rest_invalid_nonce', 'woocommerce_rest_missing_nonce'])(
    'retries once with the fresh nonce after %s',
    async code => {
      fetchMock
        .mockResolvedValueOnce(
          reply({ status: 403, nonce: 'fresh', body: { code } })
        )
        .mockResolvedValueOnce(reply({ status: 201, nonce: 'fresh' }));
      const { box, accessors } = nonceState('stale');

      const res = await storeApiPost('/cart/add-item', { id: 1 }, accessors);

      expect(res.status).toBe(201);
      expect(sentNonces(fetchMock)).toEqual(['stale', 'fresh']);
      expect(box.value).toBe('fresh');
    }
  );

  it('does not retry other errors', async () => {
    fetchMock.mockResolvedValueOnce(
      reply({
        status: 403,
        nonce: 'fresh',
        body: { code: 'woocommerce_rest_cart_product_is_not_purchasable' },
      })
    );
    const { accessors } = nonceState('seeded');

    const res = await storeApiPost('/cart/add-item', { id: 1 }, accessors);

    expect(res.status).toBe(403);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('does not retry when the server returns the same nonce', async () => {
    fetchMock.mockResolvedValueOnce(
      reply({
        status: 403,
        nonce: 'stale',
        body: { code: 'woocommerce_rest_invalid_nonce' },
      })
    );
    const { accessors } = nonceState('stale');

    const res = await storeApiPost('/cart/add-item', { id: 1 }, accessors);

    expect(res.status).toBe(403);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('retries at most once', async () => {
    fetchMock
      .mockResolvedValueOnce(
        reply({
          status: 403,
          nonce: 'fresh-1',
          body: { code: 'woocommerce_rest_invalid_nonce' },
        })
      )
      .mockResolvedValueOnce(
        reply({
          status: 403,
          nonce: 'fresh-2',
          body: { code: 'woocommerce_rest_invalid_nonce' },
        })
      );
    const { box, accessors } = nonceState('stale');

    const res = await storeApiPost('/cart/add-item', { id: 1 }, accessors);

    expect(res.status).toBe(403);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(box.value).toBe('fresh-2');
  });
});
