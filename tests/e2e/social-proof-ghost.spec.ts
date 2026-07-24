/**
 * E2E for the social-proof toast's hit-testing contract.
 *
 * The reported bug: after the toast cycled out it stayed in layout (opacity:0
 * but display:flex) with pointer-events:auto, leaving an invisible click- and
 * focus-catching "ghost" pinned to the bottom-left corner that stole every
 * interaction beneath it.
 *
 * PHPUnit locks the markup contract (aria-hidden, tabindex, no live region), but
 * only a real browser can prove the CSS hit-testing: a faded-out toast must be
 * transparent to clicks, and a visible one must intercept them. This spec loads
 * the REAL compiled social-proof stylesheet onto a plain page and drives the
 * real toast DOM through its visible → faded → visible lifecycle with real mouse
 * clicks. It injects markup (rather than standing up catalog fixtures + the
 * feature toggle + a product page) for the same reason store-notices.spec.ts
 * injects Woo's markup: the behaviour under test is the CSS, not the PHP render
 * path.
 */

import { test, expect, type Page } from '@playwright/test';
import { publishAndGetUrl, deletePage, openPageEditor } from './helpers';

/** Publish a blank page so we have a real front-end URL with theme CSS loaded. */
async function publishBlankPage(
  page: Page
): Promise<{ id: number; url: string }> {
  await openPageEditor(page);
  return publishAndGetUrl(page);
}

/**
 * Load the real compiled social-proof stylesheet (derived from the always-present
 * main theme stylesheet so there's no hard-coded theme slug), then build the real
 * toast DOM over a full-covering click target pinned to the same bottom-left area.
 */
async function buildToastOverTarget(page: Page): Promise<void> {
  await page.evaluate(async () => {
    // Derive the social-proof CSS URL from the main theme stylesheet link.
    const mainHref = [
      ...document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'),
    ]
      .map(l => l.href)
      .find(h => /\/build\/styles\/main\.css/.test(h));
    if (!mainHref) {
      throw new Error('Main theme stylesheet link not found on the page.');
    }
    const cssUrl = new URL(mainHref);
    cssUrl.pathname = cssUrl.pathname.replace(
      '/build/styles/main.css',
      '/build/styles/woocommerce/social-proof.css'
    );
    cssUrl.search = '';

    await new Promise<void>((resolve, reject) => {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = cssUrl.href;
      link.addEventListener('load', () => resolve());
      link.addEventListener('error', () =>
        reject(new Error('social-proof stylesheet failed to load'))
      );
      document.head.appendChild(link);
    });

    // Instrumentation for click routing.
    const win = window as Window & {
      __underClicks?: number;
      __toastClicks?: number;
    };
    win.__underClicks = 0;
    win.__toastClicks = 0;

    // Underlying click target covering the bottom-left region the toast sits in.
    const target = document.createElement('button');
    target.id = 'aa-e2e-under';
    target.textContent = 'Under';
    Object.assign(target.style, {
      position: 'fixed',
      left: '0',
      bottom: '0',
      width: '420px',
      height: '320px',
      zIndex: '1',
      margin: '0',
    });
    target.addEventListener('click', () => {
      win.__underClicks = (win.__underClicks ?? 0) + 1;
    });
    document.body.appendChild(target);

    // Real toast markup (mirrors render_toast_container: aria-hidden container,
    // tabindex=-1 controls). The __toast starts WITHOUT is-visible = the faded,
    // between-cycles "ghost" state we're guarding against.
    const container = document.createElement('div');
    container.className = 'aggressive-apparel-social-proof';
    container.setAttribute('aria-hidden', 'true');
    container.innerHTML = `
      <div class="aggressive-apparel-social-proof__toast" id="aa-e2e-toast">
        <a class="aggressive-apparel-social-proof__link" id="aa-e2e-toast-link" href="#" tabindex="-1">
          <div class="aggressive-apparel-social-proof__body">
            <p class="aggressive-apparel-social-proof__message">Someone just grabbed this</p>
          </div>
        </a>
        <button type="button" class="aggressive-apparel-social-proof__close" tabindex="-1" aria-label="Dismiss">&times;</button>
      </div>`;
    document.body.appendChild(container);

    const link = document.getElementById('aa-e2e-toast-link');
    link?.addEventListener('click', e => {
      e.preventDefault();
      win.__toastClicks = (win.__toastClicks ?? 0) + 1;
    });
  });

  // Confirm the real CSS actually applied before asserting behaviour.
  await expect
    .poll(() =>
      page.evaluate(() => {
        const el = document.getElementById('aa-e2e-toast');
        return el ? getComputedStyle(el).pointerEvents : null;
      })
    )
    .toBe('none');
}

/** Center point of the toast's on-screen box, measured in its visible state. */
async function toastCenter(page: Page): Promise<{ x: number; y: number }> {
  return page.evaluate(() => {
    const el = document.getElementById('aa-e2e-toast');
    if (!el) {
      throw new Error('Toast element missing.');
    }
    const prev = el.classList.contains('is-visible');
    el.classList.add('is-visible');
    const rect = el.getBoundingClientRect();
    if (!prev) {
      el.classList.remove('is-visible');
    }
    return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
  });
}

async function setVisible(page: Page, visible: boolean): Promise<void> {
  await page.evaluate(v => {
    document.getElementById('aa-e2e-toast')?.classList.toggle('is-visible', v);
  }, visible);
}

async function clickCounts(
  page: Page
): Promise<{ under: number; toast: number }> {
  return page.evaluate(() => {
    const win = window as Window & {
      __underClicks?: number;
      __toastClicks?: number;
    };
    return { under: win.__underClicks ?? 0, toast: win.__toastClicks ?? 0 };
  });
}

test.describe('social-proof toast hit-testing', () => {
  test('faded-out toast is click-transparent; visible toast intercepts; no ghost after cycling out', async ({
    page,
  }) => {
    const { id, url } = await publishBlankPage(page);

    try {
      await page.goto(url);
      await buildToastOverTarget(page);

      const { x, y } = await toastCenter(page);

      // 1) Faded (no is-visible): the click must reach the element beneath.
      await setVisible(page, false);
      await page.mouse.click(x, y);
      let counts = await clickCounts(page);
      expect(counts.under).toBe(1);
      expect(counts.toast).toBe(0);

      // The faded toast is also out of hit-testing entirely (visibility:hidden).
      const topWhenFaded = await page.evaluate(
        ({ px, py }) => document.elementFromPoint(px, py)?.id ?? '',
        { px: x, py: y }
      );
      expect(topWhenFaded).toBe('aa-e2e-under');

      // 2) Visible (is-visible): the toast now intercepts the same point.
      await setVisible(page, true);
      await page.mouse.click(x, y);
      counts = await clickCounts(page);
      expect(counts.toast).toBe(1);
      expect(counts.under).toBe(1); // unchanged from step 1

      // 3) Cycle back out: the ghost must be gone — clicks reach beneath again.
      await setVisible(page, false);
      await page.mouse.click(x, y);
      counts = await clickCounts(page);
      expect(counts.under).toBe(2);
      expect(counts.toast).toBe(1); // unchanged from step 2
    } finally {
      await deletePage(page, id);
    }
  });
});
