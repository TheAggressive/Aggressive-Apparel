/**
 * Menu Toggle Block Types
 *
 * @package Aggressive_Apparel
 */

export type IconStyle =
  | 'hamburger'
  | 'hamburger-spin'
  | 'squeeze'
  | 'arrow'
  | 'collapse'
  | 'dots';

export type AnimationType =
  | 'to-x'
  | 'spin'
  | 'squeeze'
  | 'arrow-left'
  | 'arrow-right'
  | 'collapse'
  | 'none';

export interface MenuToggleAttributes {
  label: string;
  iconStyle: IconStyle;
  animationType: AnimationType;
  showLabel: boolean;
}

export interface MenuToggleContext {
  navId?: string;
}
