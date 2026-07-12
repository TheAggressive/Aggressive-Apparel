import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests that drive the block editor + rendered front end in the
 * running wp-env site. These guard real browser behavior (sticky/grid layout,
 * the card-flip 3D flip + inert a11y) that unit tests can't cover.
 *
 * Local: wp-env must be running (`pnpm env:start`), then `pnpm test:e2e`.
 * CI: run `npx playwright install --with-deps chromium` first.
 */
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['list']] : [['list']],
  globalSetup: './tests/e2e/global-setup.ts',
  use: {
    baseURL: process.env.WP_BASE_URL ?? 'http://localhost:9910',
    storageState: 'tests/e2e/.auth/admin.json',
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
