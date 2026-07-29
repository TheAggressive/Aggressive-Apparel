import { chromium, type FullConfig, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { ensureCatalogCursorFixtures } from './catalog-fixtures';
import { wpCli } from './wp-cli';

interface ThemeRecord {
  name: string;
  status: string;
}

/** Activate the mounted project theme in a fresh wp-env installation. */
function ensureProjectThemeActive(): void {
  const themes = JSON.parse(
    wpCli(['theme', 'list', '--format=json'])
  ) as ThemeRecord[];
  const projectTheme = themes.find(
    theme => theme.name.toLowerCase() === 'aggressive-apparel'
  );

  if (!projectTheme) {
    throw new Error(
      'Aggressive Apparel is not installed in the Playwright wp-env site.'
    );
  }

  if (projectTheme.status !== 'active') {
    wpCli(['theme', 'activate', projectTheme.name]);
  }

  const activeStylesheet = wpCli(['option', 'get', 'stylesheet']);
  if (activeStylesheet !== projectTheme.name) {
    throw new Error(
      `Expected ${projectTheme.name} to be active, found ${activeStylesheet}.`
    );
  }
}

/**
 * Verify the public storefront through the same HTTP/browser boundary used by
 * the specs. WP-CLI checks alone cannot detect a missing Apache rewrite.
 */
async function ensurePublicCatalogReady(
  page: Page,
  base: string,
  shopPageId: number
): Promise<void> {
  const shopUrl = new URL('/shop/', base).toString();
  const response = await page.goto(shopUrl, {
    waitUntil: 'domcontentloaded',
  });

  if (!response) {
    throw new Error(`The E2E Shop page did not return a response: ${shopUrl}`);
  }

  if (!response.ok()) {
    throw new Error(
      `The E2E Shop page (ID ${shopPageId}) returned HTTP ${response.status()}: ${shopUrl}`
    );
  }

  const productCard = page
    .locator('.wp-block-woocommerce-product-template > .wc-block-product')
    .first();
  const sentinel = page.locator('.aa-load-more__sentinel');

  try {
    await productCard.waitFor({ state: 'attached', timeout: 15_000 });
    await sentinel.waitFor({ state: 'attached', timeout: 15_000 });
  } catch {
    throw new Error(
      `The public E2E Shop page is missing its product cards or infinite-scroll sentinel: ${shopUrl}`
    );
  }
}

/**
 * Log in as admin once and persist the session so specs start authenticated
 * for editor coverage. Catalog fixtures explicitly launch the isolated store so
 * anonymous storefront specs exercise the same public routes as production.
 */
export default async function globalSetup(_config: FullConfig): Promise<void> {
  const base = process.env.WP_BASE_URL ?? 'http://localhost:9910';
  const user = process.env.WP_ADMIN_USER ?? 'admin';
  const pass = process.env.WP_ADMIN_PASS ?? 'password';

  mkdirSync('tests/e2e/.auth', { recursive: true });

  ensureProjectThemeActive();
  const { shopPageId } = ensureCatalogCursorFixtures();

  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    await ensurePublicCatalogReady(page, base, shopPageId);
    await page.goto(`${base}/wp-login.php`);
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await page.context().storageState({ path: 'tests/e2e/.auth/admin.json' });
  } finally {
    await browser.close();
  }
}
