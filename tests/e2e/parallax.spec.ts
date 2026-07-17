import { test, expect } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

test.describe('Parallax — front end', () => {
  let pageId = 0;

  test.afterEach(async ({ page }) => {
    await deletePage(page, pageId);
    pageId = 0;
  });

  test('applies layer translate on scroll and keeps links clickable', async ({
    page,
  }) => {
    await openPageEditor(page);

    await page.evaluate(async () => {
      const { createBlock } = window.wp.blocks;
      const layer = createBlock('core/paragraph', {
        content:
          '<a href="https://example.com/parallax-link">Parallax link</a>',
        aggressiveApparelParallax: {
          enabled: true,
          speed: 1,
          direction: 'down',
          delay: 0,
          easing: 'linear',
          depth: 40,
        },
      });
      const parallax = createBlock(
        'aggressive-apparel/parallax',
        {
          intensity: 80,
          visibilityTrigger: 0,
          detectionBoundary: {
            top: '0%',
            right: '0%',
            bottom: '0%',
            left: '0%',
          },
          activationBuffer: 0,
          enableMouseInteraction: false,
          disableOnMobile: false,
        },
        [layer]
      );
      const spacer = createBlock('core/spacer', { height: '120vh' });
      window.wp.data
        .dispatch('core/block-editor')
        .insertBlocks([spacer, parallax, spacer]);
      await new Promise(r => setTimeout(r, 400));
    });

    const { id, url } = await publishAndGetUrl(page);
    pageId = id;

    await page.goto(url);
    const root = page.locator('.aggressive-apparel-parallax').first();
    await root.waitFor();
    await expect(root).not.toHaveClass(/disable-on-mobile/);

    const layer = root.locator('[data-parallax-enabled="true"]').first();
    await expect(layer).toHaveCount(1);

    await layer.scrollIntoViewIfNeeded();
    await page.waitForTimeout(200);

    const before = await layer.evaluate(
      el => getComputedStyle(el as HTMLElement).translate
    );

    await page.evaluate(() => window.scrollBy(0, 240));
    await page.waitForTimeout(250);

    const after = await layer.evaluate(
      el => getComputedStyle(el as HTMLElement).translate
    );
    expect(after).not.toBe(before);

    const link = layer.locator('a');
    await expect(link).toBeVisible();
    // Hit-testing: the link’s layout box should receive the click target.
    const box = await link.boundingBox();
    expect(box).toBeTruthy();
    if (box) {
      const hit = await page.evaluate(
        ({ x, y }) => {
          const el = document.elementFromPoint(x, y);
          return el?.closest('a')?.getAttribute('href') ?? null;
        },
        { x: box.x + box.width / 2, y: box.y + box.height / 2 }
      );
      expect(hit).toBe('https://example.com/parallax-link');
    }
  });

  test('emits disable-on-mobile class when opted in', async ({ page }) => {
    await openPageEditor(page);

    await page.evaluate(async () => {
      const { createBlock } = window.wp.blocks;
      const layer = createBlock('core/paragraph', {
        content: 'Static on phones',
        aggressiveApparelParallax: {
          enabled: true,
          speed: 1,
          direction: 'down',
          delay: 0,
          easing: 'linear',
          depth: 20,
        },
      });
      const parallax = createBlock(
        'aggressive-apparel/parallax',
        { disableOnMobile: true, intensity: 50 },
        [layer]
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(parallax);
      await new Promise(r => setTimeout(r, 400));
    });

    const { id, url } = await publishAndGetUrl(page);
    pageId = id;

    await page.goto(url);
    const root = page.locator('.aggressive-apparel-parallax').first();
    await expect(root).toHaveClass(/disable-on-mobile/);
  });
});
