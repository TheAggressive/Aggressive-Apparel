/**
 * Crawlable catalog pagination: the native Query Pagination links stay in the
 * server HTML so search engines and shoppers without JavaScript can reach
 * every product, while Load More / infinite scroll enhances it for everyone
 * else.
 *
 * Every URL is taken from rendered `href`s, never built here, so the specs hold
 * for inherited collections (`/page/N/`) and explicit ones (`?query-ID-page=N`)
 * alike — a saved Site Editor template may use either.
 */

import { test, expect, type Page } from '@playwright/test';
import { wpCli } from './wp-cli';

const CARD = '.wp-block-woocommerce-product-template > .wc-block-product';
const NATIVE_NAV = '.aa-load-more__pagination';
const NEXT_LINK = `${NATIVE_NAV} a.wp-block-query-pagination-next`;
const FEATURE_OPTION = 'aggressive_apparel_wc_features';
const ANONYMOUS = { cookies: [], origins: [] };

interface CrawledPage {
  url: string;
  ids: string[];
}

/** Product IDs (`post-123`) of the cards currently in the grid, in order. */
async function cardIds(page: Page): Promise<string[]> {
  return page
    .locator(CARD)
    .evaluateAll(cards =>
      cards.map(
        card => [...card.classList].find(name => /^post-\d+$/.test(name)) ?? ''
      )
    );
}

/** Card IDs from server HTML, without running its scripts. */
function idsFromHtml(html: string): string[] {
  const start = html.indexOf('wp-block-woocommerce-product-template');
  const end = html.indexOf('class="aa-load-more ', start);
  const grid = html.slice(start, end > start ? end : undefined);
  const ids = [...grid.matchAll(/<li[^>]*class="[^"]*\b(post-\d+)\b/g)].map(
    match => match[1]
  );
  return [...new Set(ids)];
}

/** Total from the server-rendered "Showing X of N products" line. */
async function statusTotal(page: Page): Promise<number> {
  const text = (await page.locator('.aa-load-more__count').textContent()) ?? '';
  const match = text.match(/Showing\s+\d+\s+of\s+(\d+)/i);
  expect(match, `Unexpected status line "${text}"`).not.toBeNull();
  return Number(match![1]);
}

/** Absolute URL of the native "next page" link, or null on the last page. */
async function nextHref(page: Page): Promise<string | null> {
  const next = page.locator(NEXT_LINK);
  if ((await next.count()) === 0) return null;
  const href = await next.getAttribute('href');
  expect(href, 'Next link must carry a real href').toBeTruthy();
  return new URL(href!, page.url()).href;
}

/** Follow native next links from `start` until the last page. */
async function crawl(page: Page, start: string): Promise<CrawledPage[]> {
  const pages: CrawledPage[] = [];
  let url: string | null = start;

  while (url) {
    expect(pages.length, 'Pagination should terminate').toBeLessThan(50);
    const response = await page.goto(url);
    expect(response?.status()).toBe(200);
    pages.push({ url: page.url(), ids: await cardIds(page) });
    url = await nextHref(page);
  }

  return pages;
}

/** The product category with the most products and its archive URL. */
function largestCategory(): { url: string; count: number } {
  return JSON.parse(
    wpCli([
      'eval',
      `$terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 1));
$term  = is_array($terms) && $terms ? $terms[0] : null;
echo wp_json_encode($term ? array('url' => get_term_link($term), 'count' => (int) $term->count) : array('url' => '', 'count' => 0));`,
    ])
  ) as { url: string; count: number };
}

function setLoadMoreEnabled(enabled: boolean): void {
  wpCli([
    'eval',
    `$features = get_option('${FEATURE_OPTION}', array());
if (!is_array($features)) {
	$features = array();
}
$features['load_more'] = ${enabled ? 1 : 0};
update_option('${FEATURE_OPTION}', $features, false);`,
  ]);
}

test.describe('catalog pagination without JavaScript', () => {
  test.use({ javaScriptEnabled: false, storageState: ANONYMOUS });

  test('native links reach every product exactly once', async ({ page }) => {
    await page.goto('/shop/');

    await expect(page.locator(NATIVE_NAV)).toBeVisible();
    await expect(page.locator(`${NATIVE_NAV} nav`)).toHaveAttribute(
      'aria-label',
      /.+/
    );
    // The button needs the store; without JS it must not be a dead control.
    await expect(page.locator('.aa-load-more__btn')).toBeHidden();

    const total = await statusTotal(page);
    const pages = await crawl(page, page.url());

    expect(pages.length).toBeGreaterThan(1);
    const ids = pages.flatMap(entry => entry.ids);
    expect(ids.every(Boolean)).toBe(true);
    expect(new Set(ids).size, 'No product repeats across pages').toBe(
      ids.length
    );
    expect(ids.length, 'No product is skipped').toBe(total);
  });

  test('later pages expose real product links that resolve', async ({
    page,
    request,
  }) => {
    await page.goto('/shop/');
    const second = await nextHref(page);
    expect(second).toBeTruthy();

    await page.goto(second!);
    await expect(
      page.locator(`${NATIVE_NAV} [aria-current="page"]`)
    ).toHaveText('2');

    const links = page.locator(`${CARD} .wp-block-post-title a[href]`);
    expect(await links.count()).toBe(await page.locator(CARD).count());

    const first = links.first();
    const name = ((await first.textContent()) ?? '').trim();
    const href = new URL((await first.getAttribute('href'))!, page.url());
    expect(name).not.toBe('');
    expect(href.origin).toBe(new URL(page.url()).origin);

    const product = await request.get(href.href);
    expect(product.status()).toBe(200);
    const html = await product.text();
    expect(html).toMatch(
      new RegExp(`<h1[^>]*>\\s*${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)
    );
  });

  test('sorted category pagination keeps the sort and covers the category', async ({
    page,
  }) => {
    const category = largestCategory();
    expect(category.url, 'Fixtures need a product category').not.toBe('');

    const start = new URL(category.url);
    start.searchParams.set('orderby', 'price');
    await page.goto(start.href);
    const total = await statusTotal(page);
    const pages = await crawl(page, start.href);

    expect(
      pages.length,
      'The largest category should span several pages'
    ).toBeGreaterThan(1);
    for (const entry of pages) {
      expect(new URL(entry.url).searchParams.get('orderby')).toBe('price');
    }
    const ids = pages.flatMap(entry => entry.ids);
    expect(new Set(ids).size).toBe(ids.length);
    expect(ids.length).toBe(total);
  });
});

test.describe('catalog pagination with JavaScript', () => {
  test.use({ storageState: ANONYMOUS });

  test('Load More replaces the native links without removing them', async ({
    page,
  }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/shop/');

    await expect(page.locator(NATIVE_NAV)).toBeHidden();
    await expect(page.locator(NEXT_LINK)).toHaveCount(1);
    await expect(page.locator('.aa-load-more__btn')).toBeVisible();
  });

  test('Load More on a deep-linked page appends exactly the next server page', async ({
    page,
    request,
  }) => {
    await page.goto('/shop/');
    const second = await nextHref(page);
    expect(second).toBeTruthy();

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(second!);
    const third = await nextHref(page);
    expect(third, 'Fixtures should provide at least three pages').toBeTruthy();

    const seed = JSON.parse(
      (await page
        .locator('[data-wp-interactive="aggressive-apparel/load-more"]')
        .getAttribute('data-wp-context')) ?? '{}'
    ) as { currentPage?: number };
    expect(seed.currentPage).toBe(2);

    const expected = idsFromHtml(await (await request.get(third!)).text());
    expect(expected.length).toBeGreaterThan(0);

    const before = await cardIds(page);
    const button = page.locator('.aa-load-more__btn');
    await expect(button).toBeVisible();
    // Keyboard activation: the control is a real, focusable button.
    await button.focus();
    await page.keyboard.press('Enter');

    await expect
      .poll(async () => (await cardIds(page)).length, { timeout: 15_000 })
      .toBeGreaterThan(before.length);
    await expect(button).not.toHaveAttribute('aria-busy', 'true');

    const after = await cardIds(page);
    expect(after.slice(0, before.length)).toEqual(before);
    expect(new Set(after.slice(before.length))).toEqual(new Set(expected));
    expect(new Set(after).size).toBe(after.length);
  });

  test('sorted category keeps its server page and Load More continues it', async ({
    page,
    request,
  }) => {
    const category = largestCategory();
    const start = new URL(category.url);
    start.searchParams.set('orderby', 'price');

    const serverHtml = await (await request.get(start.href)).text();
    const serverFirst = idsFromHtml(serverHtml);

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(start.href, { waitUntil: 'networkidle' });

    // Hydration must not swap the native archive for a re-sorted AJAX grid.
    const before = await cardIds(page);
    expect(before).toEqual(serverFirst);

    const second = await nextHref(page);
    expect(second).toBeTruthy();
    expect(new URL(second!).searchParams.get('orderby')).toBe('price');
    const serverSecond = idsFromHtml(await (await request.get(second!)).text());

    const continuation = page.waitForResponse(response => {
      const url = new URL(response.url());
      return (
        url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
        url.searchParams.get('orderby') === 'price' &&
        url.searchParams.has('cursor')
      );
    });
    await page.locator('.aa-load-more__btn').click();
    expect((await continuation).status()).toBe(200);

    await expect
      .poll(async () => (await cardIds(page)).length, { timeout: 15_000 })
      .toBeGreaterThan(before.length);
    const after = await cardIds(page);
    expect(new Set(after).size).toBe(after.length);
    // Same products as server pages 1 + 2: nothing duplicated or skipped.
    expect(new Set(after)).toEqual(new Set([...serverFirst, ...serverSecond]));
    await expect(page.locator('.aa-load-more__announcer')).toHaveText(
      /\d+ more products loaded/
    );
  });

  test('a deep-linked category page keeps its server-rendered products', async ({
    page,
    request,
  }) => {
    const category = largestCategory();
    const first = await (await request.get(category.url)).text();
    const href = first.match(
      /href="([^"]+)" class="wp-block-query-pagination-next"/
    )?.[1];
    expect(href, 'The largest category should span several pages').toBeTruthy();
    const second = new URL(href!.replace(/&amp;/g, '&'), category.url).href;
    const server = idsFromHtml(await (await request.get(second)).text());

    // Button mode: infinite scroll could legitimately append the next page.
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(second, { waitUntil: 'networkidle' });
    expect(await cardIds(page)).toEqual(server);
  });

  test('the theme adds no competing canonical or robots tags', async ({
    page,
  }) => {
    await page.goto('/shop/');
    const second = await nextHref(page);
    expect(second).toBeTruthy();

    for (const url of [page.url(), second!]) {
      await page.goto(url);
      const canonical = page.locator('link[rel="canonical"]');
      expect(await canonical.count()).toBeLessThanOrEqual(1);
      expect(
        await page.locator('meta[name="robots"]').count()
      ).toBeLessThanOrEqual(1);

      // Rank Math owns canonicals; when it is active, it must emit exactly one.
      if (/Rank Math/i.test(await page.content())) {
        await expect(canonical).toHaveCount(1);
      }
    }
  });
});

test.describe('catalog pagination with Load More disabled', () => {
  test.use({ storageState: ANONYMOUS });

  test.beforeAll(() => setLoadMoreEnabled(false));
  test.afterAll(() => setLoadMoreEnabled(true));

  test('native WooCommerce pagination works unchanged', async ({ page }) => {
    await page.goto('/shop/');

    await expect(page.locator('.aa-load-more')).toHaveCount(0);
    const next = page.locator('a.wp-block-query-pagination-next');
    await expect(next).toBeVisible();

    const first = await cardIds(page);
    const before = page.url();
    await Promise.all([
      page.waitForURL(url => url.href !== before),
      next.click(),
    ]);

    await expect
      .poll(async () => (await cardIds(page)).join(','))
      .not.toBe(first.join(','));
    const second = await cardIds(page);
    expect(second.filter(id => first.includes(id))).toEqual([]);
  });
});
