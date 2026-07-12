/**
 * Split Story — shared attribute → class/style derivation.
 *
 * One source of truth for the wrapper class list and CSS custom properties so
 * the editor preview and the saved markup can never drift apart.
 *
 * @package Aggressive_Apparel
 */

import type { CSSProperties } from 'react';

/** Core blockGap value: a length/preset, or an axial { top, left } pair. */
export type BlockGap = string | { top?: string; left?: string };

export interface SplitStoryAttributes {
  mediaPosition: 'left' | 'right';
  mediaWidth: number;
  mediaHeight: 'viewport' | 'content';
  sticky: boolean;
  stickyTop: number;
  stackOrder: 'media-first' | 'content-first';
  // Added by core spacing support. The native "Block spacing" control is axial
  // (declared as ["horizontal","vertical"]), so this holds a { top, left } pair
  // — top = vertical/row gap, left = horizontal/column gap.
  style?: { spacing?: { blockGap?: BlockGap } };
}

/** CSS custom properties consumed by style.css. */
type CustomProperties = CSSProperties & Record<`--${string}`, string>;

export const SPLIT_STORY_DEFAULTS: SplitStoryAttributes = {
  mediaPosition: 'left',
  mediaWidth: 50,
  mediaHeight: 'viewport',
  sticky: true,
  stickyTop: 0,
  stackOrder: 'media-first',
};

/** Clamp the media column width to a sensible editorial range. */
export function clampMediaWidth(value: number): number {
  if (!Number.isFinite(value)) {
    return SPLIT_STORY_DEFAULTS.mediaWidth;
  }
  return Math.min(75, Math.max(25, Math.round(value)));
}

const SPACING_PRESET = /^var:preset\|spacing\|(.+)$/;

/** Resolve a spacing value or `var:preset|spacing|x` reference to CSS. */
function resolveSpacingValue(value?: string): string | undefined {
  if (!value) {
    return undefined;
  }
  const preset = value.match(SPACING_PRESET);
  return preset ? `var(--wp--preset--spacing--${preset[1]})` : value;
}

/**
 * Resolve the core blockGap into a CSS `gap` (`<row> <column>`). The axial
 * control stores `{ top: vertical, left: horizontal }`; a plain value applies
 * to both axes. Mirrors core's own getGapCSSValue so presets resolve too.
 */
export function resolveBlockGap(blockGap?: BlockGap): string | undefined {
  if (!blockGap) {
    return undefined;
  }

  if (typeof blockGap === 'string') {
    return resolveSpacingValue(blockGap);
  }

  const row = resolveSpacingValue(blockGap.top) ?? '0';
  const column = resolveSpacingValue(blockGap.left) ?? '0';
  return row === column ? row : `${row} ${column}`;
}

export function getSplitStoryClassName(
  attributes: SplitStoryAttributes
): string {
  return [
    'aa-split-story',
    `aa-split-story--media-${attributes.mediaPosition}`,
    `aa-split-story--${attributes.mediaHeight}`,
    attributes.sticky ? 'aa-split-story--sticky' : '',
    `aa-split-story--stack-${attributes.stackOrder}`,
  ]
    .filter(Boolean)
    .join(' ');
}

export function getSplitStoryStyle(
  attributes: SplitStoryAttributes
): CustomProperties {
  const style: CustomProperties = {
    '--aa-split-media-width': `${clampMediaWidth(attributes.mediaWidth)}%`,
  };

  if (attributes.sticky && attributes.stickyTop > 0) {
    style['--aa-split-sticky-top'] = `${attributes.stickyTop}rem`;
  }

  const gap = resolveBlockGap(attributes.style?.spacing?.blockGap);
  if (gap && gap !== '0') {
    style['--aa-split-gap'] = gap;
  }

  return style;
}
