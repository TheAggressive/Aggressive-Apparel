/**
 * Ticker block — shared selectors, limits, and allowlists.
 *
 * @package Aggressive_Apparel
 */

/** Marks `.ticker__content` copies created at runtime (vs server-rendered). */
export const CLONE_ATTR = 'data-ticker-clone';

/**
 * Ceiling on runtime clones per ticker. Degenerate content (a few px wide
 * on a wide viewport) could otherwise demand thousands of copies and bloat
 * the DOM.
 */
export const MAX_TICKER_CLONES = 100;

/** Default loop duration in seconds when `data-ticker-speed` is missing. */
export const DEFAULT_TICKER_SPEED = 30;

/**
 * CSS custom property for the pause/play control color.
 * Synced from `.ticker__content` so adaptive content colors win over the
 * wrapper's `has-*-color` class (e.g. white wrapper + surface-elevated copy).
 */
export const CONTROL_COLOR_VAR = '--ticker-control-color';

export const SELECTORS = {
  scroll: '.ticker__scroll',
  track: '.ticker__track',
  content: '.ticker__content',
} as const;

/** Allowed marquee directions (must match block.json / editor options). */
export const TICKER_DIRECTIONS = ['left', 'right'] as const;

/** Allowed label indicator shapes. */
export const INDICATOR_SHAPES = [
  'square',
  'circle',
  'diamond',
  'none',
] as const;

/** Allowed background pattern slugs. */
export const PATTERN_SLUGS = [
  'none',
  'diagonal',
  'crosshatch',
  'dots',
  'halftone',
  'noise',
  'grain',
  'scratch',
  'grunge',
  'herringbone',
  'carbon',
  'honeycomb',
  'linen',
] as const;

/** Allowed CSS mix-blend-mode values for the pattern overlay. */
export const PATTERN_BLEND_MODES = [
  'normal',
  'overlay',
  'multiply',
  'screen',
  'soft-light',
  'difference',
] as const;

/** Allowed label types. */
export const LABEL_TYPES = ['text', 'icon'] as const;

/** Allowed label font weights (empty = inherit). */
export const LABEL_FONT_WEIGHTS = [
  '',
  '400',
  '500',
  '600',
  '700',
  '800',
  '900',
] as const;

/** Allowed label text-transform values (empty = none). */
export const LABEL_TEXT_TRANSFORMS = [
  '',
  'uppercase',
  'lowercase',
  'capitalize',
] as const;

export type TickerDirection = (typeof TICKER_DIRECTIONS)[number];
export type IndicatorShape = (typeof INDICATOR_SHAPES)[number];
export type PatternSlug = (typeof PATTERN_SLUGS)[number];
export type PatternBlendMode = (typeof PATTERN_BLEND_MODES)[number];
export type LabelType = (typeof LABEL_TYPES)[number];
