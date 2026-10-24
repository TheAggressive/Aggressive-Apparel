/**
 * E2E for product-card buttons (Store Enhancements → Quick View).
 *
 * Card Buttons on Touch Devices defaults to the wishlist heart only: one tap
 * saves, Quick View stays a mouse feature. In "behind one small chip" mode
 * each card shows a single chip that opens Quick View / Wishlist as a native
 * popover, without leaving the page; the browser closes it when another card
 * opens, on Escape, and when Quick View opens over it. Mouse users never see
 * the chip, and by default see the heart without hovering. The shop grid
 * comes from the global catalog fixtures, which turn Quick View and Wishlist
 * on.
 */

import { test, expect, type Locator } from '@playwright/test';
import { wpCli } from './wp-cli';

const STACK = '.aggressive-apparel-card-actions';
const TOGGLE = '.aggressive-apparel-card-actions__toggle';
const HEART = '.aggressive-apparel-wishlist__toggle--card-media';
const QUICK_VIEW = '.aggressive-apparel-quick-view__trigger';
const TOUCH_MODE_OPTION = 'aggressive_apparel_quick_view_touch_mode';
const ACTIONS =
  '.aggressive-apparel-card-action:not(.aggressive-apparel-card-actions__toggle)';

async function expectActions(stack: Locator, visible: boolean): Promise<void> {
  const actions = await stack.locator(ACTIONS).all();
  expect(actions.length).toBeGreaterThan(0);
  for (const action of actions) {
    if (visible) {
      await expect(action).toBeVisible();
    } else {
      await expect(action).toBeHidden();
    }
  }
}

// Workers are capped at 1, so a describe-scoped option can't leak into a
// concurrently running spec. The previous value is restored, since local runs
// share the developer's Studio site.
function useTouchMode(mode: string): void {
  let previous: string | null = null;

  test.beforeAll(() => {
    try {
      previous = wpCli(['option', 'get', TOUCH_MODE_OPTION]);
    } catch {
      previous = null;
    }
    wpCli(['option', 'update', TOUCH_MODE_OPTION, mode]);
  });
  test.afterAll(() => {
    if (previous === null) {
      wpCli(['option', 'delete', TOUCH_MODE_OPTION]);
    } else {
      wpCli(['option', 'update', TOUCH_MODE_OPTION, previous]);
    }
  });
}

test.describe('card buttons on touch devices, heart only', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    hasTouch: true,
    isMobile: true,
  });
  useTouchMode('wishlist');

  test('one tap on the heart saves without leaving the page', async ({
    page,
  }) => {
    await page.goto('/shop/');
    const first = page.locator(STACK).first();
    const heart = first.locator(HEART);

    await first.scrollIntoViewIfNeeded();
    await expect(heart).toBeVisible();
    await expect(first.locator(QUICK_VIEW)).toBeHidden();
    await expect(first.locator(TOGGLE)).toHaveCount(0);

    const url = page.url();
    await heart.tap();
    await expect(heart).toHaveClass(/\bis-active\b/);
    expect(page.url()).toBe(url);
  });
});

test.describe('card buttons on touch devices', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    hasTouch: true,
    isMobile: true,
  });
  useTouchMode('toggle');

  test('one chip reveals the card buttons without leaving the page', async ({
    page,
  }) => {
    await page.goto('/shop/');
    const first = page.locator(STACK).nth(0);
    const second = page.locator(STACK).nth(1);
    const toggle = first.locator(TOGGLE);

    await first.scrollIntoViewIfNeeded();
    await expect(toggle).toBeVisible();
    await expectActions(first, false);

    const url = page.url();
    await toggle.tap();
    await expectActions(first, true);
    expect(page.url()).toBe(url);

    // Only one card is open at a time.
    await second.locator(TOGGLE).tap();
    await expectActions(first, false);
    await expectActions(second, true);

    await page.keyboard.press('Escape');
    await expectActions(second, false);

    // Quick View closes the popover so it can't float above the modal.
    await toggle.tap();
    await first.locator(QUICK_VIEW).tap();
    await expect(page.locator('#aggressive-apparel-quick-view')).toBeVisible();
    await expectActions(first, false);
  });
});

test.describe('card buttons on touch devices, appended cards', () => {
  test.use({
    viewport: { width: 390, height: 844 },
    hasTouch: true,
    isMobile: true,
  });
  useTouchMode('toggle');

  // Load More renders page 2 in its own request: its popover ids and anchor
  // names must not repeat page 1's, or a chip would open another card.
  test('a chip on an appended card opens its own buttons', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/shop/');
    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    const before = await cards.count();
    await page.locator('.aa-load-more__btn').tap();
    await expect.poll(() => cards.count()).toBeGreaterThan(before);

    const appended = cards.nth(before).locator(STACK);
    await appended.scrollIntoViewIfNeeded();
    await appended.locator(TOGGLE).tap();
    await expectActions(appended, true);
    await expectActions(page.locator(STACK).first(), false);
  });
});

test.describe('card buttons with a mouse', () => {
  test.use({ viewport: { width: 1280, height: 900 } });

  test('the heart shows without hovering and Quick View joins on hover', async ({
    page,
  }) => {
    await page.goto('/shop/');
    const first = page.locator(STACK).first();

    await first.scrollIntoViewIfNeeded();
    await page.mouse.move(0, 0);
    await expect(first.locator(HEART)).toHaveCSS('opacity', '1');
    await expect(first.locator(QUICK_VIEW)).toHaveCSS('opacity', '0');

    await first.locator(HEART).hover();
    await expect(first.locator(QUICK_VIEW)).toHaveCSS('opacity', '1');
  });

  test.describe('with the touch chip on', () => {
    useTouchMode('toggle');

    test('the touch chip never shows', async ({ page }) => {
      await page.goto('/shop/');
      const first = page.locator(STACK).first();

      await first.scrollIntoViewIfNeeded();
      await expect(first.locator(TOGGLE)).toBeHidden();
      await expectActions(first, true);
    });
  });
});
