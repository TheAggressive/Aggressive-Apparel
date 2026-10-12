/**
 * Product Specs block — footer behaviour.
 *
 * The footer under the specs shows the picked size's measurements ("Size M ·
 * Chest 42 in · Length 29 in") and a link that opens the size guide. The
 * server prints every size's line in both units (product data, so page-cache
 * safe); this picks the one for the shopper's size and unit, and follows
 * both as they change. Ships in the size guide bundle, which every product
 * page loads, and talks to the size guide through the DOM: the link clicks
 * the guide's own trigger, so focus returns to the link when it closes.
 *
 * @package Aggressive_Apparel
 */

import { store, getElement } from '@wordpress/interactivity';
import { selectedSizes, sizeKey } from './sizes';
import { openSizeGuide, TRIGGER } from './open';
import { preferredUnit, UNIT_EVENT, type Unit } from './units';

type Part = { label: string; value: string };
type SizeLine = { size: string; title: string; in: Part[]; cm: Part[] };

const PENDING = 'is-pending';

function readSizes(footer: HTMLElement): SizeLine[] {
  try {
    const parsed: unknown = JSON.parse(footer.dataset.sizes ?? '[]');
    return Array.isArray(parsed) ? (parsed as SizeLine[]) : [];
  } catch {
    return [];
  }
}

function span(className: string, text: string, hidden = false): HTMLElement {
  const el = document.createElement('span');
  el.className = className;
  el.textContent = text;
  if (hidden) {
    el.setAttribute('aria-hidden', 'true');
  }
  return el;
}

/**
 * "Size M · Chest 42 in · Length 29 in": the dots are hidden from screen
 * readers, which hear "Size M: Chest 42 in, Length 29 in" instead.
 */
function renderLine(line: SizeLine, unit: Unit): DocumentFragment {
  const fragment = document.createDocumentFragment();
  fragment.append(
    span('aa-product-specs__fit-size', line.title),
    span('screen-reader-text', ': ')
  );
  line[unit].forEach((part, index) => {
    if (index > 0) {
      fragment.append(span('screen-reader-text', ', '));
    }
    const item = span('aa-product-specs__fit-item', `${part.label} `);
    item.append(span('aa-product-specs__fit-value', part.value));
    fragment.append(span('aa-product-specs__fit-dot', '·', true), item);
  });
  return fragment;
}

/**
 * What the line shows: the prompt until a size is picked, then that size's
 * measurements, or nothing when the chart has no row for it.
 */
function lineFor(sizes: SizeLine[]): SizeLine | 'prompt' | null {
  const picked = selectedSizes();
  if (picked.length === 0) {
    return 'prompt';
  }
  return sizes.find(row => picked.includes(sizeKey(row.size))) ?? null;
}

store('aggressive-apparel/product-specs', {
  callbacks: {
    initFooter() {
      const { ref } = getElement();
      if (!(ref instanceof HTMLElement)) {
        return;
      }

      const link = ref.querySelector<HTMLElement>(
        '.aa-product-specs__guide-link'
      );
      const hasLink = Boolean(link && document.querySelector(TRIGGER));
      if (link && hasLink) {
        // Wired here, not with data-wp-on: kses strips that in tab content.
        link.addEventListener('click', () => openSizeGuide(link));
        link.classList.remove(PENDING);
      }

      const fit = ref.querySelector<HTMLElement>('.aa-product-specs__fit');
      const sizes = readSizes(ref);
      if ((!fit || sizes.length === 0) && !hasLink) {
        return;
      }
      ref.classList.remove(PENDING);
      if (!fit || sizes.length === 0) {
        return;
      }

      // The server-rendered prompt stays until something else is due, so
      // nothing is announced on load; the line is rebuilt only on change.
      const prompt = fit.dataset.prompt ?? '';
      let unit = preferredUnit();
      let frame = 0;
      let shown = 'prompt';

      const update = () => {
        const line = lineFor(sizes);
        const key =
          line === 'prompt' || line === null
            ? String(line)
            : `${line.size}|${unit}`;
        if (key === shown) {
          return;
        }
        shown = key;
        if (line === 'prompt') {
          fit.textContent = prompt;
        } else if (line === null) {
          fit.textContent = '';
        } else {
          fit.replaceChildren(renderLine(line, unit));
        }
      };
      // Variation pickers update their own markup after the event, so read
      // the pick a frame later, once their render has landed.
      const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
          frame = requestAnimationFrame(update);
        });
      };

      document.addEventListener('change', schedule);
      document.addEventListener('click', schedule);
      document.addEventListener(UNIT_EVENT, event => {
        unit = (event as CustomEvent<Unit>).detail;
        update();
      });
      update();
    },
  },
});
