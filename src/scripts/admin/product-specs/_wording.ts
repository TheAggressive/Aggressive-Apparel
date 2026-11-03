/**
 * Product Specs settings — wording for one guide's products.
 *
 * A guide can reword a spec for its own products without changing it for
 * anyone else: its name ("title") and its step labels ("step0"…), the same
 * fields a product's own wording uses (Product_Specs::sanitize_wording).
 * Blank fields keep the spec's own wording. Pure, so it's tested on its own.
 *
 * @package Aggressive_Apparel
 */

import type { SpecItem } from './_types';

/** Item key => field => text. */
export type Wording = Record<string, Record<string, string>>;

/** The server sends an empty map as a list; read either as a map. */
export function readWording(raw: unknown): Wording {
  return raw && typeof raw === 'object' && !Array.isArray(raw)
    ? (raw as Wording)
    : {};
}

/** A spec as this guide's products show it: its name and steps reworded. */
export function worded(item: SpecItem, wording: Wording): SpecItem {
  const own = wording[item.key];
  if (!own) {
    return item;
  }
  return {
    ...item,
    name: own.title || item.name,
    steps: item.steps.map((step, i) => own[`step${i}`] || step),
  };
}

/** Set one field; an empty one goes, and so does a spec left with none. */
export function setWording(
  wording: Wording,
  key: string,
  field: string,
  text: string
): Wording {
  const own = { ...(wording[key] ?? {}) };
  if (text.trim() === '') {
    delete own[field];
  } else {
    own[field] = text;
  }
  const next = { ...wording };
  if (Object.keys(own).length === 0) {
    delete next[key];
  } else {
    next[key] = own;
  }
  return next;
}
