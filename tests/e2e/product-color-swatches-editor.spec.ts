import { test, expect } from '@playwright/test';
import { openPageEditor } from './helpers';

test('swatch editor preview uses the frontend visual and dense-card contract', async ({
  page,
}) => {
  await openPageEditor(page);

  await page.evaluate(() => {
    const { createBlock } = window.wp.blocks;
    const preview = createBlock('aggressive-apparel/product-color-swatches', {
      swatchShape: 'circle',
      swatchSize: 'xs',
      maxVisible: 4,
      mobileRowCapacity: 4,
      showTooltip: true,
      swatchAlignment: 'center',
    });
    const card = createBlock('core/group', { className: 'e2e-product-card' }, [
      preview,
    ]);
    window.wp.data.dispatch('core/block-editor').insertBlock(card);
  });

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  const preview = canvas.locator('.aa-product-color-swatches-editor-preview');
  const swatch = preview.locator('.aa-product-color-swatches__swatch').first();
  const visibleSwatches = preview.locator(
    '.aa-product-color-swatches__swatch:visible'
  );
  const standardOverflow = preview.locator(
    '.aa-product-color-swatches__overflow--standard'
  );
  const denseOverflow = preview.locator(
    '.aa-product-color-swatches__overflow--dense'
  );

  await expect(preview).toHaveClass(/is-shape-circle/);
  await expect(preview).toHaveClass(/is-size-xs/);
  await expect(preview).toHaveClass(/is-justify-center/);
  await expect(preview).toHaveClass(/has-tooltips/);
  await expect(swatch).toBeVisible();
  await expect(visibleSwatches).toHaveCount(4);
  await expect(standardOverflow).toBeVisible();
  await expect(standardOverflow).toHaveText('+6');
  await expect(denseOverflow).toBeHidden();

  const wide = await swatch.evaluate(element => {
    const target = getComputedStyle(element);
    const visual = getComputedStyle(element, '::before');
    const group = getComputedStyle(element.parentElement as Element);
    return {
      target: Number.parseFloat(target.width),
      visual: Number.parseFloat(visual.width),
      gap: Number.parseFloat(group.columnGap),
    };
  });
  expect(wide).toEqual({ target: 32, visual: 20, gap: 0 });

  await canvas.locator('body').evaluate(body => {
    const style = document.createElement('style');
    style.textContent =
      '.e2e-product-card{width:10rem;container:product-card / inline-size}';
    body.appendChild(style);
  });

  await expect
    .poll(() =>
      swatch.evaluate(element => {
        const target = getComputedStyle(element);
        const visual = getComputedStyle(element, '::before');
        const group = getComputedStyle(element.parentElement as Element);
        return {
          target: Number.parseFloat(target.width),
          visual: Number.parseFloat(visual.width),
          gap: Number.parseFloat(group.columnGap),
        };
      })
    )
    .toEqual({ target: 28, visual: 16, gap: 0 });

  await expect(visibleSwatches).toHaveCount(3);
  await expect(standardOverflow).toBeHidden();
  await expect(denseOverflow).toBeVisible();
  await expect(denseOverflow).toHaveText('+7');

  const denseRowTops = await preview
    .locator(
      '.aa-product-color-swatches__swatch:visible, .aa-product-color-swatches__overflow:visible'
    )
    .evaluateAll(elements =>
      elements.map(element => Math.round(element.getBoundingClientRect().top))
    );
  expect(denseRowTops).toHaveLength(4);
  expect(
    Math.max(...denseRowTops) - Math.min(...denseRowTops)
  ).toBeLessThanOrEqual(1);
});
