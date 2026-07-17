import { test, expect, type Page } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

type GalleryOptions = {
  snapBehavior?: 'off' | 'paged';
  count?: number;
  desktopBehavior?: 'pinned' | 'inline';
  activation?: 'top' | 'center' | 'bottom';
  itemWidth?: string;
};

/**
 * Build a horizontal-scroll block and return the published front-end URL.
 * `snapBehavior: 'off'` is the smooth scrub engine; `'paged'` is stepped.
 */
async function buildGallery(
  page: Page,
  options: GalleryOptions | 'off' | 'paged' = {},
  countArg?: number
): Promise<string> {
  // Back-compat for existing call sites: buildGallery(page, 'off', 4).
  const optionsObject: GalleryOptions =
    typeof options === 'string'
      ? { snapBehavior: options, count: countArg ?? 3 }
      : options;
  const {
    snapBehavior = 'off',
    count = 3,
    desktopBehavior = 'pinned',
    activation = 'top',
    itemWidth = '80vw',
  } = optionsObject;

  await openPageEditor(page);

  await page.evaluate(
    async attrs => {
      const { createBlock } = window.wp.blocks;
      const slides = Array.from({ length: attrs.count }, (_unused, index) =>
        createBlock('core/group', {}, [
          createBlock('core/heading', { content: `Slide ${index + 1}` }),
        ])
      );
      const gallery = createBlock(
        'aggressive-apparel/horizontal-scroll',
        {
          snapBehavior: attrs.snapBehavior,
          desktopBehavior: attrs.desktopBehavior,
          activation: attrs.activation,
          itemWidth: attrs.itemWidth,
        },
        slides
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(gallery);
      await new Promise(resolve => setTimeout(resolve, 400));
    },
    { snapBehavior, count, desktopBehavior, activation, itemWidth }
  );

  const { id, url } = await publishAndGetUrl(page);
  createdPageId = id;
  return url;
}

/** Current horizontal translate (px) of the track, negative = moved left. */
function trackTranslateX(page: Page): Promise<number> {
  return page
    .locator('.aa-hscroll__track')
    .first()
    .evaluate(el => new DOMMatrixReadOnly(getComputedStyle(el).transform).m41);
}

/** Absolute document scroll position of the sticky range's top. */
function rangeTop(page: Page): Promise<number> {
  return page
    .locator('.aa-hscroll__range')
    .first()
    .evaluate(el => el.getBoundingClientRect().top + window.scrollY);
}

let createdPageId = 0;

test.describe('Horizontal Scroll — front end', () => {
  test.beforeEach(async ({ page }) => {
    // Desktop, fine pointer → the block enters an enhanced pinned mode.
    await page.setViewportSize({ width: 1280, height: 800 });
  });

  test.afterEach(async ({ page }) => {
    await deletePage(page, createdPageId);
    createdPageId = 0;
  });

  test('scrub mode maps the track position to scroll, both directions', async ({
    page,
  }) => {
    const url = await buildGallery(page, 'off', 4);
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    // The runtime upgrades the section to the pinned/enhanced mode on desktop.
    await expect(section).toHaveClass(/is-enhanced/);

    const top = await rangeTop(page);

    // At the top of the range the track sits at its start.
    await page.evaluate(y => window.scrollTo(0, y), top);
    await page.waitForTimeout(120);
    const atStart = await trackTranslateX(page);
    expect(Math.abs(atStart)).toBeLessThan(2);

    // Scrolling deeper into the range moves the track to the left (negative X).
    await page.evaluate(y => window.scrollTo(0, y), top + 1200);
    await page.waitForTimeout(120);
    const scrolledIn = await trackTranslateX(page);
    expect(scrolledIn).toBeLessThan(-10);

    // Scrolling back up returns it toward the start — the map is reversible.
    await page.evaluate(y => window.scrollTo(0, y), top);
    await page.waitForTimeout(120);
    const backAtStart = await trackTranslateX(page);
    expect(Math.abs(backAtStart)).toBeLessThan(Math.abs(scrolledIn));
    expect(Math.abs(backAtStart)).toBeLessThan(2);
  });

  test('snap mode advances one slide per scroll gesture, both directions', async ({
    page,
  }) => {
    const url = await buildGallery(page, 'paged', 4);
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    await expect(section).toHaveClass(/is-paged/);

    const live = page.locator('.aa-hscroll__live-region');
    const top = await rangeTop(page);
    await page.evaluate(y => window.scrollTo(0, y), top);
    // Entry seats quietly (no live-region spam); readiness is the signal.
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready',
      { timeout: 3000 }
    );

    await page.mouse.move(640, 400);

    await page.mouse.wheel(0, 140);
    await expect(live).toHaveText(/Slide 2 of 4/, { timeout: 3000 });
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready'
    );

    await page.mouse.wheel(0, -140);
    await expect(live).toHaveText(/Slide 1 of 4/, { timeout: 3000 });
  });

  test('arrow keys page slides without revealing side controls', async ({
    page,
  }) => {
    const url = await buildGallery(page, 'paged', 3);
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    const live = page.locator('.aa-hscroll__live-region');
    const next = section.locator('.aa-hscroll__control--next');
    const top = await rangeTop(page);

    await page.evaluate(y => window.scrollTo(0, y), top);
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready',
      { timeout: 3000 }
    );
    await expect(next).toHaveCSS('opacity', '0');

    await page.keyboard.press('ArrowDown');
    await expect(live).toHaveText(/Slide 2 of 3/, { timeout: 3000 });
    await expect(next).toHaveCSS('opacity', '0');
    await expect(section).not.toHaveAttribute('data-aa-hscroll-keyboard', '');

    await page.keyboard.press('ArrowDown');
    await expect(live).toHaveText(/Slide 3 of 3/, { timeout: 3000 });

    await page.keyboard.press('ArrowUp');
    await expect(live).toHaveText(/Slide 2 of 3/, { timeout: 3000 });
  });

  test('exit at the last slide releases page scroll', async ({ page }) => {
    const url = await buildGallery(page, 'paged', 2);
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    const live = page.locator('.aa-hscroll__live-region');
    const top = await rangeTop(page);

    await page.evaluate(y => window.scrollTo(0, y), top);
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready',
      { timeout: 3000 }
    );

    await page.mouse.move(640, 400);
    await page.mouse.wheel(0, 140);
    await expect(live).toHaveText(/Slide 2 of 2/, { timeout: 3000 });
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready'
    );

    const before = await page.evaluate(() => window.scrollY);
    await page.mouse.wheel(0, 240);
    await page.waitForTimeout(80);
    const after = await page.evaluate(() => window.scrollY);
    expect(after).toBeGreaterThan(before);
  });

  test('narrow / coarse pointer falls back to native snap carousel', async ({
    page,
  }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const url = await buildGallery(page, {
      snapBehavior: 'off',
      count: 3,
      itemWidth: '85vw',
    });
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    await expect(section).toHaveClass(/is-snap/);
    await expect(section).not.toHaveClass(/is-enhanced/);
  });

  test('activation center applies the center modifier class', async ({
    page,
  }) => {
    const url = await buildGallery(page, {
      snapBehavior: 'off',
      count: 3,
      activation: 'center',
    });
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    await expect(section).toHaveClass(/aa-hscroll--center/);
    await expect(section).toHaveClass(/is-enhanced/);
  });

  test('inline desktop behavior pins and scrubs continuously', async ({
    page,
  }) => {
    const url = await buildGallery(page, {
      desktopBehavior: 'inline',
      count: 3,
    });
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    await expect(section).toHaveClass(/aa-hscroll--inline/);
    await expect(section).toHaveClass(/is-enhanced/);
    await expect(section).not.toHaveClass(/is-paged/);
  });

  test('prev/next controls appear for keyboard users and advance slides', async ({
    page,
  }) => {
    const url = await buildGallery(page, 'paged', 3);
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    await expect(section).toHaveAttribute('role', 'region');

    const prev = section.locator('.aa-hscroll__control--prev');
    const next = section.locator('.aa-hscroll__control--next');
    const live = page.locator('.aa-hscroll__live-region');

    const top = await rangeTop(page);
    await page.evaluate(y => window.scrollTo(0, y), top);
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready',
      { timeout: 3000 }
    );

    await expect(next).toHaveCSS('opacity', '0');

    // Tab from the region lands on the first enabled control (Next on slide 1).
    await section.focus();
    await page.keyboard.press('Tab');
    await expect(section).toHaveAttribute('data-aa-hscroll-keyboard', '');
    await expect(next).toBeFocused();
    await expect(next).toHaveCSS('opacity', '1');
    await expect(prev).toBeDisabled();
    await expect(next).toBeEnabled();

    await next.click();
    await expect(live).toHaveText(/Slide 2 of 3/, { timeout: 3000 });
    await expect(section).toHaveAttribute(
      'data-aa-hscroll-step-state',
      'ready'
    );
    await expect(prev).toBeEnabled();

    // After advance, Tab order is still Prev before Next.
    await section.focus();
    await page.keyboard.press('Tab');
    await expect(prev).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(next).toBeFocused();

    await next.click();
    await expect(live).toHaveText(/Slide 3 of 3/, { timeout: 3000 });
    await expect(next).toBeDisabled();

    await prev.click();
    await expect(live).toHaveText(/Slide 2 of 3/, { timeout: 3000 });
  });
});
