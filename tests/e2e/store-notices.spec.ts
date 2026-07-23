/**
 * E2E for the store-notices toast block.
 *
 * Unit tests cover the sanitiser and the timer/state logic in isolation; this
 * spec exercises the parts that need a real browser + real WooCommerce markup:
 * the `captureBlockNotices` MutationObserver bridge that lifts WooCommerce's own
 * notices into the toast stack, the sanitised rendering of a preserved action
 * link, neutralisation of an injected XSS payload, hiding of the original
 * notice, and manual dismissal.
 *
 * The block is inserted into a normal page (not a WC template) so the test does
 * not depend on catalog fixtures; WooCommerce notices are simulated by injecting
 * Woo's exact notice markup into the DOM, which is what the bridge observes.
 */

import { test, expect, type Page } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

/** Insert a store-notices block (bridge on) into a fresh page and publish it. */
async function insertNoticesPage(
  page: Page,
  attrs: Record<string, unknown>
): Promise<{ id: number; url: string }> {
  await openPageEditor(page);

  await page.evaluate(blockAttrs => {
    const { createBlock } = window.wp.blocks;
    const { insertBlocks } = window.wp.data.dispatch('core/block-editor');
    return insertBlocks(
      createBlock('aggressive-apparel/store-notices', blockAttrs)
    );
  }, attrs);

  return publishAndGetUrl(page);
}

/** Inject a WooCommerce block-style notice banner into the page body. */
async function injectWooBanner(
  page: Page,
  variant: 'is-error' | 'is-success' | 'is-info',
  contentHtml: string
): Promise<void> {
  await page.evaluate(
    ({ variant: v, contentHtml: html }) => {
      const banner = document.createElement('div');
      banner.className = `wc-block-components-notice-banner ${v}`;
      banner.setAttribute('data-test-origin', 'woo');
      const content = document.createElement('div');
      content.className = 'wc-block-components-notice-banner__content';
      content.innerHTML = html;
      banner.appendChild(content);
      document.body.appendChild(banner);
    },
    { variant, contentHtml }
  );
}

test.describe('store-notices toasts', () => {
  test('bridges a Woo notice, preserves a safe link, and hides the original', async ({
    page,
  }) => {
    const { id, url } = await insertNoticesPage(page, {
      captureBlockNotices: true,
      position: 'top-right',
      errorDuration: 0,
    });

    try {
      await page.goto(url);
      // Wait for the block region to hydrate so the bridge is active.
      await page.waitForSelector('.aa-notices', { state: 'attached' });
      await page.waitForTimeout(500);

      // Use an ABSOLUTE same-origin URL, matching how WooCommerce actually
      // builds notice links (wc_get_cart_url() is absolute, not relative).
      const cartUrl = new URL('/cart/', page.url()).href;
      await injectWooBanner(
        page,
        'is-error',
        `Coupon invalid. <a href="${cartUrl}">View cart</a>`
      );

      const toast = page.locator('.aa-notices__toast--error');
      await expect(toast).toBeVisible();
      await expect(toast).toContainText('Coupon invalid.');

      // The same-origin action link is preserved and hardened.
      const link = toast.locator('a');
      await expect(link).toHaveAttribute('href', cartUrl);
      await expect(link).toHaveAttribute('rel', /noopener/);

      // Errors are announced via the assertive live region (plain text).
      await expect(
        page.locator('.aa-notices [data-aa-live="assertive"]')
      ).toContainText('Coupon invalid.');

      // WooCommerce's own banner is hidden in favour of the toast.
      await expect(
        page.locator(
          '.wc-block-components-notice-banner[data-test-origin="woo"]'
        )
      ).toBeHidden();
    } finally {
      await deletePage(page, id);
    }
  });

  test('neutralises an injected XSS payload while keeping the text', async ({
    page,
  }) => {
    const { id, url } = await insertNoticesPage(page, {
      captureBlockNotices: true,
      position: 'top-right',
      errorDuration: 0,
    });

    try {
      await page.goto(url);
      await page.waitForSelector('.aa-notices', { state: 'attached' });
      await page.waitForTimeout(500);

      await injectWooBanner(
        page,
        'is-error',
        'Bad <a href="javascript:alert(1)">click</a>' +
          '<img src="x" onerror="window.__xss=1">'
      );

      const toast = page.locator('.aa-notices__toast--error');
      await expect(toast).toBeVisible();
      await expect(toast).toContainText('click');

      // No dangerous image, no executed handler, no javascript: href.
      await expect(toast.locator('img')).toHaveCount(0);
      const xssFired = await page.evaluate(
        () => (window as Window & { __xss?: unknown }).__xss
      );
      expect(xssFired).toBeUndefined();
      const href = await toast.locator('a').getAttribute('href');
      expect(href === null || !/javascript:/i.test(href)).toBe(true);
    } finally {
      await deletePage(page, id);
    }
  });

  test('dismiss button removes the toast', async ({ page }) => {
    const { id, url } = await insertNoticesPage(page, {
      captureBlockNotices: true,
      position: 'top-right',
      errorDuration: 0,
    });

    try {
      await page.goto(url);
      await page.waitForSelector('.aa-notices', { state: 'attached' });
      await page.waitForTimeout(500);

      await injectWooBanner(page, 'is-success', 'Product added to your cart.');

      const toast = page.locator('.aa-notices__toast--success');
      await expect(toast).toBeVisible();

      await toast.getByRole('button').click();
      await expect(toast).toHaveCount(0);
    } finally {
      await deletePage(page, id);
    }
  });
});
