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
import { describeValue, matchKey, splitValues } from './_utils';

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

  let steps: string[] = [];
  if (type !== 'text') {
    steps = item.steps;
  }
  if (type !== 'text' && item.type === 'text') {
    // Its values become the steps, topped up to the fewest a scale has.
    steps = (item.terms ?? []).map(term => term.name).slice(0, limits.maxSteps);
    for (let n = steps.length + 1; steps.length < limits.minSteps; n++) {
      steps.push(
        /* translators: %d: step number. */
        sprintf(__('Step %d', 'aggressive-apparel'), n)
      );
    }
  }

  const patch: Partial<SpecItem> = {
    type,
    steps,
    thresholds: type === 'number' ? bands(steps) : [],
    multiple: false,
    category_steps: [],
    // The old steps' terms say nothing about the new ones; the server resets
    // them too (Product_Specs_Retype).
    step_terms: [],
    default_term: 0,
  };
  if (type === 'text') {
    patch.advice = [];
    delete patch.multiple;
  }
  return {
    ...patch,
    default: convertValue(item, { ...item, ...patch }, item.default),
  };
}

/** Read a term's name as the new type, matching the server's value_from_text. */
function valueFromText(item: SpecItem, text: string): string {
  if (item.type === 'text') {
    return text;
  }
  if (item.type === 'pick') {
    const step = item.steps.findIndex(
      name => matchKey(name) === matchKey(text)
    );
    return step >= 0 ? String(step) : '';
  }
  return valueFor(item, text) || valueFor({ ...item, unit: '' }, text);
}

/** Re-encode an editor value through its term name, never its old step index. */
export function convertValue(
  before: SpecItem,
  after: SpecItem,
  value: string
): string {
  if (value === '' || before.type === after.type) {
    return value;
  }
  // A scale or number holds one value: a list's first.
  const text =
    before.type === 'text' && before.multiple
      ? (splitValues(value)[0] ?? '')
      : describeValue(before, value);
  return text === '' ? '' : valueFromText(after, text);
}

/** One existing value, and how the spec reads it as it is now set up. */
export type Reading = { from: string; to: string };

/** How a spec reads each of its existing values ('' for one it can't read). */
export function readings(item: SpecItem, names: string[]): Reading[] {
  return names.map(name => {
    const value = valueFromText(item, name);
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
