import { expect, test, type Page } from '@playwright/test';
import {
  createSizeGuideFixture,
  deleteSizeGuideFixture,
  type SizeGuideFixture,
} from './size-guide-fixtures';

/**
 * Browser contract for structured size charts: the unit default follows the
 * visitor's locale, the toggle switches and is remembered, and the size
 * picked in the add to cart form is highlighted when the guide opens.
 *
 * The fixture chart is in inches: S 19, M 21, L 23 (M = 53.5 cm).
 */

/**
 * Open the guide with its button when the Size Guide block is on the page,
 * else with the "Size guide" link beside the size options (a site's own
 * single-product template may drop the block; the dialog still renders).
 */
async function openGuide(page: Page) {
  const root = page.locator(
    '[data-wp-interactive="aggressive-apparel/size-guide"]'
  );
  const trigger = root.locator('.aggressive-apparel-size-guide__trigger');
  if (await trigger.isVisible()) {
    await trigger.click();
  } else {
    await page.locator('.aa-size-label__link').first().click();
  }
  const dialog = root.locator('[role="dialog"]');
  await expect(dialog).toBeVisible();
  return dialog;
}

/** The Medium chest value currently shown (both units are in the markup). */
function mediumChest(dialog: ReturnType<Page['locator']>) {
  return dialog
    .locator('tr[data-size="m"] td .aa-size-chart__v')
    .filter({ visible: true });
}

test.describe('Size chart — product page', () => {
  let fixture: SizeGuideFixture | undefined;

  test.beforeEach(() => {
    fixture = createSizeGuideFixture('chart');
  });

  test.afterEach(() => {
    if (fixture) {
      deleteSizeGuideFixture();
      fixture = undefined;
    }
  });

  test.describe('in a centimetre locale', () => {
    test.use({ locale: 'en-GB' });

    test('defaults to cm, toggles to inches, and remembers the choice', async ({
      page,
    }) => {
      await page.goto(fixture?.permalink ?? '/');
      let dialog = await openGuide(page);
      const centimetres = dialog.getByRole('button', { name: 'Centimetres' });
      const inches = dialog.getByRole('button', { name: 'Inches' });

      await expect(centimetres).toHaveAttribute('aria-pressed', 'true');
      await expect(mediumChest(dialog)).toHaveText(/^53\.5\scm$/);
      // Named generically; the guide's own name is admin-only.
      await expect(dialog.getByRole('table')).toHaveAccessibleName(
        /^Size chart/
      );
      await expect(dialog.getByText('E2E Size Chart')).toHaveCount(0);

      await inches.click();
      await expect(inches).toHaveAttribute('aria-pressed', 'true');
      await expect(centimetres).toHaveAttribute('aria-pressed', 'false');
      await expect(mediumChest(dialog)).toHaveText(/^21\sin$/);
      await expect(dialog.locator('.aa-size-chart__live')).toHaveText(
        'Showing inches'
      );

      await page.reload();
      dialog = await openGuide(page);
      await expect(mediumChest(dialog)).toHaveText(/^21\sin$/);
    });
  });

  test.describe('in an inch locale', () => {
    test.use({ locale: 'en-US' });

    test('defaults to inches', async ({ page }) => {
      await page.goto(fixture?.permalink ?? '/');
      const dialog = await openGuide(page);

      await expect(
        dialog.getByRole('button', { name: 'Inches' })
      ).toHaveAttribute('aria-pressed', 'true');
      await expect(mediumChest(dialog)).toHaveText(/^21\sin$/);
    });

    test('highlights the size picked in the form', async ({ page }) => {
      await page.goto(fixture?.permalink ?? '/');
      await page
        .locator('form')
        .getByRole('radio', { name: 'Medium' })
        .first()
        .click();

      const dialog = await openGuide(page);
      const row = dialog.locator('tr[data-size="m"]');

      await expect(row).toHaveClass(/is-selected/);
      await expect(row.locator('.aa-size-chart__current')).not.toHaveAttribute(
        'hidden'
      );
      await expect(dialog.locator('tr[data-size="s"]')).not.toHaveClass(
        /is-selected/
      );
    });

    test('Product Specs shows the picked size and opens the guide', async ({
      page,
    }) => {
      await page.goto(fixture?.permalink ?? '/');
      const specs = page.locator('.aa-product-specs.is-layout-compact').first();
      const fit = specs.locator('.aa-product-specs__fit');
      const link = specs.getByRole('button', { name: 'Size guide' });

      await expect(specs.getByRole('meter', { name: 'Fit' })).toBeVisible();
      await expect(link).toBeVisible();
      await expect(fit).toHaveText('Pick a size to see its measurements.');

      await page.getByRole('radio', { name: 'Medium' }).first().click();
      await expect(fit).toHaveText('Size M: Chest 21 in');

      await link.click();
      const dialog = page.locator(
        '[data-wp-interactive="aggressive-apparel/size-guide"] [role="dialog"]'
      );
      await expect(dialog).toBeVisible();
      await dialog.getByRole('button', { name: 'Centimetres' }).click();
      await page.keyboard.press('Escape');

      await expect(link).toBeFocused();
      await expect(fit).toHaveText('Size M: Chest 53.5 cm');
    });

    test('the Size guide link beside the size options opens the guide', async ({
      page,
    }) => {
      await page.goto(fixture?.permalink ?? '/');
      const link = page.locator('.aa-size-label__link').first();

      await expect(link).toBeVisible();
      await link.click();
      const dialog = page.locator(
        '[data-wp-interactive="aggressive-apparel/size-guide"] [role="dialog"]'
      );
      await expect(dialog).toBeVisible();
      await page.keyboard.press('Escape');
      await expect(link).toBeFocused();
    });
  });
});
