/**
 * Intersection utilities for the Parallax block
 *
 * @package Aggressive Apparel
 */

/**
 * Intersection ratio validation helper
 */
export const getValidIntersectionRatio = (
  ratio: number | undefined,
  fallback: number = 0
): number =>
  Math.max(
    0,
    Math.min(
      1,
      typeof ratio === 'number' && !isNaN(ratio)
        ? ratio
        : typeof fallback === 'number' && !isNaN(fallback)
          ? fallback
          : 0
    )
  );

/**
 * Parse visibility threshold from context
 */
export const getVisibilityThreshold = (
  visibilityTrigger: string | number | undefined
): number => {
  if (typeof visibilityTrigger === 'string') {
    return parseFloat(visibilityTrigger);
  }
  if (typeof visibilityTrigger === 'number' && !isNaN(visibilityTrigger)) {
    return visibilityTrigger;
  }
  return 0.3;
};

/**
 * Simple intersection state based on observer isIntersecting
 */
export const getIntersectionState = (
  isIntersecting: boolean,
  ratio: number,
  threshold: number
): 'not-intersecting' | 'entering' | 'triggered' => {
  if (!isIntersecting) {
    return 'not-intersecting';
  }
  return ratio >= threshold ? 'triggered' : 'entering';
};
