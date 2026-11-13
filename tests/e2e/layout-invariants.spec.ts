import { expect, test, type Page } from '@playwright/test';

/**
 * Layout invariants — the render-level regressions unit tests cannot see.
 *
 * On the storefront's key pages, at phone and desktop widths, logged in and
 * out, nothing may scroll sideways, no visible control may collapse below the
 * WCAG 2.5.8 minimum target (24×24 CSS px) — the class of bug where the
 * logged-in header avatar shrank to 0px — and no sized image may render at
 * zero width. Targets follow WCAG 2.5.8 exactly, spacing exception included. Checks are geometric invariants, not pixel snapshots, so they
 * hold across browsers' font rendering and the Studio/CI environments.
 */

const VIEWPORTS = [
  { name: 'phone', width: 390, height: 844 },
  { name: 'desktop', width: 1400, height: 900 },
];
const MIN_TARGET = 24;

async function firstProductPath(page: Page): Promise<string> {
  const response = await page.request.get(
    '/wp-json/wc/store/v1/products?per_page=1'
  );
  const [product] = (await response.json()) as Array<{
    permalink: string;
  }>;
  return new URL(product.permalink).pathname;
}

/** Every invariant violation on the current page, described for the report. */
async function layoutProblems(page: Page): Promise<string[]> {
  return page.evaluate(minTarget => {
    const problems: string[] = [];
    const root = document.documentElement;
    if (root.scrollWidth > window.innerWidth + 1) {
      problems.push(
        `page scrolls sideways: ${root.scrollWidth}px wide in a ${window.innerWidth}px viewport`
      );
    }

    const describe = (el: Element): string => {
      const cls = String(el.className || '')
        .trim()
        .split(/\s+/)[0];
      return `<${el.tagName.toLowerCase()}${cls ? `.${cls}` : ''}>`;
    };
    // Rendered for sighted users: not display:none/visibility:hidden (self or
    // ancestor), not inert or hidden, and not a visually-hidden helper.
    const shown = (el: Element): boolean =>
      el.checkVisibility({ visibilityProperty: true }) &&
      !el.closest(
        '[inert], [hidden], [aria-hidden="true"], #wpadminbar, #query-monitor-main'
      ) &&
      getComputedStyle(el).clipPath === 'none' &&
      !el.closest('.screen-reader-text, .skip-to-content');

    // Controls everywhere, plus header links (icon actions such as the
    // account avatar). Inline text links are exempt under WCAG 2.5.8.
    const controls = document.querySelectorAll(
      'button, [role="button"], input:not([type="hidden"]), select, textarea, header a'
    );
    const targets: Array<{ el: Element; rect: DOMRect; small: boolean }> = [];
    for (const el of controls) {
      if (!shown(el)) continue;
      let rect = el.getBoundingClientRect();
      // A labelled checkbox or radio is operable through its label, which
      // counts as the equivalent target.
      if (
        el instanceof HTMLInputElement &&
        /^(checkbox|radio)$/.test(el.type)
      ) {
        for (const label of el.labels ?? []) {
          const box = label.getBoundingClientRect();
          if (box.width * box.height > rect.width * rect.height) rect = box;
        }
      }
      // Half a pixel of tolerance for sub-pixel layout.
      const small =
        rect.width < minTarget - 0.5 || rect.height < minTarget - 0.5;
      targets.push({ el, rect, small });
    }

    // WCAG 2.5.8 spacing exception: an undersized target passes when a
    // 24px-diameter circle on its centre overlaps no other target and no other
    // undersized target's circle.
    const radius = minTarget / 2;
    const centre = (r: DOMRect) => ({
      x: r.x + r.width / 2,
      y: r.y + r.height / 2,
    });
    const distanceToRect = (p: { x: number; y: number }, r: DOMRect) =>
      Math.hypot(
        Math.max(r.left - p.x, 0, p.x - r.right),
        Math.max(r.top - p.y, 0, p.y - r.bottom)
      );
    for (const target of targets.filter(t => t.small)) {
      const c = centre(target.rect);
      const crowded = targets.some(
        other =>
          other !== target &&
          !other.el.contains(target.el) &&
          !target.el.contains(other.el) &&
          (distanceToRect(c, other.rect) < radius ||
            (other.small &&
              Math.hypot(
                c.x - centre(other.rect).x,
                c.y - centre(other.rect).y
              ) < minTarget))
      );
      if (crowded) {
        problems.push(
          `${describe(target.el)} is ${Math.round(target.rect.width)}×${Math.round(target.rect.height)}px and crowded: under the ${minTarget}px target without the spacing exception`
        );
      }
    }

    for (const img of document.querySelectorAll('img[width][height]')) {
      if (!shown(img)) continue;
      if (img.getBoundingClientRect().width === 0) {
        problems.push(`${describe(img)} with a declared size renders 0px wide`);
      }
    }
    return problems;
  }, MIN_TARGET);
}

for (const session of ['logged out', 'logged in'] as const) {
  test.describe(`layout invariants (${session})`, () => {
    if (session === 'logged out') {
      test.use({ storageState: { cookies: [], origins: [] } });
    }

    for (const viewport of VIEWPORTS) {
      test(`key pages hold at ${viewport.name} width`, async ({ page }) => {
        await page.setViewportSize(viewport);
        const paths = [
          '/',
          '/shop/',
          await firstProductPath(page),
          '/cart/',
          '/checkout/',
          '/my-account/',
        ];

        const problems: string[] = [];
        for (const path of paths) {
          await page.goto(path, { waitUntil: 'networkidle' });
          problems.push(
            ...(await layoutProblems(page)).map(p => `${path}: ${p}`)
          );
        }

        expect(problems).toEqual([]);
      });
    }

    test('the open mini-cart holds at phone width', async ({ page }) => {
      await page.setViewportSize(VIEWPORTS[0]);
      await page.goto('/', { waitUntil: 'networkidle' });
      await page.locator('.wc-block-mini-cart__button').first().click();
      await expect(page.locator('.wc-block-mini-cart__drawer')).toBeVisible();

      expect(await layoutProblems(page)).toEqual([]);
    });
  });
}
