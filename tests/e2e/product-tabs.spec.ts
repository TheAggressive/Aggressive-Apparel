import { test, expect } from '@playwright/test';
import {
  createProductTabsFixture,
  setProductTabsStyle,
  setAccordionExclusive,
  deleteProductTabsFixture,
  installStyleForcer,
  uninstallStyleForcer,
  clearGlobalTabsOption,
} from './product-tabs-fixtures';

/**
 * Product-tabs front-end behavior that unit tests can't cover:
 *  1. Accordion — the CSS grid-rows reveal stays consistent under rapid taps,
 *     opens sections independently by default, and (exclusive mode) keeps one
 *     section open with its header on screen (view.ts `setPanel`).
 *  2. Scrollspy on small screens — the sidebar collapses to a sticky
 *     horizontal bar that stays pinned and tracks the active section
 *     (style.css @container + view.ts scrollspy sync).
 */
test.describe('Product tabs — front end', () => {
  let productId = 0;
  let productUrl = '';

  test.beforeAll(() => {
    installStyleForcer();
    // Run with the global option absent: the block's explicit style must still
    // win via render.php's `default_option_` hook (regression guard).
    clearGlobalTabsOption();
    const fixture = createProductTabsFixture();
    productId = fixture.id;
    productUrl = fixture.url;
  });

  test.afterAll(() => {
    deleteProductTabsFixture(productId);
    uninstallStyleForcer();
    productId = 0;
  });

  test("block's explicit style is honored when the global option is absent", async ({
    page,
  }) => {
    // Regression guard: render.php passes the block's displayStyle by filtering
    // the global option, which WordPress only runs via `default_option_` when
    // the option row is missing. Before the fix every style fell back to
    // accordion on sites that never saved the settings page.
    for (const style of ['modern-tabs', 'inline', 'scrollspy'] as const) {
      setProductTabsStyle(style);
      await page.goto(productUrl);
      const info = page.locator('.aa-product-info');
      await info.waitFor();
      await expect(info).toHaveClass(
        new RegExp(`aa-product-info--${style}\\b`)
      );
    }
  });

  test('does not duplicate WooCommerce built-in tab-title headings', async ({
    page,
  }) => {
    // WooCommerce's description/additional-information callbacks emit their own
    // <h2>Title</h2>. Since every layout renders its own section title, that
    // built-in heading must be stripped from the content — otherwise each
    // section shows the title twice (once styled, once raw).
    setProductTabsStyle('inline');
    await page.setViewportSize({ width: 1024, height: 900 });
    await page.goto(productUrl);

    const info = page.locator('.aa-product-info--inline');
    await info.waitFor();

    // No section may render a content heading equal to its own title. This
    // covers description/additional-information (top-level <h2>Title</h2>) and
    // the reviews tab's heading nested in #reviews — a bare "Reviews" on a
    // zero-review product (the fixture) is an exact duplicate and must be
    // stripped. WooCommerce's "N reviews for <product>" (different text) is a
    // separate case that stays.
    const duplicates = await info.evaluate((root: HTMLElement) => {
      const norm = (s: string) =>
        s
          .toLowerCase()
          .replace(/\s+\(\d+\)$/, '')
          .replace(/\s+/g, ' ')
          .trim();
      const offenders: string[] = [];
      root.querySelectorAll('.aa-product-info__section').forEach(section => {
        const title = norm(
          section.querySelector('.aa-product-info__heading')?.textContent ?? ''
        );
        if (title === '') return;
        section
          .querySelectorAll(
            '.aa-product-info__content h1, .aa-product-info__content h2, .aa-product-info__content h3, .aa-product-info__content h4'
          )
          .forEach(h => {
            if (norm(h.textContent ?? '') === title) {
              offenders.push(title);
            }
          });
      });
      return offenders;
    });

    expect(duplicates).toEqual([]);
  });

  test('accordion stays consistent under rapid taps (no ghost-open content)', async ({
    page,
  }) => {
    // Regression guard: the old WAAPI animation stacked uncancelled animations
    // on rapid taps and left panels visible while marked closed. The CSS
    // grid-rows reveal must keep rendered height and `open` state in lockstep.
    setProductTabsStyle('accordion');
    setAccordionExclusive(false);
    await page.setViewportSize({ width: 390, height: 780 });
    await page.goto(productUrl);

    const accordion = page.locator('.aa-product-info--accordion');
    await accordion.waitFor();

    const second = accordion
      .locator('details > summary.aa-product-info__heading')
      .nth(1);
    await second.click();
    await page.waitForTimeout(50);
    await second.click();
    await page.waitForTimeout(50);
    await second.click();
    await page.waitForTimeout(700);

    const mismatches = await accordion.evaluate(
      (root: HTMLElement) =>
        [...root.querySelectorAll('details')].filter(details => {
          const reveal = details.querySelector(
            '.aa-product-info__reveal'
          ) as HTMLElement;
          const visible = reveal.getBoundingClientRect().height > 5;
          return visible !== (details as HTMLDetailsElement).open;
        }).length
    );
    expect(mismatches).toBe(0);
  });

  test('accordion sections open independently by default', async ({ page }) => {
    setProductTabsStyle('accordion');
    setAccordionExclusive(false);
    await page.setViewportSize({ width: 1024, height: 800 });
    await page.goto(productUrl);

    const accordion = page.locator('.aa-product-info--accordion');
    await accordion.waitFor();

    // Section 0 is open by default; opening section 1 must leave section 0 open.
    await accordion
      .locator('details > summary.aa-product-info__heading')
      .nth(1)
      .click();
    await page.waitForTimeout(500);

    const openCount = await accordion.evaluate(
      (root: HTMLElement) => root.querySelectorAll('details[open]').length
    );
    expect(openCount).toBeGreaterThanOrEqual(2);
  });

  test('accordion exclusive mode keeps one section open and its header on screen', async ({
    page,
  }) => {
    setProductTabsStyle('accordion');
    setAccordionExclusive(true);
    await page.setViewportSize({ width: 390, height: 780 });
    await page.goto(productUrl);

    const accordion = page.locator(
      '.aa-product-info--accordion[data-aa-exclusive]'
    );
    await accordion.waitFor();

    // Position the last header near the top, then open it — section 0 collapses
    // above it. Exactly one section stays open and the tapped header is nudged
    // back on screen rather than shoved off the top.
    const finalTop = await accordion.evaluate(async (root: HTMLElement) => {
      const summaries = [...root.querySelectorAll('summary')];
      const last = summaries[summaries.length - 1];
      window.scrollTo(
        0,
        last.getBoundingClientRect().top + window.scrollY - 60
      );
      await new Promise(resolve => requestAnimationFrame(resolve));
      last.click();
      await new Promise(resolve => setTimeout(resolve, 900));
      return last.getBoundingClientRect().top;
    });

    const openCount = await accordion.evaluate(
      (root: HTMLElement) => root.querySelectorAll('details[open]').length
    );
    expect(openCount).toBe(1);
    expect(finalTop).toBeGreaterThanOrEqual(0);
  });

  test('accordion highlights the open section heading in the accent color', async ({
    page,
  }) => {
    setProductTabsStyle('accordion');
    setAccordionExclusive(false);
    await page.setViewportSize({ width: 1024, height: 800 });
    await page.goto(productUrl);

    const accordion = page.locator('.aa-product-info--accordion');
    await accordion.waitFor();

    const colors = await accordion.evaluate((root: HTMLElement) => {
      // Resolve the accent token to an rgb() value via a throwaway probe.
      const probe = document.createElement('span');
      probe.style.color = 'var(--aa-pi-accent)';
      root.appendChild(probe);
      const accent = getComputedStyle(probe).color;
      probe.remove();

      const open = root.querySelector(
        'details[open] > .aa-product-info__heading'
      ) as HTMLElement;
      const closed = root.querySelector(
        'details:not([open]) > .aa-product-info__heading'
      ) as HTMLElement;

      return {
        accent,
        openColor: getComputedStyle(open).color,
        closedColor: getComputedStyle(closed).color,
      };
    });

    // The open heading is the accent color; a closed heading is not.
    expect(colors.openColor).toBe(colors.accent);
    expect(colors.closedColor).not.toBe(colors.accent);
  });

  test('scrollspy sidebar is sticky and tracks the active section on mobile', async ({
    page,
  }) => {
    setProductTabsStyle('scrollspy');

    await page.setViewportSize({ width: 420, height: 800 });
    await page.goto(productUrl);

    const scrollspy = page.locator('.aa-product-info--scrollspy');
    await scrollspy.waitFor();

    const sidebar = scrollspy.locator('.aa-product-info__sidebar');

    // Mobile collapses the side rail into a sticky horizontal bar.
    await expect(sidebar).toHaveCSS('position', 'sticky');

    const links = sidebar.locator('.aa-product-info__nav-link');
    const linkCount = await links.count();
    expect(linkCount).toBeGreaterThanOrEqual(2);

    // Drive the second section into the scrollspy activation band (-20%/-60%).
    const secondId = await links
      .nth(1)
      .evaluate((el: HTMLAnchorElement) =>
        (el.getAttribute('href') ?? '').slice(1)
      );
    expect(secondId).toBeTruthy();

    await page.evaluate((id: string) => {
      const section = document.getElementById(id);
      if (!section) return;
      const top = section.getBoundingClientRect().top + window.scrollY;
      window.scrollTo(0, top - window.innerHeight * 0.3);
    }, secondId);

    // Wait for the IntersectionObserver to mark the section active.
    await expect(links.nth(1)).toHaveClass(/is-active/);

    // The sticky bar stays pinned at the top instead of scrolling away.
    const barTop = await sidebar.evaluate(
      (el: HTMLElement) => el.getBoundingClientRect().top
    );
    expect(Math.abs(barTop)).toBeLessThanOrEqual(2);

    // If the rail overflows horizontally, the active link is scrolled into view.
    const centering = await sidebar.evaluate((bar: HTMLElement) => {
      if (bar.scrollWidth <= bar.clientWidth) return null;
      const active = bar.querySelector(
        '.aa-product-info__nav-link.is-active'
      ) as HTMLElement | null;
      if (!active) return null;
      const barRect = bar.getBoundingClientRect();
      const linkRect = active.getBoundingClientRect();
      const center = linkRect.left + linkRect.width / 2;
      return { center, left: barRect.left, right: barRect.right };
    });
    if (centering) {
      expect(centering.center).toBeGreaterThanOrEqual(centering.left);
      expect(centering.center).toBeLessThanOrEqual(centering.right);
    }
  });

  test('scrollspy stacks (no horizontal overflow) when its block column is narrow', async ({
    page,
  }) => {
    // Regression guard: on tablet the tabs block sits in WooCommerce's ~50%
    // summary column, so the layout switch must key off the block's own width
    // (container query on an inner wrapper), not the viewport. Before the fix
    // the 12rem + 1fr grid forced a fixed ~892px content track that overflowed
    // the ~360px block, cutting off content on the right.
    setProductTabsStyle('scrollspy');

    for (const width of [768, 834, 1024]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto(productUrl);

      const layout = page.locator(
        '.aa-product-info--scrollspy .aa-product-info__layout'
      );
      await layout.waitFor();

      const metrics = await page.evaluate(() => {
        const root = document.querySelector(
          '.aa-product-info--scrollspy'
        ) as HTMLElement;
        const inner = root.querySelector(
          '.aa-product-info__layout'
        ) as HTMLElement;
        return {
          rootWidth: root.getBoundingClientRect().width,
          layoutDisplay: getComputedStyle(inner).display,
          pageOverflow:
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
        };
      });

      // The page must never scroll horizontally.
      expect(metrics.pageOverflow).toBeLessThanOrEqual(1);
      // When the block column is narrow it collapses to the stacked bar layout.
      if (metrics.rootWidth < 768) {
        expect(metrics.layoutDisplay).toBe('block');
      }
    }
  });
});
