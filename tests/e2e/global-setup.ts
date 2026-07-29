import { chromium, type FullConfig } from '@playwright/test';
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
