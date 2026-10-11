/**
 * Size Guide — which chart row is the size picked in the product form.
 *
 * Pure helpers (no store), so they can be unit tested. Sizes are compared as
 * keys: lowercase letters and digits, like Size_Chart::size_key(), with
 * spelled-out names folded to their short form so a "Large" pill matches an
 * "L" row and "XX-Large" matches "2XL".
 *
 * @package Aggressive_Apparel
 */

const ALIASES: Record<string, string> = {
  extrasmall: 'xs',
  xsmall: 'xs',
  small: 's',
  medium: 'm',
  med: 'm',
  large: 'l',
  extralarge: 'xl',
  xlarge: 'xl',
  xxl: '2xl',
  xxlarge: '2xl',
  '2xlarge': '2xl',
  xxxl: '3xl',
  xxxlarge: '3xl',
  '3xlarge': '3xl',
  xxxxl: '4xl',
  '4xlarge': '4xl',
  '5xlarge': '5xl',
  onesize: 'os',
  onesizefitsall: 'os',
  osfa: 'os',
};

/** A comparable key for a size label or value. */
export function sizeKey(value: string): string {
  const key = value.toLowerCase().replace(/[^a-z0-9]+/g, '');
  return ALIASES[key] ?? key;
}

const SIZE_NAME = /size/i;

/** Attribute-named controls (`attribute_pa_size`, `attribute_size`, …). */
function isSizeName(name: string): boolean {
  return SIZE_NAME.test(name.replace(/^attribute_(pa_)?/, ''));
}

/**
 * Keys of every value picked in the product form, in three shapes: classic
 * selects, inputs (WooCommerce block pills keep the pick in a hidden input;
 * swatch plugins use radios), and ARIA radio pills. Size-named attributes win;
 * when none is named like "size" (a translated attribute name), every picked
 * attribute counts and only a matching row lights up.
 */
export function selectedSizes(root: ParentNode = document): string[] {
  const picks: Array<{ name: string; values: string[] }> = [];

  root
    .querySelectorAll<HTMLSelectElement>('select[name^="attribute_"]')
    .forEach(select => {
      if (select.value) {
        picks.push({
          name: select.name,
          values: [select.value, select.selectedOptions[0]?.textContent ?? ''],
        });
      }
    });

  root
    .querySelectorAll<HTMLInputElement>('input[name^="attribute_"]')
    .forEach(input => {
      const toggles = input.type === 'radio' || input.type === 'checkbox';
      if (input.value && (!toggles || input.checked)) {
        picks.push({
          name: input.name,
          values: [input.value, input.labels?.[0]?.textContent ?? ''],
        });
      }
    });

  root
    .querySelectorAll<HTMLElement>(
      '[role="radio"][aria-checked="true"][id^="attribute_"]'
    )
    .forEach(pill => {
      picks.push({
        // id is "<attribute>-<value>"; the attribute part is enough to test.
        name: pill.id,
        values: [
          pill.getAttribute('value') ?? '',
          pill.getAttribute('aria-label') ?? pill.textContent ?? '',
        ],
      });
    });

  const named = picks.filter(pick => isSizeName(pick.name));
  const keys = (named.length > 0 ? named : picks).flatMap(pick =>
    pick.values.map(sizeKey)
  );

  return [...new Set(keys.filter(Boolean))];
}
