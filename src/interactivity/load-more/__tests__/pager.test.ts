/**
 * @jest-environment jsdom
 */

import type { CatalogPage } from '../catalog-page';
import {
  CatalogPager,
  type PagerClock,
  type PagerHooks,
  type PagerState,
} from '../pager';
import { MAX_RETRIES, PageLoadError } from '../request';

const PAGE_2 = 'https://store.test/shop/page/2/';

function page(): CatalogPage {
  return {
    cards: [],
    css: [],
    scopedClasses: [],
    seed: {
      mode: 'load_more',
      perPage: 12,
      currentPage: 2,
      totalProducts: 24,
      loadedCount: 12,
      nextUrl: '',
      allLoaded: true,
    },
  };
}

/** A pager with recorded hooks and a manual clock. */
function setup() {
  const state: PagerState = {
    nextUrl: PAGE_2,
    allLoaded: false,
    isLoading: false,
    loadFailed: false,
  };
  const timers: Array<{ delayMs: number; run: () => void }> = [];
  const online: Array<() => void> = [];
  let connected = true;
  const clock: PagerClock = {
    now: () => 0,
    after: (delayMs, run) => timers.push({ delayMs, run }),
    online: () => connected,
    whenOnline: run => online.push(run),
  };
  const hooks = {
    request: jest.fn<
      ReturnType<PagerHooks['request']>,
      Parameters<PagerHooks['request']>
    >(),
    append: jest.fn((_url: string, _page: CatalogPage) => {
      state.isLoading = false;
    }),
    started: jest.fn(),
    failed: jest.fn(),
    prefetched: jest.fn(),
  };
  const pager = new CatalogPager(state, hooks, clock);

  return {
    state,
    hooks,
    pager,
    timers,
    online,
    setOnline: (value: boolean) => {
      connected = value;
    },
  };
}

/** Let pending promise callbacks run. */
const settle = () => new Promise(resolve => setTimeout(resolve, 0));

const refused = (status = 429, retryAfter: string | null = null) =>
  new PageLoadError(`HTTP ${status}`, status !== 404, retryAfter);

describe('CatalogPager', () => {
  it('loads the next page once, ignoring loads while one is busy', async () => {
    const { hooks, pager } = setup();
    hooks.request.mockResolvedValue(page());

    pager.load();
    pager.load();
    await settle();

    expect(hooks.request).toHaveBeenCalledTimes(1);
    expect(hooks.append).toHaveBeenCalledWith(PAGE_2, expect.anything());
  });

  it('uses a pending prefetch instead of requesting again', async () => {
    const { hooks, pager } = setup();
    hooks.request.mockResolvedValue(page());

    pager.prefetch();
    pager.load();
    await settle();

    expect(hooks.request).toHaveBeenCalledTimes(1);
    expect(hooks.request.mock.calls[0][2]).toBe('low');
    expect(hooks.append).toHaveBeenCalledTimes(1);
  });

  it('does not re-request at once after a refused prefetch', async () => {
    const { hooks, pager, timers } = setup();
    hooks.request.mockRejectedValueOnce(refused(429, '3'));
    hooks.request.mockResolvedValue(page());

    pager.prefetch();
    pager.load();
    await settle();

    expect(hooks.request).toHaveBeenCalledTimes(1);
    expect(timers).toEqual([{ delayMs: 3000, run: expect.any(Function) }]);

    timers[0].run();
    await settle();
    expect(hooks.request).toHaveBeenCalledTimes(2);
    expect(hooks.append).toHaveBeenCalledTimes(1);
  });

  it('re-requests at once when the prefetch was only cancelled', async () => {
    const { hooks, pager } = setup();
    hooks.request.mockRejectedValueOnce(
      new DOMException('Aborted', 'AbortError')
    );
    hooks.request.mockResolvedValue(page());

    pager.prefetch();
    pager.load();
    await settle();

    expect(hooks.request).toHaveBeenCalledTimes(2);
    expect(hooks.append).toHaveBeenCalledTimes(1);
  });

  it(`retries ${MAX_RETRIES} times, staying busy, then fails`, async () => {
    const { state, hooks, pager, timers } = setup();
    hooks.request.mockRejectedValue(refused(503));

    pager.load();
    for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
      await settle();
      expect(state.isLoading).toBe(true);
      pager.load(); // a click while waiting adds nothing
      timers.shift()?.run();
    }
    await settle();

    expect(hooks.request).toHaveBeenCalledTimes(MAX_RETRIES + 1);
    expect(state).toMatchObject({ isLoading: false, loadFailed: true });
    expect(hooks.failed).toHaveBeenCalledTimes(1);
  });

  it('fails at once on a refusal retrying cannot fix', async () => {
    const { state, hooks, pager, timers } = setup();
    hooks.request.mockRejectedValue(refused(404));

    pager.load();
    await settle();

    expect(timers).toHaveLength(0);
    expect(state.loadFailed).toBe(true);
  });

  it('waits for the connection to return when offline', async () => {
    const { hooks, pager, timers, online, setOnline } = setup();
    hooks.request.mockRejectedValueOnce(
      new PageLoadError('Network error', true)
    );
    hooks.request.mockResolvedValue(page());
    setOnline(false);

    pager.load();
    await settle();
    expect(timers).toHaveLength(0);
    expect(online).toHaveLength(1);

    setOnline(true);
    online[0]();
    await settle();
    expect(hooks.append).toHaveBeenCalledTimes(1);
  });

  it('ignores a page that arrives after a navigation', async () => {
    const { hooks, pager } = setup();
    let finish: (value: CatalogPage) => void = () => undefined;
    hooks.request.mockReturnValueOnce(
      new Promise(resolve => {
        finish = resolve;
      })
    );

    pager.load();
    pager.reset();
    finish(page());
    await settle();

    expect(hooks.append).not.toHaveBeenCalled();
  });

  it('drops a retry scheduled before a navigation', async () => {
    const { hooks, pager, timers } = setup();
    hooks.request.mockRejectedValue(refused(429));

    pager.load();
    await settle();
    pager.reset();
    timers[0].run();
    await settle();

    expect(hooks.request).toHaveBeenCalledTimes(1);
  });

  it('does nothing once the catalog is fully loaded', () => {
    const { state, hooks, pager } = setup();
    state.allLoaded = true;

    pager.load();
    pager.prefetch();

    expect(hooks.request).not.toHaveBeenCalled();
  });
});
