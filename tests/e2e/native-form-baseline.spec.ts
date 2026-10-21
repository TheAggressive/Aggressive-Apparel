/**
 * E2E for the native form baseline contract.
 *
 * Every unclassed form control should render as the shared field — the
 * WooCommerce checkout look: translucent fill, soft radius, a reserved 3px left
 * bar that turns accent on focus, and uppercase tracked labels — and Fluent
 * Forms' "Inherit Theme Style" preset should pick up the same field without
 * per-form CSS.
 *
 * Unit/PHP can't prove this: the guarantee is a computed-style outcome of the
 * cascade-layer order (main.css places `aa-forms` above Tailwind Preflight,
 * which otherwise zeroes input padding/border/radius) plus the frontend-only
 * forms.css and theme.json's `--wp--custom--field--*` properties all being
 * live on one front-end page. So this spec publishes a blank page, injects raw
 * markup, and asserts the computed result.
 *
 * The boundaries are asserted too: quantity steppers and Fluent Forms' own
 * presets must NOT receive the baseline.
 */

import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';
import { publishAndGetUrl, deletePage, openPageEditor } from './helpers';

const TRANSPARENT = new Set(['rgba(0, 0, 0, 0)', 'transparent']);

async function publishBlankPage(
  page: Page
): Promise<{ id: number; url: string }> {
  await openPageEditor(page);
  return publishAndGetUrl(page);
}

type Field = {
  paddingLeft: string;
  borderLeftWidth: string;
  borderTopWidth: string;
  borderRadius: string;
  background: string;
  minHeight: string;
};

async function injectForms(page: Page) {
  return page.evaluate(() => {
    const host = document.createElement('div');
    host.id = 'aa-e2e-forms';
    host.innerHTML = `
      <form>
        <label for="aa-e2e-text">Name</label>
        <input id="aa-e2e-text" type="text">
        <select id="aa-e2e-select"><option>One</option></select>
        <div class="quantity"><input id="aa-e2e-qty" class="qty" type="number" value="1"></div>
        <label id="aa-e2e-choice"><input id="aa-e2e-cb" type="checkbox"> I agree</label>
        <label><input id="aa-e2e-cb-on" type="checkbox" checked> Subscribed</label>
        <label><input id="aa-e2e-radio-on" type="radio" checked> Email</label>
      </form>
      <div class="fluentform ff-inherit-theme-style">
        <form class="frm-fluent-form">
          <div class="ff-el-input--label"><label id="aa-e2e-ff-label" for="aa-e2e-ff">Email</label></div>
          <input id="aa-e2e-ff" class="ff-el-form-control" type="email">
          <button id="aa-e2e-ff-submit" type="submit" class="ff-btn ff-btn-submit ff_btn_no_style">Send</button>
        </form>
      </div>
      <div class="fluentform ff-default">
        <input id="aa-e2e-ff-default" class="ff-el-form-control" type="email">
      </div>`;
    document.body.appendChild(host);

    const field = (id: string): Field => {
      const el = document.getElementById(id);
      if (!el) {
        throw new Error(`Injected control #${id} missing.`);
      }
      const cs = getComputedStyle(el);
      return {
        paddingLeft: cs.paddingLeft,
        borderLeftWidth: cs.borderLeftWidth,
        borderTopWidth: cs.borderTopWidth,
        borderRadius: cs.borderRadius,
        background: cs.backgroundColor,
        minHeight: cs.minHeight,
      };
    };
    const css = (id: string, prop: string) =>
      getComputedStyle(document.getElementById(id) as Element).getPropertyValue(
        prop
      );

    const text = document.getElementById('aa-e2e-text') as HTMLInputElement;
    const restingBar = css('aa-e2e-text', 'border-left-color');
    text.focus();
    const focusedBar = css('aa-e2e-text', 'border-left-color');

    return {
      text: field('aa-e2e-text'),
      select: field('aa-e2e-select'),
      qty: field('aa-e2e-qty'),
      ff: field('aa-e2e-ff'),
      ffDefault: field('aa-e2e-ff-default'),
      restingBar,
      focusedBar,
      fieldLabel: css('aa-e2e-ff-label', 'text-transform'),
      nativeLabel: getComputedStyle(
        document.querySelector('label[for="aa-e2e-text"]') as Element
      ).textTransform,
      choiceLabel: css('aa-e2e-choice', 'text-transform'),
      checkbox: {
        appearance: css('aa-e2e-cb', 'appearance'),
        restingMark: css('aa-e2e-cb', 'background-size'),
        checkedMark: css('aa-e2e-cb-on', 'background-size'),
        checkedFill: css('aa-e2e-cb-on', 'background-color'),
        radioDot: css('aa-e2e-radio-on', 'background-size'),
      },
      accentFill: (() => {
        const probe = document.createElement('div');
        probe.style.backgroundColor = 'var(--aa-color-accent-fill)';
        document.body.appendChild(probe);
        const value = getComputedStyle(probe).backgroundColor;
        probe.remove();
        return value;
      })(),
      ffSubmitBackground: css('aa-e2e-ff-submit', 'background-color'),
      ffSubmitTransform: css('aa-e2e-ff-submit', 'text-transform'),
    };
  });
}

test.describe('native form baseline', () => {
  test('unclassed controls and Fluent Forms inherit render the shared field', async ({
    page,
  }) => {
    const { id, url } = await publishBlankPage(page);

    try {
      await page.goto(url);
      const r = await injectForms(page);

      // The baseline survives Preflight: real padding, radius, fill and bar.
      expect(r.text.paddingLeft).not.toBe('0px');
      expect(r.text.borderRadius).not.toBe('0px');
      expect(TRANSPARENT.has(r.text.background)).toBe(false);
      expect(r.text.borderLeftWidth).toBe('3px');
      expect(r.text.borderTopWidth).toBe('0px');

      // The bar is reserved at rest and turns accent on focus.
      expect(TRANSPARENT.has(r.restingBar)).toBe(true);
      expect(TRANSPARENT.has(r.focusedBar)).toBe(false);

      // <select> (theme.json paint) matches the text field.
      expect(r.select.background).toBe(r.text.background);
      expect(r.select.borderRadius).toBe(r.text.borderRadius);
      expect(r.select.borderLeftWidth).toBe(r.text.borderLeftWidth);

      // Checkboxes and radios are drawn: no native widget, an accent fill
      // and a visible mark only once checked.
      expect(r.checkbox.appearance).toBe('none');
      expect(r.checkbox.restingMark).toMatch(/^0(px|%)?( 0(px|%)?)?$/);
      expect(r.checkbox.checkedMark).not.toMatch(/^0(px|%)?( 0(px|%)?)?$/);
      expect(r.checkbox.checkedFill).toBe(r.accentFill);
      expect(r.checkbox.radioDot).toBe('100% 100%');

      // Field labels are uppercase; choice labels stay sentence case.
      expect(r.nativeLabel).toBe('uppercase');
      expect(r.choiceLabel).not.toBe('uppercase');

      // Fluent Forms "Inherit Theme Style" gets the same field and a primary
      // submit, with no per-form CSS.
      expect(r.ff).toEqual(r.text);
      expect(r.fieldLabel).toBe('uppercase');
      expect(TRANSPARENT.has(r.ffSubmitBackground)).toBe(false);
      expect(r.ffSubmitTransform).toBe('uppercase');

      // Boundaries: quantity steppers and other Fluent Forms presets are left
      // to their own CSS.
      expect(r.qty.borderLeftWidth).not.toBe('3px');
      expect(TRANSPARENT.has(r.qty.background)).toBe(true);
      expect(r.ffDefault.minHeight).not.toBe(r.text.minHeight);
      expect(TRANSPARENT.has(r.ffDefault.background)).toBe(true);
    } finally {
      await deletePage(page, id);
    }
  });

  test('customizable select opens a themed list and stays keyboard-operable', async ({
    page,
  }) => {
    const { id, url } = await publishBlankPage(page);

    try {
      await page.goto(url);
      const supported = await page.evaluate(() =>
        CSS.supports('appearance', 'base-select')
      );
      test.skip(!supported, 'Browser lacks appearance: base-select.');

      await page.evaluate(() => {
        const host = document.createElement('div');
        host.innerHTML = `<label for="aa-e2e-pick">Size</label>
          <select id="aa-e2e-pick"><option>Small</option><option>Medium</option><option>Large</option></select>`;
        document.body.appendChild(host);
      });
      const select = page.locator('#aa-e2e-pick');

      expect(await select.evaluate(el => getComputedStyle(el).appearance)).toBe(
        'base-select'
      );

      await select.click();
      await expect
        .poll(() => select.evaluate(el => el.matches(':open')))
        .toBe(true);
      await page.keyboard.press('ArrowDown');
      await page.keyboard.press('Enter');

      await expect
        .poll(() => select.evaluate(el => el.matches(':open')))
        .toBe(false);
      expect(await select.inputValue()).toBe('Medium');
    } finally {
      await deletePage(page, id);
    }
  });

  test('a plain form on the shared field passes WCAG 2.2 AA (axe), both schemes', async ({
    page,
  }) => {
    const { id, url } = await publishBlankPage(page);

    try {
      for (const scheme of ['light', 'dark'] as const) {
        await page.emulateMedia({ colorScheme: scheme });
        await page.goto(url);
        await page.evaluate(mode => {
          document.documentElement.setAttribute('data-theme', mode);
          const host = document.createElement('form');
          host.id = 'aa-e2e-a11y';
          host.innerHTML = `
            <label for="a-name">Name <span class="required">*</span></label>
            <input id="a-name" type="text" placeholder="Jane Doe" required>
            <label for="a-email">Email</label>
            <input id="a-email" type="email" aria-invalid="true" aria-describedby="a-email-error">
            <p id="a-email-error" class="aggressive-apparel-field__error">Enter a valid email.</p>
            <label for="a-topic">Topic</label>
            <select id="a-topic"><option>Order</option><option>Sizing</option></select>
            <label for="a-msg">Message</label>
            <textarea id="a-msg" placeholder="Say something"></textarea>
            <label><input type="checkbox" checked> Keep me posted</label>
            <label><input type="radio" name="a-r" checked> Email</label>
            <input type="submit" value="Send">`;
          document.body.prepend(host);
        }, scheme);

        const results = await new AxeBuilder({ page })
          .include('#aa-e2e-a11y')
          .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
          .analyze();

        expect(
          results.violations.map(v => `${scheme}: ${v.id}`),
          'axe violations'
        ).toEqual([]);
      }
    } finally {
      await deletePage(page, id);
    }
  });
});
