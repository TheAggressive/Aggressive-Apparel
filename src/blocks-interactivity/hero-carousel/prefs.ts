/**
 * Hero Carousel — visitor preference helpers.
 *
 * @package Aggressive_Apparel
 */

export function prefersReducedMotion(): boolean {
  return (
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

/**
 * True when the visitor has opted into reduced data usage (Save-Data header
 * or the `prefers-reduced-data` media query). Autoplay preloads later slide
 * media, so we suppress it under either signal.
 */
export function prefersReducedData(): boolean {
  if (typeof navigator !== 'undefined') {
    const connection = (
      navigator as Navigator & { connection?: { saveData?: boolean } }
    ).connection;
    if (connection?.saveData) return true;
  }
  return (
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-data: reduce)').matches
  );
}
