import { test, expect, type Locator } from '@playwright/test';

interface ButtonPaint {
  background: string;
  borderColor: string;
  borderWidth: string;
  boxShadow: string;
  color: string;
  radius: number;
}

const buttonPaint = (button: Locator): Promise<ButtonPaint> =>
  button.evaluate(element => {
    const style = getComputedStyle(element);
    return {
      background: style.backgroundColor,
      borderColor: style.borderTopColor,
      borderWidth: style.borderTopWidth,
      boxShadow: style.boxShadow,
      color: style.color,
      radius: Number.parseFloat(style.borderTopLeftRadius),
    };
  });

test('mobile mini-cart actions use one adaptive button paint layer', async ({
  page,
}) => {
  await page.setViewportSize({ width: 500, height: 800 });
  await page.goto('/');

  await page.evaluate(() => {
    const fixture = document.createElement('section');
    fixture.id = 'aa-e2e-mini-cart';
    fixture.className =
      'wc-block-mini-cart__drawer wc-block-components-drawer is-mobile';
    Object.assign(fixture.style, {
      position: 'fixed',
      inset: 'auto 0 0 auto',
      transform: 'none',
      visibility: 'visible',
      width: '100%',
      height: 'auto',
      zIndex: '100002',
    });
    fixture.innerHTML = `
      <div class="wc-block-mini-cart__footer">
        <div class="wc-block-mini-cart__footer-actions">
          <a href="#cart" class="wc-block-components-button wp-element-button wc-block-mini-cart__footer-cart is-style-outline outlined">
            <span class="wc-block-components-button__text">View my cart</span>
          </a>
          <a href="#checkout" class="wc-block-components-button wp-element-button wc-block-mini-cart__footer-checkout">
            <span class="wc-block-components-button__text">Go to checkout</span>
          </a>
        </div>
      </div>`;
    document.body.appendChild(fixture);
  });

  const fixture = page.locator('#aa-e2e-mini-cart');
  const actions = fixture.locator('.wc-block-mini-cart__footer-actions');
  const cart = fixture.locator('.wc-block-mini-cart__footer-cart');
  const checkout = fixture.locator('.wc-block-mini-cart__footer-checkout');

  await expect(cart).toBeVisible();
  await expect(checkout).toBeVisible();

  const [cartBox, checkoutBox] = await Promise.all([
    cart.boundingBox(),
    checkout.boundingBox(),
  ]);
  expect(cartBox?.height).toBeGreaterThanOrEqual(44);
  expect(checkoutBox?.height).toBeGreaterThanOrEqual(44);
  expect(cartBox?.width).toBeGreaterThanOrEqual(180);
  expect(checkoutBox?.width).toBeGreaterThan(cartBox?.width ?? 0);
  expect((cartBox?.x ?? 0) + (cartBox?.width ?? 0)).toBeLessThanOrEqual(
    checkoutBox?.x ?? 0
  );

  const cartRest = await buttonPaint(cart);
  const checkoutRest = await buttonPaint(checkout);

  expect(cartRest.borderWidth).toBe('2px');
  expect(cartRest.borderColor).toBe(cartRest.color);
  expect(cartRest.boxShadow).toBe('none');
  expect(checkoutRest.borderWidth).toBe('2px');
  expect(checkoutRest.borderColor).toBe(checkoutRest.background);
  expect(checkoutRest.boxShadow).toBe('none');
  expect(cartRest.radius).toBeGreaterThanOrEqual((cartBox?.height ?? 0) / 2);
  expect(checkoutRest.radius).toBeGreaterThanOrEqual(
    (checkoutBox?.height ?? 0) / 2
  );

  await cart.hover();
  await expect
    .poll(async () => (await buttonPaint(cart)).background)
    .not.toBe(cartRest.background);
  expect((await buttonPaint(cart)).boxShadow).toBe('none');

  await checkout.hover();
  await expect
    .poll(async () => (await buttonPaint(checkout)).background)
    .not.toBe(checkoutRest.background);
  expect((await buttonPaint(checkout)).boxShadow).toBe('none');

  await cart.focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(cart).toBeFocused();
  await expect
    .poll(async () => (await buttonPaint(cart)).boxShadow)
    .not.toBe('none');

  await page.setViewportSize({ width: 320, height: 720 });
  await expect(actions).toHaveCSS('flex-direction', 'column');

  const [narrowCartBox, narrowCheckoutBox] = await Promise.all([
    cart.boundingBox(),
    checkout.boundingBox(),
  ]);
  expect(narrowCartBox?.width).toBeGreaterThanOrEqual(280);
  expect(narrowCheckoutBox?.width).toBeGreaterThanOrEqual(280);
  expect(
    (narrowCartBox?.y ?? 0) + (narrowCartBox?.height ?? 0)
  ).toBeLessThanOrEqual(narrowCheckoutBox?.y ?? 0);
});
