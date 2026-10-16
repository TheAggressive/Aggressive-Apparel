/**
 * Product filters on category archives: a single category opens its real
 * archive, and a parent category's options account for its subcategories'
 * products (the archive lists them, so the facets must too).
 */

import { test, expect, type Locator, type Page } from '@playwright/test';
import {
  CHILD_COLLECTION,
  PARENT_CATEGORY,
  PARENT_NAME,
  ensureCategoryFilterFixtures,
  type CategoryFilterFixtures,
} from './category-filter-fixtures';

let fixtures: CategoryFilterFixtures;

test.beforeAll(() => {
  fixtures = ensureCategoryFilterFixtures();
});

test.use({ storageState: { cookies: [], origins: [] } });

/** Reveal a filter control, opening the drawer layout when it is closed. */
async function reveal(page: Page, control: Locator): Promise<void> {
  if (!(await control.isVisible())) {
    await page.locator('button[class*="filter-toggle"]').first().click();
  }
  await expect(control).toBeVisible();
}

test('choosing one category opens its archive', async ({ page }) => {
  await page.goto('/shop/', { waitUntil: 'networkidle' });

  const chip = page
    .locator(
      `.aa-product-filters__category-chip[data-filter-value="${PARENT_CATEGORY}"]`
    )
    .first();
  await reveal(page, chip);

  await Promise.all([
    page.waitForURL(
      url => url.pathname === new URL(fixtures.parentUrl).pathname
    ),
    (async () => {
      await chip.click();
      // The drawer layout applies on close; other layouts navigate at once.
      await page.keyboard.press('Escape');
    })(),
  ]);

  await expect(page.locator('h1').first()).toHaveText(PARENT_NAME);
});

test('a parent category offers options from its subcategories', async ({
  page,
}) => {
  await page.goto(fixtures.parentUrl, { waitUntil: 'networkidle' });

  const chip = page
    .locator(
      `.aa-product-filters__collection-chip[data-filter-value="${CHILD_COLLECTION}"]`
    )
    .first();
  await reveal(page, chip);

  await expect(chip).not.toHaveClass(/\bis-unavailable\b/);
});
