import { expect, test, type Page } from '@playwright/test';
import {
  FREE_SHIPPING_COUNTRY,
  NO_FREE_SHIPPING_COUNTRY,
  PRODUCT_PRICE,
  THRESHOLD,
  createFreeShippingFixture,
  deleteFreeShippingFixture,
  setGuestCountry,
  type FreeShippingFixture,
} from './free-shipping-fixtures';

/**
 * Browser contract for international free-shipping progress: the threshold
 * follows the customer's shipping zone, formats in the store currency, hides
 * (not vanishes) where no free shipping exists, and costs no Store API
 * request while the cart is empty.
 */

declare global {
  interface Window {
    __aaCartFetchStacks?: string[];
  }
}

function euro(amount: number): string {
  return `${amount.toFixed(2).replace('.', ',')} €`;
}

/** The fixture page's own block (headers may carry more instances). */
function block(page: Page) {
  return page.locator(
    '.entry-content [data-wp-interactive="aggressive-apparel/free-shipping-message"]'
  );
}

/** Store API mutation from the page, so the theme's fetch hook observes it. */
async function storeApiPost(
  page: Page,
  route: string,
  body: Record<string, unknown>
): Promise<void> {
  const status = await page.evaluate(
    async ({ route: path, body: payload }) => {
      const base = `${window.location.origin}/wp-json/wc/store/v1`;
      const nonce = (await fetch(`${base}/cart`)).headers.get('Nonce') ?? '';
      const response = await fetch(`${base}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Nonce: nonce },
        body: JSON.stringify(payload),
      });
      return response.status;
    },
    { route, body }
  );

  expect(status, `POST ${route}`).toBeLessThan(300);
}

test.describe('Free shipping — international zones', () => {
  // Guests: a logged-in customer's saved address outranks the store default
  // location this suite steers.
  test.use({ storageState: { cookies: [], origins: [] } });

  let fixture: FreeShippingFixture | undefined;

  test.beforeEach(() => {
    fixture = createFreeShippingFixture();
  });

  test.afterEach(() => {
    if (fixture) {
      deleteFreeShippingFixture();
      fixture = undefined;
    }
  });

  test('renders the zone threshold in store currency without a cart request', async ({
    page,
  }) => {
    // WooCommerce's own mini-cart store fetches the cart too, so attribute
    // each cart fetch by call stack rather than counting URLs.
    await page.addInitScript(() => {
      const nativeFetch = window.fetch;
      window.__aaCartFetchStacks = [];
      window.fetch = function (input, init) {
        const url = input instanceof Request ? input.url : String(input);
        if (url.includes('/wc/store/v1/cart')) {
          window.__aaCartFetchStacks?.push(new Error().stack ?? '');
        }
        return nativeFetch.call(this, input, init);
      };
    });

    await page.goto(fixture!.pageUrl);
    await page.waitForLoadState('networkidle');

    await expect(block(page)).toBeVisible();
    await expect(block(page)).toContainText(
      `${euro(THRESHOLD)} Away from FREE Shipping!`
    );

    const stacks = await page.evaluate(() => window.__aaCartFetchStacks ?? []);
    expect(stacks.filter(stack => stack.includes('free-shipping'))).toEqual([]);
  });

  test('hides the block for a zone without free shipping', async ({ page }) => {
    setGuestCountry(NO_FREE_SHIPPING_COUNTRY);

    await page.goto(fixture!.pageUrl);

    await expect(block(page)).toHaveCount(1);
    await expect(block(page)).toBeHidden();
  });

  test('reveals the block live when the cart moves into a free-shipping zone', async ({
    page,
  }) => {
    setGuestCountry(NO_FREE_SHIPPING_COUNTRY);
    await page.goto(fixture!.pageUrl);
    // Mutations are only observed once the store has hydrated.
    await page.waitForLoadState('networkidle');
    await expect(block(page)).toBeHidden();

    await storeApiPost(page, '/cart/add-item', {
      id: fixture!.productId,
      quantity: 1,
    });
    await storeApiPost(page, '/cart/update-customer', {
      shipping_address: { country: FREE_SHIPPING_COUNTRY },
    });

    await expect(block(page)).toBeVisible();
    await expect(block(page)).toContainText(
      `${euro(THRESHOLD - PRODUCT_PRICE)} Away from FREE Shipping!`
    );
  });
});
