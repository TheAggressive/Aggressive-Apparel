/**
 * E2E for the standalone button-render contract.
 *
 * The button system lets a raw `.aggressive-apparel-button--primary` (and the
 * other shaped variants) render fully on its own — paint, radius, and brand
 * typography — WITHOUT the `wp-element-button` class it used to depend on for
 * color. theme.json still owns the paint whenever `wp-element-button` IS present;
 * `components/buttons.css` supplies a zero-specificity fallback for when it isn't.
 *
 * Unit/PHP can't prove this: the guarantee is a computed-style outcome that only
 * exists once the real theme stylesheet (`main.css`, which `@import`s buttons.css)
 * and theme.json's `--wp--custom--button--*` custom properties are both live on a
 * front-end page. So this spec publishes a blank page (loading exactly those),
 * injects the raw markup, and asserts the computed result — locking the fix so the
 * gap can't silently reopen.
 *
 * The `--text` link variant is deliberately excluded from the structural fallback
 * (a link must not inherit CTA typography); the final assertion guards that too.
 */

import { test, expect, type Page } from '@playwright/test';
import { publishAndGetUrl, deletePage, openPageEditor } from './helpers';

const TRANSPARENT = new Set(['rgba(0, 0, 0, 0)', 'transparent']);

/** Publish a blank page so we get a front-end URL with the theme CSS loaded. */
async function publishBlankPage(
  page: Page
): Promise<{ id: number; url: string }> {
  await openPageEditor(page);
  return publishAndGetUrl(page);
}

/**
 * Inject a standalone shaped variant (no `wp-element-button`), the same variant
 * WITH `wp-element-button` (the theme.json-painted reference), and a standalone
 * `--text` link. Returns the computed styles that encode the contract.
 */
async function injectButtons(page: Page): Promise<{
  standalone: {
    background: string;
    borderRadius: string;
    textTransform: string;
  };
  reference: { background: string };
  textLink: { textTransform: string };
}> {
  return page.evaluate(() => {
    const host = document.createElement('div');
    host.id = 'aa-e2e-buttons';
    host.innerHTML = `
      <button id="aa-e2e-standalone" type="button"
        class="aggressive-apparel-button aggressive-apparel-button--primary">Primary</button>
      <button id="aa-e2e-reference" type="button"
        class="aggressive-apparel-button aggressive-apparel-button--primary wp-element-button">Primary</button>
      <button id="aa-e2e-text" type="button"
        class="aggressive-apparel-button aggressive-apparel-button--text">Link</button>`;
    document.body.appendChild(host);

    const read = (id: string) => {
      const el = document.getElementById(id);
      if (!el) {
        throw new Error(`Injected button #${id} missing.`);
      }
      const cs = getComputedStyle(el);
      return {
        background: cs.backgroundColor,
        borderRadius: cs.borderRadius,
        textTransform: cs.textTransform,
      };
    };

    const standalone = read('aa-e2e-standalone');
    const reference = read('aa-e2e-reference');
    const textLink = read('aa-e2e-text');

    return {
      standalone,
      reference: { background: reference.background },
      textLink: { textTransform: textLink.textTransform },
    };
  });
}

test.describe('standalone button render', () => {
  test('raw --primary paints the accent fill and shape without wp-element-button', async ({
    page,
  }) => {
    const { id, url } = await publishBlankPage(page);

    try {
      await page.goto(url);
      const { standalone, reference, textLink } = await injectButtons(page);

      // The raw variant must resolve to a real fill, not the transparent default —
      // proof the paint fallback applied at all.
      expect(TRANSPARENT.has(standalone.background)).toBe(false);

      // ...and it must be the SAME fill the theme.json path produces, i.e. the
      // accent background. This is the "renders identically with or without
      // wp-element-button" contract.
      expect(standalone.background).toBe(reference.background);

      // Structural fallback: the raw variant is button-shaped (non-zero radius),
      // not a bare square inheriting nothing.
      expect(standalone.borderRadius).not.toBe('0px');
      expect(standalone.borderRadius).not.toBe('');

      // Brand typography fallback reaches the shaped variant.
      expect(standalone.textTransform).toBe('uppercase');

      // ...but is deliberately withheld from the --text link variant, which must
      // stay link-like (guards the filter-active-bar "Clear all" case).
      expect(textLink.textTransform).not.toBe('uppercase');
    } finally {
      await deletePage(page, id);
    }
  });
});
