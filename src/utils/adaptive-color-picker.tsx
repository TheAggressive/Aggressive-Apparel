/**
 * Adaptive Color Picker — Shared Editor Control
 *
 * Light/Dark mode toggle (also previews the editor canvas) + a single normal
 * color palette. Picking an adaptive (light-dark) theme swatch applies both
 * modes; picking a flat color updates the active mode only.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import type { CSSProperties } from 'react';
import { ColorPalette } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ColorModeToggle } from './editor-color-scheme';
import {
  ADMIN_CHROME_COLORS,
  EDITOR_RADIUS_TOKENS,
} from './editor-style-tokens';

export interface PaletteColor {
  name: string;
  slug: string;
  color: string;
}

export interface AdaptiveColorPair {
  light?: string;
  dark?: string;
}

const PREVIEW_SHELL_STYLE: CSSProperties = {
  display: 'grid',
  gridTemplateColumns: '1fr 1fr',
  gap: '8px',
  marginBottom: '12px',
};

const PREVIEW_SWATCH_STYLE: CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  gap: '4px',
  minWidth: 0,
};

const PREVIEW_CHIP_STYLE: CSSProperties = {
  height: '28px',
  borderRadius: EDITOR_RADIUS_TOKENS.control,
  border: `1px solid ${ADMIN_CHROME_COLORS.border}`,
  backgroundImage:
    'linear-gradient(45deg, #ddd 25%, transparent 25%), linear-gradient(-45deg, #ddd 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ddd 75%), linear-gradient(-45deg, transparent 75%, #ddd 75%)',
  backgroundSize: '8px 8px',
  backgroundPosition: '0 0, 0 4px, 4px -4px, -4px 0',
  overflow: 'hidden',
};

const LABEL_STYLE: CSSProperties = {
  fontSize: '13px',
  fontWeight: 600,
  color: ADMIN_CHROME_COLORS.text,
  margin: '0 0 12px',
};

const META_STYLE: CSSProperties = {
  fontSize: '11px',
  color: ADMIN_CHROME_COLORS.muted,
};

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

/**
 * Split a flat palette into adaptive theme swatches vs manual flat colors.
 *
 * Kept for navigation and other callers that still want the split.
 */
export function splitPaletteColors(allColors: PaletteColor[] | undefined): {
  flat: PaletteColor[] | undefined;
  adaptive: PaletteColor[] | undefined;
} {
  if (!allColors) {
    return { flat: undefined, adaptive: undefined };
  }

  const flat = allColors.filter(c => !c.color.startsWith('light-dark('));
  const adaptive = allColors.filter(c => c.color.startsWith('light-dark('));

  return {
    flat: flat.length > 0 ? flat : undefined,
    adaptive: adaptive.length > 0 ? adaptive : undefined,
  };
}

/**
 * Resolve which palette swatch should appear selected.
 */
function getPaletteValue(
  mode: 'light' | 'dark',
  value: AdaptiveColorPair | undefined,
  colors: PaletteColor[] | undefined
): string | undefined {
  const light = value?.light;
  const dark = value?.dark;

  if (light && dark) {
    const composed = light === dark ? light : `light-dark(${light}, ${dark})`;
    if (colors?.some(c => c.color === composed)) {
      return composed;
    }
  }

  return mode === 'light' ? light : dark;
}

/**
 * Live Light | Dark preview of the current pair.
 */
function AdaptivePairPreview({
  value,
  activeMode,
}: {
  value: AdaptiveColorPair | undefined;
  activeMode: 'light' | 'dark';
}) {
  const samples: Array<{ mode: 'light' | 'dark'; label: string }> = [
    { mode: 'light', label: __('Light', 'aggressive-apparel') },
    { mode: 'dark', label: __('Dark', 'aggressive-apparel') },
  ];

  return (
    <div style={PREVIEW_SHELL_STYLE} aria-hidden='true'>
      {samples.map(({ mode, label }) => {
        const color = mode === 'light' ? value?.light : value?.dark;
        const isActive = mode === activeMode;

        return (
          <div key={mode} style={PREVIEW_SWATCH_STYLE}>
            <span
              style={{
                ...META_STYLE,
                fontWeight: isActive ? 600 : 400,
                color: isActive
                  ? ADMIN_CHROME_COLORS.text
                  : ADMIN_CHROME_COLORS.muted,
              }}
            >
              {label}
            </span>
            <div style={PREVIEW_CHIP_STYLE}>
              {color ? (
                <div
                  style={{
                    width: '100%',
                    height: '100%',
                    backgroundColor: color,
                    boxShadow: isActive
                      ? `inset 0 0 0 2px ${ADMIN_CHROME_COLORS.text}`
                      : undefined,
                  }}
                />
              ) : null}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/**
 * Adaptive color picker body (for use inside a popover or panel).
 */
export function AdaptiveColorPicker({
  mode,
  onModeChange,
  value,
  onChange,
  colors,
  label,
  showModeToggle = true,
  showPreview = true,
}: {
  mode: 'light' | 'dark';
  onModeChange?: (mode: 'light' | 'dark') => void;
  value: AdaptiveColorPair | undefined;
  onChange: (value: AdaptiveColorPair | undefined) => void;
  colors: PaletteColor[] | undefined;
  label?: string;
  showModeToggle?: boolean;
  showPreview?: boolean;
}) {
  const light = value?.light;
  const dark = value?.dark;

  const applyColor = (color?: string) => {
    if (!color) {
      onChange(
        normalizeAdaptivePair({
          light: mode === 'light' ? '' : light,
          dark: mode === 'dark' ? '' : dark,
        })
      );
      return;
    }

    // Adaptive theme swatches apply both modes in one click.
    if (color.startsWith('light-dark(')) {
      onChange(normalizeAdaptivePair(parseLightDark(color)));
      return;
    }

    onChange(
      normalizeAdaptivePair({
        light: mode === 'light' ? color : light,
        dark: mode === 'dark' ? color : dark,
      })
    );
  };

  return (
    <div
      className='aa-adaptive-color-picker'
      style={{ color: ADMIN_CHROME_COLORS.text }}
    >
      {label ? <p style={LABEL_STYLE}>{label}</p> : null}

      {showPreview && <AdaptivePairPreview value={value} activeMode={mode} />}

      {showModeToggle && onModeChange && (
        <ColorModeToggle mode={mode} onChange={onModeChange} />
      )}

      <ColorPalette
        colors={colors}
        value={getPaletteValue(mode, value, colors)}
        onChange={applyColor}
        clearable
      />
    </div>
  );
}
