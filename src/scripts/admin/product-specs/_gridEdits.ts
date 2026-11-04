/**
 * Product Specs settings — unsaved values in a spec grid (_SpecGrid).
 *
 * @package Aggressive_Apparel
 */

/** Unsaved values: row ID => item key => value. */
export type GridEdits = Record<number, Record<string, string>>;

/** How many values are unsaved. */
export function countEdits(edits: GridEdits): number {
  return Object.values(edits).reduce(
    (count, row) => count + Object.keys(row).length,
    0
  );
}
