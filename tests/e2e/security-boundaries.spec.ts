import { expect, test } from '@playwright/test';
import { buildRestEndpoint, openPageEditor } from './helpers';

test('REST cookie authentication requires a valid nonce for privileged routes', async ({
  page,
}) => {
  await openPageEditor(page);
  const settings = await page.evaluate(() => ({
    root: window.wpApiSettings!.root!,
    nonce: window.wpApiSettings!.nonce!,
  }));
  const endpoint = buildRestEndpoint(
    settings.root,
    'aggressive-apparel/v1/icons'
  );
  const missing = await page.request.get(endpoint);
  expect(missing.status()).toBe(401);
  const invalid = await page.request.get(endpoint, {
    headers: { 'X-WP-Nonce': 'invalid' },
  });
  expect(invalid.status()).toBe(403);
  const authorized = await page.request.get(endpoint, {
    headers: { 'X-WP-Nonce': settings.nonce },
  });
  expect(authorized.status()).toBe(200);
});

test('signed-in frontend search sends its REST nonce', async ({ page }) => {
  await page.goto('/');
  await page.locator('.aa-search-trigger:visible').first().click();
  const request = page.waitForRequest(r =>
    r.url().includes('/aggressive-apparel/v1/search?')
  );
  await page.locator('.aa-search__input').fill('E2E');
  expect((await request).headers()['x-wp-nonce']).toBeTruthy();
});

test.describe('public storefront security boundaries', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('guest frontend search needs no nonce from the cached page', async ({
    page,
  }) => {
    await page.goto('/');
    await page.locator('.aa-search-trigger:visible').first().click();
    const request = page.waitForRequest(r =>
      r.url().includes('/aggressive-apparel/v1/search?')
    );
    await page.locator('.aa-search__input').fill('E2E');
    expect((await request).headers()['x-wp-nonce']).toBeUndefined();
  });

  test('quoted filter selections remain data rather than event attributes', async ({
    page,
  }) => {
    const payload = `kids" onpointerover="window.__aaInjected=1`;
    await page.goto(`/shop/?cat=${encodeURIComponent(payload)}`);
    const pills = page.locator('.aa-filter-active-bar__pills');
    await expect(pills.first()).toBeAttached();
    await expect(pills.locator('[onpointerover]')).toHaveCount(0);
    const values = await pills
      .locator('[data-filter-slug]')
      .evaluateAll(elements =>
        elements.map(el => el.getAttribute('data-filter-slug'))
      );
    expect(values).toContain(payload);
  });

  test('emits hardening headers and keeps private search responses out of shared caches', async ({
    page,
  }) => {
    const response = await page.goto('/');
    expect(response!.headers()['x-content-type-options']).toBe('nosniff');
    expect(response!.headers()['x-frame-options']).toBe('SAMEORIGIN');
    const search = await page.request.get(
      '/wp-json/aggressive-apparel/v1/search?query=E2E&scope=product'
    );
    expect(search.status()).toBe(200);
    expect(search.headers()['cache-control']).toContain('no-store');
    expect(search.headers()['cache-control']).toContain('private');
  });
});
