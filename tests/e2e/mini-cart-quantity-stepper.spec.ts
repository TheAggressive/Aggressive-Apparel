/**
 * E2E for the mini-cart quantity stepper.
 *
 * Two regressions this locks:
 *
 * 1. Clipping — the stepper's fixed 44px −/input/+ controls total ~128px, but
 *    inside the narrow mini-cart row the pill's flex parent squeezed it below
 *    that while `overflow: hidden` silently clipped the trailing "+" button off
 *    the right edge. The fix (`flex: 0 0 auto; min-width: max-content`) makes the
 *    pill hug its content so no control is ever clipped.
 *
 * 2. Shared styling — the mini-cart quantity selector must render identically to
 *    the product-form / cart / checkout one (single source of truth in
 *    woocommerce/blocks.css), not a drifted fork. We assert the tells of that
 *    shared rule: a centered, chromeless input.
 *
 * Injected as a fixture (like mini-cart-buttons.spec.ts) because the behaviour
 * under test is the CSS, not WooCommerce's PHP render path. The real theme
 * stylesheet (main.css, which owns the shared stepper rule) is live on the page;
 * the parent is deliberately constrained to reproduce the squeeze.
 */

import { test, expect, type Page } from '@playwright/test';

async function buildStepper(page: Page): Promise<void> {
  await page.evaluate(() => {
    const host = document.createElement('div');
    host.id = 'aa-e2e-stepper';
    host.className = 'wp-block-woocommerce-filled-mini-cart-contents-block';
    // Constrain the row so the pill's flex parent tries to squeeze it — the
    // exact condition that used to clip the "+" button.
    host.innerHTML = `
      <div class="wc-block-cart-item__quantity"
        style="display:flex;align-items:center;width:120px;overflow:visible;">
        <div class="wc-block-components-quantity-selector">
          <input class="wc-block-components-quantity-selector__input"
            type="number" value="12" min="1" max="99" aria-label="Quantity" />
          <button type="button"
            class="wc-block-components-quantity-selector__button wc-block-components-quantity-selector__button--minus"
            aria-label="Reduce quantity">&minus;</button>
          <button type="button"
            class="wc-block-components-quantity-selector__button wc-block-components-quantity-selector__button--plus"
            aria-label="Increase quantity">&plus;</button>
        </div>
      </div>`;
    document.body.appendChild(host);
  });
}

test('mini-cart quantity stepper: no clipped controls, PDP-shared styling', async ({
  page,
}) => {
  await page.goto('/');
  await buildStepper(page);

  const selector = page.locator(
    '#aa-e2e-stepper .wc-block-components-quantity-selector'
  );
  const minus = selector.locator(
    '.wc-block-components-quantity-selector__button--minus'
  );
  const plus = selector.locator(
    '.wc-block-components-quantity-selector__button--plus'
  );
  const input = selector.locator(
    '.wc-block-components-quantity-selector__input'
  );

  await expect(selector).toBeVisible();

  const [selBox, minusBox, plusBox, inputBox] = await Promise.all([
    selector.boundingBox(),
    minus.boundingBox(),
    plus.boundingBox(),
    input.boundingBox(),
  ]);

  // The pill is the 44px control; each −/+ is a 44px-wide tap target that fills
  // the pill's interior height (44px minus the 2px pill borders).
  expect(selBox?.height).toBeGreaterThanOrEqual(44);
  expect(minusBox?.width).toBeGreaterThanOrEqual(44);
  expect(plusBox?.width).toBeGreaterThanOrEqual(44);

  // The pill hugs its content despite the 120px parent (min-width:max-content),
  // rather than shrinking to the squeezed width the bug produced.
  expect(selBox?.width).toBeGreaterThanOrEqual(
    (minusBox?.width ?? 0) + (inputBox?.width ?? 0) + (plusBox?.width ?? 0)
  );

  // The trailing "+" is fully inside the pill — not clipped by overflow:hidden.
  const selRight = (selBox?.x ?? 0) + (selBox?.width ?? 0);
  const plusRight = (plusBox?.x ?? 0) + (plusBox?.width ?? 0);
  expect(plusRight).toBeLessThanOrEqual(selRight + 0.5);

  // Shared-styling tells: the input is centered and chromeless (same rule the
  // product form / cart / checkout use). Guards against a drifted fork.
  const inputStyle = await input.evaluate(el => {
    const cs = getComputedStyle(el);
    return { textAlign: cs.textAlign, borderTopWidth: cs.borderTopWidth };
  });
  expect(inputStyle.textAlign).toBe('center');
  expect(inputStyle.borderTopWidth).toBe('0px');
});
