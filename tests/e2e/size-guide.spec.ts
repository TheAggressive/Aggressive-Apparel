import { expect, test } from '@playwright/test';
import {
  createSizeGuideFixture,
  deleteSizeGuideFixture,
  type SizeGuideFixture,
} from './size-guide-fixtures';

/**
 * Browser-level contract for the product Size Guide dialog.
 *
 * The product template has no Size Guide block, so Size_Guide prints the
 * dialog in the footer behind a hidden trigger, and the "(Guide)" link beside
 * the Size options opens it. This exercises that path and the shared overlay
 * primitives on a real WooCommerce single-product request.
 */

test.describe('Size Guide — product page', () => {
  let fixture: SizeGuideFixture | undefined;

  test.beforeEach(async ({ page }) => {
    fixture = createSizeGuideFixture();
    await page.goto(fixture.permalink);
  });

  test.afterEach(() => {
    if (fixture) {
      deleteSizeGuideFixture();
      fixture = undefined;
    }
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
    const opener = page.locator('.aa-size-label__link');

    await expect(root).toHaveCount(1);
    await expect(trigger).toBeHidden();
    await expect(opener).toBeVisible();
    await expect(overlay).toHaveClass(/aggressive-apparel-overlay/);
    await expect(panel).toHaveClass(/aggressive-apparel-panel/);
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(overlay).toBeHidden();

    const controlledId = await trigger.getAttribute('aria-controls');
    expect(controlledId).toBeTruthy();
    await expect(overlay).toHaveAttribute('id', controlledId ?? '');

    await opener.click();

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
    await expect(opener).toBeFocused();
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
    const opener = page.locator('.aa-size-label__link');

    await opener.click();
    await expect(closeButton).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(fitNotesLink).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(closeButton).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(fitNotesLink).toBeFocused();

    await closeButton.click();
    await expect(overlay).toBeHidden();
    await expect(opener).toBeFocused();

    await opener.click();
    await expect(dialog).toBeVisible();
    await overlay
      .locator('.aggressive-apparel-size-guide__backdrop')
      .click({ position: { x: 5, y: 5 } });

    await expect(overlay).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await expect(opener).toBeFocused();
  });
});
