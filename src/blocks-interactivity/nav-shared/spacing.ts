/**
 * Spacing helpers for the navigation blocks' editor previews.
 *
 * @package Aggressive_Apparel
 */

/**
 * Convert a stored `var:preset|type|slug` value to its CSS custom property.
 */
export function resolvePresetValue(value: string): string {
  return value.replace(
    /^var:preset\|([^|]+)\|(.+)$/,
    'var(--wp--preset--$1--$2)'
  );
}

/**
 * Resolve a block's Block spacing (blockGap) value.
 *
 * Mirrors aggressive_apparel_get_nav_block_gap(): the navigation blocks have no
 * layout support, so core never prints their gap and the editor sets it as a
 * custom property instead.
 */
export function resolveBlockGap(
  attributes: Record<string, unknown>
): string | undefined {
  const spacing = (
    attributes['style'] as Record<string, unknown> | undefined
  )?.['spacing'] as Record<string, unknown> | undefined;
  const gap = spacing?.['blockGap'];
  const value =
    gap && typeof gap === 'object'
      ? (gap as Record<string, unknown>)['top']
      : gap;

  return typeof value === 'string' && value !== ''
    ? resolvePresetValue(value)
    : undefined;
}
