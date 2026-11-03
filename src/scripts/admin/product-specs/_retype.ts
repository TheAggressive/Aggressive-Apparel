/**
 * Product Specs settings — changing a spec's type.
 *
 * Values are WooCommerce terms read by name for the spec's current type
 * (Product_Specs_Taxonomy::value_from_text), so a new type keeps every term
 * and reads it anew: as text it's the name itself, as a number it's the number
 * in it, as a scale it's the step it names. Nothing on a product or guide is
 * rewritten. A text spec becoming a scale starts with its values as the steps
 * (topped up to the fewest a scale has), so every one of them still reads.
 *
 * @package Aggressive_Apparel
 */

import { __, sprintf } from '@wordpress/i18n';
import type { ItemType, SpecItem } from './_types';
import { valueFor } from './_import';

type Limits = { minSteps: number; maxSteps: number };

/** Number bands to start a scale from a number with: one between each step. */
function bands(steps: string[]): number[] {
  return steps.slice(1).map((_, i) => (i + 1) * 10);
}

/** What changes when a spec becomes another type. */
export function retype(
  item: SpecItem,
  type: ItemType,
  limits: Limits
): Partial<SpecItem> {
  if (type === item.type) {
    return {};
  }
  if (type === 'text') {
    return { type, steps: [], thresholds: [], advice: [], category_steps: [] };
  }

  let steps = item.steps;
  if (item.type === 'text') {
    // Its values become the steps, topped up to the fewest a scale has.
    steps = (item.terms ?? []).map(term => term.name).slice(0, limits.maxSteps);
    for (let n = steps.length + 1; steps.length < limits.minSteps; n++) {
      steps.push(
        /* translators: %d: step number. */
        sprintf(__('Step %d', 'aggressive-apparel'), n)
      );
    }
  }

  return {
    type,
    steps,
    thresholds: type === 'number' ? bands(steps) : [],
    multiple: false,
    category_steps: [],
  };
}

/** One existing value, and how the spec reads it as it is now set up. */
export type Reading = { from: string; to: string };

/** How a spec reads each of its existing values ('' for one it can't read). */
export function readings(item: SpecItem, names: string[]): Reading[] {
  return names.map(name => {
    const value = valueFor(item, name);
    let to = value;
    if (value !== '' && item.type === 'pick') {
      to = item.steps[Number(value)] ?? '';
    } else if (value !== '' && item.type === 'number') {
      to = `${value} ${item.unit}`.trim();
    } else if (item.type === 'text') {
      to = name;
    }
    return { from: name, to };
  });
}
