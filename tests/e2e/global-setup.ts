import { chromium, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { ensureCatalogCursorFixtures } from './catalog-fixtures';

/**
 * Log in as admin once and persist the session so specs start authenticated
 * (the store is in "Coming Soon" mode — only logged-in admins see the front end).
 */
export default async function globalSetup(_config: FullConfig): Promise<void> {
  const base = process.env.WP_BASE_URL ?? 'http://localhost:9910';
  const user = process.env.WP_ADMIN_USER ?? 'admin';
  const pass = process.env.WP_ADMIN_PASS ?? 'password';

  mkdirSync('tests/e2e/.auth', { recursive: true });

  ensureCatalogCursorFixtures();

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${base}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  await page.context().storageState({ path: 'tests/e2e/.auth/admin.json' });
  await browser.close();
}
