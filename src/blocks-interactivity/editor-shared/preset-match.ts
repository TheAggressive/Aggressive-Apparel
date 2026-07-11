/**
 * Preset matching for the shared PresetPicker.
 *
 * A preset is "active" when every value it would set is already present
 * in the current attributes/settings — i.e. the preset patch is a deep
 * subset of the current state. Applying a preset therefore always makes
 * its tile light up, and manually changing any of its values turns it
 * back off.
 *
 * @package Aggressive Apparel
 */

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' &&
  value !== null &&
  !Array.isArray(value) &&
  Object.getPrototypeOf(value) === Object.prototype;

const deepEqual = (a: unknown, b: unknown): boolean => {
  if (Object.is(a, b)) {
    return true;
  }
  if (Array.isArray(a) && Array.isArray(b)) {
    return a.length === b.length && a.every((item, i) => deepEqual(item, b[i]));
  }
  if (isPlainObject(a) && isPlainObject(b)) {
    const keysA = Object.keys(a);
    const keysB = Object.keys(b);
    return (
      keysA.length === keysB.length &&
      keysA.every(key => deepEqual(a[key], b[key]))
    );
  }
  return false;
};

/**
 * True when every key in `patch` deep-equals the corresponding value in
 * `target`. Plain objects recurse (so partial `effects` patches match a
 * merged state); arrays must match exactly.
 */
export const isDeepSubset = (patch: unknown, target: unknown): boolean => {
  if (isPlainObject(patch)) {
    if (!isPlainObject(target)) {
      return false;
    }
    return Object.keys(patch).every(key =>
      isDeepSubset(patch[key], target[key])
    );
  }
  return deepEqual(patch, target);
};

/**
 * Key of the first preset whose patch is already reflected in the
 * current state, or null.
 */
export const findActivePresetKey = <T>(
  presets: Array<{ key: string; value: T }>,
  current: unknown
): string | null =>
  presets.find(preset => isDeepSubset(preset.value, current))?.key ?? null;
