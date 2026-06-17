/**
 * Nav Drilldown Block Types
 *
 * @package Aggressive_Apparel
 */

export type AnimationStyle = 'overlay' | 'push';

export interface NavDrilldownAttributes {
  label: string;
  url: string;
  submenuId: string;
  showArrow: boolean;
  animationStyle: AnimationStyle;
}
