/**
 * Apply native color-picker values onto adaptive light/dark pairs.
 *
 * @package Aggressive_Apparel
 * @since 1.70.0
 */

import type { ColorScheme } from './color-scheme-storage';
import type { PresetColor } from './preset-colors';
import {
  composeLightDark,
  normalizeAdaptivePair,
  parseLightDark,
  type AdaptiveColorPair,
  type AdaptivePalettePair,
} from './adaptive-color-value';

const PRESET_CSS_VAR = /^var\(--wp--preset--color--([a-z0-9_-]+)\)$/i;

/**
 * Whether a CSS value is a gradient function.
 */
export function isCssGradient(value: string | undefined): boolean {
  return Boolean(value && /gradient\s*\(/i.test(value));
}

/**
 * Match a picker value to a theme.json adaptiveColors pair.
 *
 * Supports legacy light-dark() palette colors, solid light swatches (current
 * Theme-safe palette), and slug/var references.
 */
export function matchAdaptivePalettePair(
  color: string | undefined,
  presets: readonly PresetColor[],
  adaptivePairs: readonly AdaptivePalettePair[] = []
): AdaptivePalettePair | undefined {
  if (!color || !adaptivePairs.length) {
    return undefined;
  }

  if (color.startsWith('light-dark(')) {
    const parsed = parseLightDark(color);
    return adaptivePairs.find(
      pair => pair.light === parsed.light && pair.dark === parsed.dark
    );
  }

  const varMatch = color.match(PRESET_CSS_VAR);
  if (varMatch) {
    const slug = varMatch[1].toLowerCase();
    return adaptivePairs.find(pair => pair.slug.toLowerCase() === slug);
  }

  const normalized = color.toLowerCase();
  const preset = presets.find(
    entry => entry.slug && entry.color?.toLowerCase() === normalized
  );
  if (preset?.slug) {
    const bySlug = adaptivePairs.find(
      pair => pair.slug.toLowerCase() === preset.slug.toLowerCase()
    );
    if (bySlug) {
      return bySlug;
    }
  }

  return adaptivePairs.find(
    pair =>
      pair.light.toLowerCase() === normalized ||
      pair.dark.toLowerCase() === normalized ||
      composeLightDark(pair.light, pair.dark)?.toLowerCase() === normalized
  );
}

/**
 * Persist a picker value: palette hits become CSS variables so adaptive
 * palette edits stay live; custom colors stay raw.
 */
export function persistPickerColor(
  color: string | undefined,
  presets: readonly PresetColor[]
): string | undefined {
  if (!color) {
    return undefined;
  }

  // Adaptive dual swatches are expanded by applyPickerColorToPair — keep raw.
  if (color.startsWith('light-dark(')) {
    return color;
  }

  // Gradients are never palette CSS variables.
  if (color.includes('gradient(')) {
    return color;
  }

  const normalized = color.toLowerCase();
  const preset = presets.find(
    entry => entry.slug && entry.color?.toLowerCase() === normalized
  );

  if (preset?.slug) {
    return `var(--wp--preset--color--${preset.slug})`;
  }

  return color;
}

/**
 * Resolve a stored value for the native picker swatch (admin chrome).
 */
export function resolveColorForPicker(
  stored: string | undefined,
  presets: readonly PresetColor[]
): string | undefined {
  if (!stored) {
    return undefined;
  }

  const match = stored.match(PRESET_CSS_VAR);
  if (!match) {
    return stored;
  }

  const slug = match[1].toLowerCase();
  return (
    presets.find(entry => entry.slug?.toLowerCase() === slug)?.color ?? stored
  );
}

/**
 * Value shown as selected in the native color control for the active mode.
 */
export function getPickerColorValue(
  mode: ColorScheme,
  pair: AdaptiveColorPair | undefined,
  presets: readonly PresetColor[],
  adaptivePairs: readonly AdaptivePalettePair[] = []
): string | undefined {
  if (!pair) {
    return undefined;
  }

  // Both sides match a theme.json adaptive pair → show that palette swatch.
  const matched = adaptivePairs.find(
    entry => entry.light === pair.light && entry.dark === pair.dark
  );
  if (matched) {
    const preset = presets.find(
      entry => entry.slug?.toLowerCase() === matched.slug.toLowerCase()
    );
    return preset?.color ?? matched.light;
  }

  const composed = composeLightDark(pair.light, pair.dark);
  if (
    composed?.startsWith('light-dark(') &&
    presets.some(entry => entry.color === composed)
  ) {
    return composed;
  }

  const side = mode === 'light' ? pair.light : pair.dark;
  return resolveColorForPicker(side, presets);
}

export interface ChannelPickerState {
  /** Solid color for the ColorGradientControl (may be light-dark() for adaptive swatches). */
  colorValue: string | undefined;
  /** Gradient for the Gradient row control. */
  gradientValue: string | undefined;
  /** Swatch shown in editor chrome (always the active side; never light-dark()). */
  indicatorValue: string | undefined;
  /** Active side is currently a gradient. */
  sideIsGradient: boolean;
}

/**
 * Derive Color / Gradient row values for the active editor scheme.
 */
export function getChannelPickerState(
  mode: ColorScheme,
  pair: AdaptiveColorPair | undefined,
  presets: readonly PresetColor[],
  adaptivePairs: readonly AdaptivePalettePair[] = []
): ChannelPickerState {
  if (!pair) {
    return {
      colorValue: undefined,
      gradientValue: undefined,
      indicatorValue: undefined,
      sideIsGradient: false,
    };
  }

  const side = mode === 'light' ? pair.light : pair.dark;
  const resolvedSide = resolveColorForPicker(side, presets);
  const sideIsGradient = isCssGradient(resolvedSide);

  return {
    sideIsGradient,
    indicatorValue: resolvedSide,
    colorValue: sideIsGradient
      ? undefined
      : getPickerColorValue(mode, pair, presets, adaptivePairs),
    gradientValue: sideIsGradient ? resolvedSide : undefined,
  };
}

/**
 * Merge a native picker color into the active side of an adaptive pair.
 *
 * Picking an adaptive palette swatch (solid light or legacy light-dark())
 * fills both sides from theme.json adaptiveColors in one action.
 */
export function applyPickerColorToPair(
  mode: ColorScheme,
  current: AdaptiveColorPair | undefined,
  color: string | undefined,
  presets: readonly PresetColor[] = [],
  adaptivePairs: readonly AdaptivePalettePair[] = []
): AdaptiveColorPair | undefined {
  if (!color) {
    return normalizeAdaptivePair({
      light: mode === 'light' ? undefined : current?.light,
      dark: mode === 'dark' ? undefined : current?.dark,
    });
  }

  if (color.startsWith('light-dark(')) {
    return normalizeAdaptivePair(parseLightDark(color));
  }

  const adaptive = matchAdaptivePalettePair(color, presets, adaptivePairs);
  if (adaptive) {
    return normalizeAdaptivePair({
      light: adaptive.light,
      dark: adaptive.dark,
    });
  }

  const persisted = persistPickerColor(color, presets);

  return normalizeAdaptivePair({
    light: mode === 'light' ? persisted : current?.light,
    dark: mode === 'dark' ? persisted : current?.dark,
  });
}

/**
 * Bridge string `light-dark()` attributes (navigation, etc.) ↔ pair model.
 */
export function pairFromCssValue(
  value: string | undefined
): AdaptiveColorPair | undefined {
  return normalizeAdaptivePair(parseLightDark(value));
}

/**
 * Compose a pair back to a single CSS attribute string.
 */
export function pairToCssAttribute(
  pair: AdaptiveColorPair | undefined
): string | undefined {
  const normalized = normalizeAdaptivePair(pair);
  if (!normalized) {
    return undefined;
  }
  return composeLightDark(normalized.light, normalized.dark);
}
