/**
 * Product Specs settings — helpers.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import type { DraftItem, ItemType, SpecItem } from './_types';

let counter = 0;

/** Stable React key for an item that has no saved key yet. */
export function uid(): string {
  counter += 1;
  return `item-${Date.now()}-${counter}`;
}

/** Meta-key-safe key from a name: "Fabric Weight" → "fabric_weight". */
export function slugify(name: string): string {
  return name
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 32);
}

export function toDraft(item: SpecItem): DraftItem {
  return { ...item, uid: uid(), isNew: false };
}

/** Strip editor-only fields before saving. */
export function toStored(item: DraftItem): SpecItem {
  const { uid: _uid, isNew: _isNew, ...stored } = item;
  return { ...stored, key: stored.key || slugify(stored.name) };
}

/** Default label for start/middle/end when left empty. */
export function edgeLabels(item: SpecItem): {
  start: string;
  mid: string;
  end: string;
} {
  const count = item.steps.length;
  return {
    start: item.start || item.steps[0] || '',
    mid: item.mid || (count % 2 === 1 ? item.steps[Math.floor(count / 2)] : ''),
    end: item.end || item.steps[count - 1] || '',
  };
}

/** Step reached by a number given ascending boundaries. */
export function stepFor(value: number, thresholds: number[]): number {
  return thresholds.filter(boundary => value >= boundary).length;
}

/** A new, empty item of a type. */
export function blankItem(type: ItemType, steps: string[]): DraftItem {
  return {
    key: '',
    type,
    name: '',
    enabled: true,
    steps: type === 'text' ? [] : steps,
    start: '',
    mid: '',
    end: '',
    centered: false,
    unit: '',
    thresholds:
      type === 'number' ? steps.slice(1).map((_, i) => (i + 1) * 10) : [],
    advice: [],
    attributes: [],
    scope: 'all',
    categories: [],
    category_steps: [],
    in_table: true,
    default: '',
    default_term: 0,
    guides: [],
    taxonomy: '',
    step_terms: [],
    uid: uid(),
    isNew: true,
  };
}

/**
 * A new draft item from a preset, suggestion, or blank type, keyed so it never
 * shares another item's values (a second "Fit" becomes fit_2).
 *
 * @param partial Fields to start from.
 * @param taken   Items whose keys are in use (the whole registry).
 */
export function newItem(
  partial: Partial<SpecItem> & Pick<SpecItem, 'type'>,
  taken: SpecItem[]
): DraftItem {
  const base = blankItem(partial.type, [
    __('Low', 'aggressive-apparel'),
    __('Medium', 'aggressive-apparel'),
    __('High', 'aggressive-apparel'),
  ]);
  const item: DraftItem = { ...base, ...partial, uid: base.uid, isNew: true };

  item.key = uniqueKey(item.key || slugify(item.name), taken);

  return item;
}

/** The key, or the first "key_N" no other item uses ('' stays ''). */
export function uniqueKey(stem: string, taken: SpecItem[]): string {
  const keys = new Set(
    taken.map(existing => existing.key || slugify(existing.name))
  );
  let key = stem;
  for (let n = 2; key && keys.has(key); n++) {
    key = `${stem}_${n}`;
  }
  return key;
}

/**
 * A key for a spec whose value is edited before it is saved (a size guide's
 * own spec): from its name, or "spec", "spec_2"… when the name has no Latin
 * letters, so the value always has a key the server keeps.
 *
 * @param name  Spec name.
 * @param taken Items whose keys are in use.
 */
export function specKey(name: string, taken: SpecItem[]): string {
  return uniqueKey(slugify(name) || 'spec', taken);
}
