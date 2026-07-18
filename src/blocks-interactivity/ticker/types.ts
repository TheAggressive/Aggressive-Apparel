/**
 * Ticker Block Types
 *
 * @package Aggressive_Apparel
 */

export interface TickerAttributes {
  speed: number;
  direction: string;
  pauseOnHover: boolean;
  gap: number;
  fadeEdges: boolean;
  fadeWidth: number;
  showLabel: boolean;
  labelType: string;
  labelText: string;
  labelIcon: string;
  labelIconSize: number;
  labelBg: string;
  labelColor: string;
  showIndicator: boolean;
  indicatorShape: string;
  indicatorColor: string;
  pattern: string;
  patternColor: string;
  patternBlendMode: string;
  patternOpacity: number;
  patternScale: number;
  labelFontSize: number;
  labelFontWeight: string;
  labelLetterSpacing: number;
  labelTextTransform: string;
}

/** Interactivity context written by `render.php` and mutated by the store. */
export interface TickerContext {
  /** Manual pause from the play/pause control. */
  isPaused: boolean;
  /** Temporary hold from hover / keyboard focus. */
  isHeld: boolean;
  /** When true, hover temporarily holds the marquee. */
  pauseOnHover: boolean;
  /**
   * Reduced-motion lock. Animation never runs; the play control stays
   * disabled so the UI cannot imply motion is available.
   */
  motionLocked: boolean;
  /**
   * Accessible name for the play/pause control. Kept on context (not a
   * `state` getter) so server-side directive processing keeps a real label
   * before the JS store hydrates.
   */
  controlLabel: string;
  i18n: {
    play: string;
    pause: string;
  };
}
