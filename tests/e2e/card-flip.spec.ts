import { test, expect } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

/* eslint-disable @typescript-eslint/no-explicit-any */

test.describe('Card Flip — front end', () => {
  let pageId = 0;

  test.afterEach(async ({ page }) => {
    await deletePage(page, pageId);
    pageId = 0;
  });

  test('flips on click with correct inert + aria-pressed a11y', async ({
    page,
  }) => {
    await openPageEditor(page);

    await page.evaluate(async () => {
      const { createBlock } = window.wp.blocks;
      const front = createBlock('aggressive-apparel/card-flip-front', {}, [
        createBlock('core/paragraph', { content: 'FRONT' }),
      ]);
      const back = createBlock('aggressive-apparel/card-flip-back', {}, [
        createBlock('core/paragraph', {
          content: '<a href="https://example.com">BACK LINK</a>',
        }),
      ]);
      const card = createBlock(
        'aggressive-apparel/card-flip',
        { flipOn: 'click' },
        [front, back]
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(card);
      await new Promise(r => setTimeout(r, 400));
    });

    const { id, url } = await publishAndGetUrl(page);
    pageId = id;

    await page.goto(url);
    const card = page.locator('.aa-card-flip').first();
    await card.waitFor();

    const front = card.locator('.aa-card-flip__face--front');
    const back = card.locator('.aa-card-flip__face--back');
    const toggle = card.locator('.aa-card-flip__toggle');

    // Initial: back is inert (out of the tab order), front is not.
    await expect(front).not.toHaveAttribute('inert', /.*/);
    await expect(back).toHaveAttribute('inert', /.*/);
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');

    // Flip.
    await toggle.click();
    await expect(card).toHaveClass(/is-flipped/);
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(back).not.toHaveAttribute('inert', /.*/);
    await expect(front).toHaveAttribute('inert', /.*/);

    // Keyboard toggle back.
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(card).not.toHaveClass(/is-flipped/);
    await expect(back).toHaveAttribute('inert', /.*/);
  });
});
