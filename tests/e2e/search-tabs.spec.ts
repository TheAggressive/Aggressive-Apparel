import { test, expect } from '@playwright/test';

test('search scope tabs mirror the variation-pill interaction', async ({
  page,
}) => {
  await page.goto('/');

  const trigger = page.locator('.aa-search-trigger').first();
  test.skip(
    (await trigger.count()) === 0,
    'The search trigger is unavailable.'
  );

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
    const ring = getComputedStyle(element, '::after');
    return {
      background: style.backgroundColor,
      color: style.color,
      fillOpacity: fill.opacity,
      fillTransform: fill.transform,
      ringOpacity: ring.opacity,
    };
  });

  expect(rest.fillOpacity).toBe('0');
  expect(rest.ringOpacity).toBe('0');

  await productTab.hover();
  await expect
    .poll(() =>
      productTab.evaluate(element => {
        const style = getComputedStyle(element);
        const fill = getComputedStyle(element, '::before');
        const ring = getComputedStyle(element, '::after');
        return {
          background: style.backgroundColor,
          color: style.color,
          fillOpacity: fill.opacity,
          fillTransform: fill.transform,
          ringOpacity: ring.opacity,
        };
      })
    )
    .toEqual({
      background: rest.background,
      color: await allTab.evaluate(element => getComputedStyle(element).color),
      fillOpacity: '1',
      fillTransform: 'matrix(1, 0, 0, 1, 0, 0)',
      ringOpacity: '1',
    });

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
