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

/**
 * Swatch tiers (product-color-swatches/style.css): the visual dot, and the
 * floor under its target (the dot plus 12px), in standard and narrow
 * ("dense") cards. The shop template picks the tier, so tests read it.
 */
const SWATCH_TIERS = {
  xxs: { visual: 14, floor: 24, denseVisual: 12, denseFloor: 24 },
  xs: { visual: 20, floor: 32, denseVisual: 16, denseFloor: 28 },
  sm: { visual: 28, floor: 32, denseVisual: 22, denseFloor: 28 },
  md: { visual: 32, floor: 32, denseVisual: 24, denseFloor: 28 },
  lg: { visual: 44, floor: 32, denseVisual: 28, denseFloor: 28 },
} as const;

/** WCAG 2.2 AA target size (2.5.8). */
const MIN_TARGET_PX = 24;

async function swatchTier(group: Locator) {
  const size = await group.evaluate(
    element =>
      [...element.classList]
        .find(name => name.startsWith('is-size-'))
        ?.slice('is-size-'.length) ?? ''
  );
  expect(Object.keys(SWATCH_TIERS), `swatch size "${size}"`).toContain(size);
  return SWATCH_TIERS[size as keyof typeof SWATCH_TIERS];
}

const swatchVisual = (swatch: Locator) =>
  swatch.evaluate(element => ({
    width: Number.parseFloat(getComputedStyle(element, '::before').width),
    gap: Number.parseFloat(
      getComputedStyle(element.parentElement as Element).columnGap
    ),
  }));

test('product-card swatches keep an accessible target and focus state', async ({
  page,
}) => {
  const card = await openVisualFixtureCard(page);

  const group = card.locator('.aa-product-color-swatches').first();
  const swatch = group.locator('.aa-product-color-swatches__swatch').first();
  await expect(swatch).toHaveCount(1);
  const tier = await swatchTier(group);

  const box = await swatch.boundingBox();
  const target = Math.max(tier.visual + 12, tier.floor);
  expect(box?.width).toBe(target);
  expect(box?.height).toBe(target);
  expect(target).toBeGreaterThanOrEqual(MIN_TARGET_PX);

  const visual = await swatchVisual(swatch);
  expect(visual.width).toBe(tier.visual);
  expect(visual.gap).toBe(0);
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
  const tier = await swatchTier(group);

  const first = swatches.first();
  const box = await first.boundingBox();
  const target = Math.max(tier.denseVisual + 12, tier.denseFloor);
  expect(box?.width).toBe(target);
  expect(box?.height).toBe(target);
  expect(target).toBeGreaterThanOrEqual(MIN_TARGET_PX);

  const visual = await swatchVisual(first);
  expect(visual.width).toBe(tier.denseVisual);
  expect(visual.gap).toBe(0);

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

  // Visible ones: the touch-only chip is display: none on this desktop run.
  const actions = card.locator(
    '.aggressive-apparel-card-actions .aggressive-apparel-card-action:visible'
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
