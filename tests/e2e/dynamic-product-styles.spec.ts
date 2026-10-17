import { test, expect, type Locator, type Page } from '@playwright/test';
import {
  createVariationAvailabilityFixture,
  deleteVariationAvailabilityFixture,
  type VariationFixture,
} from './variation-availability-fixtures';

let visualFixture: VariationFixture;

test.beforeAll(() => {
  visualFixture = createVariationAvailabilityFixture();
});

test.afterAll(() => {
  if (visualFixture?.id) {
    deleteVariationAvailabilityFixture(visualFixture);
  }
});

const titleStyle = (locator: Locator) =>
  locator.evaluate(element => {
    const style = getComputedStyle(element);
    return {
      color: style.color,
      fontFamily: style.fontFamily,
      fontSize: style.fontSize,
      fontWeight: style.fontWeight,
      lineHeight: style.lineHeight,
      textAlign: style.textAlign,
    };
  });

async function openVisualFixtureCard(page: Page) {
  await page.goto('/shop/');
  const card = page.locator(
    `.wp-block-woocommerce-product-template > .wc-block-product.post-${visualFixture.id}`
  );
  await expect(
    card,
    'The owned variable-product fixture must render on the first catalog page.'
  ).toBeVisible();
  return card;
}

test('product-card swatches keep an accessible target and focus state', async ({
  page,
}) => {
  const card = await openVisualFixtureCard(page);

  const swatch = card.locator('.aa-product-color-swatches__swatch').first();
  await expect(swatch).toHaveCount(1);

  const box = await swatch.boundingBox();
  expect(box?.width).toBeGreaterThanOrEqual(32);
  expect(box?.height).toBeGreaterThanOrEqual(32);
  const visual = await swatch.evaluate(element => {
    const swatchStyle = getComputedStyle(element, '::before');
    const containerStyle = getComputedStyle(element.parentElement as Element);
    return {
      width: Number.parseFloat(swatchStyle.width),
      gap: Number.parseFloat(containerStyle.columnGap),
    };
  });
  expect(visual.width).toBe(20);
  expect(visual.gap).toBe(0);
  expect((box?.width ?? 0) - visual.width).toBe(12);

  const grouping = await swatch.evaluate(element => {
    const group = element.parentElement;
    const price = group?.previousElementSibling;
    if (!group || !price) {
      return null;
    }

    const target = element.getBoundingClientRect();
    const priceBox = price.getBoundingClientRect();
    const visualSize = Number.parseFloat(
      getComputedStyle(element, '::before').height
    );

    return {
      alignment: getComputedStyle(group).justifyContent,
      priceToVisual:
        target.top + (target.height - visualSize) / 2 - priceBox.bottom,
    };
  });
  expect(grouping?.alignment).toBe('center');
  expect(grouping?.priceToVisual).toBeGreaterThanOrEqual(24);
  expect(grouping?.priceToVisual).toBeLessThanOrEqual(28);
  await expect(swatch).toHaveAttribute('aria-pressed', 'false');

  await swatch.focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(swatch).toBeFocused();
  await expect
    .poll(() => swatch.evaluate(el => getComputedStyle(el).boxShadow))
    .not.toBe('none');

  await swatch.click();
  await expect(swatch).toHaveAttribute('aria-pressed', 'true');
});

test('narrow product cards use the dense swatch tier without wrapping', async ({
  page,
}) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const card = await openVisualFixtureCard(page);

  const group = card.locator('.aa-product-color-swatches').first();
  const swatches = group.locator('.aa-product-color-swatches__swatch');
  await expect(swatches.first()).toHaveCount(1);

  const first = swatches.first();
  const box = await first.boundingBox();
  expect(box?.width).toBeGreaterThanOrEqual(28);
  expect(box?.width).toBeLessThan(32);
  expect(box?.height).toBeGreaterThanOrEqual(28);
  expect(box?.height).toBeLessThan(32);

  const visual = await first.evaluate(element => {
    const swatchStyle = getComputedStyle(element, '::before');
    const containerStyle = getComputedStyle(element.parentElement as Element);
    return {
      width: Number.parseFloat(swatchStyle.width),
      gap: Number.parseFloat(containerStyle.columnGap),
    };
  });
  expect(visual.width).toBe(16);
  expect(visual.gap).toBe(0);
  expect((box?.width ?? 0) - visual.width).toBe(12);

  const visibleCount = Math.min(await swatches.count(), 4);
  if (visibleCount > 1) {
    const rows = await Promise.all(
      Array.from({ length: visibleCount }, (_, index) =>
        swatches
          .nth(index)
          .evaluate(element => element.getBoundingClientRect().y)
      )
    );
    expect(new Set(rows.map(value => Math.round(value))).size).toBe(1);
  }
});

test('product-filter choice pills keep the shared interaction target', async ({
  page,
}) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/shop/');

  const toggle = page.locator('.aa-filter-toggle:visible').first();
  await expect(
    toggle,
    'The transactional fixture must enable Product Filters in the test site.'
  ).toBeVisible();
  await toggle.click();

  const chip = page
    .locator(
      '.aa-product-filters__category-chip:visible, .aa-product-filters__size-chip:visible, .aa-product-filters__collection-chip:visible'
    )
    .first();
  await expect(chip).toHaveCount(1);

  const box = await chip.boundingBox();
  expect(box?.width).toBeGreaterThanOrEqual(44);
  expect(box?.height).toBeGreaterThanOrEqual(44);
  await expect(chip).toHaveAttribute('aria-pressed', 'false');
});

test('product-card utility actions use the shared icon-button contract', async ({
  page,
}) => {
  const card = await openVisualFixtureCard(page);

  const actions = card.locator(
    '.aggressive-apparel-card-actions .aggressive-apparel-card-action'
  );
  const actionCount = await actions.count();
  expect(actionCount).toBeGreaterThan(0);

  for (let index = 0; index < actionCount; index += 1) {
    const action = actions.nth(index);
    const box = await action.boundingBox();
    expect(box?.width ?? 0).toBeGreaterThanOrEqual(43.9);
    expect(box?.height ?? 0).toBeGreaterThanOrEqual(43.9);

    const rest = await action.evaluate(el => {
      const style = getComputedStyle(el);
      const icon = el.querySelector(
        ':scope > .aggressive-apparel-quick-view__trigger-icon, :scope > .aggressive-apparel-wishlist__icon'
      );
      return {
        background: style.backgroundColor,
        color: style.color,
        iconOpacity: icon
          ? Number.parseFloat(getComputedStyle(icon).opacity)
          : 1,
      };
    });

    await action.hover();
    await expect
      .poll(() => action.evaluate(el => getComputedStyle(el).backgroundColor))
      .toBe(rest.background);
    await expect
      .poll(() => action.evaluate(el => getComputedStyle(el).color))
      .toBe(rest.color);
    await expect
      .poll(() =>
        action.evaluate(el => {
          const icon = el.querySelector(
            ':scope > .aggressive-apparel-quick-view__trigger-icon, :scope > .aggressive-apparel-wishlist__icon'
          );
          return icon ? Number.parseFloat(getComputedStyle(icon).opacity) : 1;
        })
      )
      .toBeGreaterThan(rest.iconOpacity);
  }

  const firstAction = actions.first();
  await firstAction.focus();
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(firstAction).toBeFocused();
  await expect
    .poll(() => firstAction.evaluate(el => getComputedStyle(el).boxShadow))
    .not.toBe('none');
});

test('mobile navigation trigger stays hidden on desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('/');

  const trigger = page.locator('.aa-nav-trigger.aa-icon-button').first();
  await expect(trigger).toBeHidden();

  await page.setViewportSize({ width: 390, height: 844 });
  await expect(trigger).toBeVisible();
});

for (const colorScheme of ['light', 'dark'] as const) {
  test(`load-more cards retain computed title styles in ${colorScheme} mode`, async ({
    page,
  }) => {
    await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' });
    await page.goto('/shop/');

    const cards = page.locator(
      '.wp-block-woocommerce-product-template > .wc-block-product'
    );
    const initialCount = await cards.count();
    expect(initialCount).toBeGreaterThan(0);

    const initialTitle = cards.first().locator('.wp-block-post-title a');
    const expected = await titleStyle(initialTitle);

    const button = page.locator('.aa-load-more__btn');
    await expect(button).toBeVisible();
    await button.click();

    await expect
      .poll(() => cards.count(), { timeout: 15_000 })
      .toBeGreaterThan(initialCount);
    const productIds = await cards.evaluateAll(elements =>
      elements.map(element => {
        const productClass = [...element.classList].find(className =>
          /^post-\d+$/.test(className)
        );
        return productClass ?? '';
      })
    );
    expect(productIds).not.toContain('');
    expect(new Set(productIds).size).toBe(productIds.length);

    const appended = cards.nth(initialCount).locator('.wp-block-post-title a');
    await expect(appended).toBeVisible();
    expect(await titleStyle(appended)).toEqual(expected);

    // Per-request block-support classes (`wp-elements-N`) are renamed for
    // the appended page and styled by that page's own rules.
    const scoped = await appended.evaluate(title =>
      [
        ...(title.closest('li')?.querySelectorAll('[class*="wp-elements-"]') ??
          []),
      ].flatMap(el =>
        [...el.classList].filter(name => /^wp-elements-\d+-aa\d+$/.test(name))
      )
    );
    // Playwright's text matching skips <style> contents; read them directly.
    const pageCss = await page
      .locator('style[data-aa-catalog-page]')
      .evaluateAll(styles => styles.map(style => style.textContent ?? ''));
    for (const name of scoped) {
      expect(
        pageCss.filter(
          css => css.includes(`.${name} `) || css.includes(`.${name}{`)
        ),
        name
      ).toHaveLength(1);
    }
  });
}
