import { test, expect } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

test.describe('Split Story — front end', () => {
  let pageId = 0;

  test.afterEach(async ({ page }) => {
    await deletePage(page, pageId);
    pageId = 0;
  });

  test('renders sticky grid with media-right, gap, and mobile stacking', async ({
    page,
  }) => {
    await openPageEditor(page);

    await page.evaluate(async () => {
      const { createBlock } = window.wp.blocks;
      const media = createBlock('aggressive-apparel/split-story-media', {}, [
        createBlock('core/paragraph', { content: 'MEDIA' }),
      ]);
      const content = createBlock(
        'aggressive-apparel/split-story-content',
        {},
        [createBlock('core/paragraph', { content: 'CONTENT' })]
      );
      const story = createBlock(
        'aggressive-apparel/split-story',
        {
          mediaPosition: 'right',
          mediaWidth: 40,
          sticky: true,
          // Native axial blockGap: top = vertical/row, left = horizontal/column.
          style: { spacing: { blockGap: { top: '1.5rem', left: '3rem' } } },
          stackOrder: 'content-first',
        },
        [media, content]
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(story);
      await new Promise(r => setTimeout(r, 400));
    });

    const { id, url } = await publishAndGetUrl(page);
    pageId = id;

    // --- Desktop ---
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(url);
    const story = page.locator('.aa-split-story').first();
    await story.waitFor();
    const media = story.locator('.aa-split-story__media');

    await expect(story).toHaveCSS('display', 'grid');
    // media-right → media placed in the second grid column.
    await expect(media).toHaveCSS('grid-column-start', '2');
    // sticky media.
    await expect(media).toHaveCSS('position', 'sticky');
    // horizontal gap (columnGap 3rem = 48px) applied to the grid.
    await expect(story).toHaveCSS('column-gap', '48px');
    // vertical gap (rowGap 1.5rem = 24px).
    await expect(story).toHaveCSS('row-gap', '24px');
    // media width var (40%).
    await expect(story).toHaveCSS('--aa-split-media-width', '40%');

    // Reading order: media before content in the DOM (a11y), regardless of side.
    const order = await story.evaluate((el: HTMLElement) => {
      const m = el.querySelector('.aa-split-story__media');
      const c = el.querySelector('.aa-split-story__content');
      return !!(
        m &&
        c &&
        m.compareDocumentPosition(c) & Node.DOCUMENT_POSITION_FOLLOWING
      );
    });
    expect(order).toBe(true);

    // --- Mobile ---
    await page.setViewportSize({ width: 480, height: 900 });
    await expect(media).toHaveCSS('position', 'static');
    const rows = await story.evaluate(
      (el: HTMLElement) =>
        getComputedStyle(el).gridTemplateColumns.split(' ').length
    );
    expect(rows).toBe(1);
    // content-first stacking: content row above media row.
    const stacked = await story.evaluate((el: HTMLElement) => {
      const m = getComputedStyle(
        el.querySelector('.aa-split-story__media') as HTMLElement
      ).gridRowStart;
      const c = getComputedStyle(
        el.querySelector('.aa-split-story__content') as HTMLElement
      ).gridRowStart;
      return Number(c) < Number(m);
    });
    expect(stacked).toBe(true);
  });
});
