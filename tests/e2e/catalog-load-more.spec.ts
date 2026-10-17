/**
 * Load More / infinite scroll fetch the archive's own next page and append
 * its cards: appended cards are exactly what that page renders, in order,
 * with no repeats — also after a sort or filter navigation.
 */

import {
  test,
  expect,
  type Page,
  type APIRequestContext,
} from '@playwright/test';

const CARDS = '.wp-block-woocommerce-product-template > .wc-block-product';
const CONTROL = '[data-wp-interactive="aggressive-apparel/load-more"]';

interface Seed {
  nextUrl: string;
  totalProducts: number;
}

/** Product IDs of the cards currently in the grid, in order. */
async function gridIds(page: Page): Promise<string[]> {
  return page
    .locator(CARDS)
    .evaluateAll(cards =>
      cards.map(
        card => [...card.classList].find(name => /^post-\d+$/.test(name)) ?? ''
      )
    );
}

/** Product IDs a page renders on the server, in order. */
async function serverIds(
  request: APIRequestContext,
  url: string
): Promise<string[]> {
  const html = await (await request.get(url)).text();
  // The grid's own markup: the class name also appears in the head's CSS.
  const start = html.search(
    /<ul[^>]*\bwp-block-woocommerce-product-template\b/
  );
  const end = html.indexOf('class="aa-load-more ', start);
  const grid = html.slice(start, end > start ? end : undefined);
  const ids = [...grid.matchAll(/<li[^>]*class="[^"]*\b(post-\d+)\b/g)].map(
    match => match[1]
  );
  return [...new Set(ids)];
}

/** The Load More seed the page rendered (or the router last installed). */
async function seed(page: Page): Promise<Seed> {
  const raw = await page.locator(CONTROL).getAttribute('data-wp-context');
  return JSON.parse(raw ?? '{}') as Seed;
}

function expectUnique(ids: string[]): void {
  expect(ids.every(Boolean)).toBe(true);
  expect(new Set(ids).size).toBe(ids.length);
}

test.describe('Load More', () => {
  // Button mode (the store's reduced-motion fallback): each load is one
  // explicit, observable step.
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
  });

  test('appends exactly the next server page', async ({ page, request }) => {
    await page.goto('/shop/');
    const { nextUrl } = await seed(page);
    expect(
      nextUrl,
      'The catalog fixtures guarantee a second page.'
    ).toBeTruthy();

    const firstPage = await gridIds(page);
    const nextUrlAbsolute = new URL(nextUrl, page.url()).href;
    const expected = await serverIds(request, nextUrlAbsolute);
    expect(expected.length).toBeGreaterThan(0);

    const fetched = page.waitForResponse(
      response => response.url() === nextUrlAbsolute && response.ok()
    );
    await page.locator('.aa-load-more__btn').click();
    await fetched;

    await expect
      .poll(() => gridIds(page))
      .toEqual([
        ...firstPage,
        ...expected.filter(id => !firstPage.includes(id)),
      ]);
  });

  test('a rate-limited page is retried once, after Retry-After', async ({
    page,
    request,
  }) => {
    await page.goto('/shop/');
    const nextUrl = new URL((await seed(page)).nextUrl, page.url()).href;
    const expected = await serverIds(request, nextUrl);
    const firstPage = await gridIds(page);

    const requests: number[] = [];
    await page.route(nextUrl, async route => {
      requests.push(Date.now());
      if (requests.length === 1) {
        await route.fulfill({
          status: 429,
          headers: { 'Retry-After': '1' },
          body: 'Too Many Requests',
        });
        return;
      }
      await route.continue();
    });

    await page.locator('.aa-load-more__btn').click();
    // Clicks while it waits must not add requests.
    await page.locator('.aa-load-more__btn').click({ force: true });

    await expect
      .poll(() => gridIds(page), { timeout: 15_000 })
      .toEqual([
        ...firstPage,
        ...expected.filter(id => !firstPage.includes(id)),
      ]);
    expect(requests).toHaveLength(2);
    expect(requests[1] - requests[0]).toBeGreaterThanOrEqual(900);
  });

  test('a challenge page or dropped connection is retried, never the end', async ({
    page,
    request,
  }) => {
    await page.goto('/shop/');
    const nextUrl = new URL((await seed(page)).nextUrl, page.url()).href;
    const expected = await serverIds(request, nextUrl);
    const firstPage = await gridIds(page);

    let calls = 0;
    await page.route(nextUrl, async route => {
      calls += 1;
      if (calls === 1) {
        // A firewall challenge served with 200: not a catalog page.
        await route.fulfill({
          status: 200,
          body: '<html><body>Checking your browser…</body></html>',
        });
        return;
      }
      if (calls === 2) {
        await route.abort('connectionreset');
        return;
      }
      await route.continue();
    });

    await page.locator('.aa-load-more__btn').click();

    await expect
      .poll(() => gridIds(page), { timeout: 20_000 })
      .toEqual([
        ...firstPage,
        ...expected.filter(id => !firstPage.includes(id)),
      ]);
    expect(calls).toBe(3);
    await expect(page.locator('.aa-load-more__error')).toBeHidden();
  });

  test('a page that cannot load offers it as a link', async ({ page }) => {
    await page.goto('/shop/');
    const nextUrl = new URL((await seed(page)).nextUrl, page.url()).href;
    const firstPage = await gridIds(page);

    await page.route(nextUrl, route =>
      route.fulfill({ status: 404, body: 'Not Found' })
    );
    await page.locator('.aa-load-more__btn').click();

    const fallback = page.locator('.aa-load-more__fallback');
    await expect(fallback).toBeVisible();
    expect(
      new URL((await fallback.getAttribute('href'))!, page.url()).href
    ).toBe(nextUrl);
    await expect(page.locator('.aa-load-more__btn')).toBeVisible();
    expect(await gridIds(page)).toEqual(firstPage);

    // The link leads to the rest of the catalog.
    await page.unroute(nextUrl);
    await fallback.click();
    await page.waitForURL(nextUrl);
    expect((await gridIds(page)).length).toBeGreaterThan(0);
  });

  test('after a sort, continues the sorted catalog', async ({
    page,
    request,
  }) => {
    await page.goto('/shop/');
    await page.locator('select[name="orderby"]').first().selectOption('price');
    await page.waitForURL(/orderby=price/);

    // The router swapped the collection for the sorted page's own render.
    await expect
      .poll(() => gridIds(page))
      .toEqual(await serverIds(request, page.url()));

    const { nextUrl } = await seed(page);
    expect(nextUrl).toContain('orderby=price');
    const sortedFirst = await gridIds(page);
    const expected = await serverIds(
      request,
      new URL(nextUrl, page.url()).href
    );

    await page.locator('.aa-load-more__btn').click();
    await expect
      .poll(() => gridIds(page))
      .toEqual([
        ...sortedFirst,
        ...expected.filter(id => !sortedFirst.includes(id)),
      ]);
  });

  test('a navigation drops the cards appended to the previous page', async ({
    page,
    request,
  }) => {
    await page.goto('/shop/');
    const initial = (await gridIds(page)).length;
    await page.locator('.aa-load-more__btn').click();
    await expect
      .poll(async () => (await gridIds(page)).length)
      .toBeGreaterThan(initial);

    await page
      .locator('select[name="orderby"]')
      .first()
      .selectOption('popularity');
    await page.waitForURL(/orderby=popularity/);

    await expect
      .poll(() => gridIds(page))
      .toEqual(await serverIds(request, page.url()));
  });
});

test.describe('infinite scroll', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('reaches Showing N of N with every product exactly once', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const status = page.locator('.aa-load-more__count');
    const sentinel = page.locator('.aa-load-more__sentinel');
    await expect(
      sentinel,
      'Catalog fixtures should enable infinite_scroll (see catalog-fixtures.ts).'
    ).toHaveCount(1);

    const loaded = async (): Promise<[number, number]> => {
      const match = ((await status.textContent()) ?? '').match(
        /Showing\s+(\d+)\s+of\s+(\d+)/i
      );
      return match ? [Number(match[1]), Number(match[2])] : [0, 0];
    };

    const [, totalProducts] = await loaded();
    expect(totalProducts).toBeGreaterThan((await gridIds(page)).length);

    for (let i = 0; i < 12; i++) {
      const [before, total] = await loaded();
      if (before >= total) break;

      // Force a real non-intersecting → intersecting transition. Repeated
      // scrollIntoViewIfNeeded() calls can be no-ops while the sentinel stays
      // inside IntersectionObserver's root margin.
      await page.evaluate(
        () =>
          new Promise<void>(resolve => {
            window.scrollTo({ top: 0, behavior: 'auto' });
            requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
          })
      );
      // The DOM primitive, not Playwright's actionability-guarded helper: a
      // chained final load may hide the sentinel between the read above and
      // this scroll, which is a valid terminal state.
      await sentinel.evaluate(element => {
        element.scrollIntoView({ behavior: 'auto', block: 'center' });
      });

      // Poll the count rather than awaiting a response: background prefetches
      // fetch the same next-page URL without appending.
      await expect
        .poll(async () => (await loaded())[0], {
          timeout: 15_000,
          message: `Expected infinite scroll to advance beyond ${before} products`,
        })
        .toBeGreaterThan(before);
    }

    await expect
      .poll(
        async () => {
          const [count, total] = await loaded();
          return count === total;
        },
        { timeout: 30_000 }
      )
      .toBe(true);

    const ids = await gridIds(page);
    expectUnique(ids);
    expect(ids.length).toBe(totalProducts);
  });

  test('keeps the Load More button hidden', async ({ page }) => {
    await page.goto('/shop/');

    await expect(page.locator('.aa-load-more__sentinel')).toHaveCount(1);
    await expect(page.locator('.aa-load-more__btn')).toBeHidden();
  });
});
