/**
 * Product Specs settings — a guide's changes to specs, for every guide.
 *
 * Kept as the fields changed, not as copies of whole specs, and laid over the
 * latest saved spec when shown and when saved, so a change saved on the
 * Specs tab meanwhile is never overwritten by an old copy. Each field also
 * keeps what it was when first changed here: if the Specs tab has changed
 * that same field since, the two conflict, and the guide's save stops so the
 * latest can be checked (conflicts, rebase). Pure, so it's tested on its own.
 *
 * @package Aggressive_Apparel
 */

import type { SpecItem } from './_types';

type Fields = Partial<Record<keyof SpecItem, unknown>>;

/** One spec's changes: the fields set here, and each one before it was. */
export type SpecEdit = { patch: Fields; base: Fields };

/** Item key => that spec's changes. */
export type SpecEdits = Record<string, SpecEdit>;

/** A spec changed both here and on the Specs tab (or since removed). */
export type Conflict = { key: string; name: string; removed: boolean };

const same = (a: unknown, b: unknown): boolean =>
  JSON.stringify(a) === JSON.stringify(b);

const fields = (edit: Fields) => Object.keys(edit) as (keyof SpecItem)[];

/** A saved spec with this guide's changes laid over it. */
export function edited<T extends SpecItem>(item: T, edits: SpecEdits): T {
  const edit = edits[item.key];
  return edit ? ({ ...item, ...edit.patch } as T) : item;
}

/**
 * Record a change to a saved spec. A field changed back to what it was
 * stops being a change.
 */
export function recordEdit(
  edits: SpecEdits,
  saved: SpecItem,
  patch: Partial<SpecItem>
): SpecEdits {
  const current = edits[saved.key] ?? { patch: {}, base: {} };
  const base: Fields = { ...current.base };
  const next: Fields = { ...current.patch, ...patch };
  for (const field of fields(patch)) {
    if (!(field in base)) {
      base[field] = saved[field];
    }
  }
  for (const field of fields(next)) {
    if (same(next[field], base[field])) {
      delete next[field];
      delete base[field];
    }
  }

  return fields(next).length === 0
    ? discardEdit(edits, saved.key)
    : { ...edits, [saved.key]: { patch: next, base } };
}

/** Drop a spec's changes. */
export function discardEdit(edits: SpecEdits, key: string): SpecEdits {
  const { [key]: _dropped, ...rest } = edits;
  return rest;
}

/** Specs changed here whose changed fields the Specs tab has changed since. */
export function conflicts(edits: SpecEdits, items: SpecItem[]): Conflict[] {
  return Object.entries(edits).flatMap(([key, edit]): Conflict[] => {
    const latest = items.find(item => item.key === key);
    if (!latest) {
      const name = typeof edit.base.name === 'string' ? edit.base.name : key;
      return [{ key, name, removed: true }];
    }
    const changed = fields(edit.base).some(
      field => !same(latest[field], edit.base[field])
    );
    return changed ? [{ key, name: latest.name, removed: false }] : [];
  });
}

/**
 * The changes with every conflicting field dropped, so the latest saved
 * version shows there; a removed spec's changes are dropped too.
 */
export function rebase(edits: SpecEdits, items: SpecItem[]): SpecEdits {
  const next: SpecEdits = {};
  for (const [key, edit] of Object.entries(edits)) {
    const latest = items.find(item => item.key === key);
    const kept = latest
      ? fields(edit.base).filter(field => same(latest[field], edit.base[field]))
      : [];
    if (kept.length > 0) {
      next[key] = {
        patch: Object.fromEntries(
          kept.map(field => [field, edit.patch[field]])
        ),
        base: Object.fromEntries(kept.map(field => [field, edit.base[field]])),
      };
    }
  }
  return next;
}
