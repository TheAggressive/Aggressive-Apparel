import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

import {
  createVariationAvailabilityFixture,
  deleteVariationAvailabilityFixture,
  type VariationFixture,
} from './variation-availability-fixtures';
import {
  deleteCategoryFilterFixtures,
  ensureCategoryFilterFixtures,
} from './category-filter-fixtures';
import { wpCli } from './wp-cli';

/**
 * Automated WCAG 2.2 AA scans of the storefront's key pages and overlays.
 *
 * axe catches the mechanical failures (names, roles, contrast, landmarks,
 * ARIA misuse) on the rendered page, including states that only exist after
 * interaction — the mobile drawer, search modal, and quick view. It cannot
 * judge focus visibility or forced-colors rendering, so those stay manual.
 */

const WCAG_TAGS = [
  'wcag2a',
  'wcag2aa',
  'wcag21a',
  'wcag21aa',
  'wcag22a',
  'wcag22aa',
];

let fixture: VariationFixture;
let categoryUrl = '';
let cartUrl = '';

/**
 * Scan the page (or one region of it) and fail with a readable list of
 * violations: rule, impact, and the offending selectors.
 */
async function expectAccessible(page: Page, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(WCAG_TAGS);
  if (include) {
    builder = builder.include(include);
  }

  const { violations } = await builder.analyze();
  const report = violations.map(violation => {
    // failureSummary's second line carries the specifics, e.g. the measured
    // contrast ratio and both colors.
    const targets = violation.nodes
      .slice(0, 5)
      .map(node => {
        const detail = node.failureSummary?.split('\n')[1]?.trim() ?? '';
        return `    ${node.target.join(' ')}\n      ${detail}`;
      })
      .join('\n');
    const more =
      violation.nodes.length > 5
        ? `\n    …and ${violation.nodes.length - 5} more`
        : '';
    return `${violation.id} [${violation.impact}] ${violation.help}\n${targets}${more}`;
  });

  expect(report, report.join('\n\n')).toEqual([]);
}

test.beforeAll(() => {
  fixture = createVariationAvailabilityFixture();

  const urls = JSON.parse(
    wpCli([
      'eval',
      `echo wp_json_encode(array(
        'category' => get_term_link('e2e-browser-fixtures', 'product_cat'),
        'cart'     => wc_get_cart_url(),
      ));`,
    ])
  ) as { category: unknown; cart: unknown };

  categoryUrl = typeof urls.category === 'string' ? urls.category : '';
  cartUrl = typeof urls.cart === 'string' ? urls.cart : '';

  if (!categoryUrl || !cartUrl) {
    throw new Error('Accessibility fixture URLs could not be resolved.');
  }
});

test.afterAll(() => {
  if (fixture?.id) {
    deleteVariationAvailabilityFixture(fixture);
  }
  // The filter-drawer test creates these on demand.
  deleteCategoryFilterFixtures();
});

test.describe('accessibility — WCAG 2.2 AA', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test.beforeEach(async ({ page }) => {
    // Scan settled states, not mid-transition frames (contrast checks read
    // computed colors, which fades and slides distort).
    await page.emulateMedia({ reducedMotion: 'reduce' });
  });

  for (const colorScheme of ['light', 'dark'] as const) {
    test.describe(`${colorScheme} scheme`, () => {
      test.beforeEach(async ({ page }) => {
        await page.emulateMedia({ colorScheme });
      });

      test('home page', async ({ page }) => {
        await page.goto('/');
        await expectAccessible(page);
      });

      test('product page', async ({ page }) => {
        await page.goto(fixture.permalink);
        await expectAccessible(page);
      });

      test('product category archive', async ({ page }) => {
        await page.goto(categoryUrl);
        await expectAccessible(page);
      });
    });
  }

  test('cart page (empty)', async ({ page }) => {
    await page.goto(cartUrl);
    await expectAccessible(page);
  });

  test.describe('shop catalog', () => {
    // Button mode: each Load More state is one explicit step.
    test.beforeEach(async ({ page }) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.goto('/shop/', { waitUntil: 'networkidle' });
    });

    test('after Load More appends a page', async ({ page }) => {
      const button = page.locator('.aa-load-more__btn');
      await button.click();
      await expect(button).not.toHaveAttribute('aria-busy', 'true');
      await expect(page.locator('.aa-load-more__announcer')).toHaveText(
        /more products loaded/
      );

      await expectAccessible(page, '.aa-product-filters');
    });

    test('filter drawer, with unavailable options', async ({ page }) => {
      // The fixture's parent category holds one product without a colour, so
      // its colour options are unavailable there once facets load.
      const { parentUrl } = ensureCategoryFilterFixtures();
      await page.goto(parentUrl, { waitUntil: 'networkidle' });
      await page.locator('button[class*="filter-toggle"]').first().click();
      const drawer = page.locator('#aa-product-filters-drawer');
      await expect(drawer.locator('.aa-product-filters__close')).toBeFocused();
      await expect(
        drawer
          .locator('.aa-product-filters__color-swatch[aria-disabled="true"]')
          .first()
      ).toBeAttached();

      await expectAccessible(page, '#aa-product-filters-drawer');
    });

    test('Load More failure offers the next page', async ({ page }) => {
      const seed = JSON.parse(
        (await page.locator('.aa-load-more').getAttribute('data-wp-context'))!
      ) as { nextUrl: string };
      await page.route(new URL(seed.nextUrl, page.url()).href, route =>
        route.fulfill({ status: 404, body: 'Not Found' })
      );
      await page.locator('.aa-load-more__btn').click();
      await expect(page.locator('.aa-load-more__fallback')).toBeVisible();

      await expectAccessible(page, '.aa-load-more');
    });
  });

  test('quick view modal', async ({ page }) => {
    await page.goto(categoryUrl);

    const trigger = page
      .locator('.aggressive-apparel-quick-view__trigger')
      .first();
    await trigger.click();

    const modal = page.locator('.aggressive-apparel-quick-view__modal');
    await expect(modal).toBeVisible();
    await expect(
      modal.locator('#aggressive-apparel-quick-view-title')
    ).not.toBeEmpty();

    await expectAccessible(page, '.aggressive-apparel-quick-view__modal');
  });

  test.describe('mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('navigation drawer', async ({ page }) => {
      await page.goto('/');

      const trigger = page.locator('.aa-nav-trigger:visible').first();
      await trigger.click();
      await expect(trigger).toHaveAttribute('aria-expanded', 'true');

      const panelId = await trigger.getAttribute('aria-controls');
      expect(panelId).toBeTruthy();
      const panel = page.locator(`#${panelId}`);
      await expect(panel).toHaveAttribute('aria-hidden', 'false');

      await expectAccessible(page, `#${panelId}`);
    });

    test('search modal', async ({ page }) => {
      await page.goto('/');

      // The header's own trigger, or, where the header hides it on phones,
      // the one in the menu drawer.
      if (!(await page.locator('header .aa-search-trigger').isVisible())) {
        await page.locator('.aa-nav-trigger:visible').first().click();
      }
      await page.locator('.aa-search-trigger:visible').first().click();
      const modal = page.locator('.aa-search.is-open');
      await expect(modal).toBeVisible();

      await expect(
        modal.locator('[role="dialog"] [aria-live="polite"]')
      ).toHaveAttribute('aria-atomic', 'true');

      await expectAccessible(page, '.aa-search.is-open');
    });
  });
});
