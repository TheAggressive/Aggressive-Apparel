/**
 * Ticker block — visitor preference helpers.
 *
 * @package Aggressive_Apparel
 */

/** Whether the visitor prefers reduced motion. */
export function prefersReducedMotion(): boolean {
  return (
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

/**
 * True when hover is a real primary input (fine pointer + hover capability).
 *
 * Touch devices often synthesize sticky `mouseenter` on tap; using that for
 * pause-on-hover traps the marquee in a held state after the pause control
 * is used. Match the CSS that always shows the pause button on coarse pointers.
 */
export function canUseHoverPause(): boolean {
  return (
    typeof window !== 'undefined' &&
    window.matchMedia('(hover: hover) and (pointer: fine)').matches
  );
}

/**
 * Resolve after document fonts are ready so content widths are final.
 * Falls back immediately when the Font Loading API is unavailable.
 */
export function whenDocumentFontsReady(): Promise<void> {
  if (typeof document !== 'undefined' && document.fonts?.ready) {
    return document.fonts.ready.then(() => undefined);
  }

  return Promise.resolve();
}
