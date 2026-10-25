/**
 * Colour swatch chips in WooCommerce's variation selector.
 *
 * Color_Block_Swatch_Manager decorates the chips WooCommerce server-renders.
 * Two things then happen in the browser that this keeps in step:
 *
 * - WooCommerce's chip script gives each chip a `title` (the colour name) when
 *   it hydrates, which would add the browser's own tooltip on top of the one
 *   color-swatches.css draws (name plus any fabric details). It's dropped; the
 *   chip keeps its `aria-label`, so assistive tech loses nothing.
 * - Options past WooCommerce's first 30 render only in the browser, on
 *   "+N more". They're decorated from the list's `data-aa-color-swatches` map
 *   exactly as the server decorates the rest.
 *
 * Watches only the colour chip lists, and only `title` changes and added
 * chips.
 *
 * @package Aggressive_Apparel
 */

const LIST_SELECTOR = '.wc-block-product-filter-chips[data-aa-color-swatches]';
const CHIP_CLASS = 'aggressive-apparel-color-chip';
const SWATCH_SELECTOR = '.wc-block-product-filter-chips__swatch';
const SWATCH_CLASSES = [
  'aggressive-apparel-color-swatch',
  'aggressive-apparel-color-swatch--interactive',
  'aggressive-apparel-color-swatch__circle',
];

export interface SwatchEntry {
  name: string;
  ring: string;
  pattern: boolean;
  color: string;
  tooltip?: string;
}

type SwatchMap = Record<string, SwatchEntry>;

/**
 * Read a list's swatch map.
 */
function readMap(list: Element): SwatchMap {
  try {
    const map = JSON.parse(list.getAttribute('data-aa-color-swatches') || '{}');
    return map && typeof map === 'object' ? (map as SwatchMap) : {};
  } catch {
    return {};
  }
}

/**
 * Decorate one chip WooCommerce rendered in the browser.
 */
export function decorateChip(chip: HTMLElement, entry: SwatchEntry): void {
  chip.classList.add(CHIP_CLASS);
  chip.removeAttribute('title');
  chip.style.setProperty('--swatch-color', entry.ring);

  const swatch = chip.querySelector<HTMLElement>(SWATCH_SELECTOR);
  if (!swatch) {
    return;
  }
  swatch.classList.add(...SWATCH_CLASSES);
  swatch.classList.toggle(
    'aggressive-apparel-color-swatch--pattern',
    entry.pattern
  );
  swatch.dataset.colorName = entry.name;
  if (entry.color) {
    swatch.dataset.color = entry.color;
  }
  if (entry.tooltip) {
    swatch.dataset.tooltip = entry.tooltip;
  }
}

/**
 * Bring every chip in a list in line: decorate new ones, drop titles.
 */
export function syncList(list: Element, map: SwatchMap = readMap(list)): void {
  list
    .querySelectorAll<HTMLButtonElement>('.wc-block-product-filter-chips__item')
    .forEach(chip => {
      if (!chip.classList.contains(CHIP_CLASS)) {
        const entry = map[chip.value];
        if (!entry) {
          return;
        }
        decorateChip(chip, entry);
      }
      chip.removeAttribute('title');
    });
}

/**
 * Sync each colour chip list now and whenever WooCommerce changes it.
 */
export function initColorSwatchChips(doc: Document = document): void {
  doc.querySelectorAll(LIST_SELECTOR).forEach(list => {
    const map = readMap(list);
    syncList(list, map);

    new MutationObserver(() => syncList(list, map)).observe(list, {
      attributes: true,
      attributeFilter: ['title'],
      childList: true,
      subtree: true,
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initColorSwatchChips());
} else {
  initColorSwatchChips();
}
