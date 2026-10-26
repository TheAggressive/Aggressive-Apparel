import { test, expect, type Page } from '@playwright/test';
import { ownPostPhp } from './fixture-cleanup';
import { wpCli } from './wp-cli';

const storageKey = 'aggressive_apparel_wishlist';
let fixture: {
  page: number;
  cartPage: number;
  simple: number;
  variable: number;
};

/** Real rendered blocks and product metadata; intercepted account writes are isolated. */
test.beforeAll(() => {
  fixture = JSON.parse(
    wpCli([
      'eval',
      `
    $simple = new WC_Product_Simple();
    $simple->set_name('Wishlist browser fixture');
    $simple->set_status('publish');
    $simple->set_regular_price('20');
    $simple->save();
    $variable = new WC_Product_Variable();
    $variable->set_name('Wishlist options fixture');
    $variable->set_status('publish');
    $variable->save();
    $content = '<!-- wp:html -->' . \\Aggressive_Apparel\\WooCommerce\\Wishlist::get_heart_button_html($simple->get_id()) . \\Aggressive_Apparel\\WooCommerce\\Wishlist::get_heart_button_html($variable->get_id()) . '<!-- /wp:html -->' . \\Aggressive_Apparel\\WooCommerce\\Wishlist::get_default_page_content();
    $page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Wishlist browser fixture', 'post_content' => $content));
    $cart_content = str_replace('wishlist-item-actions /-->', 'wishlist-item-actions {"showAddToCart":true} /-->', $content);
    $cart_page = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Wishlist cart fallback fixture', 'post_content' => $cart_content));
    foreach (array($page, $cart_page, $simple->get_id(), $variable->get_id()) as $id) {
      ${ownPostPhp('$id', 'wishlist')}
    }
    echo wp_json_encode(array('page' => $page, 'cartPage' => $cart_page, 'simple' => $simple->get_id(), 'variable' => $variable->get_id()));
  `,
    ])
  );
});

test.afterAll(() => {
  if (fixture)
    wpCli([
      'post',
      'delete',
      String(fixture.page),
      String(fixture.cartPage),
      String(fixture.simple),
      String(fixture.variable),
      '--force',
    ]);
});

const heart = (page: Page, id: number) =>
  page
    .locator(`.aggressive-apparel-wishlist__toggle[data-aa-product-id="${id}"]`)
    .first();
const open = async (page: Page) => {
  await page.goto(`/?page_id=${fixture.page}`);
  await expect(page.locator('.aa-wishlist-page__loading').first()).toBeHidden();
};
const savedProduct = () => ({
  id: fixture.simple,
  product_id: fixture.simple,
  key: 'browser-fixture',
  name: 'Wishlist browser fixture',
  permalink: null,
  prices: null,
});

test('guest hearts persist across reloads without a routine notice', async ({
  browser,
  baseURL,
}) => {
  const context = await browser.newContext({
    baseURL,
    storageState: { cookies: [], origins: [] },
  });
  try {
    const page = await context.newPage();
    await open(page);
    await heart(page, fixture.simple).click();
    await expect(heart(page, fixture.simple)).toHaveAttribute(
      'aria-pressed',
      'true'
    );
    await expect
      .poll(() => page.evaluate(key => localStorage.getItem(key), storageKey))
      .toBe(JSON.stringify([fixture.simple]));
    await page.reload();
    await expect(heart(page, fixture.simple)).toHaveAttribute(
      'aria-pressed',
      'true'
    );
    await expect(page.locator('[data-aa-wishlist-feedback]')).toBeHidden();
    await expect(page.locator('.aa-wishlist-page__card')).toHaveCount(1);
  } finally {
    await context.close();
  }
});

test('variable account saves stay local and never navigate or POST a parent', async ({
  page,
}) => {
  let posts = 0;
  await page.route('**/shopper-lists/wishlist/items**', route => {
    if (route.request().method() === 'POST') posts++;
    return route.fulfill({ json: [] });
  });
  await open(page);
  const originalUrl = page.url();
  await heart(page, fixture.variable).click();
  await expect(heart(page, fixture.variable)).toHaveAttribute(
    'aria-pressed',
    'true'
  );
  await expect(page.locator('.aa-wishlist-page__card')).toHaveCount(1);
  await expect(page.locator('.aa-wl-item-price__storage')).toHaveText(
    'Saved on this device'
  );
  await expect(page.locator('.aa-wl-item-actions__view')).toContainText(
    'Choose options'
  );
  expect(page.url()).toBe(originalUrl);
  await page.reload();
  await expect(heart(page, fixture.variable)).toHaveAttribute(
    'aria-pressed',
    'true'
  );
  expect(posts).toBe(0);
});

test('a slow account save paints immediately and ignores repeat clicks', async ({
  page,
}) => {
  let finish!: () => void;
  const pending = new Promise<void>(resolve => {
    finish = resolve;
  });
  let posts = 0;
  await page.route('**/shopper-lists/wishlist/items**', async route => {
    if (route.request().method() === 'POST') {
      posts++;
      await pending;
      await route.fulfill({ status: 201, json: savedProduct() });
    } else await route.fulfill({ json: [] });
  });
  await open(page);
  await heart(page, fixture.simple).click();
  await expect(heart(page, fixture.simple)).toHaveAttribute(
    'aria-pressed',
    'true'
  );
  await expect(heart(page, fixture.simple)).toHaveAttribute(
    'aria-busy',
    'true'
  );
  await heart(page, fixture.simple).click();
  finish();
  await expect(heart(page, fixture.simple)).not.toHaveAttribute('aria-busy');
  expect(posts).toBe(1);
  await expect(page.locator('[data-aa-wishlist-feedback]')).toBeHidden();
});

test('failed local product details keep account cards visible and offer retry', async ({
  page,
}) => {
  await page.addInitScript(
    ({ key, id }) => localStorage.setItem(key, JSON.stringify([id])),
    { key: storageKey, id: fixture.variable }
  );
  await page.route('**/shopper-lists/wishlist/items**', route =>
    route.fulfill({ json: [savedProduct()] })
  );
  await page.route('**/wc/store/v1/products?**', route =>
    route.fulfill({ status: 503, json: { message: 'Unavailable' } })
  );
  await open(page);
  await expect(page.locator('.aa-wishlist-page__card')).toHaveCount(1);
  await expect(page.locator('.aa-wishlist-page__empty')).toBeHidden();
  await expect(
    page.getByRole('button', { name: 'Try again', exact: true })
  ).toBeVisible();
  await page.unroute('**/wc/store/v1/products?**');
  await page.getByRole('button', { name: 'Try again', exact: true }).click();
  await expect(page.locator('.aa-wishlist-page__card')).toHaveCount(2);
  await expect(
    page.getByRole('button', { name: 'Try again', exact: true })
  ).toBeHidden();
});

test('denied browser storage rolls back the heart and reports the failure', async ({
  page,
}) => {
  await page.route('**/shopper-lists/wishlist/items**', route =>
    route.fulfill({ json: [] })
  );
  await page.addInitScript(() => {
    Storage.prototype.setItem = () => {
      throw new DOMException('Storage blocked', 'QuotaExceededError');
    };
  });
  await open(page);
  await heart(page, fixture.variable).click();
  await expect(heart(page, fixture.variable)).not.toHaveAttribute('aria-busy');
  await expect(heart(page, fixture.variable)).toHaveAttribute(
    'aria-pressed',
    'false'
  );
  await expect(page.locator('[data-aa-wishlist-feedback]')).toContainText(
    'could not be updated'
  );
});

test('a timed-out write releases the heart and reconciles before another save', async ({
  page,
}) => {
  let posts = 0;
  let finish!: () => void;
  const pending = new Promise<void>(resolve => {
    finish = resolve;
  });
  await page.route('**/shopper-lists/wishlist/items**', async route => {
    if (route.request().method() === 'POST') {
      posts++;
      await pending;
      // The browser aborts the timed-out request; no second write is sent.
      await route
        .fulfill({ status: 201, json: savedProduct() })
        .catch(() => {});
    } else await route.fulfill({ json: posts ? [savedProduct()] : [] });
  });
  try {
    await open(page);
    await page.clock.install();
    await heart(page, fixture.simple).click();
    await expect.poll(() => posts).toBe(1);
    await page.clock.fastForward(15_001);
    await expect(heart(page, fixture.simple)).not.toHaveAttribute('aria-busy');
    await expect(heart(page, fixture.simple)).toHaveAttribute(
      'aria-pressed',
      'false'
    );
    await heart(page, fixture.simple).click();
    await expect(heart(page, fixture.simple)).not.toHaveAttribute('aria-busy');
    await expect(heart(page, fixture.simple)).toHaveAttribute(
      'aria-pressed',
      'true'
    );
    expect(posts).toBe(1);
  } finally {
    finish();
  }
});

test('Choose options remains available when Add to Cart is enabled', async ({
  page,
}) => {
  await page.route('**/shopper-lists/wishlist/items**', route =>
    route.fulfill({ json: [] })
  );
  await page.goto(`/?page_id=${fixture.cartPage}`);
  await heart(page, fixture.variable).click();
  await expect(page.locator('.aa-wl-item-actions__view')).toContainText(
    'Choose options'
  );
  await expect(page.locator('.aa-wl-item-actions__view')).toBeVisible();
  await expect(page.locator('.aa-wl-item-actions__atc')).toBeHidden();
});
