import { expect, test } from '@playwright/test';

test('the packaged theme boots on the public and administrative surfaces', async ({
  page,
}) => {
  const storefront = await page.goto('/');
  expect(storefront?.ok()).toBe(true);
  await expect(page.locator('body')).toBeVisible();

  const themes = await page.goto('/wp-admin/themes.php');
  expect(themes?.ok()).toBe(true);
  await expect(
    page.locator('[data-slug="aggressive-apparel"].active')
  ).toBeVisible();
});
