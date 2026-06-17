/**
 * Navigation Trigger Block Types
 *
 * @package Aggressive_Apparel
 */

export type TriggerIconStyle =
  | 'hamburger'
  | 'dots'
  | 'squeeze'
  | 'arrow'
  | 'collapse';

export type TriggerAnimationType =
  | 'to-x'
  | 'spin'
  | 'squeeze'
  | 'arrow-left'
  | 'arrow-right'
  | 'collapse'
  | 'none';

export interface NavigationTriggerAttributes {
  iconStyle: TriggerIconStyle;
  animationType: TriggerAnimationType;
  label: string;
  showLabel: boolean;
  panelSlug: string;
}
