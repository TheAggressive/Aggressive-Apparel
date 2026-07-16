/**
 * Stagger Children CSS specificity lock.
 *
 * The bug: a base `[data-animate-id]:not(.has-animation-sequence) > *`
 * rule set `transition-delay: var(--initial-delay)` and beat a weaker
 * `[data-stagger-children] > *` rule, so every child fired at once even
 * though JS wrote distinct `--stagger-delay` values on each child.
 *
 * jsdom's cascade does not match browsers for that pair (source order
 * wins there), so we assert (1) the strong selector beats the base rule
 * in jsdom and (2) style.css still ships those strong selectors.
 *
 * @jest-environment jsdom
 */

import fs from 'fs';
import path from 'path';

const STYLE_PATH = path.join(__dirname, '../style.css');

const BASE_DELAY_RULE = `
.wp-block-animate-on-scroll[data-animate-id]:not(.has-animation-sequence) > * {
	transition-delay: 0s;
	animation-delay: 0s;
}
`;

/** Current fix — must beat the base rule (literals; jsdom skips var()). */
const STRONG_STAGGER_RULE = `
.wp-block-animate-on-scroll[data-animate-id]:not(.has-animation-sequence)[data-stagger-children="true"] > * {
	transition-delay: 0.3s;
	animation-delay: 0.3s;
}
`;

const mountStaggerChild = (css: string): HTMLElement => {
  document.head.innerHTML = `<style>${css}</style>`;
  document.body.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'wp-block-animate-on-scroll';
  wrap.setAttribute('data-animate-id', 'test');
  wrap.setAttribute('data-stagger-children', 'true');

  const child = document.createElement('p');
  wrap.appendChild(child);
  document.body.appendChild(wrap);

  return child;
};

describe('stagger children CSS specificity', () => {
  afterEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  it('applies per-child stagger delay when the strong selector is present', () => {
    // Base rule first (same order as style.css sections 2 then 6).
    const child = mountStaggerChild(BASE_DELAY_RULE + STRONG_STAGGER_RULE);
    expect(getComputedStyle(child).transitionDelay).toBe('0.3s');
    expect(getComputedStyle(child).animationDelay).toBe('0.3s');
  });

  it('ships the high-specificity stagger selectors in style.css', () => {
    const css = fs.readFileSync(STYLE_PATH, 'utf8');

    // Non-sequence: must include [data-animate-id] so it beats section 2.
    // A bare `&[data-stagger-children="true"] > *` is not enough.
    expect(css).toMatch(
      /&\[data-animate-id\]:not\(\.has-animation-sequence\)\[data-stagger-children="true"\]\s*>\s*\*/
    );

    // Sequence: must include [data-animate-id] so it beats section 4.
    expect(css).toMatch(
      /&\[data-animate-id\]\.has-animation-sequence\[data-stagger-children="true"\]\s*>\s*\[data-animate-sequence-type\]/
    );

    const staggerBlock = css.slice(css.indexOf('6. STAGGER ANIMATION SUPPORT'));

    // One effective-delay token feeds both transition and animation delay.
    expect(staggerBlock).toMatch(
      /--wp-block-animate-on-scroll-effective-delay:\s*calc\(/
    );
    expect(staggerBlock).toMatch(
      /transition-delay:\s*var\(--wp-block-animate-on-scroll-effective-delay\)/
    );
    expect(staggerBlock).toMatch(
      /animation-delay:\s*var\(--wp-block-animate-on-scroll-effective-delay\)/
    );
  });
});

describe('sequence visible states avoid bounce-killing !important', () => {
  it('resets non-bounce transforms without !important', () => {
    const css = fs.readFileSync(STYLE_PATH, 'utf8');
    const sequenceBlock = css.slice(
      css.indexOf('4. ANIMATION SEQUENCE SUPPORT'),
      css.indexOf('5. REVERSE ANIMATION STATES')
    );

    // Strip comments so prose about the old bug cannot false-positive.
    const rulesOnly = sequenceBlock.replace(/\/\*[\s\S]*?\*\//g, '');

    expect(rulesOnly).not.toMatch(/transform:\s*none\s*!important/);
    expect(rulesOnly).toMatch(
      /&\.is-visible\s*>\s*\[data-animate-sequence-type\]:not\(\[data-animate-sequence-type="bounce"\]\)/
    );
    expect(rulesOnly).toMatch(/transform:\s*none\s*;/);
  });

  it('ships bounce-out keyframes and lean reverse section', () => {
    const css = fs.readFileSync(STYLE_PATH, 'utf8');
    expect(css).toMatch(/@keyframes bounce-out/);
    expect(css).toMatch(/@keyframes elastic-out/);
    expect(css).toMatch(/@keyframes spring-out/);

    const reverseBlock = css.slice(
      css.indexOf('5. REVERSE ANIMATION STATES'),
      css.indexOf('6. STAGGER ANIMATION SUPPORT')
    );
    // Directional slide/zoom reverse copies should be gone (initials handle it).
    expect(reverseBlock).not.toMatch(/&\.slide\.is-exiting/);
    expect(reverseBlock).toMatch(/bounce-out/);
  });
});
