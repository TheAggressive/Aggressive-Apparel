import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { wpCli } from './wp-cli';

let pageId = 0;
let url = '';
let products: { id: number; name: string; url: string }[] = [];

test.beforeAll(() => {
  const fixture = JSON.parse(
    wpCli([
      'eval',
      `
    $id = wp_insert_post(array(
      'post_type' => 'page', 'post_status' => 'publish',
      'post_title' => 'E2E Recently Viewed',
      'post_content' => '<!-- wp:aggressive-apparel/recently-viewed /-->',
    ));
    echo wp_json_encode(array(
      'id' => $id, 'url' => get_permalink($id),
      'products' => array_map(static fn($p) => array('id' => $p->get_id(), 'name' => $p->get_name(), 'url' => $p->get_permalink()), wc_get_products(array('limit' => 2, 'status' => 'publish', 'type' => 'simple'))),
    ));
  `,
    ])
  ) as { id: number; url: string; products: typeof products };
  pageId = fixture.id;
  url = fixture.url;
  products = fixture.products;
  expect(products).toHaveLength(2);
});

test.afterAll(() => {
  if (pageId) wpCli(['post', 'delete', String(pageId), '--force']);
});

test.use({ storageState: { cookies: [], origins: [] } });

test('renders real Store API history in viewing order and exposes accessible links', async ({
  page,
}) => {
  await page.addInitScript(
    ids =>
      localStorage.setItem(
        'aggressive_apparel_recently_viewed',
        JSON.stringify(ids)
      ),
    products.map(p => p.id)
  );
  await page.goto(url);
  const grid = page.locator('.aggressive-apparel-recently-viewed__grid');
  await expect(
    grid.locator('.aggressive-apparel-recently-viewed__name')
  ).toHaveText(products.map(p => p.name));
  await expect(grid.locator('a').first()).toHaveAttribute(
    'href',
    products[0].url
  );
  const { violations } = await new AxeBuilder({ page })
    .include('.aggressive-apparel-recently-viewed')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
    .analyze();
  expect(violations).toEqual([]);
});

test('keeps corrupt history hidden without page errors', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(() =>
    localStorage.setItem('aggressive_apparel_recently_viewed', '{invalid json')
  );
  await page.goto(url);
  await expect(
    page.locator('.aggressive-apparel-recently-viewed__title')
  ).toBeHidden();
  expect(errors).toEqual([]);
});

test('keeps failed responses hidden without page errors', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(
    ids =>
      localStorage.setItem(
        'aggressive_apparel_recently_viewed',
        JSON.stringify(ids)
      ),
    products.map(p => p.id)
  );
  await page.route('**/wc/store/v1/products?**', route =>
    route.fulfill({ status: 503, contentType: 'application/json', body: '{}' })
  );
  const response = page.waitForResponse(
    r => r.url().includes('/wc/store/v1/products?') && r.status() === 503
  );
  await page.goto(url);
  await response;
  await expect(
    page.locator('.aggressive-apparel-recently-viewed__title')
  ).toBeHidden();
  expect(errors).toEqual([]);
});
