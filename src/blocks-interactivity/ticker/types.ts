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
