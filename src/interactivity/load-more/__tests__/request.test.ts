/**
 * @jest-environment jsdom
 */

import {
  fetchPageHtml,
  PAGE_TIMEOUT_MS,
  PageLoadError,
  retryDelayMs,
} from '../request';

const URL = 'https://store.test/shop/page/2/';

function respond(status: number, headers: Record<string, string> = {}): void {
  globalThis.fetch = jest.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    url: URL,
    headers: { get: (name: string) => headers[name] ?? null },
    text: () => Promise.resolve('<html></html>'),
  });
}

async function failure(
  promise: Promise<unknown>
): Promise<PageLoadError | Error> {
  try {
    await promise;
  } catch (error) {
    return error as Error;
  }
  throw new Error('Expected a failure');
}

describe('fetchPageHtml', () => {
  afterEach(() => {
    jest.useRealTimers();
  });

  it('returns the HTML and the final URL', async () => {
    respond(200);

    await expect(
      fetchPageHtml(URL, new AbortController().signal)
    ).resolves.toEqual({ html: '<html></html>', url: URL });
  });

  it.each([408, 429, 500, 502, 503, 504])(
    'marks HTTP %i as worth retrying',
    async status => {
      respond(status, { 'Retry-After': '3' });

      const error = await failure(
        fetchPageHtml(URL, new AbortController().signal)
      );

      expect(error).toBeInstanceOf(PageLoadError);
      expect(error).toMatchObject({ retryable: true, retryAfter: '3' });
    }
  );

  it.each([403, 404, 410])('marks HTTP %i as final', async status => {
    respond(status);

    const error = await failure(
      fetchPageHtml(URL, new AbortController().signal)
    );

    expect(error).toMatchObject({ retryable: false });
  });

  it('marks a dropped connection as worth retrying', async () => {
    globalThis.fetch = jest.fn().mockRejectedValue(new TypeError('Failed'));

    const error = await failure(
      fetchPageHtml(URL, new AbortController().signal)
    );

    expect(error).toMatchObject({ name: 'PageLoadError', retryable: true });
  });

  it('abandons a request that hangs, and marks it worth retrying', async () => {
    jest.useFakeTimers();
    globalThis.fetch = jest.fn(
      (_url: string, init: RequestInit) =>
        new Promise((_resolve, reject) => {
          init.signal?.addEventListener('abort', () =>
            reject(new DOMException('Aborted', 'AbortError'))
          );
        })
    ) as jest.Mock;

    const pending = failure(fetchPageHtml(URL, new AbortController().signal));
    jest.advanceTimersByTime(PAGE_TIMEOUT_MS);

    expect(await pending).toMatchObject({
      name: 'PageLoadError',
      retryable: true,
    });
  });

  it("passes the caller's cancellation through untouched", async () => {
    const caller = new AbortController();
    globalThis.fetch = jest.fn(
      (_url: string, init: RequestInit) =>
        new Promise((_resolve, reject) => {
          init.signal?.addEventListener('abort', () =>
            reject(new DOMException('Aborted', 'AbortError'))
          );
        })
    ) as jest.Mock;

    const pending = failure(fetchPageHtml(URL, caller.signal));
    caller.abort();

    expect(await pending).toMatchObject({ name: 'AbortError' });
  });
});

describe('retryDelayMs', () => {
  const now = Date.parse('2026-10-17T06:00:00Z');

  it('honours Retry-After in seconds or as an HTTP date', () => {
    expect(retryDelayMs('5', 0, now)).toBe(5000);
    expect(retryDelayMs('Sat, 17 Oct 2026 06:00:07 GMT', 0, now)).toBe(7000);
  });

  it('backs off exponentially without a usable header', () => {
    expect(retryDelayMs(null, 0, now)).toBe(2000);
    expect(retryDelayMs('soon', 1, now)).toBe(4000);
    expect(retryDelayMs(null, 2, now)).toBe(8000);
  });

  it('never waits less than 1s or more than 10s', () => {
    expect(retryDelayMs('0', 0, now)).toBe(1000);
    expect(retryDelayMs('3600', 0, now)).toBe(10_000);
    expect(retryDelayMs('Sat, 17 Oct 2026 05:00:00 GMT', 0, now)).toBe(1000);
  });
});
