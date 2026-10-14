/**
 * Product Specs settings — helpers.
 *
 * @package Aggressive_Apparel
 */

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
    uid: uid(),
    isNew: true,
  };
}
