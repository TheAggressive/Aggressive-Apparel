/**
 * E2E for the sticky add-to-cart bar's hit-testing + focus contract.
 *
 * The bar is a fixed bottom overlay that slides off (transform: translateY(100%))
 * when the main add-to-cart is in view. While hidden it must not catch clicks and
 * its controls must not be reachable by keyboard — otherwise it's the same class
 * of invisible "ghost" the social-proof toast had. Production drives this with
 * BOTH the `.is-visible` class (CSS: transform/pointer-events/visibility) and the
 * inverse `inert` attribute (`data-wp-bind--inert="!state.isVisible"`); this spec
 * replicates that pairing and asserts the contract holds in a real browser.
 *
 * As with social-proof-ghost.spec.ts, it loads the REAL compiled stylesheet onto
 * a plain page and injects the real bar markup rather than standing up the
 * feature toggle + a product page — the behaviour under test is the CSS/attribute
 * contract, not the PHP render path.
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
 * Load the real compiled sticky-add-to-cart stylesheet (URL derived from the
 * always-present main theme stylesheet, so there's no hard-coded theme slug),
 * then build the real bar markup over a full-width click target at the bottom.
 * The bar starts hidden: no `is-visible` class + `inert`, matching first paint.
 */
async function buildBarOverTarget(page: Page): Promise<void> {
  await page.evaluate(async () => {
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
      '/build/styles/woocommerce/sticky-add-to-cart.css'
    );
    cssUrl.search = '';

    await new Promise<void>((resolve, reject) => {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = cssUrl.href;
      link.addEventListener('load', () => resolve());
      link.addEventListener('error', () =>
        reject(new Error('sticky-add-to-cart stylesheet failed to load'))
      );
      document.head.appendChild(link);
    });

    // Disable transitions on the test bar so state changes settle synchronously
    // (this spec asserts settled hit-testing states, not the slide animation or
    // the deliberate 0.3s visibility-flip delay).
    const noAnim = document.createElement('style');
    noAnim.textContent =
      '#aa-e2e-bar, #aa-e2e-bar * { transition: none !important; animation: none !important; }';
    document.head.appendChild(noAnim);

    const win = window as Window & {
      __underClicks?: number;
      __barClicks?: number;
    };
    win.__underClicks = 0;
    win.__barClicks = 0;

    // Full-width click target strip across the bottom, beneath the bar.
    const target = document.createElement('button');
    target.id = 'aa-e2e-under';
    target.textContent = 'Under';
    Object.assign(target.style, {
      position: 'fixed',
      left: '0',
      right: '0',
      bottom: '0',
      width: '100%',
      height: '160px',
      zIndex: '1',
      margin: '0',
    });
    target.addEventListener('click', () => {
      win.__underClicks = (win.__underClicks ?? 0) + 1;
    });
    document.body.appendChild(target);

    // Real bar markup (mirrors render): container carries `inert` while hidden;
    // the add-to-cart button is the interactive control we probe for focus.
    const bar = document.createElement('div');
    bar.className = 'aa-sticky-cart';
    bar.id = 'aa-e2e-bar';
    bar.setAttribute('aria-hidden', 'true');
    bar.setAttribute('inert', '');
    bar.innerHTML = `
      <div class="aa-sticky-cart__inner">
        <div class="aa-sticky-cart__product">
          <div class="aa-sticky-cart__info">
            <span class="aa-sticky-cart__title">Sample product</span>
          </div>
        </div>
        <div class="aa-sticky-cart__actions">
          <button type="button" class="aa-sticky-cart__button wp-element-button" id="aa-e2e-bar-button">
            <span class="aa-sticky-cart__button-text">Add to cart</span>
          </button>
        </div>
      </div>`;
    // Count a click anywhere on the bar as "intercepted" — the bar's centre sits
    // over its product info, not the action button.
    bar.addEventListener('click', () => {
      win.__barClicks = (win.__barClicks ?? 0) + 1;
    });
    document.body.appendChild(bar);
  });

  // Confirm the real CSS applied (base bar is pointer-transparent) before asserting.
  await expect
    .poll(() =>
      page.evaluate(() => {
        const el = document.getElementById('aa-e2e-bar');
        return el ? getComputedStyle(el).pointerEvents : null;
      })
    )
    .toBe('none');
}

/** Center point of the bar's on-screen box, measured in its visible state. */
async function barCenter(page: Page): Promise<{ x: number; y: number }> {
  return page.evaluate(() => {
    const el = document.getElementById('aa-e2e-bar');
    if (!el) {
      throw new Error('Bar element missing.');
    }
    // Transitions are disabled on this bar (see buildBarOverTarget), so the
    // class flip settles synchronously and the box is the on-screen position.
    const wasVisible = el.classList.contains('is-visible');
    const wasInert = el.hasAttribute('inert');
    el.classList.add('is-visible');
    el.removeAttribute('inert');
    const rect = el.getBoundingClientRect();
    if (!wasVisible) {
      el.classList.remove('is-visible');
    }
    if (wasInert) {
      el.setAttribute('inert', '');
    }
    return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
  });
}

/** Toggle the bar the way the Interactivity store does: is-visible + inverse inert. */
async function setVisible(page: Page, visible: boolean): Promise<void> {
  await page.evaluate(v => {
    const el = document.getElementById('aa-e2e-bar');
    if (!el) {
      return;
    }
    el.classList.toggle('is-visible', v);
    if (v) {
      el.removeAttribute('inert');
    } else {
      el.setAttribute('inert', '');
    }
  }, visible);
}

async function clickCounts(
  page: Page
): Promise<{ under: number; bar: number }> {
  return page.evaluate(() => {
    const win = window as Window & {
      __underClicks?: number;
      __barClicks?: number;
    };
    return { under: win.__underClicks ?? 0, bar: win.__barClicks ?? 0 };
  });
}

/** Whether focusing the bar's button actually lands focus on it. */
async function buttonIsFocusable(page: Page): Promise<boolean> {
  return page.evaluate(() => {
    const btn = document.getElementById('aa-e2e-bar-button');
    if (!btn) {
      return false;
    }
    (document.activeElement as HTMLElement | null)?.blur();
    btn.focus();
    return document.activeElement === btn;
  });
}

test.describe('sticky add-to-cart hit-testing', () => {
  test('hidden bar is click-transparent and unfocusable; visible bar intercepts; no ghost after hiding', async ({
    page,
  }) => {
    const { id, url } = await publishBlankPage(page);

    try {
      await page.goto(url);
      await buildBarOverTarget(page);

      const { x, y } = await barCenter(page);

      // 1) Hidden (no is-visible + inert): click reaches beneath, button unfocusable.
      await setVisible(page, false);
      await page.mouse.click(x, y);
      let counts = await clickCounts(page);
      expect(counts.under).toBe(1);
      expect(counts.bar).toBe(0);
      expect(await buttonIsFocusable(page)).toBe(false);

      const topWhenHidden = await page.evaluate(
        ({ px, py }) => document.elementFromPoint(px, py)?.id ?? '',
        { px: x, py: y }
      );
      expect(topWhenHidden).toBe('aa-e2e-under');

      // 2) Visible (is-visible, not inert): the bar intercepts and is focusable.
      await setVisible(page, true);
      await page.mouse.click(x, y);
      counts = await clickCounts(page);
      expect(counts.bar).toBe(1);
      expect(counts.under).toBe(1); // unchanged from step 1
      expect(await buttonIsFocusable(page)).toBe(true);

      // 3) Hide again: no ghost — clicks reach beneath and the button re-locks.
      await setVisible(page, false);
      await page.mouse.click(x, y);
      counts = await clickCounts(page);
      expect(counts.under).toBe(2);
      expect(counts.bar).toBe(1); // unchanged from step 2
      expect(await buttonIsFocusable(page)).toBe(false);
    } finally {
      await deletePage(page, id);
    }
  });
});
