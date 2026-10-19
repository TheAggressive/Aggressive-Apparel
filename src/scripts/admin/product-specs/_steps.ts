/**
 * Product Specs settings — step edits as pure functions, so they can be
 * tested: a number scale always keeps one boundary fewer than it has steps
 * (Product_Specs_Registry rejects any other count), per category too.
 *
 * @package Aggressive_Apparel
 */

import type { DraftItem } from './_types';

/** A list padded (each new entry 10 above the last) or cut to a length. */
function sized(list: number[], length: number): number[] {
  const next = list.slice(0, Math.max(0, length));
  while (next.length < length) {
    next.push((next[next.length - 1] ?? 0) + 10);
  }
  return next;
}

/** The changes that add an empty step at the end. */
export function addStep(item: DraftItem): Partial<DraftItem> {
  const steps = [...item.steps, ''];
  const boundaries = Math.max(0, steps.length - 1);
  return {
    steps,
    thresholds:
      item.type === 'number'
        ? sized(item.thresholds, boundaries)
        : item.thresholds,
    category_steps: item.category_steps.map(row => ({
      ...row,
      thresholds: sized(row.thresholds, boundaries),
    })),
  };
}

/**
 * The changes that remove step i: its name, term, and advice, and the
 * boundary where it starts (step 0 has none, so the next one's goes).
 */
export function removeStep(item: DraftItem, i: number): Partial<DraftItem> {
  const drop = (list: number[]) =>
    list.filter((_, n) => n !== Math.max(0, i - 1));
  return {
    steps: item.steps.filter((_, n) => n !== i),
    // Each step's term goes with it, so the rest keep their own terms.
    step_terms: item.step_terms.filter((_, n) => n !== i),
    advice: item.advice.filter((_, n) => n !== i),
    thresholds:
      item.type === 'number' ? drop(item.thresholds) : item.thresholds,
    category_steps: item.category_steps.map(row => ({
      ...row,
      thresholds: drop(row.thresholds),
    })),
  };
}

/**
 * A boundary from its field: NaN while empty or half-typed, so it is
 * flagged ("Enter a number") and never silently saved as 0.
 */
export function boundary(value: string): number {
  return value.trim() === '' ? NaN : Number(value);
}
