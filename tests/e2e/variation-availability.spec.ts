import { test, expect } from '@playwright/test';
import {
  createVariationAvailabilityFixture,
  deleteVariationAvailabilityFixture,
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

let fixture: VariationFixture = {
  id: 0,
  permalink: '',
  createdTermIds: [],
  createdCategoryId: 0,
};

test.beforeAll(() => {
  fixture = createVariationAvailabilityFixture();
});

test.afterAll(() => {
  if (fixture.id) {
    deleteVariationAvailabilityFixture(fixture);
  }
});

test.describe('sticky cart — variation availability', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('renders the compact variable-product purchase row on mobile', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await page.goto(fixture.permalink);

    const bar = page.locator('.aa-sticky-cart');
    const sourceForm = page
      .locator(
        '.wc-block-add-to-cart-form, .wp-block-woocommerce-add-to-cart-with-options, .variations_form'
      )
      .first();

    await expect(bar).toHaveClass(/aa-sticky-cart--variable/);
    await sourceForm.scrollIntoViewIfNeeded();
    await page.evaluate(
      () =>
        new Promise<void>(resolve => {
          requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
        })
    );

    await sourceForm.evaluate(form => {
      window.scrollBy(0, form.getBoundingClientRect().bottom + 1);
    });

    await expect(bar).toBeVisible({ timeout: 15_000 });
    await expect(bar).toHaveClass(/is-visible/);

    const product = bar.locator('.aa-sticky-cart__product');
    const image = bar.locator('.aa-sticky-cart__image');
    const title = bar.locator('.aa-sticky-cart__title');
    const price = bar.locator('.aa-sticky-cart__price');
    const action = bar.locator('.aa-sticky-cart__button');

    await expect(image).toBeVisible();
    await expect(image).toHaveAttribute('alt', '');
    await expect(price).toBeVisible();
    await expect(title).toHaveCSS('position', 'absolute');
    await expect(action).toBeVisible();

    const [productBox, imageBox, priceBox, actionBox] = await Promise.all(
      [product, image, price, action].map(locator => locator.boundingBox())
    );

    expect(imageBox?.width).toBe(48);
    expect(imageBox?.height).toBe(48);
    expect(actionBox?.width).toBeGreaterThanOrEqual(120);
    expect(actionBox?.height).toBeGreaterThanOrEqual(44);
    expect((imageBox?.x ?? 0) + (imageBox?.width ?? 0)).toBeLessThanOrEqual(
      priceBox?.x ?? 0
    );
    expect((productBox?.x ?? 0) + (productBox?.width ?? 0)).toBeLessThanOrEqual(
      actionBox?.x ?? 0
    );
  });

  test('dims the sold-out colour while keeping it accessible', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 844 });
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
    await expect(green).toHaveClass(/aa-choice-pill/);
    // Uses aria-disabled, NOT the native disabled attribute (stays focusable).
    expect(await green.evaluate(el => (el as HTMLButtonElement).disabled)).toBe(
      false
    );
    const unavailableTransform = await green.evaluate(
      el => getComputedStyle(el).transform
    );
    await green.hover();
    await expect
      .poll(() => green.evaluate(el => getComputedStyle(el).transform))
      .toBe(unavailableTransform);

    // Red is in stock → selectable, not dimmed.
    const red = option('red');
    await expect(red).not.toHaveClass(/is-unavailable/);
    await expect(red).not.toHaveAttribute('aria-disabled', 'true');
    await expect(red).toHaveAttribute('aria-pressed', 'false');
    await expect(red).toHaveClass(/aa-choice-pill/);
    const redBox = await red.boundingBox();
    expect(redBox?.width).toBeGreaterThanOrEqual(44);
    expect(redBox?.height).toBeGreaterThanOrEqual(44);

    // Return with the keyboard so Chromium applies :focus-visible rather than
    // the pointer-focus state left by the unavailable-option hover check.
    await red.focus();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Shift+Tab');
    await expect(red).toBeFocused();
    await expect
      .poll(() => red.evaluate(el => getComputedStyle(el).boxShadow))
      .not.toBe('none');

    // Selection is exposed to assistive technology, not only through paint.
    await red.click();
    await expect(red).toHaveClass(/is-selected/);
    await expect(red).toHaveAttribute('aria-pressed', 'true');

    // The purchase decision has one visually dominant action. Buy Now is the
    // filled primary; Add to Cart is the secondary outline and fills only when
    // the shopper shows intent.
    const addToCart = page.locator(
      '.aa-sticky-cart__drawer-add.aggressive-apparel-button--outline'
    );
    const buyNow = page.locator(
      '.aa-sticky-cart__drawer-buy-now.aggressive-apparel-button--primary'
    );
    await expect(addToCart).toBeVisible();
    await expect(buyNow).toBeVisible();
    for (const action of [addToCart, buyNow]) {
      const shape = await action.evaluate(el => {
        const style = getComputedStyle(el);
        return {
          height: el.getBoundingClientRect().height,
          radius: Number.parseFloat(style.borderTopLeftRadius),
          weight: style.fontWeight,
        };
      });
      expect(shape.radius).toBeGreaterThanOrEqual(shape.height / 2);
      expect(Number(shape.weight)).toBeGreaterThanOrEqual(700);
    }
    await expect
      .poll(() => buyNow.evaluate(el => getComputedStyle(el).backgroundColor))
      .not.toBe('rgba(0, 0, 0, 0)');
    await expect
      .poll(() =>
        addToCart.evaluate(el => getComputedStyle(el).backgroundColor)
      )
      .toBe('rgba(0, 0, 0, 0)');
    await addToCart.hover();
    await expect
      .poll(() =>
        addToCart.evaluate(el => getComputedStyle(el).backgroundColor)
      )
      .not.toBe('rgba(0, 0, 0, 0)');

    const close = page.locator('.aa-sticky-cart__drawer-close.aa-icon-button');
    const closeBox = await close.boundingBox();
    expect(closeBox?.width).toBeGreaterThanOrEqual(44);
    expect(closeBox?.height).toBeGreaterThanOrEqual(44);
    const closeRest = await close.evaluate(el => {
      const style = getComputedStyle(el);
      return {
        background: style.backgroundColor,
        color: style.color,
      };
    });
    await close.hover();
    await expect
      .poll(() => close.evaluate(el => getComputedStyle(el).backgroundColor))
      .toBe(closeRest.background);
    await expect
      .poll(() => close.evaluate(el => getComputedStyle(el).color))
      .not.toBe(closeRest.color);
    await close.focus();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Shift+Tab');
    await expect(close).toBeFocused();
    await expect
      .poll(() => close.evaluate(el => getComputedStyle(el).boxShadow))
      .not.toBe('none');

    // Activating the sold-out option is rejected (it never becomes selected).
    await green.dispatchEvent('click');
    await expect(green).not.toHaveClass(/is-selected/);
  });
});
