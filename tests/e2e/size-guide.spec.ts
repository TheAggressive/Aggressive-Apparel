import { expect, test } from '@playwright/test';
import {
  createSizeGuideFixture,
  deleteSizeGuideFixture,
  type SizeGuideFixture,
} from './size-guide-fixtures';

/**
 * Browser-level contract for the dynamic product Size Guide block.
 *
 * This intentionally exercises the production block renderer and shared
 * overlay primitives on a real WooCommerce single-product request.
 */

test.describe('Size Guide — product page', () => {
  let fixture: SizeGuideFixture;

  test.beforeAll(() => {
    fixture = createSizeGuideFixture();
  });

  test.afterAll(() => {
    if (fixture) {
      deleteSizeGuideFixture(fixture);
    }
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(fixture.permalink);
  });

  test('opens an accessible dialog, locks scroll, and restores focus on Escape', async ({
    page,
  }) => {
    const root = page.locator(
      '[data-wp-interactive="aggressive-apparel/size-guide"]'
    );
    const trigger = root.locator('.aggressive-apparel-size-guide__trigger');
    const overlay = root.locator('.aggressive-apparel-size-guide__overlay');
    const dialog = root.locator('[role="dialog"]');
    const panel = dialog.locator('.aggressive-apparel-size-guide__modal');
    const closeButton = dialog.getByRole('button', { name: 'Close' });

    await expect(root).toHaveCount(1);
    await expect(trigger).toHaveClass(/wp-block-aggressive-apparel-size-guide/);
    await expect(overlay).toHaveClass(/aggressive-apparel-overlay/);
    await expect(panel).toHaveClass(/aggressive-apparel-panel/);
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(overlay).toBeHidden();

    const controlledId = await trigger.getAttribute('aria-controls');
    expect(controlledId).toBeTruthy();
    await expect(overlay).toHaveAttribute('id', controlledId ?? '');

    await trigger.click();

    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAccessibleName('Size Guide');
    await expect(overlay).toHaveClass(/is-open/);
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(closeButton).toBeFocused();
    await expect(
      dialog.getByText('E2E measurement 38–40 inches.')
    ).toBeVisible();
    await expect
      .poll(() => page.evaluate(() => document.body.style.overflow))
      .toBe('hidden');

    await page.keyboard.press('Escape');

    await expect(overlay).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(trigger).toBeFocused();
    await expect
      .poll(() => page.evaluate(() => document.body.style.overflow))
      .toBe('');
  });

  test('traps focus and supports close-button and backdrop dismissal', async ({
    page,
  }) => {
    const root = page.locator(
      '[data-wp-interactive="aggressive-apparel/size-guide"]'
    );
    const trigger = root.locator('.aggressive-apparel-size-guide__trigger');
    const overlay = root.locator('.aggressive-apparel-size-guide__overlay');
    const dialog = root.getByRole('dialog', { name: 'Size Guide' });
    const closeButton = dialog.getByRole('button', { name: 'Close' });
    const fitNotesLink = dialog.getByRole('link', { name: 'Fit notes' });

    await trigger.click();
    await expect(closeButton).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(fitNotesLink).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(closeButton).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(fitNotesLink).toBeFocused();

    await closeButton.click();
    await expect(overlay).toBeHidden();
    await expect(trigger).toBeFocused();

    await trigger.click();
    await expect(dialog).toBeVisible();
    await overlay
      .locator('.aggressive-apparel-size-guide__backdrop')
      .click({ position: { x: 5, y: 5 } });

    await expect(overlay).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(trigger).toBeFocused();
  });
});
