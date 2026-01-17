/**
 * Nav Submenu Block Types
 *
 * @package Aggressive_Apparel
 */

export type MenuType = 'dropdown' | 'mega' | 'drilldown';
export type OpenTrigger = 'hover' | 'click';

export interface NavSubmenuAttributes {
  label: string;
  url: string;
  menuType: MenuType;
  openOn: OpenTrigger;
  submenuId: string;
  showArrow: boolean;
  panelBackgroundColor?: string;
  panelTextColor?: string;
}

export interface NavSubmenuContext {
  submenuId: string;
  menuType: MenuType;
  openOn: OpenTrigger;
}
