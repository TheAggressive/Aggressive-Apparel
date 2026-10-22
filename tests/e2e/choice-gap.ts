import type { Page } from '@playwright/test';

/**
 * Rendered space between each visible checkbox/radio and the first glyph of
 * its label text — the gap a shopper sees, whatever markup or rule produces
 * it (a margin, a space character, a flex gap, table cells).
 */
export interface ChoiceGap {
  name: string;
  gap: number;
}

export async function measureChoiceGaps(
  page: Page,
  rootSelector: string
): Promise<ChoiceGap[]> {
  return page.evaluate(selector => {
    const root = document.querySelector(selector);
    if (!root) {
      throw new Error(`Missing ${selector}`);
    }

    const gaps: { name: string; gap: number }[] = [];
    root
      .querySelectorAll<HTMLInputElement>(
        'input[type="checkbox"], input[type="radio"]'
      )
      .forEach(input => {
        const box = input.getBoundingClientRect();
        const label =
          input.closest('label') ??
          (input.id ? root.querySelector(`label[for="${input.id}"]`) : null);
        if (!label || box.width === 0) {
          return;
        }

        const walker = document.createTreeWalker(label, NodeFilter.SHOW_TEXT, {
          acceptNode: node =>
            node.textContent?.trim()
              ? NodeFilter.FILTER_ACCEPT
              : NodeFilter.FILTER_REJECT,
        });
        const text = walker.nextNode() as Text | null;
        if (!text) {
          return;
        }

        const start = text.data.search(/\S/);
        const range = document.createRange();
        range.setStart(text, start);
        range.setEnd(text, start + 1);
        gaps.push({
          name: input.name || input.id,
          gap: range.getBoundingClientRect().left - box.right,
        });
      });
    return gaps;
  }, rootSelector);
}

/** The design system's choice gap (`--aa-field-choice-gap`) in pixels. */
export async function choiceGapPx(page: Page): Promise<number> {
  return page.evaluate(() => {
    const probe = document.createElement('div');
    probe.style.width = 'var(--aa-field-choice-gap)';
    document.body.appendChild(probe);
    const width = probe.getBoundingClientRect().width;
    probe.remove();
    return width;
  });
}

/** Text rendering rounds sub-pixel positions; allow that much drift. */
export const CHOICE_GAP_TOLERANCE_PX = 1.5;
