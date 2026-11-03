/**
 * Guide values keep their source definition while specs are being edited.
 * Unchanged saved values stay as terms on the server, even when the new type
 * cannot read them. Only values explicitly edited here are submitted again.
 *
 * @package Aggressive_Apparel
 */

import type { SpecItem } from './_types';
import { convertValue } from './_retype';

export type ValueEdits = Record<string, { value: string; item?: SpecItem }>;

/** Values for the current definitions, read from their original definitions. */
export function guideValues(
  saved: Record<string, string>,
  original: SpecItem[],
  items: SpecItem[],
  edits: ValueEdits
): Record<string, string> {
  const sources = new Map(original.map(item => [item.key, item]));
  const targets = new Map(items.map(item => [item.key, item]));
  const values = { ...saved };
  for (const [key, edit] of Object.entries(edits)) {
    values[key] = edit.value;
  }
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => {
      const target = targets.get(key);
      const source = edits[key]?.item ?? sources.get(key) ?? target;
      return [
        key,
        source && target ? convertValue(source, target, value) : value,
      ];
    })
  );
}

/** Record only changed fields, with the definition under which they were typed. */
export function editGuideValues(
  current: Record<string, string>,
  next: Record<string, string>,
  items: SpecItem[],
  edits: ValueEdits
): ValueEdits {
  const result = { ...edits };
  for (const key of new Set([...Object.keys(current), ...Object.keys(next)])) {
    if ((current[key] ?? '') !== (next[key] ?? '')) {
      result[key] = {
        value: next[key] ?? '',
        item: items.find(item => item.key === key),
      };
    }
  }
  return result;
}
