/**
 * Panel Header Block Types
 *
 * @package Aggressive_Apparel
 */

export type JustifyContent =
  | 'flex-start'
  | 'center'
  | 'flex-end'
  | 'space-between';

export interface PanelHeaderAttributes {
  justifyContent: JustifyContent;
}
