/**
 * E2E for the checkout field contract.
 *
 * The block checkout is the reference for the site's field design: every
 * other form reads the same --aa-field-* tokens. This spec locks the checkout
 * side of that contract so a token or cascade change can't silently regress
 * it: the text inputs and the State/Country select container paint the shared
 * fill, radius and 3px left bar; labels keep the uppercase field type and
 * still float (WooCommerce's floating-label behaviour); focus turns the bar
 * accent; and WooCommerce's `.has-error` turns it to the error colour.
 *
 * Expected values are resolved from the live tokens on the same page, so the
 * spec asserts "checkout uses the shared field", not specific colours.
 */

import { test, expect, type Page } from '@playwright/test';
import {
  CHOICE_GAP_TOLERANCE_PX,
  choiceGapPx,
  measureChoiceGaps,
} from './choice-gap';
import { wpCli } from './wp-cli';

const FIXTURE_OWNER_META = '_aa_e2e_fixture_owner';
const FIXTURE_OWNER = 'checkout-field-contract';

let productId = 0;
let shippingInstanceId = 0;

test.beforeAll(() => {
  productId = Number(
    wpCli([
      'eval',
      `$product = new WC_Product_Simple();
       $product->set_name('E2E Checkout Field Product');
       $product->set_regular_price('19');
       $product->set_status('publish');
       $id = $product->save();
       update_post_meta($id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}');
       echo $id;`,
    ])
  );
  expect(productId).toBeGreaterThan(0);

  // A shipping method gives checkout its shipping step, and with it
  // WooCommerce's "Use same address for billing" checkbox, so the choice-gap
  // check below has a checkbox whatever optional blocks the template keeps.
  shippingInstanceId = Number(
    wpCli([
      'eval',
      `echo ( new WC_Shipping_Zone( 0 ) )->add_shipping_method( 'flat_rate' );`,
    ])
  );
  expect(shippingInstanceId).toBeGreaterThan(0);
});

test.afterAll(() => {
  if (shippingInstanceId) {
    wpCli([
      'eval',
      `( new WC_Shipping_Zone( 0 ) )->delete_shipping_method( ${shippingInstanceId} );`,
    ]);
  }
  wpCli([
    'eval',
    `foreach (get_posts(array('post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '${FIXTURE_OWNER_META}', 'meta_value' => '${FIXTURE_OWNER}')) as $id) { wp_delete_post($id, true); }`,
  ]);
});

async function addToCart(page: Page, id: number): Promise<void> {
  const status = await page.evaluate(async productId => {
    const base = `${window.location.origin}/wp-json/wc/store/v1`;
    const nonce = (await fetch(`${base}/cart`)).headers.get('Nonce') ?? '';
    const response = await fetch(`${base}/cart/add-item`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Nonce: nonce },
      body: JSON.stringify({ id: productId, quantity: 1 }),
    });
    return response.status;
  }, id);
  expect(status).toBeLessThan(300);
}

/** Resolve a token to its computed value on a throwaway probe element. */
async function token(page: Page, prop: string, value: string): Promise<string> {
  return page.evaluate(
    ([p, v]) => {
      const probe = document.createElement('div');
      probe.style.setProperty(p, v);
      document.body.appendChild(probe);
      const resolved = getComputedStyle(probe).getPropertyValue(p);
      probe.remove();
      return resolved;
    },
    [prop, value]
  );
}

test.describe('checkout field contract', () => {
  test('checkout fields use the shared field tokens and keep floating labels', async ({
    page,
  }) => {
    await page.goto('/');
    await addToCart(page, productId);
    await page.goto('/checkout/');
    await page.waitForSelector('#email');

    const fieldBg = await token(page, 'background-color', 'var(--aa-field-bg)');
    const fieldRadius = await token(
      page,
      'border-top-left-radius',
      'var(--aa-field-radius)'
    );
    const barFocus = await token(page, 'color', 'var(--aa-field-bar-focus)');
    const barInvalid = await token(
      page,
      'color',
      'var(--aa-field-bar-invalid)'
    );

    const field = await page.evaluate(() => {
      const read = (selector: string) => {
        const el = document.querySelector(selector);
        if (!el) {
          throw new Error(`Missing ${selector}`);
        }
        const cs = getComputedStyle(el);
        return {
          background: cs.backgroundColor,
          radius: cs.borderTopLeftRadius,
          barWidth: cs.borderLeftWidth,
          topWidth: cs.borderTopWidth,
        };
      };
      const label = getComputedStyle(
        document.querySelector('label[for="email"]') as Element
      );
      return {
        email: read('#email'),
        select: read('.wc-blocks-components-select__container'),
        labelTransform: label.textTransform,
      };
    });

    for (const control of [field.email, field.select]) {
      expect(control.background).toBe(fieldBg);
      expect(control.radius).toBe(fieldRadius);
      expect(control.barWidth).toBe('3px');
      expect(control.topWidth).toBe('0px');
    }
    expect(field.labelTransform).toBe('uppercase');

    // Checkout checkboxes are the reference for --aa-field-choice-gap, the
    // spacing every other form's choices are held to.
    const choiceGap = await choiceGapPx(page);
    const checkoutChoices = await measureChoiceGaps(page, '.wc-block-checkout');
    expect(checkoutChoices.length).toBeGreaterThan(0);
    for (const { name, gap } of checkoutChoices) {
      expect
        .soft(Math.abs(gap - choiceGap), `${name}: ${gap}px from its text`)
        .toBeLessThanOrEqual(CHOICE_GAP_TOLERANCE_PX);
    }

    // Focus: the bar turns accent and the label floats (scaled down). The bar
    // animates (checkout.css), so poll for the end state.
    const barColor = () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('#email') as Element)
            .borderLeftColor
      );
    await page.focus('#email');
    await page.keyboard.type('shopper@example.com');
    await expect.poll(barColor).toBe(barFocus);
    expect(
      await page.evaluate(
        () =>
          getComputedStyle(
            document.querySelector('label[for="email"]') as Element
          ).scale
      )
    ).toBe('0.85');

    // WooCommerce's validation state turns the bar to the error colour.
    await page.evaluate(() =>
      document
        .querySelector('#email')
        ?.closest('.wc-block-components-text-input')
        ?.classList.add('has-error')
    );
    await expect.poll(barColor).toBe(barInvalid);
  });
});
