/**
 * Panel Footer Block Types
 *
 * @package Aggressive_Apparel
 */

export type JustifyContent =
  | 'flex-start'
  | 'center'
  | 'flex-end'
  | 'space-between';

export interface PanelFooterAttributes {
  justifyContent: JustifyContent;
}
