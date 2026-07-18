/**
 * Pure adaptive color value helpers (no React / WordPress UI deps).
 *
 * @package Aggressive_Apparel
 * @since 1.70.0
 */

export interface AdaptiveColorPair {
  light?: string;
  dark?: string;
}

/**
 * theme.json settings.custom.adaptiveColors entry (source of truth).
 */
export interface AdaptivePalettePair {
  slug: string;
  name?: string;
  light: string;
  dark: string;
}

/**
 * Resolve a CSS value that may be light-dark() to one scheme side.
 * Used only for chrome that cannot parse light-dark() (not for canvas CSS).
 */
export function resolveLightDarkForScheme(
  value: string | undefined,
  scheme: 'light' | 'dark'
): string | undefined {
  if (!value) {
    return undefined;
  }

  const pair = parseLightDark(value);
  return scheme === 'dark' ? pair.dark || pair.light : pair.light || pair.dark;
}

/**
 * Parse a CSS color value that may contain light-dark().
 */
export function parseLightDark(value: string | undefined): AdaptiveColorPair {
  if (!value) {
    return {};
  }

  if (value.startsWith('light-dark(') && value.endsWith(')')) {
    const inner = value.slice(11, -1);
    let depth = 0;
    for (let i = 0; i < inner.length; i++) {
      if (inner[i] === '(') {
        depth++;
      } else if (inner[i] === ')') {
        depth--;
      } else if (inner[i] === ',' && depth === 0) {
        return {
          light: inner.slice(0, i).trim(),
          dark: inner.slice(i + 1).trim(),
        };
      }
    }
  }

  return { light: value, dark: value };
}

/**
 * Compose light and dark colors into a CSS value.
 */
export function composeLightDark(
  light?: string,
  dark?: string
): string | undefined {
  if (light && dark) {
    return light === dark ? light : `light-dark(${light}, ${dark})`;
  }
  return light || dark || undefined;
}

/**
 * Normalize an adaptive attribute pair (empty strings → undefined).
 */
export function normalizeAdaptivePair(
  pair: AdaptiveColorPair | undefined
): AdaptiveColorPair | undefined {
  if (!pair) {
    return undefined;
  }

  const light = pair.light || undefined;
  const dark = pair.dark || undefined;

  if (!light && !dark) {
    return undefined;
  }

  return { light, dark };
}

/**
 * Whether both sides of a pair are set (required for light-dark() switching).
 */
export function isCompleteAdaptivePair(
  pair: AdaptiveColorPair | undefined
): boolean {
  return Boolean(pair?.light && pair?.dark);
}

/**
 * Whether any side of a pair is set.
 */
export function hasAdaptivePairValue(
  pair: AdaptiveColorPair | undefined
): boolean {
  return Boolean(pair?.light || pair?.dark);
}
