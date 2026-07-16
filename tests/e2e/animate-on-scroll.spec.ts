import { test, expect } from '@playwright/test';
import { openPageEditor, publishAndGetUrl, deletePage } from './helpers';

test.describe('Animate On Scroll — front end', () => {
  let pageId = 0;

  test.afterEach(async ({ page }) => {
    await deletePage(page, pageId);
    pageId = 0;
  });

  test('staggers child transition-delay after becoming visible', async ({
    page,
  }) => {
    await openPageEditor(page);

    await page.evaluate(async () => {
      const { createBlock } = window.wp.blocks;
      const aos = createBlock(
        'aggressive-apparel/animate-on-scroll',
        {
          animation: 'fade',
          staggerChildren: true,
          staggerDelay: 0.2,
          duration: 0.4,
          initialDelay: 0,
          threshold: '0',
          detectionBoundary: {
            top: '0%',
            right: '0%',
            bottom: '0%',
            left: '0%',
          },
          respectReducedMotion: false,
        },
        [
          createBlock('core/paragraph', { content: 'Child A' }),
          createBlock('core/paragraph', { content: 'Child B' }),
          createBlock('core/paragraph', { content: 'Child C' }),
        ]
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(aos);
      await new Promise(r => setTimeout(r, 400));
    });

    const { id, url } = await publishAndGetUrl(page);
    pageId = id;

    await page.goto(url);
    const root = page.locator('.wp-block-animate-on-scroll').first();
    await root.waitFor();
    await expect(root).toHaveAttribute('data-stagger-children', 'true');
    await expect(root).toHaveClass(/is-visible/, { timeout: 10_000 });

    const delays = await root.evaluate(el =>
      Array.from(el.children).map(
        child => getComputedStyle(child as HTMLElement).transitionDelay
      )
    );

    expect(delays).toHaveLength(3);
    expect(parseFloat(delays[0])).toBeCloseTo(0, 2);
    expect(parseFloat(delays[1])).toBeCloseTo(0.2, 2);
    expect(parseFloat(delays[2])).toBeCloseTo(0.4, 2);
  });

  test('sequence bounce runs keyframes without transform !important lock', async ({
    page,
  }) => {
    await openPageEditor(page);

    await page.evaluate(async () => {
      const { createBlock } = window.wp.blocks;
      const aos = createBlock(
        'aggressive-apparel/animate-on-scroll',
        {
          useSequence: true,
          animationSequence: [
            { animation: 'bounce', direction: 'standard' },
            { animation: 'fade', direction: '' },
          ],
          staggerChildren: true,
          staggerDelay: 0.15,
          duration: 0.6,
          initialDelay: 0,
          threshold: '0',
          detectionBoundary: {
            top: '0%',
            right: '0%',
            bottom: '0%',
            left: '0%',
          },
          respectReducedMotion: false,
        },
        [
          createBlock('core/paragraph', { content: 'Bounce me' }),
          createBlock('core/paragraph', { content: 'Fade me' }),
        ]
      );
      window.wp.data.dispatch('core/block-editor').insertBlock(aos);
      await new Promise(r => setTimeout(r, 400));
    });

    const { id, url } = await publishAndGetUrl(page);
    pageId = id;

    await page.goto(url);
    const root = page.locator('.wp-block-animate-on-scroll').first();
    await root.waitFor();
    await expect(root).toHaveClass(/has-animation-sequence/);
    await expect(root).toHaveClass(/is-visible/, { timeout: 10_000 });

    const bounceWrap = root.locator('[data-animate-sequence-type="bounce"]');
    await expect(bounceWrap).toHaveCount(1);

    // Bounce uses keyframes; a stuck `transform: none !important` would keep
    // the matrix at identity for the whole entrance.
    const bounceState = await bounceWrap.evaluate(el => {
      const style = getComputedStyle(el as HTMLElement);
      return {
        animationName: style.animationName,
        animationDuration: style.animationDuration,
      };
    });

    expect(bounceState.animationName).toMatch(/bounce/i);
    expect(parseFloat(bounceState.animationDuration)).toBeGreaterThan(0);

    // Sample mid-flight: keyframes must be able to move transform.
    await page.waitForTimeout(120);
    const transform = await bounceWrap.evaluate(
      el => getComputedStyle(el as HTMLElement).transform
    );
    expect(transform).not.toBe('none');
  });
});
