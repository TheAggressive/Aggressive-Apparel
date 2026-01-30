/**
 * Nav Menu Block Types
 *
 * @package Aggressive_Apparel
 */

export type MenuOrientation = 'horizontal' | 'vertical';

export interface NavMenuAttributes {
  orientation: MenuOrientation;
  itemSpacing: string;
}
