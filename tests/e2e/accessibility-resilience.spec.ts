import { expect, test } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

test.beforeEach(async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
});

for (const route of ['/', '/shop/']) {
  test(`enlarged text reflows without horizontal page scrolling on ${route}`, async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(route, { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
    await page.evaluate(() => document.fonts.ready);
    await expect
      .poll(() =>
        page.evaluate(() => document.documentElement.scrollWidth - innerWidth)
      )
      .toBeLessThanOrEqual(1);
  });
}

test('search retains keyboard focus in forced colors and returns it on Escape', async ({
  page,
}) => {
  await page.emulateMedia({ forcedColors: 'active' });
  await page.goto('/');
  const trigger = page.locator('.aa-search-trigger:visible').first();
  await trigger.click();
  await expect(page.locator('.aa-search__input')).toBeFocused();
  for (const key of ['Tab', 'Shift+Tab']) {
    for (let i = 0; i < 12; i += 1) {
      await page.keyboard.press(key);
      expect(
        await page.evaluate(() =>
          Boolean(document.activeElement?.closest('.aa-search__dialog'))
        )
      ).toBe(true);
    }
  }
  await page.keyboard.press('Escape');
  await expect(trigger).toBeFocused();
});
