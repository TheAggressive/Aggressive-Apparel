/**
 * Catalog cursor pagination e2e coverage for Load More / infinite scroll.
 */

import { test, expect } from '@playwright/test';

test.describe('catalog cursor pagination', () => {
  test('load more sends a cursor and appends unique products', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    const initialCount = await cards.count();
    test.skip(initialCount === 0, 'The E2E catalogue has no products.');

    const responsePromise = page.waitForResponse(response => {
      const url = new URL(response.url());
      return (
        url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
        url.searchParams.has('cursor') &&
        response.status() === 200
      );
    });

    const button = page.locator('.aa-load-more__btn:visible');
    if (await button.count()) {
      await button.click();
    } else {
      await page.locator('.aa-load-more__sentinel').scrollIntoViewIfNeeded();
    }

    const response = await responsePromise;
    expect(new URL(response.url()).searchParams.get('cursor')).toBeTruthy();

    const body = await response.json();
    expect(body).toHaveProperty('next_cursor');
    expect(typeof body.next_cursor).toBe('string');

    const returnedIds = (
      typeof body.html === 'string'
        ? body.html.match(/\bpost-\d+\b/g) || []
        : []
    ).filter(
      (id: string, index: number, all: string[]) => all.indexOf(id) === index
    );

    await expect
      .poll(() => cards.count(), { timeout: 15_000 })
      .toBeGreaterThan(initialCount);

    const appended = (await cards.count()) - initialCount;
    const remaining =
      typeof body.total_products === 'number'
        ? body.total_products - initialCount
        : appended;
    // Regression: wrong orderby on the cursor used to append a single card
    // when many products remained.
    if (remaining > 1) {
      expect(appended).toBeGreaterThan(1);
      expect(returnedIds.length).toBeGreaterThan(1);
    } else {
      expect(appended).toBeGreaterThan(0);
    }

    const productIds = await cards.evaluateAll(elements =>
      elements
        .filter(el => !el.classList.contains('aa-product-grid__spacer'))
        .map(
          element =>
            [...element.classList].find(className =>
              /^post-\d+$/.test(className)
            ) || ''
        )
    );
    expect(productIds.every(Boolean)).toBe(true);
    expect(new Set(productIds).size).toBe(productIds.length);
  });

  test('sorting resets and subsequent load more uses a fresh cursor', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const select = page.locator('select[name="orderby"]').first();
    test.skip(
      (await select.count()) === 0,
      'Catalog sorting UI is unavailable.'
    );

    await Promise.all([
      page.waitForURL(/orderby=price/, { timeout: 15_000 }).catch(() => null),
      select.selectOption('price'),
    ]);
    await page.waitForLoadState('domcontentloaded');

    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    await expect.poll(() => cards.count()).toBeGreaterThan(0);
    const sortedCount = await cards.count();

    const nextPage = page.waitForResponse(response => {
      const url = new URL(response.url());
      return (
        url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
        url.searchParams.get('orderby') === 'price' &&
        url.searchParams.has('cursor') &&
        response.status() === 200
      );
    });

    const button = page.locator('.aa-load-more__btn:visible');
    if (await button.count()) {
      await button.click();
    } else {
      await page.locator('.aa-load-more__sentinel').scrollIntoViewIfNeeded();
    }

    const response = await nextPage;
    expect(new URL(response.url()).searchParams.get('cursor')).toBeTruthy();

    await expect
      .poll(() => cards.count(), { timeout: 15_000 })
      .toBeGreaterThan(sortedCount);

    const productIds = await cards.evaluateAll(elements =>
      elements
        .filter(el => !el.classList.contains('aa-product-grid__spacer'))
        .map(element =>
          [...element.classList].find(className => /^post-\d+$/.test(className))
        )
    );
    expect(productIds.every(Boolean)).toBe(true);
    expect(new Set(productIds).size).toBe(productIds.length);
  });
});

test.describe('anonymous catalog cursor pagination', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('infinite scroll requests include a cursor', async ({ page }) => {
    const responsePromise = page.waitForResponse(response => {
      const url = new URL(response.url());
      return (
        url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
        url.searchParams.has('cursor') &&
        response.status() === 200
      );
    });

    await page.goto('/shop/');

    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    const initialCount = await cards.count();
    test.skip(initialCount === 0, 'The public E2E catalogue has no products.');

    await page.locator('.aa-load-more__sentinel').scrollIntoViewIfNeeded();
    const response = await responsePromise;
    const body = await response.json();
    const returned =
      typeof body.html === 'string'
        ? (body.html.match(/\bpost-\d+\b/g) || []).length
        : 0;

    await expect.poll(() => cards.count()).toBeGreaterThan(initialCount);

    const appended = (await cards.count()) - initialCount;
    const remaining =
      typeof body.total_products === 'number'
        ? body.total_products - initialCount
        : appended;
    // Regression: mismatched orderby/cursor used to append ~1 product after dedupe.
    if (remaining > 1) {
      expect(appended).toBeGreaterThan(1);
      expect(returned).toBeGreaterThan(1);
    } else {
      expect(appended).toBeGreaterThan(0);
    }

    const productIds = await cards.evaluateAll(elements =>
      elements
        .filter(el => !el.classList.contains('aa-product-grid__spacer'))
        .map(element =>
          [...element.classList].find(className => /^post-\d+$/.test(className))
        )
    );
    expect(productIds.every(Boolean)).toBe(true);
    expect(new Set(productIds).size).toBe(productIds.length);
  });

  test('infinite scroll reaches Showing N of N without sparse gaps', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const status = page.locator('.aa-load-more__count');
    const sentinel = page.locator('.aa-load-more__sentinel');
    await expect(
      sentinel,
      'Catalog fixtures should enable infinite_scroll (see catalog-fixtures.ts).'
    ).toHaveCount(1);
    await expect(status).toHaveCount(1);

    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    const initialCount = await cards.count();
    expect(initialCount).toBeGreaterThan(0);

    const initialStatus = ((await status.textContent()) || '').trim();
    expect(initialStatus).toMatch(/Showing\s+\d+\s+of\s+\d+/i);
    const totals = initialStatus.match(/Showing\s+(\d+)\s+of\s+(\d+)/i);
    expect(totals).not.toBeNull();
    const totalProducts = Number(totals![2]);
    expect(totalProducts).toBeGreaterThan(initialCount);

    // Keep triggering the sentinel until the catalog is exhausted.
    for (let i = 0; i < 12; i++) {
      const text = ((await status.textContent()) || '').trim();
      const match = text.match(/Showing\s+(\d+)\s+of\s+(\d+)/i);
      if (match && Number(match[1]) >= Number(match[2])) {
        break;
      }

      const beforeLoaded = match ? Number(match[1]) : initialCount;
      const next = page.waitForResponse(
        response => {
          const url = new URL(response.url());
          return (
            url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
            url.searchParams.has('cursor') &&
            response.status() === 200
          );
        },
        { timeout: 15_000 }
      );

      await sentinel.scrollIntoViewIfNeeded();
      await next;
      await expect
        .poll(async () => {
          const current = ((await status.textContent()) || '').trim();
          const m = current.match(/Showing\s+(\d+)\s+of\s+(\d+)/i);
          return m ? Number(m[1]) : 0;
        })
        .toBeGreaterThan(beforeLoaded);
    }

    await expect
      .poll(
        async () => {
          const text = (await status.textContent()) || '';
          const match = text.match(/Showing\s+(\d+)\s+of\s+(\d+)/i);
          if (!match) {
            return false;
          }
          return Number(match[1]) === Number(match[2]);
        },
        { timeout: 30_000 }
      )
      .toBe(true);

    const productIds = await cards.evaluateAll(elements =>
      elements
        .filter(el => !el.classList.contains('aa-product-grid__spacer'))
        .map(
          element =>
            [...element.classList].find(className =>
              /^post-\d+$/.test(className)
            ) || ''
        )
    );
    expect(productIds.every(Boolean)).toBe(true);
    expect(new Set(productIds).size).toBe(productIds.length);
    expect(productIds.length).toBe(totalProducts);
  });

  test('infinite_scroll mode keeps the Load More button hidden', async ({
    page,
  }) => {
    await page.goto('/shop/');

    const sentinel = page.locator('.aa-load-more__sentinel');
    await expect(
      sentinel,
      'Catalog fixtures should enable infinite_scroll (see catalog-fixtures.ts).'
    ).toHaveCount(1);

    const button = page.locator('.aa-load-more__btn');
    await expect(button).toBeHidden();
  });
});
