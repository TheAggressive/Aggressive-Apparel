/**
 * Bridge between the core BorderRadiusControl value and the submenu radius
 * attribute, which stays a plain CSS `border-radius` string so the submenu
 * render callbacks can print it as-is.
 *
 * @package Aggressive_Apparel
 */

import { resolvePresetValue } from '../nav-shared/spacing';

export type BorderRadiusCorners = {
  topLeft?: string;
  topRight?: string;
  bottomRight?: string;
  bottomLeft?: string;
};

/**
 * Stored CSS string → control value. A four-value shorthand opens unlinked
 * with each corner filled in; anything else is a single linked value.
 */
export function radiusToControlValue(
  value: string | undefined
): string | BorderRadiusCorners | undefined {
  const parts = (value ?? '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 4) {
    const [topLeft, topRight, bottomRight, bottomLeft] = parts;
    return { topLeft, topRight, bottomRight, bottomLeft };
  }
  return parts.length ? parts.join(' ') : undefined;
}

/**
 * Control value → stored CSS string. Preset references resolve to their
 * custom property; per-corner values collapse to the shorthand.
 */
export function controlValueToRadius(
  value: string | BorderRadiusCorners | undefined
): string | undefined {
  if (!value) {
    return undefined;
  }
  if (typeof value === 'string') {
    return resolvePresetValue(value);
  }

  const corners = [
    value.topLeft,
    value.topRight,
    value.bottomRight,
    value.bottomLeft,
  ];
  if (corners.every(corner => !corner)) {
    return undefined;
  }
  return corners
    .map(corner => (corner ? resolvePresetValue(corner) : '0'))
    .join(' ');
}
