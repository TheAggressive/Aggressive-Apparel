/**
 * Product Specs settings — import helpers, mirroring Printful_Import so a
 * fact's value updates as soon as the store points it at another spec.
 *
 * @package Aggressive_Apparel
 */

import type { DraftItem, ImportAttribute, ItemType, SpecItem } from './_types';
import { blankItem, joinValues, splitValues } from './_utils';

const GSM_PER_OZ = 33.906;

/** A number from text in a unit: g/m² prefers metric, else converts oz. */
function parseNumber(text: string, unit: string): number {
  const clean = text.replace(/,/g, '.');
  if (/^(g\/m²|g\/m2|gsm)$/i.test(unit.replace(/\s/g, ''))) {
    const metric = clean.match(
      /(\d+(?:\.\d+)?)\s*(?:g\s*\/\s*m(?:²|2|\^2)?|gsm)(?![a-z])/i
    );
    if (metric) {
      return Number(metric[1]);
    }
    const imperial = clean.match(/(\d+(?:\.\d+)?)\s*oz(?![a-z])/i);
    return imperial ? Math.round(Number(imperial[1]) * GSM_PER_OZ) : 0;
  }
  const first = clean.match(/\d+(?:\.\d+)?/);
  return first ? Number(first[0]) : 0;
}

/** A fact's value for a spec (Printful_Import::value_for). */
export function valueFor(item: SpecItem, text: string): string {
  if (item.type === 'number') {
    const number = parseNumber(text, item.unit);
    return number > 0 ? String(number) : '';
  }
  if (item.type === 'pick') {
    const lower = text.toLowerCase();
    const step = item.steps.findIndex(
      name =>
        name !== '' &&
        new RegExp(
          `\\b${name.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`
        ).test(lower)
    );
    return step >= 0 ? String(step) : '';
  }
  const value = text
    .replace(/\s*\(.*$/, '')
    .replace(/^(fabric|material|composition)\s*:\s*/i, '')
    .replace(/\s+construction$/i, '')
    .trim();
  return value.charAt(0).toUpperCase() + value.slice(1);
}

/**
 * Several facts mapped to one spec, as one value: text joins (a list for a
 * multi-value spec), a scale or number keeps the first.
 */
export function combine(item: SpecItem, values: string[]): string {
  const set = values.filter(Boolean);
  if (set.length === 0) {
    return '';
  }
  if (item.type !== 'text') {
    return set[0];
  }
  if (item.multiple) {
    return joinValues(splitValues(joinValues(set)));
  }
  return [...new Set(set)].join(', ');
}

/** A short spec name from a fact ("Pre-shrunk fabric" → "Pre-shrunk fabric"). */
export function nameFrom(text: string): string {
  const name = text
    .replace(/\s*\(.*$/, '')
    .split(':')[0]
    .trim();
  return name.length > 40 ? `${name.slice(0, 37)}…` : name;
}

/** A spec as an import's new-attribute form describes it. */
export function attributeItem(attribute: ImportAttribute): DraftItem {
  const steps = attribute.steps
    .split(',')
    .map(step => step.trim())
    .filter(Boolean);
  const type: ItemType =
    attribute.kind === 'pick' || attribute.kind === 'number'
      ? attribute.kind
      : 'text';
  const base = blankItem(
    type,
    steps.length > 1 ? steps : ['Low', 'Medium', 'High']
  );
  return {
    ...base,
    key: attribute.key,
    name: attribute.name,
    unit: type === 'number' ? attribute.unit : '',
    multiple: attribute.kind === 'multiple',
    archives: type === 'text' && attribute.archives,
    taxonomy: attribute.taxonomy,
    guides: [],
  };
}
