/**
 * Navigation Panel Block Types
 *
 * @package Aggressive_Apparel
 */

export type PanelPosition = 'left' | 'right';
export type AnimationStyle = 'slide' | 'push' | 'reveal' | 'fade';

export interface NavigationPanelAttributes {
  position: PanelPosition;
  animationStyle: AnimationStyle;
  width: string;
  showOverlay: boolean;
  showCloseButton: boolean;
}

export interface NavigationPanelContext {
  navId?: string;
  animationStyle?: AnimationStyle;
  position?: PanelPosition;
}
