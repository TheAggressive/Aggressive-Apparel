/**
 * Enum select bound to a `badge_*` field.
 *
 * `SelectControl` is generic over its option values, so a literal `options`
 * array narrows `V` to that union while the studio's field map is plain
 * `Record<string, string>` — the two never line up and every call site fails
 * to type-check. Pinning `V = string` here fixes it in one place instead of
 * casting at each control.
 *
 * @package Aggressive_Apparel
 */

import { SelectControl } from '@wordpress/components';

/** Option list annotated so `SelectControl` infers `V = string`. */
export type EnumOption = { label: string; value: string };

type Props = {
  label: string;
  value: string;
  options: EnumOption[];
  onChange: (value: string) => void;
};

/**
 * Select control for a stringly-typed badge field.
 *
 * @param props Component props.
 */
export default function EnumField({ label, value, options, onChange }: Props) {
  return (
    <SelectControl
      label={label}
      value={value}
      options={options}
      onChange={onChange}
    />
  );
}
