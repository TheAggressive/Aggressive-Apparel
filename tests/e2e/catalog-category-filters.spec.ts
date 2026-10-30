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
  deleteCategoryFilterFixtures,
  ensureCategoryFilterFixtures,
  type CategoryFilterFixtures,
} from './category-filter-fixtures';

let fixtures: CategoryFilterFixtures;

test.beforeAll(() => {
  fixtures = ensureCategoryFilterFixtures();
});

test.afterAll(() => {
  deleteCategoryFilterFixtures();
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
    // One Collection is an audience landing page (/shop/{collection}/).
    await page.goto(`/shop/${CHILD_COLLECTION}/`);
    expect(await cardTitles(page)).toEqual(['E2E Filter Child Product']);
    await expect(page.locator('h1').first()).toHaveText('E2E Child Only');

    await page.goto(`/shop/${CHILD_COLLECTION}/${PARENT_CATEGORY}/`);
    expect(await cardTitles(page)).toEqual(['E2E Filter Child Product']);
    await expect(page.locator('h1').first()).toHaveText(
      `E2E Child Only ${PARENT_NAME}`
    );

    await page.goto('/shop/?collection=e2e-no-such-collection');
    await expect(page.locator('.aa-catalog-no-results')).toBeVisible();
  });
});

test('picking one Collection opens its landing page', async ({ page }) => {
  await page.goto(fixtures.parentUrl, { waitUntil: 'networkidle' });

  const chip = page
    .locator(
      `.aa-product-filters__collection-chip[data-filter-value="${CHILD_COLLECTION}"]`
    )
    .first();
  await reveal(page, chip);

  await Promise.all([
    page.waitForURL(
      url => url.pathname === `/shop/${CHILD_COLLECTION}/${PARENT_CATEGORY}/`
    ),
    (async () => {
      await chip.click();
      await page.keyboard.press('Escape');
    })(),
  ]);
  expect(await cardTitles(page)).toEqual(['E2E Filter Child Product']);

  // Loaded directly, the landing page shows its Collection as picked.
  await page.reload({ waitUntil: 'networkidle' });
  const picked = page
    .locator(
      `.aa-product-filters__collection-chip[data-filter-value="${CHILD_COLLECTION}"]`
    )
    .first();
  await reveal(page, picked);
  await expect(picked).toHaveAttribute('aria-pressed', 'true');
});

test('a filter on the same page swaps the grid in place; back restores it', async ({
  page,
}) => {
  // A landing page keeps its path when another filter is added, so only the
  // collection is swapped (picking an audience is a full navigation instead,
  // so the new page's title and breadcrumbs come from the server).
  await page.goto(`/shop/${CHILD_COLLECTION}/`, { waitUntil: 'networkidle' });
  const unfiltered = await cardTitles(page);
  expect(unfiltered).toEqual(['E2E Filter Child Product']);
  await page.evaluate(() => {
    (window as Window & { aaSamePage?: boolean }).aaSamePage = true;
  });

  const toggle = page.locator('.aa-product-filters__stock-checkbox').first();
  await reveal(page, toggle);
  await toggle.check();
  await Promise.all([
    page.waitForURL(
      url =>
        url.pathname === `/shop/${CHILD_COLLECTION}/` &&
        url.searchParams.get('stock') === 'instock'
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
  await expect(page).toHaveURL(url => !url.searchParams.has('stock'));
  await expect.poll(() => cardTitles(page)).toEqual(unfiltered);
});

test('sorting keeps the active filters', async ({ page }) => {
  await page.goto(`/shop/${CHILD_COLLECTION}/`, {
    waitUntil: 'networkidle',
  });

  await Promise.all([
    page.waitForURL(url => url.searchParams.get('orderby') === 'price'),
    page.locator('select[name="orderby"]').first().selectOption('price'),
  ]);

  expect(new URL(page.url()).pathname).toBe(`/shop/${CHILD_COLLECTION}/`);
  expect(await cardTitles(page)).toEqual(['E2E Filter Child Product']);
});
