/**
 * Product Specs block — footer behaviour.
 *
 * The footer under the specs shows the picked size's measurements ("Size M:
 * Chest 42 in, Length 29 in") and a link that opens the size guide. The
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

type SizeLine = { size: string; in: string; cm: string };

const PENDING = 'is-pending';

function readSizes(footer: HTMLElement): SizeLine[] {
  try {
    const parsed: unknown = JSON.parse(footer.dataset.sizes ?? '[]');
    return Array.isArray(parsed) ? (parsed as SizeLine[]) : [];
  } catch {
    return [];
  }
}

/**
 * The line for the picked size: the prompt until one is picked, then its
 * measurements, or nothing when the chart has no row for it.
 */
function lineFor(sizes: SizeLine[], unit: Unit, prompt: string): string {
  const picked = selectedSizes();
  if (picked.length === 0) {
    return prompt;
  }
  const line = sizes.find(row => picked.includes(sizeKey(row.size)));
  return line ? line[unit] : '';
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

      // One Text node, updated in place (no childList mutations): the
      // server-rendered prompt's, so nothing is announced on load.
      const prompt = fit.dataset.prompt ?? '';
      const text =
        fit.firstChild instanceof Text
          ? fit.firstChild
          : fit.appendChild(document.createTextNode(prompt));
      let unit = preferredUnit();
      let frame = 0;

      const update = () => {
        const next = lineFor(sizes, unit, prompt);
        if (text.data !== next) {
          text.data = next;
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
