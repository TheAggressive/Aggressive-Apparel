/**
 * Mega Menu Content Block Types
 *
 * @package Aggressive_Apparel
 */

export type LayoutType = 'columns' | 'grid' | 'flex';

export interface MegaMenuContentAttributes {
  layout: LayoutType;
  columns: number;
  gap: string;
  fullWidth: boolean;
}
