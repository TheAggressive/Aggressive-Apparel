import { test, expect } from '@playwright/test';

test('search scope tabs mirror the variation-pill interaction', async ({
  page,
}) => {
  await page.goto('/');

  const trigger = page.locator('.aa-search-trigger').first();
  await expect(trigger).toHaveCount(1);

  await trigger.click();

  const modal = page.locator('.aa-search.is-open');
  const allTab = modal.locator('.aa-search__tab[data-scope="all"]');
  const productTab = modal.locator('.aa-search__tab[data-scope="product"]');

  await expect(modal).toBeVisible();
  await expect(allTab).toHaveAttribute('aria-selected', 'true');
  await expect(productTab).toHaveAttribute('aria-selected', 'false');

  await expect
    .poll(async () => (await productTab.boundingBox())?.height ?? 0)
    .toBeGreaterThanOrEqual(44);
  expect((await productTab.boundingBox())?.width).toBeGreaterThanOrEqual(44);

  const rest = await productTab.evaluate(element => {
    const style = getComputedStyle(element);
    const fill = getComputedStyle(element, '::before');
    return {
      background: style.backgroundColor,
      color: style.color,
      fillOpacity: fill.opacity,
      fillTransform: fill.transform,
      ring: style.boxShadow,
    };
  });

  expect(rest.fillOpacity).toBe('0');
  // The ring is an inset box-shadow on the pill itself (muted at rest, full
  // border colour on hover) — not a pseudo-element. See the .aa-choice-pill
  // primitive in styles/components/buttons.css.
  expect(rest.ring).not.toBe('none');

  await productTab.hover();
  await expect
    .poll(() =>
      productTab.evaluate(element => {
        const style = getComputedStyle(element);
        const fill = getComputedStyle(element, '::before');
        return {
          background: style.backgroundColor,
          color: style.color,
          fillOpacity: fill.opacity,
          fillTransform: fill.transform,
        };
      })
    )
    .toEqual({
      background: rest.background,
      color: await allTab.evaluate(element => getComputedStyle(element).color),
      fillOpacity: '1',
      fillTransform: 'matrix(1, 0, 0, 1, 0, 0)',
    });

  // Hover strengthens the ring from the muted resting colour to the full border.
  await expect
    .poll(() => productTab.evaluate(el => getComputedStyle(el).boxShadow))
    .not.toBe(rest.ring);

  await productTab.click();
  await expect(productTab).toHaveAttribute('aria-selected', 'true');
  await expect(productTab).toHaveClass(/is-active/);
  await expect(allTab).toHaveAttribute('aria-selected', 'false');
  await expect
    .poll(() =>
      productTab.evaluate(element => ({
        fillOpacity: getComputedStyle(element, '::before').opacity,
        fillTransform: getComputedStyle(element, '::before').transform,
        checkOpacity: getComputedStyle(
          element.querySelector('.aa-search__tab-check')!
        ).opacity,
        labelTransform: getComputedStyle(
          element.querySelector('.aa-search__tab-label')!
        ).transform,
      }))
    )
    .toEqual({
      fillOpacity: '1',
      fillTransform: 'matrix(1, 0, 0, 1, 0, 0)',
      checkOpacity: '1',
      labelTransform: 'matrix(1, 0, 0, 1, 0, 0)',
    });

  await productTab.focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(productTab).toBeFocused();
  await expect
    .poll(() => productTab.evaluate(el => getComputedStyle(el).boxShadow))
    .not.toBe('none');

  const articleTab = modal.locator('.aa-search__tab[data-scope="post"]');
  await productTab.press('ArrowRight');
  await expect(articleTab).toBeFocused();
  await expect(articleTab).toHaveAttribute('aria-selected', 'true');
  await expect(articleTab).toHaveAttribute('tabindex', '0');
  await expect(productTab).toHaveAttribute('aria-selected', 'false');
  await expect(productTab).toHaveAttribute('tabindex', '-1');
});
