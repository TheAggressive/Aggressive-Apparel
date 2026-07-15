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
