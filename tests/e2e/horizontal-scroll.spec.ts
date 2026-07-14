import { test, expect, type Page } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

/**
 * Build a horizontal-scroll block with `count` slides in the given scroll mode
 * and return the published front-end URL. `snapBehavior: 'off'` is the smooth
 * scrub engine; `'paged'` is the input-locked step engine.
 */
async function buildGallery(
  page: Page,
  snapBehavior: 'off' | 'paged',
  count: number
): Promise<string> {
  await openPageEditor(page);

  await page.evaluate(
    async ({ behavior, slideCount }) => {
      const { createBlock } = window.wp.blocks;
      const slides = Array.from({ length: slideCount }, (_unused, index) =>
        createBlock('core/group', {}, [
          createBlock('core/heading', { content: `Slide ${index + 1}` }),
        ])
      );
      const gallery = createBlock(
        'aggressive-apparel/horizontal-scroll',
        { snapBehavior: behavior, itemWidth: '80vw' },
        slides
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(gallery);
      await new Promise(resolve => setTimeout(resolve, 400));
    },
    { behavior: snapBehavior, slideCount: count }
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

  test('step mode advances exactly one slide per gesture, both directions', async ({
    page,
  }) => {
    const url = await buildGallery(page, 'paged', 4);
    await page.goto(url);

    const section = page.locator('.aa-hscroll').first();
    await section.waitFor();
    await expect(section).toHaveClass(/is-paged/);

    const live = page.locator('.aa-hscroll__live-region');

    // Enter the pinned range; the section settles onto the first slide.
    const top = await rangeTop(page);
    await page.evaluate(y => window.scrollTo(0, y), top);
    await expect(live).toHaveText(/Slide 1 of 4/, { timeout: 3000 });

    // Position the cursor over the pinned panel so wheel events land on it.
    await page.mouse.move(640, 400);

    // One wheel notch down → advance one slide.
    await page.mouse.wheel(0, 140);
    await expect(live).toHaveText(/Slide 2 of 4/, { timeout: 3000 });

    // One wheel notch up → step back one slide.
    await page.mouse.wheel(0, -140);
    await expect(live).toHaveText(/Slide 1 of 4/, { timeout: 3000 });
  });
});
