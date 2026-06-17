/**
 * Nav Mega Menu Block Types
 *
 * @package Aggressive_Apparel
 */

export type OpenTrigger = 'hover' | 'click';

export interface NavMegaAttributes {
  label: string;
  url: string;
  submenuId: string;
  showArrow: boolean;
  openOn: OpenTrigger;
  columns: number;
}

export interface NavMegaContext {
  'aggressive-apparel/navigationId'?: string;
  'aggressive-apparel/navigationOpenOn'?: OpenTrigger;
  'aggressive-apparel/submenuBackgroundColor'?: string;
  'aggressive-apparel/submenuTextColor'?: string;
  'aggressive-apparel/submenuBorderRadius'?: string;
  'aggressive-apparel/submenuBorderWidth'?: string;
  'aggressive-apparel/submenuBorderColor'?: string;
  'aggressive-apparel/submenuBorderStyle'?: string;
}
