import { test, expect, type Locator } from '@playwright/test';

const titleStyle = (locator: Locator) =>
  locator.evaluate(element => {
    const style = getComputedStyle(element);
    return {
      color: style.color,
      fontFamily: style.fontFamily,
      fontSize: style.fontSize,
      fontWeight: style.fontWeight,
      lineHeight: style.lineHeight,
      textAlign: style.textAlign,
    };
  });

for (const colorScheme of ['light', 'dark'] as const) {
  test(`load-more cards retain computed title styles in ${colorScheme} mode`, async ({
    page,
  }) => {
    await page.emulateMedia({ colorScheme });
    await page.goto('/shop/');

    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    const initialCount = await cards.count();
    test.skip(initialCount === 0, 'The E2E catalogue has no products.');

    const initialTitle = cards.first().locator('.wp-block-post-title a');
    const expected = await titleStyle(initialTitle);

    const button = page.locator('.aa-load-more__btn:visible');
    if (await button.count()) {
      await button.click();
    } else {
      await page.locator('.aa-load-more__sentinel').scrollIntoViewIfNeeded();
    }

    await expect
      .poll(() => cards.count(), { timeout: 15_000 })
      .toBeGreaterThan(initialCount);
    const productIds = await cards.evaluateAll(elements =>
      elements.map(element => {
        const productClass = [...element.classList].find(className =>
          /^post-\d+$/.test(className)
        );
        return productClass ?? '';
      })
    );
    expect(productIds).not.toContain('');
    expect(new Set(productIds).size).toBe(productIds.length);

    const appended = cards.nth(initialCount).locator('.wp-block-post-title a');
    await expect(appended).toBeVisible();
    expect(await titleStyle(appended)).toEqual(expected);

    const dynamicStyle = page.locator('style[data-dynamic-style-id]');
    await expect(dynamicStyle).toHaveCount(1);
    await expect(dynamicStyle).toHaveAttribute(
      'data-dynamic-style-id',
      /^[a-f0-9]{64}$/
    );
    expect(await dynamicStyle.evaluate(style => style.textContent)).toMatch(
      /wp-elements-/
    );
  });
}

test('catalog sorting resets paging and appends unique products', async ({
  page,
}) => {
  await page.goto('/shop/');

  const select = page.locator('select[name="orderby"]').first();
  await expect(select).toBeVisible();

  const sortedPage = page.waitForResponse(response => {
    const url = new URL(response.url());
    return (
      url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
      url.searchParams.get('orderby') === 'price' &&
      url.searchParams.get('page') === '1'
    );
  });
  await select.selectOption('price');
  await sortedPage;

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
      url.searchParams.get('page') === '2'
    );
  });

  await page.evaluate(() => {
    document.dispatchEvent(
      new CustomEvent('aa:load-more-page', { detail: { page: 2 } })
    );
  });
  await nextPage;

  await expect
    .poll(() => cards.count(), { timeout: 15_000 })
    .toBeGreaterThan(sortedCount);
  const productIds = await cards.evaluateAll(elements =>
    elements.map(element =>
      [...element.classList].find(className => /^post-\d+$/.test(className))
    )
  );
  expect(productIds.every(Boolean)).toBe(true);
  expect(new Set(productIds).size).toBe(productIds.length);
});

test.describe('anonymous catalog pagination', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('default WooCommerce ordering loads the remaining unique products', async ({
    page,
  }) => {
    const responsePromise = page.waitForResponse(response => {
      const url = new URL(response.url());
      return (
        url.pathname.endsWith('/aggressive-apparel/v1/products/rendered') &&
        url.searchParams.get('orderby') === 'menu_order' &&
        url.searchParams.get('page') === '2' &&
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
    await responsePromise;
    await expect.poll(() => cards.count()).toBeGreaterThan(initialCount);

    const productIds = await cards.evaluateAll(elements =>
      elements.map(element =>
        [...element.classList].find(className => /^post-\d+$/.test(className))
      )
    );
    expect(productIds.every(Boolean)).toBe(true);
    expect(new Set(productIds).size).toBe(productIds.length);
  });
});
