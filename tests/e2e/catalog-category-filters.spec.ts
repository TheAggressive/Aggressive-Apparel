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

  await expect(chip).not.toHaveAttribute('aria-disabled', 'true');
});

/**
 * Titles of the server-rendered product cards in the grid — not the ones
 * Load More appended, which infinite scroll may add at any time.
 */
async function cardTitles(page: Page): Promise<string[]> {
  return page
    .locator(
      '.wp-block-woocommerce-product-template > .wc-block-product:not([data-aa-appended])'
    )
    .evaluateAll(cards =>
      cards.map(
        card =>
          card.querySelector('.wp-block-post-title')?.textContent?.trim() ?? ''
      )
    );
}

test.describe('filtered URLs', () => {
  test.use({ javaScriptEnabled: false });

  test('render the filtered products on the server', async ({ page }) => {
    await page.goto(`/shop/?collection=${CHILD_COLLECTION}`);
    expect(await cardTitles(page)).toEqual(['E2E Filter Child Product']);

    await page.goto('/shop/?collection=e2e-no-such-collection');
    await expect(page.locator('.aa-catalog-no-results')).toBeVisible();
  });
});

test('applying a filter swaps the grid in place; back restores it', async ({
  page,
}) => {
  await page.goto('/shop/', { waitUntil: 'networkidle' });
  const unfiltered = await cardTitles(page);
  expect(unfiltered.length).toBeGreaterThan(1);
  await page.evaluate(() => {
    (window as Window & { aaSamePage?: boolean }).aaSamePage = true;
  });

  const chip = page
    .locator(
      `.aa-product-filters__collection-chip[data-filter-value="${CHILD_COLLECTION}"]`
    )
    .first();
  await reveal(page, chip);
  await chip.click();
  await Promise.all([
    page.waitForURL(
      url => url.searchParams.get('collection') === CHILD_COLLECTION
    ),
    page.keyboard.press('Escape'),
  ]);

  await expect
    .poll(() => cardTitles(page))
    .toEqual(['E2E Filter Child Product']);
  expect(
    await page.evaluate(
      () => (window as Window & { aaSamePage?: boolean }).aaSamePage === true
    ),
    'The router swaps the collection; the page itself never reloads.'
  ).toBe(true);
  await expect(page.locator('.aa-product-filters__announcer')).toHaveText(
    '1 product found.'
  );

  await page.goBack();
  await expect.poll(() => cardTitles(page)).toEqual(unfiltered);
  await expect(chip).toHaveAttribute('aria-pressed', 'false');
});

test('sorting keeps the active filters', async ({ page }) => {
  await page.goto(`/shop/?collection=${CHILD_COLLECTION}`, {
    waitUntil: 'networkidle',
  });

  await Promise.all([
    page.waitForURL(url => url.searchParams.get('orderby') === 'price'),
    page.locator('select[name="orderby"]').first().selectOption('price'),
  ]);

  expect(new URL(page.url()).searchParams.get('collection')).toBe(
    CHILD_COLLECTION
  );
  expect(await cardTitles(page)).toEqual(['E2E Filter Child Product']);
});
