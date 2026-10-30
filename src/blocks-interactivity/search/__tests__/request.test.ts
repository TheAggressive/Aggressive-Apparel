/** @jest-environment jsdom */
import { fetchSearch } from '../request';

afterEach(() => jest.restoreAllMocks());

it('preserves plain-permalink routing and the signed-in REST nonce', async () => {
  window.fetch = jest.fn().mockResolvedValue({ ok: true });
  const signal = new AbortController().signal;
  await fetchSearch(
    '/index.php?rest_route=/aggressive-apparel/v1/search',
    'Tom & Jerry',
    signal,
    'private-nonce'
  );
  const [url, options] = (window.fetch as jest.Mock).mock.calls[0];
  const parsed = new URL(url);
  expect(parsed.searchParams.get('rest_route')).toBe(
    '/aggressive-apparel/v1/search'
  );
  expect(parsed.searchParams.get('query')).toBe('Tom & Jerry');
  expect(parsed.searchParams.get('scope')).toBe('all');
  expect(options.signal).toBe(signal);
  expect(options.mode).toBe('same-origin');
  expect(options.credentials).toBe('same-origin');
  expect(options.headers).toEqual({ 'X-WP-Nonce': 'private-nonce' });
});

it('does not require or send an expiring nonce for guest search', async () => {
  window.fetch = jest.fn().mockResolvedValue({ ok: true });
  await fetchSearch(
    '/wp-json/aggressive-apparel/v1/search',
    'tee',
    new AbortController().signal
  );
  expect((window.fetch as jest.Mock).mock.calls[0][1].headers).toEqual({});
});
