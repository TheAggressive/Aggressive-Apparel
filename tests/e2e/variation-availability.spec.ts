import { test, expect } from '@playwright/test';
import {
  ensureVariationAvailabilityFixtures,
  type VariationFixture,
} from './variation-availability-fixtures';

/**
 * Regression coverage for the variation-availability affordance that unit tests
 * can't reach: a sold-out variation must dim its option AND stay accessible —
 * `aria-disabled` + an "Unavailable" accessible name, never the native
 * `disabled` attribute (which would drop the control out of the tab order).
 *
 * Driven through the sticky add-to-cart drawer on the single-product page — the
 * deterministic surface (Quick View lives only on archive cards, whose template
 * varies by env). The Quick View store shares the same `isOptionAvailable`
 * helper and `.is-unavailable` styling, and is covered by unit tests + the
 * shared helper suite.
 *
 * Fixture: Red $12 (in stock), Blue $15 (in stock), Green $14 (OUT OF STOCK).
 */

let fixture: VariationFixture = { id: 0, permalink: '' };

test.beforeAll(() => {
  fixture = ensureVariationAvailabilityFixtures();
});

test.describe('sticky cart — variation availability', () => {
  test('dims the sold-out colour while keeping it accessible', async ({
    page,
  }) => {
    test.skip(
      !fixture.id || !fixture.permalink,
      'variation-availability fixture unavailable'
    );

    await page.goto(fixture.permalink);
    // Scroll the add-to-cart form out of view so the sticky bar reveals.
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForSelector('.aa-sticky-cart.is-visible', {
      timeout: 15_000,
    });

    // For a variable product the CTA opens the options drawer.
    await page.locator('.aa-sticky-cart__button').first().click();
    await page.waitForSelector('.aa-sticky-cart__drawer.is-open');

    const option = (value: string) =>
      page
        .locator(`.aa-sticky-cart__drawer-option[data-value="${value}"]`)
        .first();

    // Green is sold out → dimmed, aria-disabled, and announces "Unavailable".
    const green = option('green');
    await expect(green).toHaveClass(/is-unavailable/);
    await expect(green).toHaveAttribute('aria-disabled', 'true');
    await expect(green).toHaveAttribute('aria-label', /Unavailable/);
    // Uses aria-disabled, NOT the native disabled attribute (stays focusable).
    expect(await green.evaluate(el => (el as HTMLButtonElement).disabled)).toBe(
      false
    );

    // Red is in stock → selectable, not dimmed.
    const red = option('red');
    await expect(red).not.toHaveClass(/is-unavailable/);
    await expect(red).not.toHaveAttribute('aria-disabled', 'true');

    // Activating the sold-out option is rejected (it never becomes selected).
    await green.dispatchEvent('click');
    await expect(green).not.toHaveClass(/is-selected/);
  });
});
